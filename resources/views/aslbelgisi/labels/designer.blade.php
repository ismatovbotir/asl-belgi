@extends('layouts.app')
@section('title', 'Label Designer — ' . ($order->name ?? $order->id))

@push('styles')
<style>
    /* 60 × 40 mm @ 96 dpi */
    .label-preview {
        width: 227px; height: 151px;
        border: 1.5px solid #ccc;
        background: #fff;
        padding: 4px;  /* 1 mm */
        font-family: Arial, sans-serif;
        display: flex;
        flex-direction: row;
        box-shadow: 0 2px 8px rgba(0,0,0,0.12);
        border-radius: 2px;
        overflow: hidden;
    }

    /* Left 50%: DataMatrix */
    .lp-left {
        width: 50%; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
    }
    #previewDm { display: block; }

    /* Right 50%: three rows */
    .lp-right {
        width: 50%;
        display: flex; flex-direction: column;
        padding-left: 3px;
    }
    .lp-name-row {
        height: 50%; overflow: hidden;
        display: flex; align-items: flex-start;
    }
    .lp-name { font-size: 8px; font-weight: bold; line-height: 1.3; word-break: break-word; }

    .lp-bc-row {
        height: 45%;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
    }
    #previewBc  { display: block; max-width: 100%; }
    .lp-gtin    { font-size: 5.5px; font-family: monospace; color: #555; margin-top: 1px; }

    .lp-pg-row  {
        height: 5%;
        display: flex; align-items: center;
        font-size: 5.5px; color: #999; font-family: monospace;
    }
</style>
@endpush

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <a href="{{ route('asl.labels.index') }}" class="text-muted text-decoration-none small">
            <i class="bi bi-arrow-left"></i> Label Design
        </a>
        <h4 class="mb-0 mt-1">
            <i class="bi bi-layout-text-window"></i>
            {{ $order->name ?? $order->external_order_id ?? '#'.$order->id }}
        </h4>
        <small class="text-muted">
            {{ number_format($codes->total()) }} codes available
            &nbsp;|&nbsp; {{ $order->items->count() }} GTIN group(s)
            &nbsp;|&nbsp; <i class="bi bi-calendar3"></i> {{ $order->created_at?->format('d M Y') }}
        </small>
    </div>
    <a id="headerPdfLink"
       href="{{ $order->pdf_path ? route('asl.labels.downloadPdf', ['order' => $order->id, 'file' => basename($order->pdf_path)]) : '#' }}"
       class="btn btn-outline-danger btn-sm"
       style="{{ $order->pdf_path ? '' : 'display:none;' }}">
        <i class="bi bi-file-earmark-pdf"></i> Download PDF
    </a>
</div>

<div class="row g-4">

    {{-- Settings Panel --}}
    <div class="col-md-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-sliders"></i> Label Settings</div>
            <div class="card-body">
                <label class="form-label fw-semibold small">Label Size</label>
                <select class="form-select form-select-sm mb-3" id="labelSize">
                    <option value="58x40" selected>58 × 40 mm (standard)</option>
                    <option value="100x75">100 × 75 mm (large)</option>
                    <option value="40x30">40 × 30 mm (small)</option>
                </select>

                <label class="form-label fw-semibold small">Filter by GTIN</label>
                <select class="form-select form-select-sm mb-3" id="filterGtin">
                    <option value="">All GTINs</option>
                    @foreach($gtins as $gtin)
                        @php $p = $products->get($gtin) @endphp
                        <option value="{{ $gtin }}">{{ $gtin }}{{ $p ? ' — '.$p->name : '' }}</option>
                    @endforeach
                </select>

                <label class="form-label fw-semibold small">Quantity to Print</label>
                <input type="number" class="form-control form-control-sm mb-3" id="printQty"
                       value="{{ $codes->total() }}" min="1" max="{{ $codes->total() }}">

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="showProduct" checked>
                    <label class="form-check-label small" for="showProduct">Show product name</label>
                </div>
            </div>
        </div>

        {{-- Products in Order --}}
        @if($products->isNotEmpty())
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-box-seam"></i> Products in Order</div>
            <div class="card-body p-0">
                @foreach($products as $product)
                <div class="p-3 border-bottom">
                    <div class="fw-semibold small">{{ $product->name }}</div>
                    <div class="text-muted" style="font-size:0.75rem;">GTIN: <code>{{ $product->gtin }}</code></div>
                    @if($product->brand)
                        <div class="text-muted" style="font-size:0.75rem;">{{ $product->brand }}</div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @else
        <div class="card shadow-sm">
            <div class="card-body text-center py-3 text-muted small">
                <i class="bi bi-info-circle"></i>
                No product info found.<br>
                <a href="{{ route('asl.products.index') }}">Sync the product registry</a> to see product names on labels.
            </div>
        </div>
        @endif
    </div>

    {{-- Preview + Actions --}}
    <div class="col-md-8">

        {{-- Live Preview --}}
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-eye"></i> Label Preview</div>
            <div class="card-body d-flex gap-4 flex-wrap align-items-start">
                @php
                    $firstCode     = $codes->first();
                    $sampleGtin         = $firstCode?->gtin   ?? $order->items->first()?->gtin ?? '00000000000000';
                    $sampleSerial       = $firstCode?->serial_number ?? '';
                    $sampleExpiry       = $firstCode?->expiry_date?->format('d.m.Y') ?? '';
                    $sampleExpiryYymmdd = $firstCode?->expiry_date?->format('ymd') ?? '';
                    $sampleBatch        = $firstCode?->batch  ?? '';
                    $sampleProduct      = $products->get($sampleGtin);
                    // EAN-13: strip leading zero from GTIN-14
                    $sampleEan13   = (strlen($sampleGtin) === 14 && $sampleGtin[0] === '0') ? substr($sampleGtin, 1) : $sampleGtin;
                @endphp

                <div>
                    <div class="label-preview" id="previewBox">
                        <div class="lp-left">
                            <canvas id="previewDm"></canvas>
                        </div>
                        <div class="lp-right">
                            <div class="lp-name-row">
                                <div class="lp-name" id="previewName">
                                    {{ $sampleProduct?->name ?? ($order->name ?? 'Product Name') }}
                                </div>
                            </div>
                            <div class="lp-bc-row">
                                <canvas id="previewBc"></canvas>
                                <div class="lp-gtin">{{ ($sampleGtin[0] ?? '') === '0' ? substr($sampleGtin, 1) : $sampleGtin }}</div>
                            </div>
                            <div class="lp-pg-row">1</div>
                        </div>
                    </div>
                    <div class="text-muted mt-2 text-center" style="font-size:0.75rem;">60 × 40 mm preview</div>
                </div>

                <div class="d-flex flex-column gap-2 justify-content-center">
                    <div class="text-muted small">
                        <i class="bi bi-info-circle"></i>
                        Preview shows the first code.<br>
                        Each label gets its own DataMatrix.
                    </div>
                    <table class="table table-sm table-borderless mb-0" style="font-size:0.78rem;">
                        <tr><td class="text-muted py-0">GTIN</td><td class="py-0"><code>{{ $sampleGtin }}</code></td></tr>
                        <tr><td class="text-muted py-0">Serial</td><td class="py-0"><code class="small">{{ Str::limit($sampleSerial, 22) }}</code></td></tr>
                        @if($sampleExpiry)
                        <tr><td class="text-muted py-0">Expiry</td><td class="py-0">{{ $sampleExpiry }}</td></tr>
                        @endif
                        @if($sampleBatch)
                        <tr><td class="text-muted py-0">Batch</td><td class="py-0">{{ $sampleBatch }}</td></tr>
                        @endif
                    </table>
                </div>
            </div>
        </div>

        {{-- Print Actions --}}
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-printer"></i> Print</div>
            <div class="card-body">
                @if($order->items->count() > 1)
                <div class="row g-2 mb-3">
                    @foreach($order->items as $item)
                    @if($item->codes_downloaded > 0)
                    <div class="col-md-6">
                        <div class="border rounded p-3">
                            <div class="fw-semibold small mb-1"><code>{{ $item->gtin ?? '—' }}</code></div>
                            @php $ip = $products->get($item->gtin) @endphp
                            @if($ip)<div class="text-muted small">{{ $ip->name }}</div>@endif
                            <div class="text-muted small mb-2">{{ number_format($item->codes_downloaded) }} codes</div>
                            <a href="{{ route('asl.labels.print', ['order' => $order->id, 'item_id' => $item->id]) }}"
                               class="btn btn-sm btn-outline-primary" target="_blank">
                                <i class="bi bi-printer"></i> Print this GTIN
                            </a>
                        </div>
                    </div>
                    @endif
                    @endforeach
                </div>
                <hr>
                @endif

                <div class="d-flex gap-3 align-items-center flex-wrap">
                    <a id="btnPrintAll" href="{{ route('asl.labels.print', $order) }}"
                       class="btn btn-success" target="_blank">
                        <i class="bi bi-printer-fill"></i> Print All {{ number_format($codes->total()) }} Labels
                    </a>
                    <button id="btnSavePdf" type="button" class="btn btn-outline-danger"
                            data-bs-toggle="modal" data-bs-target="#pdfModal">
                        <i class="bi bi-file-earmark-pdf"></i> Save {{ number_format($codes->total()) }} as PDF
                    </button>
                    <form method="POST" action="{{ route('asl.labels.markPrinted', $order) }}">
                        @csrf
                        <button class="btn btn-outline-secondary"
                                onclick="return confirm('Mark ALL codes for this order as printed?')">
                            <i class="bi bi-check2-all"></i> Mark All as Printed
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Codes Preview --}}
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <span><i class="bi bi-upc-scan"></i> Codes (first {{ $codes->count() }} of {{ number_format($codes->total()) }})</span>
            </div>
            <div class="card-body p-0" style="max-height:280px; overflow-y:auto;">
                <table class="table table-sm mb-0" style="font-size:0.72rem;">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th>#</th>
                            <th>GTIN</th>
                            <th>Serial</th>
                            <th>Expiry</th>
                            <th>Batch</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody class="font-monospace">
                        @foreach($codes as $code)
                        <tr>
                            <td class="text-muted">{{ $loop->iteration }}</td>
                            <td>{{ $code->gtin }}</td>
                            <td title="{{ $code->cis }}" style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                {{ $code->serial_number }}
                            </td>
                            <td>{{ $code->expiry_date?->format('d.m.Y') ?? '—' }}</td>
                            <td>{{ $code->batch ?? '—' }}</td>
                            <td><span class="badge status-badge badge-{{ $code->status }}">{{ $code->status }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

{{-- PDF Generation Modal --}}
<div class="modal fade" id="pdfModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-labelledby="pdfModalLabel">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pdfModalLabel">
                    <i class="bi bi-file-earmark-pdf text-danger"></i> PDF Generation
                </h5>
            </div>
            <div class="modal-body text-center py-4" id="pdfModalBody">
                <div class="spinner-border text-danger mb-3" style="width:3rem;height:3rem;" role="status"></div>
                <div class="fw-semibold">Starting…</div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/bwip-js@3/dist/bwip-js-min.js"></script>
<script>
    const sampleGtin        = @json($sampleGtin);
    const sampleSerial      = @json($sampleSerial);
    const sampleExpiryYymmdd = @json($sampleExpiryYymmdd);
    const sampleBatch       = @json($sampleBatch);
    const sampleEan13       = @json($sampleEan13);

    function buildSampleGs1Dm() {
        if (!sampleGtin || sampleGtin.length !== 14) return null;
        let s = `(01)${sampleGtin}`;
        if (sampleSerial)       s += `(21)${sampleSerial}`;
        if (sampleExpiryYymmdd) s += `(17)${sampleExpiryYymmdd}`;
        if (sampleBatch)        s += `(10)${sampleBatch}`;
        return s;
    }

    function renderPreview() {
        const gs1dm = buildSampleGs1Dm();
        if (gs1dm) {
            try {
                bwipjs.toCanvas('previewDm', {
                    bcid: 'gs1datamatrix', text: gs1dm,
                    parse: true, scale: 4, includetext: false,
                });
            } catch (e) { console.warn('DataMatrix render:', e.message); }
        }

        try {
            if (sampleEan13 && /^\d{13}$/.test(sampleEan13)) {
                bwipjs.toCanvas('previewBc', {
                    bcid: 'ean13', text: sampleEan13,
                    scale: 1, height: 7,
                    includetext: true, textxalign: 'center', textsize: 5,
                });
            }
        } catch (e) { console.warn('EAN-13 render:', e.message); }
    }

    renderPreview();

    document.getElementById('showProduct').addEventListener('change', function () {
        document.getElementById('previewName').style.display = this.checked ? '' : 'none';
    });

    // --- Quantity + GTIN filter → update print/save buttons ---
    const totalCodes   = {{ $codes->total() }};
    const basePrintUrl = "{{ route('asl.labels.print', $order) }}";
    const orderItems   = {!! json_encode($order->items->map(fn($i) => ['id' => $i->id, 'gtin' => $i->gtin, 'codes_downloaded' => $i->codes_downloaded])->values()) !!};

    function buildPrintUrl(mode = null) {
        const gtin = document.getElementById('filterGtin').value;
        const qty  = parseInt(document.getElementById('printQty').value);
        const url  = new URL(basePrintUrl, window.location.origin);

        if (gtin) {
            const item = orderItems.find(i => i.gtin === gtin);
            if (item) url.searchParams.set('item_id', item.id);
        } else if (!isNaN(qty) && qty < totalCodes) {
            url.searchParams.set('limit', qty);
        }

        if (mode) url.searchParams.set('mode', mode);
        return url.toString();
    }

    function getDisplayCount() {
        const gtin = document.getElementById('filterGtin').value;
        if (gtin) {
            return orderItems.find(i => i.gtin === gtin)?.codes_downloaded ?? 0;
        }
        const qty = parseInt(document.getElementById('printQty').value);
        return isNaN(qty) ? totalCodes : Math.min(qty, totalCodes);
    }

    function syncButtons() {
        const n = getDisplayCount().toLocaleString();
        document.getElementById('btnPrintAll').href = buildPrintUrl();
        document.getElementById('btnPrintAll').innerHTML = `<i class="bi bi-printer-fill"></i> Print ${n} Labels`;
        document.getElementById('btnSavePdf').innerHTML  = `<i class="bi bi-file-earmark-pdf"></i> Save ${n} as PDF`;
    }

    document.getElementById('printQty').addEventListener('input', syncButtons);
    document.getElementById('filterGtin').addEventListener('change', function () {
        if (this.value) {
            const item = orderItems.find(i => i.gtin === this.value);
            if (item) document.getElementById('printQty').value = item.codes_downloaded;
        } else {
            document.getElementById('printQty').value = totalCodes;
        }
        syncButtons();
    });

    // --- PDF Generation ---
    const pdfGenerateUrl = "{{ route('asl.labels.generatePdf', $order) }}";
    const csrfToken      = "{{ csrf_token() }}";
    let   pdfModalInst   = null;

    document.getElementById('pdfModal').addEventListener('show.bs.modal', function () {
        setPdfState('generating');
    });
    document.getElementById('pdfModal').addEventListener('shown.bs.modal', function () {
        doGeneratePdf();
    });

    function setPdfState(state, data) {
        const el = document.getElementById('pdfModalBody');
        if (state === 'generating') {
            const n = getDisplayCount().toLocaleString();
            el.innerHTML = `
                <div class="spinner-border text-danger mb-3" style="width:3rem;height:3rem;" role="status"></div>
                <div class="fw-semibold">Generating PDF…</div>
                <div class="text-muted small mt-1">${n} labels — this may take a moment</div>
            `;
        } else if (state === 'success') {
            el.innerHTML = `
                <div class="fs-1 text-success mb-2"><i class="bi bi-check-circle-fill"></i></div>
                <div class="fw-semibold mb-1">PDF saved successfully</div>
                <div class="text-muted small mb-3">${data.count.toLocaleString()} labels</div>
                <a href="${data.url}" download="${data.filename}" class="btn btn-danger btn-sm me-2">
                    <i class="bi bi-download"></i> Download
                </a>
                <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            `;
            // Reveal header download link
            const hdr = document.getElementById('headerPdfLink');
            if (hdr) { hdr.href = data.url; hdr.style.display = ''; }
        } else if (state === 'error') {
            el.innerHTML = `
                <div class="fs-1 text-danger mb-2"><i class="bi bi-exclamation-triangle-fill"></i></div>
                <div class="fw-semibold mb-1">PDF generation failed</div>
                <div class="text-muted small mb-3">${data.message ?? 'Unknown error'}</div>
                <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            `;
        }
    }

    async function doGeneratePdf() {
        const gtin = document.getElementById('filterGtin').value;
        const qty  = parseInt(document.getElementById('printQty').value);

        const body = new URLSearchParams();
        body.append('_token', csrfToken);
        if (gtin) {
            const item = orderItems.find(i => i.gtin === gtin);
            if (item) body.append('item_id', item.id);
        } else if (!isNaN(qty) && qty < totalCodes) {
            body.append('limit', qty);
        }

        try {
            const resp = await fetch(pdfGenerateUrl, {
                method:  'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body:    body,
            });
            const data = await resp.json();
            if (resp.ok && data.success) {
                setPdfState('success', data);
            } else {
                setPdfState('error', { message: data.message ?? `Server error (${resp.status})` });
            }
        } catch (err) {
            setPdfState('error', { message: err.message });
        }
    }
</script>
@endpush
