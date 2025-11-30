@extends('layouts.home')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="container-xxl flex-grow-1 container-p-y">

    <div class="d-flex align-items-center mb-3">
        <h4 class="mb-0 fw-bold">Stock Adjustment</h4>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ================== FORM ADJUSTMENT ================== --}}
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Buat Adjustment Baru</h5>
            <small class="text-muted">
                Navigasi keyboard: <code>Alt+N</code> tambah baris, <code>Alt+S</code> simpan
            </small>
        </div>
        <div class="card-body">

            <form id="frmAdjust" action="{{ route('stock-adjustments.store') }}" method="POST">
                @csrf

                <div class="row g-2 mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Tanggal</label>
                        <input type="date" name="adj_date" id="adj_date"
                               class="form-control nav-input"
                               data-next="#warehouse_id"
                               value="{{ old('adj_date', now()->toDateString()) }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Warehouse</label>
                        <select name="warehouse_id" id="warehouse_id"
                                class="form-select nav-input"
                                data-next="#stock_scope_mode">
                            <option value="">— Pilih —</option>
                            @foreach($warehouses as $w)
                                <option value="{{ $w->id }}"
                                        {{ old('warehouse_id') == $w->id ? 'selected' : '' }}>
                                    {{ $w->warehouse_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label">Catatan (global)</label>
                        <input type="text" name="notes" id="notes_header"
                               class="form-control nav-input"
                               data-next="#stock_scope_mode"
                               value="{{ old('notes') }}">
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Mode Stok</label>
                        <select name="stock_scope_mode" id="stock_scope_mode"
                                class="form-select">
                            <option value="">— Pilih Mode —</option>
                            <option value="single" {{ old('stock_scope_mode','single') === 'single' ? 'selected' : '' }}>
                                Item (manual)
                            </option>
                            <option value="all" {{ old('stock_scope_mode') === 'all' ? 'selected' : '' }}>
                                All Produk di Warehouse
                            </option>
                        </select>
                        <small class="text-muted d-block">
                            Mode "Semua Produk" akan memuat semua item via AJAX.
                        </small>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Mode Update Harga</label>
                        <select name="price_update_mode" id="price_update_mode"
                                class="form-select">
                            <option value="stock" {{ old('price_update_mode','stock') === 'stock' ? 'selected' : '' }}>
                                Update Stok Fisik
                            </option>
                            <option value="purchase" {{ old('price_update_mode') === 'purchase' ? 'selected' : '' }}>
                                Update Harga Beli
                            </option>
                            <option value="selling" {{ old('price_update_mode') === 'selling' ? 'selected' : '' }}>
                                Update Harga Jual
                            </option>
                            <option value="purchase_selling" {{ old('price_update_mode') === 'purchase_selling' ? 'selected' : '' }}>
                                Update Harga Beli &amp; Jual
                            </option>
                            <option value="stock_purchase_selling" {{ old('price_update_mode') === 'stock_purchase_selling' ? 'selected' : '' }}>
                                Update Stok Fisik + Harga Beli &amp; Jual
                            </option>
                        </select>
                        <small class="text-muted d-block">
                            Kolom pada tabel di bawah akan menyesuaikan mode update.
                        </small>
                    </div>
                </div>

                {{-- Wrapper tabel item --}}
                <div id="itemsSection" class="mt-3">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-2" id="tblItems">
                            <thead class="table-light">
                                <tr class="text-center">
                                    <th style="width:30%">Produk</th>
                                    <th class="col-stock" style="width:12%">Stok Fisik</th>
                                    <th class="col-price-beli" style="width:14%">Harga Beli</th>
                                    <th class="col-price-jual" style="width:14%">Harga Jual</th>
                                    <th style="width:22%">Catatan Item</th>
                                    <th style="width:8%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <select name="items[0][product_id]" id="items-0-product_id"
                                                class="form-select item-product nav-input"
                                                data-next="#items-0-qty_after"
                                                required>
                                            <option value="">— Pilih Produk —</option>
                                            @foreach(\App\Models\Product::orderBy('name')->get() as $p)
                                                <option value="{{ $p->id }}">{{ $p->product_code }} — {{ $p->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="td-stock">
                                        <input type="number"
                                               name="items[0][qty_after]"
                                               id="items-0-qty_after"
                                               class="form-control nav-input"
                                               data-next="#items-0-purchasing_price"
                                               min="0">
                                    </td>
                                    <td class="td-price-beli">
                                        <input type="number"
                                               name="items[0][purchasing_price]"
                                               id="items-0-purchasing_price"
                                               class="form-control nav-input"
                                               data-next="#items-0-selling_price"
                                               min="0">
                                    </td>
                                    <td class="td-price-jual">
                                        <input type="number"
                                               name="items[0][selling_price]"
                                               id="items-0-selling_price"
                                               class="form-control nav-input"
                                               data-next="#items-0-notes"
                                               min="0">
                                    </td>
                                    <td>
                                        <input type="text"
                                               name="items[0][notes]"
                                               id="items-0-notes"
                                               class="form-control nav-input"
                                               data-next="#btnAddRow">
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-danger btnRemoveRow" tabindex="-1">
                                            <i class="bx bx-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between">
                        <button type="button" id="btnAddRow" class="btn btn-outline-secondary">
                            <i class="bx bx-plus"></i> Tambah Baris (Alt+N)
                        </button>

                        <button type="submit" id="btnSave" class="btn btn-primary">
                            <i class="bx bx-save"></i> Simpan (Alt+S)
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </div>

    {{-- ================== LIST SUMMARY ADJUSTMENT ================== --}}
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Riwayat Adjustment (Dokumen)</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="tblAdjustments">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 70px">ID</th>
                            <th>Kode</th>
                            <th>Tanggal</th>
                            <th>Warehouse</th>
                            <th>Jumlah Item</th>
                            <th>Dibuat Oleh</th>
                            <th>Dibuat Pada</th>
                            <th style="width: 130px" class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($adjustments as $adj)
                            <tr>
                                <td>{{ $adj->id }}</td>
                                <td>{{ $adj->adj_code }}</td>
                                <td>{{ $adj->adj_date->format('d/m/Y') }}</td>
                                <td>
                                    @if($adj->warehouse_id)
                                        {{ $adj->warehouse->warehouse_name ?? '-' }}
                                    @else
                                        Stock Central
                                    @endif
                                </td>
                                <td>{{ $adj->items_count }}</td>
                                <td>{{ $adj->creator->name ?? '-' }}</td>
                                <td>{{ $adj->created_at->format('d/m/Y H:i') }}</td>
                                <td class="text-end">
                                    <button type="button"
                                            class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="modal"
                                            data-bs-target="#mdlAdj-{{ $adj->id }}">
                                        <i class="bx bx-search-alt"></i> Detail
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted">Belum ada adjustment.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="p-2">
                {{ $adjustments->links() }}
            </div>
        </div>
    </div>

</div>

{{-- ================== MODAL DETAIL UNTUK TIAP ADJUSTMENT ================== --}}
@foreach($adjustments as $adj)
    @php
        $mode = $adj->price_update_mode;

        // dukung kode baru & lama
        $showBeli = in_array($mode, [
            'purchase', 'purchase_selling', 'stock_purchase_selling',
            'update_purchase', 'update_both'
        ], true);

        $showJual = in_array($mode, [
            'selling', 'purchase_selling', 'stock_purchase_selling',
            'update_selling', 'update_both'
        ], true);

        $colCount = 5 + 1 /* catatan */ + ($showBeli ? 1 : 0) + ($showJual ? 1 : 0);
    @endphp

    <div class="modal fade" id="mdlAdj-{{ $adj->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <div>
                        <h5 class="modal-title fw-bold">FORM STOCK ADJUSTMENT</h5>
                        <small class="text-muted">Detail dokumen penyesuaian stok</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    {{-- Header dokumen --}}
                    <div class="border rounded p-3 mb-3">
                        <div class="row mb-1">
                            <div class="col-md-6">
                                <div><strong>No. Dokumen</strong> : {{ $adj->adj_code }}</div>
                                <div><strong>Tanggal</strong> : {{ $adj->adj_date->format('d/m/Y') }}</div>
                                <div><strong>Warehouse</strong> :
                                    @if($adj->warehouse_id)
                                        {{ $adj->warehouse->warehouse_name ?? '-' }}
                                    @else
                                        Stock Central
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div><strong>Dibuat oleh</strong> : {{ $adj->creator->name ?? '-' }}</div>
                                <div><strong>Dibuat pada</strong> : {{ $adj->created_at->format('d/m/Y H:i') }}</div>
                                <div><strong>Jumlah item</strong> : {{ $adj->items_count }}</div>
                            </div>
                        </div>
                        <div class="mt-2">
                            <strong>Catatan Global</strong><br>
                            <span class="text-muted">{{ $adj->notes ?: '-' }}</span>
                        </div>
                    </div>

                    {{-- Detail item --}}
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light">
                                <tr class="text-center">
                                    <th style="width:40px">No.</th>
                                    <th>Produk</th>
                                    <th class="text-end" style="width:110px">Stok Sebelum</th>
                                    <th class="text-end" style="width:110px">Stok Sesudah</th>
                                    <th class="text-end" style="width:110px">Selisih</th>

                                    @if($showBeli)
                                        <th class="text-end" style="width:150px">
                                            Harga Beli<br>
                                            <small class="text-muted">Sebelum → Sesudah</small>
                                        </th>
                                    @endif

                                    @if($showJual)
                                        <th class="text-end" style="width:150px">
                                            Harga Jual<br>
                                            <small class="text-muted">Sebelum → Sesudah</small>
                                        </th>
                                    @endif

                                    <th style="width:220px">Catatan Item</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($adj->items as $i => $it)
                                    @php
                                        $diff = (int) $it->qty_diff;
                                        $pb   = $it->purchase_price_before;
                                        $pa   = $it->purchase_price_after;
                                        $sb   = $it->selling_price_before;
                                        $sa   = $it->selling_price_after;
                                    @endphp
                                    <tr>
                                        <td class="text-center">{{ $i + 1 }}</td>
                                        <td>
                                            <div class="fw-semibold">{{ $it->product->name ?? '-' }}</div>
                                            <div class="text-muted small">{{ $it->product->product_code ?? '' }}</div>
                                        </td>
                                        <td class="text-end">{{ number_format($it->qty_before, 0, ',', '.') }}</td>
                                        <td class="text-end">{{ number_format($it->qty_after, 0, ',', '.') }}</td>
                                        <td class="text-end">
                                            @if($diff > 0)
                                                <span class="text-success fw-semibold">
                                                    +{{ number_format($diff,0,',','.') }}
                                                </span>
                                            @elseif($diff < 0)
                                                <span class="text-danger fw-semibold">
                                                    {{ number_format($diff,0,',','.') }}
                                                </span>
                                            @else
                                                <span class="text-muted">0</span>
                                            @endif
                                        </td>

                                        @if($showBeli)
                                            <td class="text-end">
                                                @if(!is_null($pb) || !is_null($pa))
                                                    {{ !is_null($pb) ? 'Rp'.number_format($pb,0,',','.') : '-' }}
                                                    <span class="text-muted">→</span>
                                                    {{ !is_null($pa) ? 'Rp'.number_format($pa,0,',','.') : '-' }}
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                        @endif

                                        @if($showJual)
                                            <td class="text-end">
                                                @if(!is_null($sb) || !is_null($sa))
                                                    {{ !is_null($sb) ? 'Rp'.number_format($sb,0,',','.') : '-' }}
                                                    <span class="text-muted">→</span>
                                                    {{ !is_null($sa) ? 'Rp'.number_format($sa,0,',','.') : '-' }}
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                        @endif

                                        <td>{{ $it->notes ?: '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $colCount }}" class="text-center text-muted">
                                            Tidak ada item.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endforeach

{{-- ================== JS ================== --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    // SweetAlert2 untuk success & error
    @if(session('success'))
        Swal.fire({
            title: 'Berhasil',
            text: @json(session('success')),
            icon: 'success',
            confirmButtonText: 'OK'
        });
    @endif

    @if($errors->any())
        const errs = @json($errors->all());
        Swal.fire({
            title: 'Terjadi Kesalahan',
            html: errs.join('<br>'),
            icon: 'error',
            confirmButtonText: 'OK'
        });
    @endif

    let rowIndex = 1;

    const tblItemsBody      = document.querySelector('#tblItems tbody');
    const btnAddRow         = document.getElementById('btnAddRow');
    const frmAdjust         = document.getElementById('frmAdjust');
    const stockScopeSelect  = document.getElementById('stock_scope_mode');
    const updateModeSelect  = document.getElementById('price_update_mode');
    const warehouseSelect   = document.getElementById('warehouse_id');
    const csrfToken         = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const ajaxProductsUrl   = "{{ route('stock-adjustments.ajax-products') }}";

    const itemsSection      = document.getElementById('itemsSection');
    const btnSave           = document.getElementById('btnSave');

    // GLOBAL SEARCH riwayat - kalau ada input id="globalSearch" di navbar
    const globalSearch  = document.getElementById('globalSearch');
    const adjTableBody  = document.querySelector('#tblAdjustments tbody');
    if (globalSearch && adjTableBody) {
        globalSearch.addEventListener('keyup', function () {
            const q = this.value.toLowerCase();
            adjTableBody.querySelectorAll('tr').forEach(tr => {
                const text = tr.textContent.toLowerCase();
                tr.style.display = text.includes(q) ? '' : 'none';
            });
        });
    }

    // Enter => pindah ke field berikutnya
    document.addEventListener('keydown', function (e) {
        if (e.target.classList.contains('nav-input') && e.key === 'Enter') {
            e.preventDefault();
            const nextSel = e.target.dataset.next;
            if (nextSel) {
                const next = document.querySelector(nextSel);
                if (next) next.focus();
            }
        }
    });

    // ==== SHOW / HIDE kolom sesuai Mode Update Harga ====
    function applyUpdateModeUI() {
        const mode = updateModeSelect.value;

        const showStock = (mode === 'stock' || mode === 'stock_purchase_selling');
        const showBeli  = (mode === 'purchase' || mode === 'purchase_selling' || mode === 'stock_purchase_selling');
        const showJual  = (mode === 'selling'  || mode === 'purchase_selling' || mode === 'stock_purchase_selling');

        document.querySelectorAll('.col-stock').forEach(el => el.classList.toggle('d-none', !showStock));
        document.querySelectorAll('.td-stock').forEach(el => el.classList.toggle('d-none', !showStock));

        document.querySelectorAll('.col-price-beli').forEach(el => el.classList.toggle('d-none', !showBeli));
        document.querySelectorAll('.td-price-beli').forEach(el => el.classList.toggle('d-none', !showBeli));

        document.querySelectorAll('.col-price-jual').forEach(el => el.classList.toggle('d-none', !showJual));
        document.querySelectorAll('.td-price-jual').forEach(el => el.classList.toggle('d-none', !showJual));

        // required sesuai mode
        document.querySelectorAll('input[name$="[qty_after]"]').forEach(i => i.required = showStock);
        document.querySelectorAll('input[name$="[purchasing_price]"]').forEach(i => i.required = showBeli);
        document.querySelectorAll('input[name$="[selling_price]"]').forEach(i => i.required = showJual);
    }

    updateModeSelect.addEventListener('change', applyUpdateModeUI);
    applyUpdateModeUI();

    function updateItemsSectionVisibility() {
        const mode = stockScopeSelect.value;

        if (!mode) {
            itemsSection.classList.add('d-none');
            btnAddRow.disabled = true;
            btnSave.disabled   = true;
            tblItemsBody.innerHTML = '';
        } else {
            itemsSection.classList.remove('d-none');
            btnSave.disabled   = false;
            if (mode === 'single' && !tblItemsBody.querySelector('tr')) {
                addDefaultRow();
            }
        }
    }

    function addDefaultRow() {
        const firstRowHtml = `
            <tr>
                <td>
                    <select name="items[0][product_id]" id="items-0-product_id"
                            class="form-select item-product nav-input"
                            data-next="#items-0-qty_after"
                            required>
                        <option value="">— Pilih Produk —</option>
                        @foreach(\App\Models\Product::orderBy('name')->get() as $p)
                            <option value="{{ $p->id }}">{{ $p->product_code }} — {{ $p->name }}</option>
                        @endforeach
                    </select>
                </td>
                <td class="td-stock">
                    <input type="number"
                           name="items[0][qty_after]"
                           id="items-0-qty_after"
                           class="form-control nav-input"
                           data-next="#items-0-purchasing_price"
                           min="0">
                </td>
                <td class="td-price-beli">
                    <input type="number"
                           name="items[0][purchasing_price]"
                           id="items-0-purchasing_price"
                           class="form-control nav-input"
                           data-next="#items-0-selling_price"
                           min="0">
                </td>
                <td class="td-price-jual">
                    <input type="number"
                           name="items[0][selling_price]"
                           id="items-0-selling_price"
                           class="form-control nav-input"
                           data-next="#items-0-notes"
                           min="0">
                </td>
                <td>
                    <input type="text"
                           name="items[0][notes]"
                           id="items-0-notes"
                           class="form-control nav-input"
                           data-next="#btnAddRow">
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger btnRemoveRow" tabindex="-1">
                        <i class="bx bx-trash"></i>
                    </button>
                </td>
            </tr>
        `;
        tblItemsBody.insertAdjacentHTML('beforeend', firstRowHtml);
        applyUpdateModeUI();
    }

    function makeRow(idx, item = null) {
        const tr = document.createElement('tr');

        const productCellHtml = item
            ? `
                <input type="hidden" name="items[${idx}][product_id]" value="${item.id}">
                <div class="fw-semibold">${item.product_code} — ${item.name}</div>
            `
            : `
                <select name="items[${idx}][product_id]" id="items-${idx}-product_id"
                        class="form-select item-product nav-input"
                        data-next="#items-${idx}-qty_after"
                        required>
                    <option value="">— Pilih Produk —</option>
                    @foreach(\App\Models\Product::orderBy('name')->get() as $p)
                        <option value="{{ $p->id }}">{{ $p->product_code }} — {{ $p->name }}</option>
                    @endforeach
                </select>
            `;

        const qtyValue = item ? item.qty_before : '';

        tr.innerHTML = `
            <td>
                ${productCellHtml}
            </td>
            <td class="td-stock">
                <input type="number"
                       name="items[${idx}][qty_after]"
                       id="items-${idx}-qty_after"
                       class="form-control nav-input"
                       data-next="#items-${idx}-purchasing_price"
                       min="0"
                       value="${qtyValue}">
            </td>
            <td class="td-price-beli">
                <input type="number"
                       name="items[${idx}][purchasing_price]"
                       id="items-${idx}-purchasing_price"
                       class="form-control nav-input"
                       data-next="#items-${idx}-selling_price"
                       min="0">
            </td>
            <td class="td-price-jual">
                <input type="number"
                       name="items[${idx}][selling_price]"
                       id="items-${idx}-selling_price"
                       class="form-control nav-input"
                       data-next="#items-${idx}-notes"
                       min="0">
            </td>
            <td>
                <input type="text"
                       name="items[${idx}][notes]"
                       id="items-${idx}-notes"
                       class="form-control nav-input"
                       data-next="#btnAddRow">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger btnRemoveRow" tabindex="-1">
                    <i class="bx bx-trash"></i>
                </button>
            </td>
        `;

        tblItemsBody.appendChild(tr);
        applyUpdateModeUI();

        const firstInput = tr.querySelector('.item-product') || tr.querySelector('input[name$="[qty_after]"]');
        if (firstInput) firstInput.focus();
    }

    function addRow() {
        const idx = rowIndex++;
        makeRow(idx, null);
    }

    btnAddRow.addEventListener('click', function () {
        if (stockScopeSelect.value === 'single') {
            addRow();
        }
    });

    tblItemsBody.addEventListener('click', function (e) {
        if (e.target.closest('.btnRemoveRow')) {
            const rows = tblItemsBody.querySelectorAll('tr');
            if (rows.length === 1) {
                const inputs = rows[0].querySelectorAll('input, select');
                inputs.forEach(i => i.value = '');
            } else {
                e.target.closest('tr').remove();
            }
        }
    });

    function loadAllProducts() {
        const whId = warehouseSelect.value || '';

        tblItemsBody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center text-muted">Memuat data produk...</td>
            </tr>
        `;

        const params = new URLSearchParams({ warehouse_id: whId });

        fetch(ajaxProductsUrl + '?' + params.toString(), {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(json => {
            if (json.status !== 'ok') throw new Error('Status bukan ok');

            tblItemsBody.innerHTML = '';
            rowIndex = 0;

            json.items.forEach(item => {
                const idx = rowIndex++;
                makeRow(idx, item);
            });

            if (rowIndex === 0) {
                tblItemsBody.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center text-muted">Tidak ada produk.</td>
                    </tr>
                `;
            }
        })
        .catch(err => {
            console.error(err);
            tblItemsBody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center text-danger">Gagal memuat produk.</td>
                </tr>
            `;
        });
    }

    stockScopeSelect.addEventListener('change', function () {
        const val = this.value;
        updateItemsSectionVisibility();

        if (!val) return;

        if (val === 'all') {
            btnAddRow.disabled = true;
            loadAllProducts();
        } else if (val === 'single') {
            btnAddRow.disabled = false;
            tblItemsBody.innerHTML = '';
            rowIndex = 1;
            addDefaultRow();
        }
    });

    warehouseSelect.addEventListener('change', function () {
        if (stockScopeSelect.value === 'all') {
            loadAllProducts();
        }
    });

    // Hotkey global
    document.addEventListener('keydown', function (e) {
        if (e.altKey && e.key.toLowerCase() === 'n') {
            e.preventDefault();
            if (stockScopeSelect.value === 'single') {
                addRow();
            }
        }

        if (e.altKey && e.key.toLowerCase() === 's') {
            e.preventDefault();
            frmAdjust.submit();
        }
    });

    // init
    updateItemsSectionVisibility();
    applyUpdateModeUI();

    if (stockScopeSelect.value === 'all') {
        btnAddRow.disabled = true;
        loadAllProducts();
    }
});
</script>
@endsection
