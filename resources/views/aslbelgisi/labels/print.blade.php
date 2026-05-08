<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Labels — {{ $order->name ?? $order->external_order_id ?? '#'.$order->id }}</title>
    <script src="https://cdn.jsdelivr.net/npm/bwip-js@3/dist/bwip-js-min.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; background: #f0f0f0; }

        .no-print {
            background: #1a2332; color: #fff; padding: 12px 20px;
            display: flex; justify-content: space-between; align-items: center; gap: 12px;
        }
        .no-print h6  { margin: 0; font-size: 0.9rem; }
        .no-print .info { font-size: 0.8rem; color: #aaa; margin-top: 2px; }
        .btn-print {
            background: #198754; color: #fff; border: none;
            padding: 8px 20px; border-radius: 4px; cursor: pointer; font-size: 0.9rem;
        }
        .btn-print:hover { background: #157347; }

        .labels-page { padding: 10px; }
        .labels-grid { display: flex; flex-wrap: wrap; gap: 4px; }

        /* === 60 × 40 mm label === */
        .label {
            width: 227px;  /* 60 mm @ 96 dpi */
            height: 151px; /* 40 mm @ 96 dpi */
            border: 0.5px solid #bbb;
            background: #fff;
            padding: 4px;  /* 1 mm */
            display: flex;
            flex-direction: row;
            overflow: hidden;
            page-break-inside: avoid;
        }

        /* Left 50 % — DataMatrix */
        .lbl-left {
            width: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .lbl-left canvas { display: block; }

        /* Right 50 % — three rows */
        .lbl-right {
            width: 50%;
            display: flex;
            flex-direction: column;
            padding-left: 3px;
        }

        /* Row 1 — 50 % — product name */
        .lbl-name-row {
            height: 50%;
            overflow: hidden;
            display: flex;
            align-items: flex-start;
        }
        .lbl-name {
            font-size: 8px;
            font-weight: bold;
            line-height: 1.3;
            word-break: break-word;
        }

        /* Row 2 — 5 % — page number */
        .lbl-pg-row {
            height: 5%;
            display: flex;
            align-items: center;
            font-size: 5.5px;
            color: #999;
            font-family: monospace;
        }

        /* Row 3 — 45 % — EAN-13 barcode */
        .lbl-bc-row {
            height: 45%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .lbl-bc-row canvas { display: block; max-width: 100%; }

        .rendering-msg { text-align: center; padding: 40px; color: #666; font-size: 0.9rem; }

        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .labels-page { padding: 0; }
            .labels-grid { gap: 0; }
            .label { width: 60mm; height: 40mm; padding: 1mm; border: 0.25px solid #ccc; }
            @page { margin: 5mm; size: A4 portrait; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <div>
        <h6>{{ $order->name ?? $order->external_order_id ?? '#'.$order->id }}</h6>
        <div class="info">
            {{ $codes->count() }} labels &nbsp;|&nbsp; 60 × 40 mm
            @if($order->created_at) &nbsp;|&nbsp; {{ $order->created_at->format('d M Y') }} @endif
        </div>
    </div>
    <button class="btn-print" onclick="window.print()">&#128438; Print</button>
</div>

<div class="labels-page">
    <div class="rendering-msg" id="renderingMsg">Rendering labels<span id="renderCount"></span>…</div>
    <div class="labels-grid" id="labelsGrid"></div>
</div>

<script>
const codes = {!! json_encode($codes->map(fn($c) => [
    'gtin'          => $c->gtin,
    'serial'        => $c->serial_number,
    'expiry_yymmdd' => $c->expiry_date?->format('ymd'),
    'batch'         => $c->batch,
])->values()) !!};
const productsByGtin = {!! json_encode($products->map(fn($p) => ['name' => $p->name])) !!};

function toGs1Dm(c) {
    if (!c.gtin || c.gtin.length !== 14) return null;
    let s = `(01)${c.gtin}`;
    if (c.serial)        s += `(21)${c.serial}`;
    if (c.expiry_yymmdd) s += `(17)${c.expiry_yymmdd}`;
    if (c.batch)         s += `(10)${c.batch}`;
    return s;
}

function toEan13(gtin) {
    if (!gtin || gtin.length !== 14 || gtin[0] !== '0') return null;
    const ean = gtin.slice(1);
    return /^\d{13}$/.test(ean) ? ean : null;
}

function escHtml(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

async function renderLabels() {
    const grid    = document.getElementById('labelsGrid');
    const msg     = document.getElementById('renderingMsg');
    const countEl = document.getElementById('renderCount');

    for (let i = 0; i < codes.length; i++) {
        const c    = codes[i];
        const name = productsByGtin[c.gtin]?.name || '';
        const ean  = toEan13(c.gtin);

        const label = document.createElement('div');
        label.className = 'label';
        label.innerHTML = `
            <div class="lbl-left"><canvas id="dm_${i}"></canvas></div>
            <div class="lbl-right">
                <div class="lbl-name-row">
                    <div class="lbl-name">${escHtml(name || c.gtin)}</div>
                </div>
                <div class="lbl-pg-row">${i + 1}</div>
                <div class="lbl-bc-row">
                    ${ean ? `<canvas id="bc_${i}"></canvas>` : ''}
                </div>
            </div>
        `;
        grid.appendChild(label);

        const gs1dm = toGs1Dm(c);
        if (gs1dm) {
            try {
                bwipjs.toCanvas('dm_' + i, {
                    bcid: 'gs1datamatrix', text: gs1dm,
                    parse: true, scale: 4, includetext: false,
                });
            } catch(e) {}
        }

        if (ean) {
            try {
                bwipjs.toCanvas('bc_' + i, {
                    bcid: 'ean13', text: ean,
                    scale: 1, height: 7,
                    includetext: true, textxalign: 'center', textsize: 5,
                });
            } catch(e) {}
        }

        if (i % 10 === 9) {
            countEl.textContent = ' (' + (i + 1) + ' / ' + codes.length + ')';
            await new Promise(r => setTimeout(r, 0));
        }
    }

    msg.style.display = 'none';

    if (new URLSearchParams(window.location.search).get('mode') === 'save') {
        window.print();
    }
}

document.addEventListener('DOMContentLoaded', renderLabels);
</script>
</body>
</html>
