<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'DejaVu Sans', sans-serif; }

.label {
    width: 60mm; height: 40mm;
    page-break-after: always;
    overflow: hidden;
}

/* Outer 2-column table */
table.lbl-outer {
    width: 60mm; height: 40mm;
    border-collapse: collapse;
    table-layout: fixed;
}
table.lbl-outer > tbody > tr > td {
    border: none;
    padding: 1mm;
    vertical-align: middle;
}
.td-left  { width: 30mm; text-align: center; }
.td-right { width: 30mm; padding: 1mm 1mm 1mm 0; }

/* Right inner 3-row table */
table.lbl-right {
    width: 100%; height: 38mm; /* 40mm - 1mm top - 1mm bottom padding */
    border-collapse: collapse;
    table-layout: fixed;
}
table.lbl-right > tbody > tr > td {
    border: none;
    padding: 0;
}

.tr-name { height: 19mm; }
.tr-pg   { height: 1.9mm; }
.tr-bc   { height: 17.1mm; }

.td-name {
    vertical-align: top;
    padding-top: 1mm;
    font-size: 7.5px;
    font-weight: bold;
    line-height: 1.3;
    overflow: hidden;
}
.td-pg {
    vertical-align: middle;
    font-size: 5px;
    color: #777;
}
.td-bc {
    vertical-align: middle;
    text-align: center;
    overflow: hidden;
}

@page { margin: 0; size: 60mm 40mm; }
</style>
</head>
<body>
<script type="text/php">
    if (isset($pdf)) {
        $font = $fontMetrics->getFont("DejaVu Sans", "normal");
        // Right half: x=32mm=90.7pt; page num row starts at 1mm+19mm=20mm≈56.7pt from top
        $pdf->page_text(90.7, 61, "{PAGE_NUM}", $font, 5, [0.6, 0.6, 0.6]);
    }
</script>

@foreach($codes as $code)
    @php
        $product = $products->get($code->gtin);
        $name    = $product?->name ?? ($code->gtin ?? '');
        $gtin    = $code->gtin ?? '';
        $ean13   = (strlen($gtin) === 14 && ($gtin[0] ?? '') === '0') ? substr($gtin, 1) : '';
        if ($ean13 && !preg_match('/^\d{13}$/', $ean13)) { $ean13 = ''; }

        // GS1 DataMatrix ECC200: FNC1 (chr 232) signals GS1 mode; chr(29) = GS separator after variable-length AIs
        $dmString = null;
        if ($gtin && strlen($gtin) === 14) {
            $dmString  = chr(232) . '01' . $gtin;   // FNC1 + AI 01 (fixed 14 chars — no GS needed after)
            if ($code->serial_number) $dmString .= '21' . $code->serial_number . chr(29);
            if ($code->expiry_date)   $dmString .= '17' . $code->expiry_date->format('ymd'); // AI 17 is fixed 6 chars
            if ($code->batch)         $dmString .= '10' . $code->batch . chr(29);
        }
    @endphp

    <div class="label">
      <table class="lbl-outer"><tbody><tr>

        {{-- Left: DataMatrix --}}
        <td class="td-left">
            @if($dmString && strlen($dmString) > 3)
                @php $dm = \DNS2D::getBarcodePNG($dmString, 'DATAMATRIX', 3, 3); @endphp
                <img src="data:image/png;base64,{{ $dm }}" style="width:25mm; height:25mm;" alt="">
            @endif
        </td>

        {{-- Right: 3 rows --}}
        <td class="td-right">
          <table class="lbl-right"><tbody>

            <tr class="tr-name">
                <td class="td-name">{{ mb_strimwidth($name, 0, 60, '…') }}</td>
            </tr>

            <tr class="tr-pg">
                <td class="td-pg">&nbsp;</td>{{-- number injected by page_text() --}}
            </tr>

            <tr class="tr-bc">
                <td class="td-bc">
                    @if($ean13)
                        @php $ean = \DNS1D::getBarcodePNG($ean13, 'EAN13', 1, 30, [0,0,0], true); @endphp
                        <img src="data:image/png;base64,{{ $ean }}" style="max-width:26mm; max-height:15mm; height:auto;" alt="">
                    @endif
                </td>
            </tr>

          </tbody></table>
        </td>

      </tr></tbody></table>
    </div>

@endforeach
</body>
</html>
