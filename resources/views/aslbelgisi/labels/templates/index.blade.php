@extends('layouts.app')
@section('title', 'Label Templates')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4 class="mb-0"><i class="bi bi-layout-text-window-reverse"></i> Label Templates</h4>
        <small class="text-muted">Define reusable label layouts for PDF generation</small>
    </div>
    <a href="{{ route('asl.label-templates.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg"></i> New Template
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if($templates->isEmpty())
    <div class="card shadow-sm">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-layout-text-window-reverse fs-1 d-block mb-3"></i>
            No templates yet. <a href="{{ route('asl.label-templates.create') }}">Create your first template</a>.
        </div>
    </div>
@else
<div class="row g-3">
    @foreach($templates as $tpl)
    <div class="col-md-4 col-lg-3">
        <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h6 class="mb-0 fw-semibold">{{ $tpl->name }}</h6>
                    <span class="badge bg-secondary ms-2">{{ $tpl->width_mm }}×{{ $tpl->height_mm }}mm</span>
                </div>

                {{-- Mini label preview --}}
                @php
                    $scale = 3; // px per mm — mini card preview
                    $W = $tpl->width_mm * $scale;
                    $H = $tpl->height_mm * $scale;
                    $dm = $tpl->el('datamatrix');
                    $nm = $tpl->el('name');
                    $en = $tpl->el('ean13');
                    $bt = $tpl->el('batch');
                    $pn = $tpl->el('page_number');
                @endphp
                <div style="position:relative; width:{{ $W }}px; height:{{ $H }}px; border:1px solid #ddd; background:#fff; overflow:hidden; margin: 0 auto 0.75rem; flex-shrink:0;">
                    {{-- DataMatrix placeholder --}}
                    <div style="position:absolute;
                                left:{{ $dm['x'] * $scale }}px; top:{{ $dm['y'] * $scale }}px;
                                width:{{ $dm['size'] * $scale }}px; height:{{ $dm['size'] * $scale }}px;
                                background:#eee; border:1px solid #ccc; display:flex; align-items:center; justify-content:center;">
                        <i class="bi bi-qr-code" style="font-size:{{ $dm['size'] * $scale * 0.5 }}px; color:#999;"></i>
                    </div>
                    {{-- Name placeholder --}}
                    @if($nm['visible'] ?? true)
                    <div style="position:absolute;
                                left:{{ ($nm['x1'] ?? 27) * $scale }}px; top:{{ ($nm['y1'] ?? 1) * $scale }}px;
                                width:{{ (($nm['x2'] ?? 59) - ($nm['x1'] ?? 27)) * $scale }}px;
                                max-height:{{ (($nm['y2'] ?? 16) - ($nm['y1'] ?? 1)) * $scale }}px;
                                font-size:{{ ($nm['font_size'] ?? 7.5) * $scale * 0.5 }}px;
                                font-weight:{{ ($nm['bold'] ?? true) ? 'bold' : 'normal' }};
                                line-height:1.2; overflow:hidden; color:#333;">
                        Product Name
                    </div>
                    @endif
                    {{-- EAN-13 placeholder --}}
                    @if($en['visible'] ?? true)
                    <div style="position:absolute;
                                left:{{ ($en['x1'] ?? 27) * $scale }}px; top:{{ ($en['y1'] ?? 19) * $scale }}px;
                                width:{{ (($en['x2'] ?? 59) - ($en['x1'] ?? 27)) * $scale }}px;
                                height:{{ (($en['y2'] ?? 37) - ($en['y1'] ?? 19)) * $scale }}px;
                                background:repeating-linear-gradient(90deg,#333 0,#333 2px,#fff 2px,#fff 4px);
                                border:1px solid #ddd;">
                    </div>
                    @endif
                    {{-- Batch placeholder --}}
                    @if($bt['visible'] ?? true)
                    <div style="position:absolute;
                                left:{{ ($bt['x1'] ?? 27) * $scale }}px; top:{{ ($bt['y1'] ?? 16) * $scale }}px;
                                font-size:{{ ($bt['font_size'] ?? 5) * $scale * 0.5 }}px; color:#777;">
                        BATCH001
                    </div>
                    @endif
                    {{-- Page number placeholder --}}
                    @if($pn['visible'] ?? true)
                    <div style="position:absolute;
                                left:{{ ($pn['x1'] ?? 27) * $scale }}px; top:{{ ($pn['y1'] ?? 37) * $scale }}px;
                                font-size:{{ ($pn['font_size'] ?? 5) * $scale * 0.5 }}px; color:#aaa;">
                        1
                    </div>
                    @endif
                </div>

                <div class="mt-auto d-flex gap-2">
                    <a href="{{ route('asl.label-templates.edit', $tpl) }}" class="btn btn-outline-primary btn-sm flex-grow-1">
                        <i class="bi bi-pencil"></i> Edit
                    </a>
                    <form method="POST" action="{{ route('asl.label-templates.destroy', $tpl) }}"
                          onsubmit="return confirm('Delete template \'{{ $tpl->name }}\'?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-outline-danger btn-sm">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif
@endsection
