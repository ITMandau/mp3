<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\RestockReceipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PreOController extends Controller
{
    /** LIST PO */
    public function index(Request $request)
    {
        $q      = trim($request->get('q', ''));
        $status = $request->get('status');

        $perPage = 10;

        $pos = PurchaseOrder::with([
                'supplier',
                'items.product.supplier',
                'items.warehouse',          // <<=== tambah warehouse
                'restockReceipts',
            ])
            ->withCount('items')
            ->when($q, function ($qq) use ($q) {
                $qq->where('po_code', 'like', "%{$q}%");
            })
            ->when($status, fn ($qq) => $qq->where('status', $status))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->appends($request->query());

        // default gr_count = 0
        $pos->getCollection()->each(function ($po) {
            $po->gr_count = 0;
        });

        if (
            Schema::hasTable('restock_receipts') &&
            Schema::hasColumn('restock_receipts', 'purchase_order_id')
        ) {
            $poIds = $pos->pluck('id')->all();

            if (!empty($poIds)) {
                $grCounts = DB::table('restock_receipts')
                    ->select('purchase_order_id', DB::raw('COUNT(*) as c'))
                    ->whereIn('purchase_order_id', $poIds)
                    ->groupBy('purchase_order_id')
                    ->pluck('c', 'purchase_order_id');

                $pos->getCollection()->transform(function ($po) use ($grCounts) {
                    $po->gr_count = (int) ($grCounts[$po->id] ?? 0);
                    return $po;
                });
            }
        }

        if ($request->ajax()) {
            return view('admin.po._table', compact('pos', 'q', 'status'));
        }

        return view('admin.po.index', compact('pos', 'q', 'status'));
    }

    /** EDIT PO (manual / dari Restock Request) */
/** EDIT PO (manual / dari Restock Request) */
public function edit(PurchaseOrder $po)
{
    // load relasi lengkap
    $po->load([
        'items.product.supplier',
        'items.warehouse',
        'supplier'
    ]);

    $suppliers  = Supplier::orderBy('name')->get(['id', 'name']);
    $warehouses = Warehouse::orderBy('warehouse_name')->get(['id', 'warehouse_name']);

    // siapkan kolom dinamis buat harga
    $cols = ['id', 'product_code', 'name', 'supplier_id'];
    if (Schema::hasColumn('products', 'purchase_price')) $cols[] = 'purchase_price';
    if (Schema::hasColumn('products', 'buy_price'))      $cols[] = 'buy_price';
    if (Schema::hasColumn('products', 'cost_price'))     $cols[] = 'cost_price';
    if (Schema::hasColumn('products', 'selling_price'))  $cols[] = 'selling_price';

    $products = Product::with('supplier:id,name')
        ->orderBy('name')
        ->get($cols);

    // PO dari Request Restock atau manual?
    $isFromRequest = $po->items->whereNotNull('request_id')->isNotEmpty();

    // FLAG LOCK: sekarang pakai helper poIsLocked()
    $isLocked = $this->poIsLocked($po);

    return view('admin.po.edit', compact(
        'po',
        'suppliers',
        'warehouses',
        'products',
        'isFromRequest',
        'isLocked'
    ));
}



        /** SIMPAN PERUBAHAN PO */
    public function update(Request $request, PurchaseOrder $po)
    {
        // kalau sudah locked (ORDERED + punya GR), tidak boleh diubah
        if ($this->poIsLocked($po)) {
            return back()->with(
                'error',
                'PO sudah ORDERED dan memiliki Goods Received, tidak dapat diubah.'
            );
        }

        // === INFO AWAL: apakah PO ini awalnya FROM REQUEST? ===
        $wasFromRequest = $po->items()
            ->whereNotNull('request_id')
            ->exists();

        // simpan semua request_id lama (untuk ngecek mana item yang dihapus)
        $oldRequestIds = [];
        if (
            $wasFromRequest &&
            Schema::hasTable('purchase_order_items') &&
            Schema::hasColumn('purchase_order_items', 'request_id') &&
            Schema::hasTable('request_restocks')
        ) {
            $oldRequestIds = DB::table('purchase_order_items')
                ->where('purchase_order_id', $po->id)
                ->whereNotNull('request_id')
                ->pluck('request_id')
                ->unique()
                ->all();
        }

        // === VALIDASI INPUT ===
        $validated = $request->validate([
            'supplier_id'           => ['nullable', 'exists:suppliers,id'],
            'notes'                 => ['nullable', 'string'],

            'items'                 => ['array'],
            'items.*.id'            => ['nullable', 'integer', 'exists:purchase_order_items,id'],
            'items.*.product_id'    => ['required', 'exists:products,id'],
            'items.*.warehouse_id'  => ['nullable', 'exists:warehouses,id'],
            'items.*.qty'           => ['required', 'integer', 'min:1'],
            'items.*.unit_price'    => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_type' => ['nullable', 'in:percent,amount'],
            'items.*.discount_value'=> ['nullable', 'numeric', 'min:0'],
            'items.*.request_id'    => ['nullable', 'integer'],
        ]);

        DB::transaction(function () use ($validated, $po, $wasFromRequest) {
            $po->supplier_id = $validated['supplier_id'] ?? null;
            $po->notes       = $validated['notes'] ?? null;

            $itemsInput    = $validated['items'] ?? [];

            // === HARGA DEFAULT (buy / sell) ===
            $productIds = collect($itemsInput)->pluck('product_id')->filter()->unique()->all();
            $productPrices = collect();

            if ($productIds) {
                $cols = ['id'];
                if (Schema::hasColumn('products', 'purchase_price')) $cols[] = 'purchase_price';
                if (Schema::hasColumn('products', 'buy_price'))      $cols[] = 'buy_price';
                if (Schema::hasColumn('products', 'cost_price'))     $cols[] = 'cost_price';
                if (Schema::hasColumn('products', 'selling_price'))  $cols[] = 'selling_price';

                $rows = Product::whereIn('id', $productIds)->get($cols);

                $productPrices = $rows->mapWithKeys(function ($p) use ($wasFromRequest) {
                    $buy  = (float)($p->purchase_price ?? $p->buy_price ?? $p->cost_price ?? 0);
                    $sell = (float)($p->selling_price ?? 0);

                    // FROM REQUEST  → pakai harga JUAL (kalau ada), fallback ke BUY
                    // MANUAL        → pakai harga BELI, fallback ke SELL
                    $price = $wasFromRequest
                        ? ($sell ?: $buy)
                        : ($buy  ?: $sell);

                    return [$p->id => $price];
                });
            }

            $existing = $po->items()->get()->keyBy('id');
            $keepIds  = [];

            $subtotal      = 0;
            $discountTotal = 0;

            foreach ($itemsInput as $row) {
                if (empty($row['product_id']) || empty($row['qty'])) {
                    continue;
                }

                // ambil / buat item baru
                if (!empty($row['id']) && $existing->has($row['id'])) {
                    $item = $existing->get($row['id']);
                } else {
                    $item = new PurchaseOrderItem();
                    $item->purchase_order_id = $po->id;
                }

                $item->product_id  = $row['product_id'];
                $item->qty_ordered = (int)($row['qty'] ?? 0);

                // === WAREHOUSE ===
                if ($wasFromRequest) {
                    // FROM REQUEST → warehouse dari input (boleh dipilih untuk item tambahan)
                    $item->warehouse_id = $row['warehouse_id'] ?? null;
                } else {
                    // PO MANUAL → TIDAK diikat ke warehouse, dianggap CENTRAL
                    $item->warehouse_id = null;
                }

                // === HARGA ===
                $rawPrice = array_key_exists('unit_price', $row) ? $row['unit_price'] : null;

                if ($rawPrice === null || $rawPrice === '') {
                    $price = (float)($productPrices[$row['product_id']] ?? 0);
                } else {
                    $price = (float)$rawPrice;
                }

                $item->unit_price = $price;

                // === DISKON ===
                $item->discount_type  = $row['discount_type'] ?: null;
                $item->discount_value = (float)($row['discount_value'] ?? 0);

                // request_id (kalau awalnya dari RF)
                if (!empty($row['request_id']) || $item->request_id) {
                    $item->request_id = $row['request_id'] ?? $item->request_id;
                }

                // HITUNG LINE TOTAL
                $lineTotal = $item->qty_ordered * $item->unit_price;

                if ($item->discount_type === 'percent') {
                    $disc = $lineTotal * min(max($item->discount_value, 0), 100) / 100;
                } elseif ($item->discount_type === 'amount') {
                    $disc = min($item->discount_value, $lineTotal);
                } else {
                    $disc = 0;
                }

                $item->line_total = max($lineTotal - $disc, 0);
                $item->save();

                $keepIds[]      = $item->id;
                $subtotal      += $lineTotal;
                $discountTotal += $disc;
            }

            // hapus item yang nggak ada di form lagi
            if (count($keepIds)) {
                $po->items()->whereNotIn('id', $keepIds)->delete();
            } else {
                $po->items()->delete();
            }

            $po->subtotal       = $subtotal;
            $po->discount_total = $discountTotal;
            $po->grand_total    = $subtotal - $discountTotal;
            $po->save();
        });

        // reload relasi items setelah transaksi selesai
        $po->load('items');

        // === SYNC KE REQUEST RESTOCK (update qty/harga, tambah item, note "cancelled" untuk yang dihapus) ===
        $this->syncRequestsFromPo($po, $wasFromRequest, $oldRequestIds);

        return back()->with('success', 'PO berhasil disimpan.');
    }


    /** SET PO → ORDERED  */
/** SET PO → ORDERED  */
        public function order(PurchaseOrder $po)
        {
            if ($this->poIsLocked($po)) {
                return redirect()
                    ->route('po.index')
                    ->with('info', 'PO sudah ORDERED dan memiliki Goods Received, tidak dapat diubah statusnya.');
            }

            $fromRequest = $po->items()->whereNotNull('request_id')->exists();

            // ===== PO DARI RESTOCK REQUEST =====
            if ($fromRequest) {
                if ($po->status === 'ordered') {
                    return redirect()
                        ->route('po.index')
                        ->with('info', 'PO dari Restock Request sudah berstatus ORDERED.');
                }

                $po->status = 'ordered';
                if (Schema::hasColumn('purchase_orders', 'ordered_at')) {
                    $po->ordered_at = now();
                }
                $po->save();

                // sinkron lagi (tanpa info requestId lama; cuma update qty/harga + item baru)
                $po->load('items');
                $this->syncRequestsFromPo($po, true, []);

                // optional: kalau lu memang mau status RF ikut jadi 'ordered'
                if (
                    Schema::hasTable('purchase_order_items') &&
                    Schema::hasColumn('purchase_order_items', 'request_id') &&
                    Schema::hasTable('request_restocks') &&
                    Schema::hasColumn('request_restocks', 'status')
                ) {
                    $requestIds = $po->items()
                        ->whereNotNull('request_id')
                        ->pluck('request_id')
                        ->unique()
                        ->all();

                    if (!empty($requestIds)) {
                        DB::table('request_restocks')
                            ->whereIn('id', $requestIds)
                            ->update([
                                'status'     => 'ordered',
                                'updated_at' => now(),
                            ]);
                    }
                }

                return redirect()
                    ->route('po.index')
                    ->with('success', 'PO dari Restock Request diset ORDERED. Stok akan berpindah saat proses Goods Received.');
            }

            // ===== PO MANUAL =====
            if (in_array($po->status, ['ordered', 'completed', 'cancelled'], true)) {
                return redirect()
                    ->route('po.index')
                    ->with('info', 'Status PO manual saat ini: ' . strtoupper($po->status) . '.');
            }

            $po->status = 'ordered';
            if (Schema::hasColumn('purchase_orders', 'ordered_at')) {
                $po->ordered_at = now();
            }
            $po->save();

            return redirect()
                ->route('po.index')
                ->with('success', 'PO manual diset ORDERED. Buat Goods Received ketika barang datang.');
        }



    public function cancel(PurchaseOrder $po)
    {
        if ($this->poIsLocked($po)) {
            return back()->with('error', 'PO sudah ORDERED/COMPLETED, tidak dapat dibatalkan.');
        }

        DB::transaction(function () use ($po) {
            $po->status = 'cancelled';
            if (Schema::hasColumn('purchase_orders', 'cancelled_at')) {
                $po->cancelled_at = now();
            }
            $po->save();

            if (
                Schema::hasTable('purchase_order_items') &&
                Schema::hasColumn('purchase_order_items', 'request_id') &&
                Schema::hasTable('request_restocks') &&
                Schema::hasColumn('request_restocks', 'status')
            ) {
                $requestIds = $po->items()
                    ->whereNotNull('request_id')
                    ->pluck('request_id')
                    ->unique()
                    ->all();

                if (!empty($requestIds)) {
                    DB::table('request_restocks')
                        ->whereIn('id', $requestIds)
                        ->update([
                            'status'     => 'cancelled',
                            'updated_at' => now(),
                        ]);
                }
            }
        });

        return back()->with('success', 'PO dibatalkan dan request ikut CANCELLED.');
    }


    /** Create PO manual (draft kosong) */
    public function store(Request $r)
    {
        $code = $this->generateManualPoCode();

        $po = PurchaseOrder::create([
            'po_code'        => $code,
            'supplier_id'    => null,
            'ordered_by'     => auth()->id(),
            'status'         => 'draft',
            'subtotal'       => 0,
            'discount_total' => 0,
            'grand_total'    => 0,
            'notes'          => null,
        ]);

        return redirect()
            ->route('po.edit', $po->id)
            ->with('success', 'PO baru berhasil dibuat, silakan isi item.');
    }

    protected function generateManualPoCode(): string
    {
        $prefix = 'PO-' . now()->format('Ymd') . '-';

        $lastCode = PurchaseOrder::where('po_code', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('po_code');

        $next = 1;
        if ($lastCode) {
            $lastSeq = (int) substr($lastCode, strlen($prefix));
            $next    = $lastSeq + 1;
        }

        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    protected function getCentralWarehouseId(): ?int
    {
        if (!Schema::hasTable('warehouses')) {
            return null;
        }

        $query = Warehouse::query();

        if (Schema::hasColumn('warehouses', 'is_central')) {
            $query->where('is_central', 1);
        } elseif (Schema::hasColumn('warehouses', 'warehouse_name')) {
            $query->where('warehouse_name', 'Central Stock');
        }

        $id = $query->value('id');

        if (!$id) {
            $id = Warehouse::orderBy('id')->value('id');
        }

        return $id ?: null;
    }

    protected function poIsLocked(PurchaseOrder $po): bool
    {
        // 1) Begitu status sudah ORDERED / COMPLETED / CANCELLED → PO dikunci
        if (in_array($po->status, ['ordered', 'completed', 'cancelled'], true)) {
            return true;
        }

        // 2) Fallback: kalau entah gimana sudah ada GR tapi status belum diupdate,
        //    kita kunci juga supaya aman.
        if (
            Schema::hasTable('restock_receipts') &&
            Schema::hasColumn('restock_receipts', 'purchase_order_id')
        ) {
            return DB::table('restock_receipts')
                ->where('purchase_order_id', $po->id)
                ->exists();
        }

        return false;
    }



    // ====== SYNC PO → request_restocks ======
/**
 * Sinkron data PO (FROM REQUEST) ke tabel request_restocks:
 * - update qty & harga untuk item yang masih terhubung
 * - buat baris baru untuk item tambahan (request_id null)
 * - tambahkan note "Cancelled di PO" untuk request yang item-nya dihapus dari PO
 */
    protected function syncRequestsFromPo(
        PurchaseOrder $po,
        bool $wasFromRequest = false,
        array $oldRequestIds = []
    ): void {
        if (!$wasFromRequest) {
            // PO manual → nggak ada hubungan ke request_restocks
            return;
        }

        if (
            !Schema::hasTable('purchase_order_items') ||
            !Schema::hasColumn('purchase_order_items', 'product_id') ||
            !Schema::hasTable('request_restocks')
        ) {
            return;
        }

        $po->loadMissing('items');

        $poNote   = trim((string) $po->notes);
        $hasCode  = Schema::hasColumn('request_restocks', 'code');
        $hasNote  = Schema::hasColumn('request_restocks', 'note');
        $hasDate  = Schema::hasColumn('request_restocks', 'request_date');

        // — Ambil 1 baris RF lama sebagai template (code, requester, tanggal, dll)
        $baseHeader = null;
        if (!empty($oldRequestIds)) {
            $selectCols = ['id', 'warehouse_id', 'requested_by'];
            if ($hasCode) $selectCols[] = 'code';
            if ($hasDate) $selectCols[] = 'request_date';
            if ($hasNote) $selectCols[] = 'note';

            $baseHeader = DB::table('request_restocks')
                ->whereIn('id', $oldRequestIds)
                ->orderBy('id')
                ->first($selectCols);
        }

        // ---------------- 1) UPDATE ITEM YANG MASIH TERHUBUNG ----------------
        if (Schema::hasColumn('purchase_order_items', 'request_id')) {
            $itemsWithReq = $po->items()
                ->whereNotNull('request_id')
                ->get(['request_id', 'qty_ordered', 'unit_price']);

            foreach ($itemsWithReq as $row) {
                $qty   = (int) $row->qty_ordered;
                $price = (float) $row->unit_price;

                $update = [
                    'quantity_requested' => $qty,
                    'cost_per_item'      => $price,
                    'total_cost'         => $qty * $price,
                    'updated_at'         => now(),
                ];

                if ($poNote !== '' && $hasNote) {
                    $update['note'] = $poNote;
                }

                DB::table('request_restocks')
                    ->where('id', $row->request_id)
                    ->update($update);
            }
        }

        // ---------------- 2) BUAT REQUEST BARU UNTUK ITEM TAMBAHAN ----------------
        if (Schema::hasColumn('purchase_order_items', 'request_id')) {
            $extraItems = $po->items()
                ->whereNull('request_id')
                ->get(['id', 'product_id', 'warehouse_id', 'qty_ordered', 'unit_price']);

            if ($extraItems->isNotEmpty()) {
                $productSuppliers = Product::whereIn(
                    'id',
                    $extraItems->pluck('product_id')->filter()->unique()
                )->pluck('supplier_id', 'id');

                foreach ($extraItems as $item) {
                    $productId   = (int) $item->product_id;
                    $warehouseId = (int) $item->warehouse_id;
                    $qty         = (int) $item->qty_ordered;
                    $price       = (float) $item->unit_price;

                    if (!$productId || !$warehouseId || $qty <= 0) {
                        continue;
                    }

                    $supplierId = (int) ($productSuppliers[$productId] ?? $po->supplier_id);

                    $rfStatus = in_array($po->status, ['ordered', 'completed'], true)
                        ? 'ordered'
                        : 'approved';

                    $insert = [
                        'supplier_id'        => $supplierId ?: null,
                        'product_id'         => $productId,
                        'warehouse_id'       => $baseHeader->warehouse_id ?? $warehouseId,
                        'requested_by'       => $baseHeader->requested_by ?? ($po->ordered_by ?: auth()->id()),
                        'quantity_requested' => $qty,
                        'quantity_received'  => 0,
                        'cost_per_item'      => $price,
                        'total_cost'         => $qty * $price,
                        'status'             => $rfStatus,
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ];

                    if ($hasCode && $baseHeader && !empty($baseHeader->code)) {
                        // pakai code dokumen RR yang sama → tetap 1 dokumen
                        $insert['code'] = $baseHeader->code;
                    }

                    if ($hasDate) {
                        $insert['request_date'] = $baseHeader->request_date ?? now()->toDateString();
                    }

                    if ($hasNote && $poNote !== '') {
                        $insert['note'] = $poNote;
                    }

                    $reqId = DB::table('request_restocks')->insertGetId($insert);

                    // kalau lu masih pengen auto generate code sendiri ketika awalnya nggak ada
                    if ($hasCode && (!$baseHeader || empty($baseHeader->code))) {
                        $code = 'RR-' . $reqId;
                        DB::table('request_restocks')
                            ->where('id', $reqId)
                            ->update([
                                'code'       => $code,
                                'updated_at' => now(),
                            ]);
                    }

                    // link balik ke PO item
                    DB::table('purchase_order_items')
                        ->where('id', $item->id)
                        ->update([
                            'request_id' => $reqId,
                            'updated_at' => now(),
                        ]);
                }
            }
        }

        // ---------------- 3) NOTE "Cancelled di PO" UNTUK ITEM YANG DIHAPUS ----------------
        if (!empty($oldRequestIds) && $hasNote) {
            $currentReqIds = $po->items()
                ->whereNotNull('request_id')
                ->pluck('request_id')
                ->unique()
                ->all();

            $removedReqIds = array_diff($oldRequestIds, $currentReqIds);

            if (!empty($removedReqIds)) {
                $rows = DB::table('request_restocks')
                    ->whereIn('id', $removedReqIds)
                    ->get(['id', 'note']);

                $marker = 'Cancelled di PO';

                foreach ($rows as $rf) {
                    $note = trim((string)($rf->note ?? ''));

                    if ($note === '') {
                        $newNote = $marker;
                    } elseif (stripos($note, $marker) === false) {
                        $newNote = $note . ' | ' . $marker;
                    } else {
                        $newNote = $note; // sudah ada
                    }

                    DB::table('request_restocks')
                        ->where('id', $rf->id)
                        ->update([
                            'note'       => $newNote,
                            'updated_at' => now(),
                        ]);
                }
            }
        }
    }


    /** Barang MASUK ke CENTRAL dari PO manual (status completed) */
    protected function applyStockFromManualPo(PurchaseOrder $po): void
    {
        if (!Schema::hasTable('stock_levels')) {
            return;
        }
        if (
            !Schema::hasColumn('stock_levels', 'product_id') ||
            !Schema::hasColumn('stock_levels', 'owner_type') ||
            !Schema::hasColumn('stock_levels', 'owner_id') ||
            !Schema::hasColumn('stock_levels', 'quantity')
        ) {
            return;
        }

        $items = $po->items()->whereNull('request_id')->get();

        foreach ($items as $item) {
            $productId = $item->product_id;
            $qty       = (int) $item->qty_ordered;

            if (!$productId || $qty <= 0) {
                continue;
            }

            $level = DB::table('stock_levels')
                ->where('owner_type', 'central')
                ->where('product_id', $productId)
                ->first();

            if ($level) {
                DB::table('stock_levels')
                    ->where('id', $level->id)
                    ->update([
                        'quantity'   => (int) $level->quantity + $qty,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('stock_levels')->insert([
                    'owner_type' => 'central',
                    'owner_id'   => 0,
                    'product_id' => $productId,
                    'quantity'   => $qty,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /** Barang KELUAR dari CENTRAL ke WAREHOUSE (PO dari request gudang) */
    protected function applyStockForWarehousePo(PurchaseOrder $po): void
    {
        // SEKARANG DIPANGGIL DARI PROSES GR (BUKAN LAGI DARI order())
        if (!Schema::hasTable('stock_levels')) {
            return;
        }
        if (
            !Schema::hasColumn('stock_levels', 'product_id') ||
            !Schema::hasColumn('stock_levels', 'owner_type') ||
            !Schema::hasColumn('stock_levels', 'owner_id') ||
            !Schema::hasColumn('stock_levels', 'quantity')
        ) {
            return;
        }

        $items = $po->items()->whereNotNull('request_id')->get();

        foreach ($items as $item) {
            $productId   = $item->product_id;
            $warehouseId = $item->warehouse_id;
            $qty         = (int) $item->qty_ordered;

            if (!$productId || !$warehouseId || $qty <= 0) {
                continue;
            }

            // Kurangi CENTRAL
            $central = DB::table('stock_levels')
                ->where('owner_type', 'central')
                ->where('product_id', $productId)
                ->first();

            if ($central) {
                $newQty = max(0, (int) $central->quantity - $qty);
                DB::table('stock_levels')
                    ->where('id', $central->id)
                    ->update([
                        'quantity'   => $newQty,
                        'updated_at' => now(),
                    ]);
            }

            // Tambah ke WAREHOUSE
            $levelWh = DB::table('stock_levels')
                ->where('owner_type', 'warehouse')
                ->where('owner_id', $warehouseId)
                ->where('product_id', $productId)
                ->first();

            if ($levelWh) {
                DB::table('stock_levels')
                    ->where('id', $levelWh->id)
                    ->update([
                        'quantity'   => (int) $levelWh->quantity + $qty,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('stock_levels')->insert([
                    'owner_type' => 'warehouse',
                    'owner_id'   => $warehouseId,
                    'product_id' => $productId,
                    'quantity'   => $qty,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function exportPdf(PurchaseOrder $po)
    {
        return back()->with('info', 'Export PDF belum diaktifkan.');
    }

    public function exportExcel(PurchaseOrder $po)
    {
        return back()->with('info', 'Export Excel belum diaktifkan.');
    }
}
