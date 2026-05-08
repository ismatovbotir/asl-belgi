@extends('layouts.app')
@section('title', $template ? 'Edit: '.$template->name : 'New Label Template')

@push('styles')
<style>
/* ── Preview ────────────────────────────────────────────── */
.prev-section {
    position: sticky;
    top: 0;
    z-index: 200;
    background: #e8ecf0;
    border-radius: .5rem;
    padding: 1.25rem 1.5rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 180px;
    margin-bottom: 1.5rem;
    user-select: none;
    box-shadow: 0 2px 8px rgba(0,0,0,.12);
}
#label-preview {
    position: relative;
    background: #fff;
    border: 2px solid #0d6efd;
    overflow: visible;
    box-shadow: 0 4px 24px rgba(0,0,0,.18);
}
.lp-el, #label-preview canvas {
    position: absolute;
    cursor: grab;
    touch-action: none;
}
.lp-el { line-height: 1.3; overflow: hidden; word-break: break-word; }
.lp-el:hover, #label-preview canvas:hover { outline: 1px dashed rgba(13,110,253,.5); }
.lp-el.dragging, #label-preview canvas.dragging { cursor: grabbing !important; outline: 1px dashed #0d6efd; }
.lp-bbox {
    position: absolute;
    border: 1px dashed rgba(100,100,100,.35);
    pointer-events: none;
}
.lp-intersect {
    position: absolute;
    background: rgba(220,53,69,.28);
    border: 1px solid rgba(220,53,69,.7);
    z-index: 50;
    pointer-events: none;
}
.lp-resize-handle {
    position: absolute;
    width: 10px;
    height: 10px;
    background: #0d6efd;
    border: 2px solid #fff;
    border-radius: 2px;
    cursor: se-resize;
    z-index: 110;
    touch-action: none;
    box-shadow: 0 1px 4px rgba(0,0,0,.35);
}

/* ── Form blocks ─────────────────────────────────────────── */
.el-block { border: 1px solid #dee2e6; border-radius: .4rem; padding: .9rem 1rem .5rem; margin-bottom: 0; transition: border-color .15s, box-shadow .15s, background .15s; }
.el-block.el-active { border-color: #198754; box-shadow: 0 0 0 3px rgba(25,135,84,.15); background: rgba(25,135,84,.04); }
.el-block-title {
    font-weight: 600; font-size: .82rem; text-transform: uppercase;
    letter-spacing: .04em; color: #495057; margin-bottom: .6rem;
    display: flex; align-items: center; justify-content: space-between;
}
.fl { font-size: .72rem; color: #6c757d; margin-bottom: 1px; }
.coord-row { display: grid; grid-template-columns: 1fr 1fr; gap: .4rem; margin-bottom: .4rem; }
.coord-label { font-size: .68rem; color: #888; margin-bottom: 1px; text-transform: uppercase; letter-spacing: .03em; }
</style>
@endpush

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <a href="{{ route('asl.label-templates.index') }}" class="text-muted text-decoration-none small">
            <i class="bi bi-arrow-left"></i> Templates
        </a>
        <h4 class="mb-0 mt-1">{{ $template ? 'Edit: '.$template->name : 'New Label Template' }}</h4>
    </div>
</div>

@if($errors->any())
<div class="alert alert-danger py-2">
    <ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li class="small">{{ $e }}</li>@endforeach</ul>
</div>
@endif

{{-- ══ TOP: LIVE PREVIEW ══════════════════════════════════ --}}
<div class="prev-section" id="preview-section">
    <div class="text-muted small mb-2">
        Live Preview &nbsp;<span id="prev-size-lbl" class="text-primary fw-semibold"></span>
        <span class="text-muted ms-2" style="font-size:.68rem;">— drag to move · <i class="bi bi-arrows-angle-expand" style="font-size:.65rem;"></i> corner to resize · red = overlap</span>
    </div>
    <div id="label-preview">
        <canvas  id="prev-dm" data-el="dm"></canvas>
        <div     id="prev-nm" class="lp-el" data-el="nm">Sample Product Name</div>
        <canvas  id="prev-en" data-el="en"></canvas>
        <div     id="prev-bt" class="lp-el" data-el="bt" style="color:#555;">BATCH001</div>
        <div     id="prev-pn" class="lp-el" data-el="pn" style="color:#aaa;">1</div>
        {{-- Resize handles (one per element) --}}
        <div id="rh-dm" class="lp-resize-handle" title="Resize DataMatrix"></div>
        <div id="rh-nm" class="lp-resize-handle" title="Resize Name"></div>
        <div id="rh-en" class="lp-resize-handle" title="Resize EAN-13"></div>
        <div id="rh-bt" class="lp-resize-handle" title="Resize Batch"></div>
        <div id="rh-pn" class="lp-resize-handle" title="Resize Page#"></div>
    </div>
</div>

{{-- ══ BOTTOM: SETTINGS FORM ══════════════════════════════ --}}
<form method="POST"
      action="{{ $template ? route('asl.label-templates.update', $template) : route('asl.label-templates.store') }}">
@csrf
@if($template) @method('PUT') @endif

<div class="card shadow-sm">
  <div class="card-body">

    {{-- Label size ─────────────────────────────────────────── --}}
    <div class="el-block mb-3">
      <div class="el-block-title"><span><i class="bi bi-rulers"></i> Label Size</span></div>
      <div class="row g-2">
        <div class="col-6">
          <label class="fl">Template Name</label>
          <input type="text" name="name" class="form-control form-control-sm"
                 value="{{ old('name', $template?->name) }}" required>
        </div>
        <div class="col-3">
          <label class="fl">Width (mm)</label>
          <input type="number" id="lbl_w" name="width_mm"
                 class="form-control form-control-sm prev-trigger"
                 step="0.5" min="10" max="300"
                 value="{{ old('width_mm', $template?->width_mm ?? 60) }}" required>
        </div>
        <div class="col-3">
          <label class="fl">Height (mm)</label>
          <input type="number" id="lbl_h" name="height_mm"
                 class="form-control form-control-sm prev-trigger"
                 step="0.5" min="10" max="300"
                 value="{{ old('height_mm', $template?->height_mm ?? 40) }}" required>
        </div>
      </div>
    </div>

    {{-- 5 element blocks in a 3-column grid ─────────────────── --}}
    <div class="row g-3 mb-3">

      {{-- DataMatrix ------------------------------------------ --}}
      @php $dm = $template?->el('datamatrix') ?? $defaults['datamatrix']; @endphp
      <div class="col-4">
      <div id="block-dm" class="el-block h-100">
        <div class="el-block-title">
          <span><i class="bi bi-qr-code"></i> DataMatrix</span>
          <span class="badge bg-secondary fw-normal" style="font-size:.68rem;">always on</span>
        </div>
        <div class="coord-label">Start (X, Y)</div>
        <div class="coord-row">
          <div>
            <label class="fl">X (mm)</label>
            <input type="number" id="dm_x" name="elements[datamatrix][x]"
                   class="form-control form-control-sm prev-trigger"
                   step="0.5" value="{{ old('elements.datamatrix.x', $dm['x']) }}">
          </div>
          <div>
            <label class="fl">Y (mm)</label>
            <input type="number" id="dm_y" name="elements[datamatrix][y]"
                   class="form-control form-control-sm prev-trigger"
                   step="0.5" value="{{ old('elements.datamatrix.y', $dm['y']) }}">
          </div>
        </div>
        <div class="coord-label">Size</div>
        <div class="coord-row">
          <div>
            <label class="fl">Size (mm)</label>
            <input type="number" id="dm_size" name="elements[datamatrix][size]"
                   class="form-control form-control-sm prev-trigger"
                   step="0.5" min="5"
                   value="{{ old('elements.datamatrix.size', $dm['size']) }}">
          </div>
        </div>
      </div>
      </div>

      {{-- Product Name ----------------------------------------- --}}
      @php $nm = $template?->el('name') ?? $defaults['name']; @endphp
      <div class="col-4">
      <div id="block-nm" class="el-block h-100">
        <div class="el-block-title">
          <span><i class="bi bi-type"></i> Product Name</span>
          <div class="form-check form-switch mb-0">
            <input class="form-check-input prev-trigger" type="checkbox" id="nm_visible"
                   name="elements[name][visible]" value="1"
                   {{ old('elements.name.visible', $nm['visible'] ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" style="font-size:.75rem;" for="nm_visible">Visible</label>
          </div>
        </div>
        <div class="coord-label">Start (X1, Y1)</div>
        <div class="coord-row">
          <div>
            <label class="fl">X1 (mm)</label>
            <input type="number" id="nm_x1" name="elements[name][x1]"
                   class="form-control form-control-sm prev-trigger"
                   step="0.5" value="{{ old('elements.name.x1', $nm['x1']) }}">
          </div>
          <div>
            <label class="fl">Y1 (mm)</label>
            <input type="number" id="nm_y1" name="elements[name][y1]"
                   class="form-control form-control-sm prev-trigger"
                   step="0.5" value="{{ old('elements.name.y1', $nm['y1']) }}">
          </div>
        </div>
        <div class="coord-label">End (X2, Y2)</div>
        <div class="coord-row">
          <div>
            <label class="fl">X2 (mm)</label>
            <input type="number" id="nm_x2" name="elements[name][x2]"
                   class="form-control form-control-sm prev-trigger"
                   step="0.5" value="{{ old('elements.name.x2', $nm['x2']) }}">
          </div>
          <div>
            <label class="fl">Y2 (mm)</label>
            <input type="number" id="nm_y2" name="elements[name][y2]"
                   class="form-control form-control-sm prev-trigger"
                   step="0.5" value="{{ old('elements.name.y2', $nm['y2']) }}">
          </div>
        </div>
        <div class="coord-row">
          <div>
            <label class="fl">Font size (px)</label>
            <input type="number" id="nm_fs" name="elements[name][font_size]"
                   class="form-control form-control-sm prev-trigger"
                   step="0.5" min="4"
                   value="{{ old('elements.name.font_size', $nm['font_size']) }}">
          </div>
          <div class="d-flex align-items-end pb-1">
            <div class="form-check mb-0">
              <input class="form-check-input prev-trigger" type="checkbox" id="nm_bold"
                     name="elements[name][bold]" value="1"
                     {{ old('elements.name.bold', $nm['bold'] ?? true) ? 'checked' : '' }}>
              <label class="form-check-label small" for="nm_bold">Bold</label>
            </div>
          </div>
        </div>
      </div>
      </div>

      {{-- EAN-13 ----------------------------------------------- --}}
      @php $en = $template?->el('ean13') ?? $defaults['ean13']; @endphp
      <div class="col-4">
      <div id="block-en" class="el-block h-100">
        <div class="el-block-title">
          <span><i class="bi bi-upc"></i> EAN-13</span>
          <div class="form-check form-switch mb-0">
            <input class="form-check-input prev-trigger" type="checkbox" id="en_visible"
                   name="elements[ean13][visible]" value="1"
                   {{ old('elements.ean13.visible', $en['visible'] ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" style="font-size:.75rem;" for="en_visible">Visible</label>
          </div>
        </div>
        <div class="coord-label">Start (X1, Y1)</div>
        <div class="coord-row">
          <div>
            <label class="fl">X1 (mm)</label>
            <input type="number" id="en_x1" name="elements[ean13][x1]"
                   class="form-control form-control-sm prev-trigger"
                   step="0.5" value="{{ old('elements.ean13.x1', $en['x1']) }}">
          </div>
          <div>
            <label class="fl">Y1 (mm)</label>
            <input type="number" id="en_y1" name="elements[ean13][y1]"
                   class="form-control form-control-sm prev-trigger"
                   step="0.5" value="{{ old('elements.ean13.y1', $en['y1']) }}">
          </div>
        </div>
        <div class="coord-label">End (X2, Y2)</div>
        <div class="coord-row">
          <div>
            <label class="fl">X2 (mm)</label>
            <input type="number" id="en_x2" name="elements[ean13][x2]"
                   class="form-control form-control-sm prev-trigger"
                   step="0.5" value="{{ old('elements.ean13.x2', $en['x2']) }}">
          </div>
          <div>
            <label class="fl">Y2 (mm)</label>
            <input type="number" id="en_y2" name="elements[ean13][y2]"
                   class="form-control form-control-sm prev-trigger"
                   step="0.5" value="{{ old('elements.ean13.y2', $en['y2']) }}">
          </div>
        </div>
        <div class="coord-row">
          <div>
            <label class="fl">Digit font size (px)</label>
            <input type="number" id="en_fs" name="elements[ean13][font_size]"
                   class="form-control form-control-sm prev-trigger"
                   step="0.5" min="2"
                   value="{{ old('elements.ean13.font_size', $en['font_size'] ?? 4) }}">
          </div>
        </div>
      </div>
      </div>

      {{-- Batch ----------------------------------------------- --}}
      @php $bt = $template?->el('batch') ?? $defaults['batch']; @endphp
      <div class="col-4">
      <div id="block-bt" class="el-block h-100">
        <div class="el-block-title">
          <span><i class="bi bi-tag"></i> Batch Number</span>
          <div class="form-check form-switch mb-0">
            <input class="form-check-input prev-trigger" type="checkbox" id="bt_visible"
                   name="elements[batch][visible]" value="1"
                   {{ old('elements.batch.visible', $bt['visible'] ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" style="font-size:.75rem;" for="bt_visible">Visible</label>
          </div>
        </div>
        <div class="coord-label">Start (X1, Y1)</div>
        <div class="coord-row">
          <div>
            <label class="fl">X1 (mm)</label>
            <input type="number" id="bt_x1" name="elements[batch][x1]"
                   class="form-control form-control-sm prev-trigger"
                   step="0.5" value="{{ old('elements.batch.x1', $bt['x1']) }}">
          </div>
          <div>
            <label class="fl">Y1 (mm)</label>
            <input type="number" id="bt_y1" name="elements[batch][y1]"
                   class="form-control form-control-sm prev-trigger"
                   step="0.5" value="{{ old('elements.batch.y1', $bt['y1']) }}">
          </div>
        </div>
        <div class="coord-label">End (X2, Y2)</div>
        <div class="coord-row">
          <div>
            <label class="fl">X2 (mm)</label>
            <input type="number" id="bt_x2" name="elements[batch][x2]"
                   class="form-control form-control-sm prev-trigger"
                   step="0.5" value="{{ old('elements.batch.x2', $bt['x2']) }}">
          </div>
          <div>
            <label class="fl">Y2 (mm)</label>
            <input type="number" id="bt_y2" name="elements[batch][y2]"
                   class="form-control form-control-sm prev-trigger"
                   step="0.5" value="{{ old('elements.batch.y2', $bt['y2']) }}">
          </div>
        </div>
        <div class="coord-row">
          <div>
            <label class="fl">Font size (px)</label>
            <input type="number" id="bt_fs" name="elements[batch][font_size]"
                   class="form-control form-control-sm prev-trigger"
                   step="0.5" min="3"
                   value="{{ old('elements.batch.font_size', $bt['font_size']) }}">
          </div>
        </div>
      </div>
      </div>

      {{-- Page Number ------------------------------------------ --}}
      @php $pn = $template?->el('page_number') ?? $defaults['page_number']; @endphp
      <div class="col-4">
      <div id="block-pn" class="el-block h-100">
        <div class="el-block-title">
          <span><i class="bi bi-hash"></i> Page Number</span>
          <div class="form-check form-switch mb-0">
            <input class="form-check-input prev-trigger" type="checkbox" id="pn_visible"
                   name="elements[page_number][visible]" value="1"
                   {{ old('elements.page_number.visible', $pn['visible'] ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" style="font-size:.75rem;" for="pn_visible">Visible</label>
          </div>
        </div>
        <div class="coord-label">Start (X1, Y1)</div>
        <div class="coord-row">
          <div>
            <label class="fl">X1 (mm)</label>
            <input type="number" id="pn_x1" name="elements[page_number][x1]"
                   class="form-control form-control-sm prev-trigger"
                   step="0.5" value="{{ old('elements.page_number.x1', $pn['x1']) }}">
          </div>
          <div>
            <label class="fl">Y1 (mm)</label>
            <input type="number" id="pn_y1" name="elements[page_number][y1]"
                   class="form-control form-control-sm prev-trigger"
                   step="0.5" value="{{ old('elements.page_number.y1', $pn['y1']) }}">
          </div>
        </div>
        <div class="coord-label">End (X2, Y2)</div>
        <div class="coord-row">
          <div>
            <label class="fl">X2 (mm)</label>
            <input type="number" id="pn_x2" name="elements[page_number][x2]"
                   class="form-control form-control-sm prev-trigger"
                   step="0.5" value="{{ old('elements.page_number.x2', $pn['x2']) }}">
          </div>
          <div>
            <label class="fl">Y2 (mm)</label>
            <input type="number" id="pn_y2" name="elements[page_number][y2]"
                   class="form-control form-control-sm prev-trigger"
                   step="0.5" value="{{ old('elements.page_number.y2', $pn['y2']) }}">
          </div>
        </div>
        <div class="coord-row">
          <div>
            <label class="fl">Font size (px)</label>
            <input type="number" id="pn_fs" name="elements[page_number][font_size]"
                   class="form-control form-control-sm prev-trigger"
                   step="0.5" min="3"
                   value="{{ old('elements.page_number.font_size', $pn['font_size']) }}">
          </div>
        </div>
      </div>
      </div>

    </div>{{-- /row g-3 --}}

    <div class="d-flex gap-2 pt-2">
      <button type="submit" class="btn btn-primary">
        <i class="bi bi-check-lg"></i> {{ $template ? 'Save Changes' : 'Create Template' }}
      </button>
      <a href="{{ route('asl.label-templates.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>

  </div>
</div>
</form>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/bwip-js@4/dist/bwip-js-min.js"></script>
@php
    // Sample values from ПКМ №148 decree example (used when no real code exists)
    $sGtin     = $sampleCode?->gtin             ?? '04323456788176';
    $sSn       = $sampleCode?->serial_number     ?? 'AF9-&eDmMbl?6CkOyAEB';
    $sKey      = $sampleCode?->verification_key  ?? 'UZF0';
    $sVCode    = $sampleCode?->verification_code ?? 'TDRFasF7Lf27Qv4/muaq3qoqmZ5x3WAKhKoLmzlyNi4=';
    $sEan13    = (strlen($sGtin) === 14 && $sGtin[0] === '0') ? substr($sGtin, 1) : '5901234123457';

    // GS1 bracketed notation for bwip-js (parse:true inserts FNC1 + GS separators automatically)
    $sDmText = '[01]' . $sGtin . '[21]' . $sSn . '[91]' . $sKey . '[92]' . $sVCode;
@endphp
<script>
const SAMPLE_EAN    = @json($sEan13);
const SAMPLE_DM_TXT = @json($sDmText);

function n(id)  { return parseFloat(document.getElementById(id)?.value) || 0; }
function c(id)  { return document.getElementById(id)?.checked ?? false; }
function set(id, val) { const el = document.getElementById(id); if (el) el.value = val; }

let currentScale = 5;

function getScale(wMm, hMm) {
    const s = document.getElementById('preview-section');
    const maxW = Math.max(150, s.offsetWidth - 80);
    return Math.min(maxW / wMm, 340 / hMm);
}

/* ── DataMatrix: render to tmp canvas, draw to display at sz×sz ── */
function renderDM(sc) {
    const cv = document.getElementById('prev-dm');
    const sz = Math.round(n('dm_size') * sc);
    cv.style.left = (n('dm_x') * sc) + 'px';
    cv.style.top  = (n('dm_y') * sc) + 'px';
    cv.width  = sz;
    cv.height = sz;

    const ctx = cv.getContext('2d');
    ctx.fillStyle = '#f0f0f0';
    ctx.fillRect(0, 0, sz, sz);

    try {
        const tmp = document.createElement('canvas');
        bwipjs.toCanvas(tmp, {
            bcid: 'gs1datamatrix',
            text: SAMPLE_DM_TXT,
            parse: true, scale: 4,
        });
        ctx.imageSmoothingEnabled = false;
        ctx.drawImage(tmp, 0, 0, sz, sz);
    } catch(e) {
        ctx.strokeStyle = '#bbb'; ctx.lineWidth = 1;
        ctx.strokeRect(1, 1, sz - 2, sz - 2);
        ctx.fillStyle = '#aaa';
        ctx.font = `${Math.max(8, sz * .2)}px sans-serif`;
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillText('DM', sz / 2, sz / 2);
    }
}

/* ── EAN-13: standard aspect-ratio-preserving render ── */
function renderEAN(sc) {
    const cv  = document.getElementById('prev-en');
    const vis = c('en_visible');
    cv.style.display = vis ? 'block' : 'none';
    if (!vis) return;

    const x1 = n('en_x1'), y1 = n('en_y1'), x2 = n('en_x2'), y2 = n('en_y2');
    const w = Math.round((x2 - x1) * sc);
    const h = Math.round((y2 - y1) * sc);
    if (w < 2 || h < 2) return;

    cv.style.left = (x1 * sc) + 'px';
    cv.style.top  = (y1 * sc) + 'px';
    cv.width  = w;
    cv.height = h;

    const ctx = cv.getContext('2d');
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, w, h);

    try {
        const tmp = document.createElement('canvas');
        // No textxalign override — bwip-js places EAN-13 digits in standard positions:
        // isolated first digit left, two groups of 6 under the bars, guard bars extend below
        bwipjs.toCanvas(tmp, {
            bcid: 'ean13',
            text: SAMPLE_EAN,
            scale: 3,
            includetext: true,
            guardwhitespace: true,
        });
        // Fit inside bounding box preserving aspect ratio (letterbox, centered)
        const ratio = Math.min(w / tmp.width, h / tmp.height);
        const dw = Math.round(tmp.width  * ratio);
        const dh = Math.round(tmp.height * ratio);
        const dx = Math.round((w - dw) / 2);
        const dy = Math.round((h - dh) / 2);
        ctx.imageSmoothingEnabled = false;
        ctx.drawImage(tmp, dx, dy, dw, dh);
    } catch(e) { /* silent */ }
}

/* ── Bounding boxes for all visible elements (px) ── */
function getBBoxes(sc) {
    const dmSz = n('dm_size');
    const out = [
        { id: 'dm', label: 'DataMatrix',
          x1: n('dm_x') * sc,             y1: n('dm_y') * sc,
          x2: (n('dm_x') + dmSz) * sc,    y2: (n('dm_y') + dmSz) * sc },
    ];
    if (c('nm_visible')) out.push({ id:'nm', label:'Name',
        x1: n('nm_x1')*sc, y1: n('nm_y1')*sc, x2: n('nm_x2')*sc, y2: n('nm_y2')*sc });
    if (c('en_visible')) out.push({ id:'en', label:'EAN-13',
        x1: n('en_x1')*sc, y1: n('en_y1')*sc, x2: n('en_x2')*sc, y2: n('en_y2')*sc });
    if (c('bt_visible')) out.push({ id:'bt', label:'Batch',
        x1: n('bt_x1')*sc, y1: n('bt_y1')*sc, x2: n('bt_x2')*sc, y2: n('bt_y2')*sc });
    if (c('pn_visible')) out.push({ id:'pn', label:'Page#',
        x1: n('pn_x1')*sc, y1: n('pn_y1')*sc, x2: n('pn_x2')*sc, y2: n('pn_y2')*sc });
    return out;
}

/* ── Draw dashed bounding boxes + red intersections ── */
function drawOverlays(boxes) {
    document.querySelectorAll('.lp-intersect, .lp-bbox').forEach(e => e.remove());
    const preview = document.getElementById('label-preview');

    // Dashed bounding box for each element
    boxes.forEach(b => {
        const d = document.createElement('div');
        d.className = 'lp-bbox';
        Object.assign(d.style, {
            left: b.x1 + 'px', top: b.y1 + 'px',
            width: (b.x2 - b.x1) + 'px', height: (b.y2 - b.y1) + 'px',
        });
        preview.appendChild(d);
    });

    // Red intersection areas
    for (let i = 0; i < boxes.length; i++) {
        for (let j = i + 1; j < boxes.length; j++) {
            const a = boxes[i], b = boxes[j];
            const ix1 = Math.max(a.x1, b.x1), iy1 = Math.max(a.y1, b.y1);
            const ix2 = Math.min(a.x2, b.x2), iy2 = Math.min(a.y2, b.y2);
            if (ix1 < ix2 && iy1 < iy2) {
                const d = document.createElement('div');
                d.className = 'lp-intersect';
                Object.assign(d.style, {
                    left: ix1 + 'px', top: iy1 + 'px',
                    width: (ix2 - ix1) + 'px', height: (iy2 - iy1) + 'px',
                });
                preview.appendChild(d);
            }
        }
    }
}

/* ── Reposition all resize handles to element bottom-right corners ── */
function updateHandles() {
    const sc = currentScale;
    const defs = {
        'rh-dm': { x2: n('dm_x') + n('dm_size'), y2: n('dm_y') + n('dm_size'), vis: true },
        'rh-nm': { x2: n('nm_x2'),               y2: n('nm_y2'),               vis: c('nm_visible') },
        'rh-en': { x2: n('en_x2'),               y2: n('en_y2'),               vis: c('en_visible') },
        'rh-bt': { x2: n('bt_x2'),               y2: n('bt_y2'),               vis: c('bt_visible') },
        'rh-pn': { x2: n('pn_x2'),               y2: n('pn_y2'),               vis: c('pn_visible') },
    };
    Object.entries(defs).forEach(([id, d]) => {
        const rh = document.getElementById(id);
        if (!rh) return;
        rh.style.left    = (d.x2 * sc - 5) + 'px';
        rh.style.top     = (d.y2 * sc - 5) + 'px';
        rh.style.display = d.vis ? 'block' : 'none';
    });
}

/* ── Full preview refresh ── */
function updatePreview() {
    const wMm = n('lbl_w'), hMm = n('lbl_h');
    if (wMm < 1 || hMm < 1) return;

    currentScale = getScale(wMm, hMm);
    const sc = currentScale;

    const preview = document.getElementById('label-preview');
    preview.style.width  = (wMm * sc) + 'px';
    preview.style.height = (hMm * sc) + 'px';
    document.getElementById('prev-size-lbl').textContent = `${wMm} × ${hMm} mm`;

    renderDM(sc);

    // Name
    const nm = document.getElementById('prev-nm');
    nm.style.display    = c('nm_visible') ? 'block' : 'none';
    nm.style.left       = (n('nm_x1') * sc) + 'px';
    nm.style.top        = (n('nm_y1') * sc) + 'px';
    nm.style.width      = Math.max(0, (n('nm_x2') - n('nm_x1')) * sc) + 'px';
    nm.style.maxHeight  = Math.max(0, (n('nm_y2') - n('nm_y1')) * sc) + 'px';
    nm.style.fontSize   = (n('nm_fs') * sc / 5) + 'px';
    nm.style.fontWeight = c('nm_bold') ? 'bold' : 'normal';

    renderEAN(sc);

    // Batch
    const bt = document.getElementById('prev-bt');
    bt.style.display   = c('bt_visible') ? 'block' : 'none';
    bt.style.left      = (n('bt_x1') * sc) + 'px';
    bt.style.top       = (n('bt_y1') * sc) + 'px';
    bt.style.width     = Math.max(0, (n('bt_x2') - n('bt_x1')) * sc) + 'px';
    bt.style.maxHeight = Math.max(0, (n('bt_y2') - n('bt_y1')) * sc) + 'px';
    bt.style.fontSize  = (n('bt_fs') * sc / 5) + 'px';

    // Page number
    const pn = document.getElementById('prev-pn');
    pn.style.display  = c('pn_visible') ? 'block' : 'none';
    pn.style.left     = (n('pn_x1') * sc) + 'px';
    pn.style.top      = (n('pn_y1') * sc) + 'px';
    pn.style.fontSize = (n('pn_fs') * sc / 5) + 'px';

    drawOverlays(getBBoxes(sc));
    updateHandles();
}

/* ── Highlight the matching settings block ── */
const BLOCK_MAP = {
    'prev-dm': 'block-dm', 'rh-dm': 'block-dm',
    'prev-nm': 'block-nm', 'rh-nm': 'block-nm',
    'prev-en': 'block-en', 'rh-en': 'block-en',
    'prev-bt': 'block-bt', 'rh-bt': 'block-bt',
    'prev-pn': 'block-pn', 'rh-pn': 'block-pn',
};

function activateBlock(blockId) {
    document.querySelectorAll('.el-block').forEach(b => b.classList.remove('el-active'));
    if (!blockId) return;
    const bl = document.getElementById(blockId);
    if (!bl) return;
    bl.classList.add('el-active');
    bl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

/* ── Drag to reposition ── */
const DRAG_MAP = {
    'prev-dm': { type: 'xy',  x:  'dm_x',  y:  'dm_y' },
    'prev-nm': { type: 'box', x1: 'nm_x1', y1: 'nm_y1', x2: 'nm_x2', y2: 'nm_y2' },
    'prev-en': { type: 'box', x1: 'en_x1', y1: 'en_y1', x2: 'en_x2', y2: 'en_y2' },
    'prev-bt': { type: 'box', x1: 'bt_x1', y1: 'bt_y1', x2: 'bt_x2', y2: 'bt_y2' },
    'prev-pn': { type: 'box', x1: 'pn_x1', y1: 'pn_y1', x2: 'pn_x2', y2: 'pn_y2' },
};

function makeDraggable(elId) {
    const el  = document.getElementById(elId);
    const map = DRAG_MAP[elId];
    if (!el || !map) return;

    let startPX, startPY, startX1, startY1, startX2, startY2, wasDragged;

    el.addEventListener('pointerdown', e => {
        if (e.button !== 0) return;
        e.preventDefault();
        el.setPointerCapture(e.pointerId);
        el.classList.add('dragging');
        wasDragged = false;
        startPX = e.clientX; startPY = e.clientY;

        if (map.type === 'xy') {
            startX1 = n(map.x);  startY1 = n(map.y);
        } else {
            startX1 = n(map.x1); startY1 = n(map.y1);
            startX2 = n(map.x2); startY2 = n(map.y2);
        }

        el.addEventListener('pointermove', onMove);
        el.addEventListener('pointerup',   onUp);
        el.addEventListener('pointercancel', onUp);
    });

    function onMove(e) {
        wasDragged = true;
        const sc   = currentScale;
        const wMm  = n('lbl_w'), hMm = n('lbl_h');
        const snap = v => Math.round(v * 2) / 2;
        const clamp = (v, lo, hi) => Math.min(Math.max(v, lo), hi);

        if (map.type === 'xy') {
            const sz  = n('dm_size');
            const nx1 = clamp(snap(startX1 + (e.clientX - startPX) / sc), 0, Math.max(0, wMm - sz));
            const ny1 = clamp(snap(startY1 + (e.clientY - startPY) / sc), 0, Math.max(0, hMm - sz));
            el.style.left = (nx1 * sc) + 'px';
            el.style.top  = (ny1 * sc) + 'px';
            set(map.x, nx1.toFixed(1));
            set(map.y, ny1.toFixed(1));
        } else {
            const elW = startX2 - startX1;
            const elH = startY2 - startY1;
            const nx1 = clamp(snap(startX1 + (e.clientX - startPX) / sc), 0, Math.max(0, wMm - elW));
            const ny1 = clamp(snap(startY1 + (e.clientY - startPY) / sc), 0, Math.max(0, hMm - elH));
            el.style.left = (nx1 * sc) + 'px';
            el.style.top  = (ny1 * sc) + 'px';
            set(map.x1, nx1.toFixed(1));
            set(map.y1, ny1.toFixed(1));
            set(map.x2, (nx1 + elW).toFixed(1));
            set(map.y2, (ny1 + elH).toFixed(1));
        }

        drawOverlays(getBBoxes(sc));
        updateHandles();
    }

    function onUp() {
        el.classList.remove('dragging');
        el.removeEventListener('pointermove', onMove);
        el.removeEventListener('pointerup',   onUp);
        el.removeEventListener('pointercancel', onUp);
        if (!wasDragged) activateBlock(BLOCK_MAP[elId]);
        updatePreview();
    }
}

Object.keys(DRAG_MAP).forEach(makeDraggable);

/* ── Resize from bottom-right corner handle ── */
const RESIZE_MAP = {
    'rh-dm': { type: 'size', size: 'dm_size', x: 'dm_x',  y: 'dm_y'  },
    'rh-nm': { type: 'box',  x1: 'nm_x1', y1: 'nm_y1', x2: 'nm_x2', y2: 'nm_y2' },
    'rh-en': { type: 'box',  x1: 'en_x1', y1: 'en_y1', x2: 'en_x2', y2: 'en_y2' },
    'rh-bt': { type: 'box',  x1: 'bt_x1', y1: 'bt_y1', x2: 'bt_x2', y2: 'bt_y2' },
    'rh-pn': { type: 'box',  x1: 'pn_x1', y1: 'pn_y1', x2: 'pn_x2', y2: 'pn_y2' },
};

function makeResizable(rhId) {
    const rh  = document.getElementById(rhId);
    const map = RESIZE_MAP[rhId];
    if (!rh || !map) return;

    let startPX, startPY, startSize, startX2, startY2;

    rh.addEventListener('pointerdown', e => {
        if (e.button !== 0) return;
        e.preventDefault();
        e.stopPropagation();
        activateBlock(BLOCK_MAP[rhId]);
        rh.setPointerCapture(e.pointerId);
        startPX = e.clientX; startPY = e.clientY;

        if (map.type === 'size') {
            startSize = n(map.size);
        } else {
            startX2 = n(map.x2);
            startY2 = n(map.y2);
        }

        rh.addEventListener('pointermove', onMove);
        rh.addEventListener('pointerup',   onUp);
        rh.addEventListener('pointercancel', onUp);
    });

    function onMove(e) {
        const sc    = currentScale;
        const wMm   = n('lbl_w'), hMm = n('lbl_h');
        const snap  = v => Math.round(v * 2) / 2;
        const clamp = (v, lo, hi) => Math.min(Math.max(v, lo), hi);

        if (map.type === 'size') {
            const dx = (e.clientX - startPX) / sc;
            const dy = (e.clientY - startPY) / sc;
            const delta = (dx + dy) / 2;
            const maxW = wMm - n(map.x);
            const maxH = hMm - n(map.y);
            const newSize = clamp(snap(startSize + delta), 5, Math.min(maxW, maxH));
            set(map.size, newSize.toFixed(1));
        } else {
            const nx2 = clamp(snap(startX2 + (e.clientX - startPX) / sc), n(map.x1) + 3, wMm);
            const ny2 = clamp(snap(startY2 + (e.clientY - startPY) / sc), n(map.y1) + 3, hMm);
            set(map.x2, nx2.toFixed(1));
            set(map.y2, ny2.toFixed(1));
        }

        updatePreview();
    }

    function onUp() {
        rh.removeEventListener('pointermove', onMove);
        rh.removeEventListener('pointerup',   onUp);
        rh.removeEventListener('pointercancel', onUp);
    }
}

Object.keys(RESIZE_MAP).forEach(makeResizable);

document.querySelectorAll('.prev-trigger').forEach(el => {
    el.addEventListener('input',  updatePreview);
    el.addEventListener('change', updatePreview);
});

updatePreview();
window.addEventListener('resize', updatePreview);
</script>
@endpush
