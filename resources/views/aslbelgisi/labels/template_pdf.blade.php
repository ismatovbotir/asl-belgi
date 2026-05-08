<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'DejaVu Sans', sans-serif; }

.label {
    position: relative;
    width: {{ $tpl->width_mm }}mm;
    height: {{ $tpl->height_mm }}mm;
    overflow: hidden;
    page-break-after: always;
}

@page { margin: 0; size: {{ $tpl->width_mm }}mm {{ $tpl->height_mm }}mm; }
</style>
</head>
<body>
@php
    $dmEl = $tpl->el('datamatrix');
    $nmEl = $tpl->el('name');
    $enEl = $tpl->el('ean13');
    $btEl = $tpl->el('batch');
    $pnEl = $tpl->el('page_number');

    $nmW       = ($nmEl['x2'] ?? 59) - ($nmEl['x1'] ?? 27);
    $nmH       = ($nmEl['y2'] ?? 16) - ($nmEl['y1'] ?? 1);
    $enW       = ($enEl['x2'] ?? 59) - ($enEl['x1'] ?? 27);
    $enH       = ($enEl['y2'] ?? 37) - ($enEl['y1'] ?? 19);
    $btW       = ($btEl['x2'] ?? 59) - ($btEl['x1'] ?? 27);
    $enFontPx  = $enEl['font_size'] ?? 4;

    // EAN-13 split: barcode bars occupy the space above the digit row
    // digit row height ≈ font_size * 0.352778mm/pt (1px = 0.75pt = 0.264mm at 96dpi) * 1.6 line-height
    $enDigitH  = round($enFontPx * 0.264 * 1.8, 2);          // mm
    $enBarH    = max(1, round($enH - $enDigitH, 2));          // mm

    $labelNum  = 0;
@endphp

@foreach($codes as $code)
    @php
        $labelNum++;

        $product  = $products->get($code->gtin);
        $name     = $product?->name ?? ($code->gtin ?? '');
        $gtin     = $code->gtin ?? '';
        $ean13    = (strlen($gtin) === 14 && ($gtin[0] ?? '') === '0') ? substr($gtin, 1) : '';
        if ($ean13 && !preg_match('/^\d{13}$/', $ean13)) { $ean13 = ''; }

        $dmString = null;
        if ($gtin && strlen($gtin) === 14) {
            // GS1 DataMatrix per ПКМ №148: FNC1 + AI01(GTIN) + AI21(SN) + AI91(key) + AI92(code)
            // chr(232) = FNC1 (GS1 start), chr(29) = GS separator between variable-length AIs
            $dmString = chr(232) . '01' . $gtin;
            if ($code->serial_number)    $dmString .= '21' . $code->serial_number    . chr(29);
            if ($code->verification_key) $dmString .= '91' . $code->verification_key . chr(29);
            if ($code->verification_code) $dmString .= '92' . $code->verification_code;
        }

        $dmSize = $dmEl['size'] ?? 25;
    @endphp

    <div class="label">

        {{-- DataMatrix (always on) --}}
        @if($dmString && strlen($dmString) > 3)
            @php $dm = \DNS2D::getBarcodePNG($dmString, 'DATAMATRIX', 3, 3); @endphp
            <img src="data:image/png;base64,{{ $dm }}"
                 style="position:absolute;
                        left:{{ $dmEl['x'] ?? 1 }}mm;
                        top:{{ $dmEl['y'] ?? 7.5 }}mm;
                        width:{{ $dmSize }}mm;
                        height:{{ $dmSize }}mm;"
                 alt="">
        @endif

        {{-- Product Name --}}
        @if($nmEl['visible'] ?? true)
            <div style="position:absolute;
                        left:{{ $nmEl['x1'] ?? 27 }}mm;
                        top:{{ $nmEl['y1'] ?? 1 }}mm;
                        width:{{ $nmW }}mm;
                        max-height:{{ $nmH }}mm;
                        font-size:{{ $nmEl['font_size'] ?? 7.5 }}px;
                        font-weight:{{ ($nmEl['bold'] ?? true) ? 'bold' : 'normal' }};
                        line-height:1.3;
                        overflow:hidden;">{{ mb_strimwidth($name, 0, 80, '…') }}</div>
        @endif

        {{-- Batch --}}
        @if(($btEl['visible'] ?? true) && $code->batch)
            <div style="position:absolute;
                        left:{{ $btEl['x1'] ?? 27 }}mm;
                        top:{{ $btEl['y1'] ?? 16 }}mm;
                        width:{{ $btW }}mm;
                        font-size:{{ $btEl['font_size'] ?? 5 }}px;
                        color:#555;">{{ $code->batch }}</div>
        @endif

        {{-- EAN-13: barcode bars (no built-in text) + separate digit row --}}
        @if(($enEl['visible'] ?? true) && $ean13)
            @php $eanImg = \DNS1D::getBarcodePNG($ean13, 'EAN13', 1, 40, [0,0,0], false); @endphp
            <img src="data:image/png;base64,{{ $eanImg }}"
                 style="position:absolute;
                        left:{{ $enEl['x1'] ?? 27 }}mm;
                        top:{{ $enEl['y1'] ?? 19 }}mm;
                        width:{{ $enW }}mm;
                        height:{{ $enBarH }}mm;"
                 alt="">
            <div style="position:absolute;
                        left:{{ $enEl['x1'] ?? 27 }}mm;
                        top:{{ ($enEl['y1'] ?? 19) + $enBarH }}mm;
                        width:{{ $enW }}mm;
                        font-size:{{ $enFontPx }}px;
                        text-align:center;
                        letter-spacing:0.08em;
                        color:#000;">{{ $ean13 }}</div>
        @endif

        {{-- Page / label number --}}
        @if($pnEl['visible'] ?? true)
            <div style="position:absolute;
                        left:{{ $pnEl['x1'] ?? 27 }}mm;
                        top:{{ $pnEl['y1'] ?? 37 }}mm;
                        font-size:{{ $pnEl['font_size'] ?? 5 }}px;
                        color:#888;">{{ $labelNum }}</div>
        @endif

    </div>

@endforeach
</body>
</html>
