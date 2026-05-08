@extends('layouts.app')
@section('title', $order->name ?? $order->external_order_id ?? 'Order #'.$order->id)

@push('styles')
<style>
#label-preview-wrap { position: relative; background: white; border: 2px solid #dee2e6; overflow: hidden; display: inline-block; }
#label-preview-wrap canvas { position: absolute; }
.lp-abs { position: absolute; }
</style>
@endpush

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <a href="{{ route('asl.orders.index') }}" class="text-muted text-decoration-none small">
            <i class="bi bi-arrow-left"></i> Orders
        </a>
        <h4 class="mb-0 mt-1">
            {{ $order->name ?? $order->external_order_id ?? '#'.$order->id }}
            <span class="badge status-badge badge-{{ $order->status }} ms-2">{{ $order->status }}</span>
        </h4>
        <small class="text-muted">
            @if($order->external_order_id)
                <code class="small">{{ $order->external_order_id }}</code> &nbsp;|&nbsp;
            @endif
            <i class="bi bi-upc-scan"></i>
            <strong>{{ number_format($order->total_codes_downloaded) }}</strong>
            / {{ number_format($order->total_codes_requested) }} codes downloaded
            &nbsp;|&nbsp;
            <i class="bi bi-calendar3"></i> {{ $order->created_at?->format('d M Y, H:i') }}
        </small>
    </div>
    <div class="d-flex gap-2">
        {{-- API-only controls: only show when order was synced from API --}}
        @if(! $order->isDone() && $order->external_order_id)
            <form method="POST" action="{{ route('asl.orders.refresh', $order) }}">
                @csrf
                <button class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-repeat"></i> Refresh Status
                </button>
            </form>
            <form method="POST" action="{{ route('asl.orders.poll', $order) }}">
                @csrf
                <button class="btn btn-outline-info btn-sm">
                    <i class="bi bi-clock"></i> Auto-Poll
                </button>
            </form>
        @endif
        @if($order->total_codes_downloaded > 0)
            <a href="{{ route('asl.labels.designer', $order) }}" class="btn btn-success btn-sm">
                <i class="bi bi-printer"></i> Design Labels
            </a>
        @endif
        @if($order->pdf_path)
            <a href="{{ Storage::url($order->pdf_path) }}" target="_blank" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-file-earmark-pdf"></i> Download PDF
            </a>
        @endif
    </div>
</div>

{{-- Label Template Selector --}}
@if($order->total_codes_downloaded > 0)
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-layout-text-window-reverse"></i> Label Template</span>
        @if($order->labelTemplate)
            <span class="badge bg-primary">{{ $order->labelTemplate->name }}</span>
        @endif
    </div>
    <div class="card-body">
        @if($templates->isEmpty())
            <div class="text-muted small">
                No templates yet.
                <a href="{{ route('asl.label-templates.create') }}">Create your first template</a>
                to enable custom label layouts.
            </div>
        @else
        <div class="row align-items-start g-3">
            <div class="col-md-5">
                <label class="form-label fw-semibold small">Select Template</label>
                <select id="tpl-select" class="form-select form-select-sm">
                    <option value="">— Default layout (no template) —</option>
                    @foreach($templates as $tpl)
                        <option value="{{ $tpl->id }}"
                                data-config="{{ json_encode($tpl->elements) }}"
                                data-w="{{ $tpl->width_mm }}"
                                data-h="{{ $tpl->height_mm }}"
                                {{ $order->label_template_id == $tpl->id ? 'selected' : '' }}>
                            {{ $tpl->name }} ({{ $tpl->width_mm }}×{{ $tpl->height_mm }}mm)
                        </option>
                    @endforeach
                </select>
                <div class="d-flex gap-2 mt-3">
                    <button id="btn-gen-pdf" class="btn btn-danger btn-sm" {{ $firstCode ? '' : 'disabled' }}>
                        <i class="bi bi-file-earmark-pdf"></i> Generate PDF
                    </button>
                    <a href="{{ route('asl.labels.designer', $order) }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-printer"></i> Print View
                    </a>
                    <a href="{{ route('asl.label-templates.create') }}" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-plus"></i> New Template
                    </a>
                </div>
                <div id="pdf-status" class="mt-2 small"></div>
            </div>
            <div class="col-md-7 text-center">
                <div class="text-muted small mb-2">Preview <span id="prev-size-lbl" class="text-primary"></span></div>
                <div id="label-preview-wrap">
                    <canvas id="prev-dm-c" class="lp-abs"></canvas>
                    <div id="prev-nm-d" class="lp-abs" style="line-height:1.3;overflow:hidden;">
                        {{ $firstProduct?->name ?? ($firstCode?->gtin ?? 'Product Name') }}
                    </div>
                    <canvas id="prev-en-c" class="lp-abs"></canvas>
                    <div id="prev-bt-d" class="lp-abs" style="color:#555;">{{ $firstCode?->batch ?? 'BATCH001' }}</div>
                    <div id="prev-pn-d" class="lp-abs" style="color:#aaa;">1</div>
                </div>
                @if(!$firstCode)
                    <div class="text-muted small mt-2">No available codes to preview.</div>
                @endif
            </div>
        </div>
        @endif
    </div>
</div>
@endif

{{-- GTIN Items --}}
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-layers"></i> Items — {{ $order->items->count() }} GTIN group(s)</span>
        <small class="text-muted">Each row is one product (GTIN) in this order</small>
    </div>
    <div class="card-body p-0">
        @if($order->items->isEmpty())
            <div class="p-4 text-center text-muted">No items found.</div>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>GTIN</th>
                        <th>Quantity</th>
                        <th>Downloaded</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->items as $item)
                    <tr>
                        <td><code class="small">{{ $item->gtin ?? '—' }}</code></td>
                        <td>{{ number_format($item->quantity) }}</td>
                        <td>
                            {{ number_format($item->codes_downloaded) }}
                            @if($item->quantity > 0)
                                @php $pct = round($item->codes_downloaded / $item->quantity * 100) @endphp
                                <div class="progress mt-1" style="height:3px; width:80px;">
                                    <div class="progress-bar bg-success" style="width:{{ $pct }}%"></div>
                                </div>
                            @endif
                        </td>
                        <td>
                            <span class="badge status-badge badge-{{ $item->status }}">{{ $item->status }}</span>
                        </td>
                        <td class="text-end">
                            @if(in_array($item->status, ['READY', 'AVAILABLE']))
                                <form method="POST" action="{{ route('asl.orders.download', [$order, $item]) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-success">
                                        <i class="bi bi-download"></i> Download
                                    </button>
                                </form>
                            @elseif($item->status === 'DOWNLOADED')
                                <span class="text-success small">
                                    <i class="bi bi-check-circle-fill"></i> {{ number_format($item->codes_downloaded) }} saved
                                </span>
                            @elseif($item->status === 'PENDING')
                                <span class="text-muted small"><i class="bi bi-hourglass-split"></i> Waiting…</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

{{-- Marking Codes --}}
@if($codes->total() > 0)
<div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-upc-scan"></i> Marking Codes ({{ number_format($codes->total()) }})</span>
        <a href="{{ route('asl.labels.designer', $order) }}" class="btn btn-success btn-sm">
            <i class="bi bi-printer"></i> Design & Print Labels
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" style="font-size:0.8rem;">
                <thead class="table-light">
                    <tr>
                        <th class="text-muted fw-normal">#</th>
                        <th>GTIN</th>
                        <th>Serial Number</th>
                        <th>Expiry</th>
                        <th>Batch</th>
                        <th>Status</th>
                        <th>Printed</th>
                        <th>Applied</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($codes as $code)
                    <tr>
                        <td class="text-muted">{{ $code->id }}</td>
                        <td><code class="small">{{ $code->gtin ?? '—' }}</code></td>
                        <td>
                            <code class="small" title="{{ $code->cis }}">{{ $code->serial_number ?? '—' }}</code>
                        </td>
                        <td>
                            @if($code->expiry_date)
                                <span class="{{ $code->expiry_date->isPast() ? 'text-danger' : 'text-dark' }}">
                                    {{ $code->expiry_date->format('d.m.Y') }}
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>{{ $code->batch ?? '—' }}</td>
                        <td>
                            <span class="badge status-badge badge-{{ $code->status }}">
                                {{ $code->status }}
                            </span>
                        </td>
                        <td>
                            @if($code->printed_at)
                                <small class="text-muted">{{ $code->printed_at->format('d/m H:i') }}</small>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($code->applied_at)
                                <small class="text-muted">{{ $code->applied_at->format('d/m H:i') }}</small>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($codes->hasPages())
        <div class="card-footer bg-white">
            {{ $codes->links() }}
        </div>
        @endif
    </div>
</div>
@endif
@endsection

@push('scripts')
@if($order->total_codes_downloaded > 0 && $templates->isNotEmpty())
<script src="https://cdn.jsdelivr.net/npm/bwip-js@4/dist/bwip-js-min.js"></script>
<script>
const SCALE  = 4; // px per mm for order preview
const setUrl = "{{ route('asl.labels.setTemplate', $order) }}";
const pdfUrl = "{{ route('asl.labels.generatePdf', $order) }}";
const csrfToken = "{{ csrf_token() }}";

@php
    $firstGtin = $firstCode?->gtin ?? '04607004951015';
    $ean13Sample = (strlen($firstGtin) === 14 && $firstGtin[0] === '0') ? substr($firstGtin, 1) : '5901234123457';
    if (!preg_match('/^\d{13}$/', $ean13Sample)) $ean13Sample = '5901234123457';
    $batchSample = $firstCode?->batch ?? 'BATCH001';
    $nameSample  = addslashes($firstProduct?->name ?? $firstCode?->gtin ?? 'Product Name');
@endphp

const SAMPLE_EAN  = "{{ $ean13Sample }}";
const SAMPLE_GTIN = "{{ $firstGtin }}";
const SAMPLE_NAME = "{{ $nameSample }}";
const SAMPLE_BATCH = "{{ $batchSample }}";

function renderPreview(config, w, h) {
    const wrap = document.getElementById('label-preview-wrap');
    wrap.style.width  = (w * SCALE) + 'px';
    wrap.style.height = (h * SCALE) + 'px';
    document.getElementById('prev-size-lbl').textContent = `(${w}×${h} mm)`;

    const dm = config?.datamatrix  ?? {x:1, y:7.5, size:25};
    const nm = config?.name        ?? {visible:true, x1:27, y1:1,  x2:59, y2:16, font_size:7.5, bold:true};
    const en = config?.ean13       ?? {visible:true, x1:27, y1:19, x2:59, y2:37};
    const bt = config?.batch       ?? {visible:true, x1:27, y1:16, x2:59, y2:19, font_size:5};
    const pn = config?.page_number ?? {visible:true, x1:27, y1:37, x2:42, y2:39, font_size:5};

    // DataMatrix — render to tmp canvas then draw at target size
    const dmC   = document.getElementById('prev-dm-c');
    const dmSz  = Math.round((dm.size ?? 25) * SCALE);
    dmC.style.left = ((dm.x ?? 1) * SCALE) + 'px';
    dmC.style.top  = ((dm.y ?? 7.5) * SCALE) + 'px';
    dmC.width  = dmSz;
    dmC.height = dmSz;
    try {
        const tmp = document.createElement('canvas');
        bwipjs.toCanvas(tmp, {
            bcid: 'gs1datamatrix',
            text: `[01]${SAMPLE_GTIN}[21]SN000001`,
            parse: true, scale: 4,
        });
        const ctx = dmC.getContext('2d');
        ctx.imageSmoothingEnabled = false;
        ctx.drawImage(tmp, 0, 0, dmSz, dmSz);
    } catch(e) { console.warn('DM', e); }

    // Name
    const nmD = document.getElementById('prev-nm-d');
    nmD.style.display    = nm.visible ? 'block' : 'none';
    nmD.style.left       = ((nm.x1 ?? 27) * SCALE) + 'px';
    nmD.style.top        = ((nm.y1 ?? 1)  * SCALE) + 'px';
    nmD.style.width      = (((nm.x2 ?? 59) - (nm.x1 ?? 27)) * SCALE) + 'px';
    nmD.style.maxHeight  = (((nm.y2 ?? 16) - (nm.y1 ?? 1))  * SCALE) + 'px';
    nmD.style.fontSize   = (nm.font_size ?? 7.5) + 'px';
    nmD.style.fontWeight = nm.bold ? 'bold' : 'normal';
    nmD.textContent      = SAMPLE_NAME;

    // EAN-13 — render to tmp canvas then draw at target size
    const enC = document.getElementById('prev-en-c');
    enC.style.display = en.visible ? 'block' : 'none';
    if (en.visible) {
        const enW = Math.round(((en.x2 ?? 59) - (en.x1 ?? 27)) * SCALE);
        const enH = Math.round(((en.y2 ?? 37) - (en.y1 ?? 19)) * SCALE);
        enC.style.left = ((en.x1 ?? 27) * SCALE) + 'px';
        enC.style.top  = ((en.y1 ?? 19) * SCALE) + 'px';
        enC.width  = enW;
        enC.height = enH;
        try {
            const tmp = document.createElement('canvas');
            bwipjs.toCanvas(tmp, {
                bcid: 'ean13', text: SAMPLE_EAN,
                scale: Math.max(1, Math.round(enH / 15)),
                includetext: true, textxalign: 'center',
            });
            const ctx = enC.getContext('2d');
            ctx.imageSmoothingEnabled = true;
            ctx.drawImage(tmp, 0, 0, enW, enH);
        } catch(e) { console.warn('EAN', e); }
    }

    // Batch
    const btD = document.getElementById('prev-bt-d');
    btD.style.display  = bt.visible ? 'block' : 'none';
    btD.style.left     = ((bt.x1 ?? 27) * SCALE) + 'px';
    btD.style.top      = ((bt.y1 ?? 16) * SCALE) + 'px';
    btD.style.fontSize = (bt.font_size ?? 5) + 'px';
    btD.textContent    = SAMPLE_BATCH;

    // Page number
    const pnD = document.getElementById('prev-pn-d');
    pnD.style.display  = pn.visible ? 'block' : 'none';
    pnD.style.left     = ((pn.x1 ?? 27) * SCALE) + 'px';
    pnD.style.top      = ((pn.y1 ?? 37) * SCALE) + 'px';
    pnD.style.fontSize = (pn.font_size ?? 5) + 'px';
}

// Default preview on load (no template or saved template)
(function() {
    const sel = document.getElementById('tpl-select');
    const opt = sel?.options[sel.selectedIndex];
    let config = null, wMm = 60, hMm = 40;
    if (opt?.value) {
        try { config = JSON.parse(opt.dataset.config); } catch(e) {}
        wMm = parseFloat(opt.dataset.w) || 60;
        hMm = parseFloat(opt.dataset.h) || 40;
    }
    renderPreview(config, wMm, hMm);
})();

document.getElementById('tpl-select')?.addEventListener('change', async function() {
    const opt = this.options[this.selectedIndex];
    let config = null, wMm = 60, hMm = 40;
    if (opt.value) {
        try { config = JSON.parse(opt.dataset.config); } catch(e) {}
        wMm = parseFloat(opt.dataset.w) || 60;
        hMm = parseFloat(opt.dataset.h) || 40;
    }
    renderPreview(config, wMm, hMm);

    // Save template selection to order
    await fetch(setUrl, {
        method: 'POST',
        headers: {'Content-Type':'application/json', 'X-CSRF-TOKEN': csrfToken},
        body: JSON.stringify({label_template_id: opt.value || null}),
    });
});

document.getElementById('btn-gen-pdf')?.addEventListener('click', async function() {
    this.disabled = true;
    const status  = document.getElementById('pdf-status');
    status.innerHTML = '<span class="text-muted"><i class="bi bi-hourglass-split"></i> Generating PDF…</span>';

    try {
        const resp = await fetch(pdfUrl, {
            method: 'POST',
            headers: {'Content-Type':'application/json', 'X-CSRF-TOKEN': csrfToken},
            body: JSON.stringify({}),
        });
        const data = await resp.json();
        if (data.success) {
            status.innerHTML = `<span class="text-success"><i class="bi bi-check-circle"></i>
                ${data.count} labels •
                <a href="${data.url}" target="_blank" class="fw-semibold">Download PDF</a>
            </span>`;
        } else {
            status.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-circle"></i> ${data.message}</span>`;
        }
    } catch(e) {
        status.innerHTML = '<span class="text-danger">Request failed. Try again.</span>';
    }
    this.disabled = false;
});
</script>
@endif
@endpush
