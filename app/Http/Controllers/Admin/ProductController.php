<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    private string $codePrefix = 'PRD-';

    public function index()
    {
        // data buat filter & form
        $categories = Category::select('id','category_name')
            ->orderBy('category_name')
            ->get();

        $suppliers  = Supplier::select('id','name')
            ->orderBy('name')
            ->get();

        $packages   = Package::select('id','package_name')
            ->orderBy('package_name')
            ->get();

        $nextProductCode = $this->generateNextCode();

        // SUMMARY


        return view('admin.masterdata.products', compact(
            'categories',
            'suppliers',
            'packages',
            'nextProductCode',
        ));
    }

public function datatable(Request $request)
{
    try {
        $draw        = (int) $request->input('draw', 1);
        $start       = (int) $request->input('start', 0);
        $length      = (int) $request->input('length', 10);
        $orderColIdx = (int) $request->input('order.0.column', 1);
        $orderDir    = $request->input('order.0.dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $search      = trim((string) $request->input('search.value', ''));

        // === FILTER DROPDOWN (dikirim dari JS) ===
        $filterCategory = $request->input('category'); // isi: nama kategori
        $filterSupplier = $request->input('supplier'); // isi: nama supplier

        $q = DB::table('products as p')
            ->leftJoin('categories as c','c.id','=','p.category_id')
            ->leftJoin('packages  as g','g.id','=','p.package_id')
            ->leftJoin('suppliers as s','s.id','=','p.supplier_id');

        // ====== JOIN STOK CENTRAL (owner_type = 'pusat') ======
        $stockExpr = '0';
        if (Schema::hasTable('stock_levels')) {
            $stockSub = DB::table('stock_levels as sl')
                ->selectRaw('sl.product_id, SUM(sl.quantity) as qty_stock')
                ->where('sl.owner_type', 'pusat')
                ->groupBy('sl.product_id');

            $q->leftJoinSub($stockSub, 'st', 'st.product_id', '=', 'p.id');
            $stockExpr = 'COALESCE(st.qty_stock,0)';
        }

        $q->select([
            'p.id',
            'p.product_code',
            'p.name',
            'p.category_id',
            'p.package_id',
            'p.supplier_id',
            'p.description',
            'p.purchasing_price',
            'p.selling_price',
            'p.stock_minimum',
            DB::raw("$stockExpr AS total_stock"),
            'c.category_name',
            DB::raw('g.package_name AS package_name'),
            DB::raw('s.name AS supplier_name'),
        ]);

        // ====== FILTER DROPDOWN ======
        if ($filterCategory !== null && $filterCategory !== '') {
            $q->where('c.category_name', $filterCategory);
        }

        if ($filterSupplier !== null && $filterSupplier !== '') {
            $q->where('s.name', $filterSupplier);
        }

        // ====== SEARCH GLOBAL (navbar) ======
        if ($search !== '') {
            $q->where(function($w) use ($search){
                $like = "%{$search}%";
                $w->where('p.product_code','like',$like)
                  ->orWhere('p.name','like',$like)
                  ->orWhere('c.category_name','like',$like)
                  ->orWhere('g.package_name','like',$like)
                  ->orWhere('s.name','like',$like)
                  ->orWhere('p.description','like',$like);
            });
        }

        // ====== ORDER MAP (sesuai index kolom DataTables) ======
        // 0 = rownum
        // 1 = product_code
        // 2 = name
        // 3 = category
        // 4 = package (UOM)
        // 5 = supplier
        // 6 = description
        // 7 = stock
        // 8 = min_stock
        // 9 = status (badge) -> nggak bisa sort
        // 10 = purchasing_price
        // 11 = selling_price
        // 12 = actions
        $orderMap = [
            1  => 'p.product_code',
            2  => 'p.name',
            3  => 'c.category_name',
            4  => 'g.package_name',
            5  => 's.name',
            6  => 'p.description',
            7  => 'total_stock',
            8  => 'p.stock_minimum',
            // 9 status sengaja ga dipakai
            10 => 'p.purchasing_price',
            11 => 'p.selling_price',
        ];
        $orderCol = $orderMap[$orderColIdx] ?? 'p.product_code';

        $recordsTotal    = DB::table('products')->count();
        $recordsFiltered = (clone $q)->select('p.id')->distinct()->count('p.id');

        if ($orderCol === 'total_stock') {
            $q->orderByRaw('total_stock '.$orderDir);
        } else {
            $q->orderBy($orderCol, $orderDir);
        }

        $data = $q->offset($start)->limit($length)->get();

        $rows = $data->map(function($p,$i) use ($start){
            $qty = max((int)($p->total_stock ?? 0), 0);
            $min = (int)($p->stock_minimum ?? 0);

            $isLow = $min > 0 && $qty <= $min;

            $statusBadge = '-';
            if ($min > 0) {
                $statusBadge = $isLow
                    ? '<span class="badge bg-danger">LOW</span>'
                    : '<span class="badge bg-success">OK</span>';
            }

            $stockHtml = $isLow
                ? '<span class="text-danger fw-bold">' . number_format($qty, 0, ',', '.') . '</span>'
                : number_format($qty, 0, ',', '.');

            $actions = sprintf(
                '<div class="d-flex gap-1">
                    <button class="btn btn-sm btn-icon btn-outline-secondary js-edit"
                        data-id="%1$d"
                        data-product_code="%2$s"
                        data-name="%3$s"
                        data-category_id="%4$s"
                        data-package_id="%5$s"
                        data-supplier_id="%6$s"
                        data-description="%7$s"
                        data-purchasing_price="%8$d"
                        data-selling_price="%9$d"
                        data-stock_minimum="%10$s">
                        <i class="bx bx-edit-alt"></i>
                    </button>
                    <button class="btn btn-sm btn-icon btn-outline-danger js-del" data-id="%1$d">
                        <i class="bx bx-trash"></i>
                    </button>
                </div>',
                $p->id, e($p->product_code), e($p->name),
                $p->category_id ?? '', $p->package_id ?? '', $p->supplier_id ?? '',
                e($p->description ?? ''), (int)$p->purchasing_price, (int)$p->selling_price, e($p->stock_minimum ?? '')
            );

            return [
                'rownum'           => $start + $i + 1,
                'product_code'     => e($p->product_code),
                'name'             => e($p->name),
                'category'         => e($p->category_name ?? '-'),
                'package'          => e($p->package_name ?? '-'),
                'supplier'         => e($p->supplier_name ?? '-'),
                'description'      => e(Str::limit($p->description ?? '-', 80)),
                'stock'            => $stockHtml,
                'min_stock'        => number_format($min, 0, ',', '.'),
                'purchasing_price' => 'Rp'.number_format((int)$p->purchasing_price, 0, ',', '.'),
                'selling_price'    => 'Rp'.number_format((int)$p->selling_price, 0, ',', '.'),
                'status'           => $statusBadge,
                'actions'          => $actions,
            ];
        });

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $rows,
        ]);
    } catch (\Throwable $e) {
        Log::error('DT Products error: '.$e->getMessage());
        return response()->json([
            'draw'            => (int)$request->input('draw',1),
            'recordsTotal'    => 0,
            'recordsFiltered' => 0,
            'data'            => [],
            'error'           => $e->getMessage(),
        ], 500);
    }
}


    public function store(Request $request)
    {
        $code = strtoupper(trim((string)$request->input('product_code','')));
        if ($code === '') $code = $this->generateNextCode();
        $request->merge(['product_code' => $code]);

        $data = $request->validate([
            'product_code'     => ['required','max:50','unique:products,product_code'],
            'name'             => ['required','max:150'],
            'category_id'      => ['required','exists:categories,id'],
            'package_id'       => ['nullable','exists:packages,id'],
            'supplier_id'      => ['nullable','exists:suppliers,id'],
            'description'      => ['nullable','string'],
            'purchasing_price' => ['required','integer','min:0'],
            'selling_price'    => ['required','integer','min:0'],
            'stock_minimum'    => ['nullable','integer','min:0'],
        ]);

        Product::create($data);
        return response()->json(['success' => 'Product created successfully.']);
    }

    public function update(Request $request, Product $product)
    {
        $code = strtoupper(trim((string)$request->input('product_code','')));
        if ($code === '') $code = $product->product_code;
        $request->merge(['product_code' => $code]);

        $data = $request->validate([
            'product_code'     => ['required','max:50', Rule::unique('products','product_code')->ignore($product->id)],
            'name'             => ['required','max:150'],
            'category_id'      => ['required','exists:categories,id'],
            'package_id'       => ['nullable','exists:packages,id'],
            'supplier_id'      => ['nullable','exists:suppliers,id'],
            'description'      => ['nullable','string'],
            'purchasing_price' => ['required','integer','min:0'],
            'selling_price'    => ['required','integer','min:0'],
            'stock_minimum'    => ['nullable','integer','min:0'],
        ]);

        // Kunci harga beli & jual saat EDIT
        $priceChanged = (
            (int)$data['purchasing_price'] !== (int)$product->purchasing_price ||
            (int)$data['selling_price']    !== (int)$product->selling_price
        );

        if ($priceChanged) {
            return response()->json([
                'error' => 'Harga beli & harga jual tidak bisa diubah dari sini. Silakan gunakan menu Adjustment untuk mengubah harga.',
            ], 422);
        }

        $product->update($data);

        return response()->json(['success' => 'Product updated successfully.']);
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return response()->json(['success' => 'Product deleted successfully.']);
    }

    public function nextCode()
    {
        return response()->json(['next_code' => $this->generateNextCode()]);
    }

    private function generateNextCode(): string
    {
        $prefix = $this->codePrefix;
        $latest = Product::where('product_code','like',$prefix.'%')
            ->orderByRaw(
                'CAST(SUBSTRING(product_code, '.(strlen($prefix)+1).') AS UNSIGNED) DESC'
            )
            ->value('product_code');

        $num = 0;
        if ($latest && preg_match('/^'.preg_quote($prefix,'/').'(\d+)$/i',$latest,$m)) {
            $num = (int)$m[1];
        }

        return $prefix . str_pad($num + 1, 3, '0', STR_PAD_LEFT);
    }
}
