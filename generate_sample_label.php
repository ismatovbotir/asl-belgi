<?php
/**
 * Generates samples/label_sample.jpg — 60×40mm label at 300 DPI
 * Run: php generate_sample_label.php
 */

chdir(__DIR__);
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// ── Sample data ────────────────────────────────────────────────────────────
$sampleGtin   = '04607004951015'; // 14-digit GTIN-14 (valid EAN-13 check digit)
$sampleSerial = 'SN123456';
$sampleBatch  = 'B2026A';
$sampleExpiry = '261231';         // YYMMDD
$productName  = "Тестовый продукт\nSample Product";
$ean13        = substr($sampleGtin, 1); // strip leading 0 → 13 digits
$gtinDisplay  = $sampleGtin[0] === '0' ? substr($sampleGtin, 1) : $sampleGtin;

// ── Build GS1 DataMatrix string ─────────────────────────────────────────────
$dmString  = chr(232) . '01' . $sampleGtin;
$dmString .= '21' . $sampleSerial . chr(29);
$dmString .= '17' . $sampleExpiry;
$dmString .= '10' . $sampleBatch  . chr(29);

// ── Generate barcode PNGs via milon/barcode ─────────────────────────────────
$dmBase64  = DNS2D::getBarcodePNG($dmString, 'DATAMATRIX', 4, 4);
try {
    $eanBase64 = DNS1D::getBarcodePNG($ean13, 'EAN13', 2, 45, [0, 0, 0], true);
} catch (\Throwable $e) {
    $eanBase64 = null;
    echo "Warning: EAN-13 skipped ({$e->getMessage()})\n";
}

// ── Canvas dimensions (300 DPI) ─────────────────────────────────────────────
$dpi   = 300;
$mm    = fn(float $mm) => (int) round($mm * $dpi / 25.4);

$W      = $mm(60);   // 709 px
$H      = $mm(40);   // 472 px
$PAD    = $mm(1);    // 12 px  — 1 mm padding
$HALF   = $mm(30);   // 354 px — left / right boundary

// ── Fonts ───────────────────────────────────────────────────────────────────
$fontDir   = __DIR__ . '/vendor/dompdf/dompdf/lib/fonts/';
$fontBold  = $fontDir . 'DejaVuSans-Bold.ttf';
$fontMono  = $fontDir . 'DejaVuSansMono.ttf';
$fontReg   = $fontDir . 'DejaVuSans.ttf';

// ── Create image ─────────────────────────────────────────────────────────────
$img   = imagecreatetruecolor($W, $H);
$white = imagecolorallocate($img, 255, 255, 255);
$black = imagecolorallocate($img, 0, 0, 0);
$lgray = imagecolorallocate($img, 187, 187, 187);
$mgray = imagecolorallocate($img, 100, 100, 100);

imagefill($img, 0, 0, $white);

// Border
imagerectangle($img, 0, 0, $W - 1, $H - 1, $lgray);

// ── Left cell: DataMatrix 25×25mm, centered ──────────────────────────────────
$dmPx = $mm(25);  // 295 px square
$dmX  = $PAD + (int)(($HALF - $PAD * 2 - $dmPx) / 2);
$dmY  = $PAD + (int)(($H   - $PAD * 2 - $dmPx) / 2);

$dmImg = imagecreatefromstring(base64_decode($dmBase64));
if ($dmImg) {
    imagecopyresampled($img, $dmImg, $dmX, $dmY, 0, 0, $dmPx, $dmPx,
                       imagesx($dmImg), imagesy($dmImg));
    imagedestroy($dmImg);
}

// ── Right cell layout ────────────────────────────────────────────────────────
// Inner area: x from HALF, y from PAD, width = W-HALF-PAD, height = H-2*PAD = 38mm
$rx      = $HALF + $PAD;          // right inner start x
$ry      = $PAD;                  // right inner start y
$rw      = $W - $HALF - $PAD;    // right inner width
$innerH  = $H - 2 * $PAD;        // 38 mm in px

$nameH = (int)round($innerH * 0.50); // 50% = 19mm
$pgH   = (int)round($innerH * 0.05); //  5% =  1.9mm
$bcH   = $innerH - $nameH - $pgH;   // 45% = 17.1mm  (EAN-13 at bottom)

// Row 1 — product name
$fontSize = 7.5 * $dpi / 72;
$lines    = explode("\n", wordwrap($productName, 18, "\n", true));
$lineH    = (int)($fontSize * 1.3);
$ty       = $ry + (int)($fontSize);
foreach ($lines as $line) {
    imagettftext($img, $fontSize, 0, $rx, $ty, $black, $fontBold, $line);
    $ty += $lineH;
    if ($ty > $ry + $nameH) break;
}

// Row 2 — page number (between name and EAN-13)
$pgSize     = 5.5 * $dpi / 72;
$pgRowY     = $ry + $nameH;
$pgBaseline = $pgRowY + (int)$pgSize;
imagettftext($img, $pgSize, 0, $rx, $pgBaseline, $black, $fontMono, '1');

// Row 3 — EAN-13 barcode image (bottom)
$eanImg = $eanBase64 ? imagecreatefromstring(base64_decode($eanBase64)) : false;
if ($eanImg) {
    $eanSrcW = imagesx($eanImg);
    $eanSrcH = imagesy($eanImg);
    $eanDstW = (int)min($rw - $PAD, $mm(26));
    $eanDstH = (int)($eanSrcH * ($eanDstW / $eanSrcW));
    $eanX    = $rx + (int)(($rw - $eanDstW) / 2);
    $eanY    = $ry + $nameH + $pgH + (int)(($bcH - $eanDstH) / 2);
    imagecopyresampled($img, $eanImg, $eanX, $eanY, 0, 0,
                       $eanDstW, $eanDstH, $eanSrcW, $eanSrcH);
    imagedestroy($eanImg);
}

// ── Save as JPG ──────────────────────────────────────────────────────────────
$out = __DIR__ . '/samples/label_sample.jpg';
imagejpeg($img, $out, 95);
imagedestroy($img);

echo "Saved: $out\n";
echo "Size:  {$W}×{$H} px  (60×40 mm @ {$dpi} DPI)\n";
