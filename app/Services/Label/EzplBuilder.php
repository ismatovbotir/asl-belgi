<?php

namespace App\Services\Label;

/**
 * Builds Godex EZPL/EZPGL command strings.
 * Coordinates in mm are converted to dots internally.
 * Reference: Godex EZPL Programming Manual + WBPrint UM.
 */
class EzplBuilder
{
    private array $lines = [];

    public function __construct(
        private readonly float $widthMm,
        private readonly float $heightMm,
        private readonly int   $dpi = 203,
    ) {}

    /** Convert mm to dots for this DPI */
    public function d(float $mm): int
    {
        return (int) round($mm * $this->dpi / 25.4);
    }

    /** Default module size in dots targeting ~20mm barcode (22 modules wide) */
    public function defaultModuleSize(): int
    {
        return max(2, (int) round($this->dpi / 50));
        // 203 dpi → 4  |  300 dpi → 6  |  600 dpi → 12
    }

    /**
     * EZPL label header.
     * ^Q height is in mm; ^W width is in mm — NOT dots.
     */
    public function header(int $copies = 1, int $gapMm = 3, int $darkness = 8, int $speed = 3): self
    {
        $this->lines = [
            "^Q{$this->heightMm},{$gapMm}",  // height mm, gap mm
            "^W{$this->widthMm}",             // width mm
            "^H{$darkness}",                  // darkness 1–15
            "^P{$copies}",
            "^S{$speed}",
            '^AT',                            // die-cut media
            '^C1',
            '^R0',
            '~Q+0',
            '^O0',
            '^D0',
            '^E0',
            '~R255',
            '^XSET,CODEPAGE,UTF-8',
            '^L',                             // start of format
        ];
        return $this;
    }

    /**
     * DataMatrix barcode.
     * Command: Dm2,x,y,module_size,data
     * x,y in dots; module_size in dots (size of each cell).
     */
    public function dataMatrix(float $xMm, float $yMm, string $data, ?int $moduleSize = null): self
    {
        $moduleSize ??= $this->defaultModuleSize();
        $this->lines[] = sprintf('Dm2,%d,%d,%d,%s',
            $this->d($xMm),
            $this->d($yMm),
            $moduleSize,
            $data,
        );
        return $this;
    }

    /**
     * Text element.
     * Command: A x,y,rotation,font_type,height_dots,multx,multy,reverse,data
     * font_type: 0–7 = built-in bitmap fonts; height_dots controls size.
     */
    public function text(
        float  $xMm,
        float  $yMm,
        string $data,
        int    $heightDots = 0,  // 0 = auto from dpi
        bool   $bold = false,
        int    $fontType = 3,
        int    $rotation = 0,
    ): self {
        $data = trim($data);
        if ($data === '') return $this;

        if ($heightDots === 0) {
            $heightDots = $this->d(3); // ~3mm default
        }

        // EZPL text bold is handled by doubling multx/multy
        $mult = $bold ? 2 : 1;

        $this->lines[] = sprintf('A%d,%d,%d,%d,%d,%d,%d,0,%s',
            $this->d($xMm),
            $this->d($yMm),
            $rotation,
            $fontType,
            $heightDots,
            $mult,
            $mult,
            mb_substr($data, 0, 120),
        );
        return $this;
    }

    /**
     * EAN-13 barcode.
     * Command: B x,y,rotation,type,narrow,wide,height,readable,data
     * type 'E' = EAN-13 in EZPL.
     */
    public function ean13(float $xMm, float $yMm, string $data, float $heightMm = 15): self
    {
        $data = preg_replace('/\D/', '', $data);
        if (strlen($data) < 12) return $this;
        $data = substr($data, 0, 13);

        $this->lines[] = sprintf('B%d,%d,0,E,2,2,%d,B,%s',
            $this->d($xMm),
            $this->d($yMm),
            $this->d($heightMm),
            $data,
        );
        return $this;
    }

    /**
     * Draw a horizontal line.
     * Command: LE x,y,width_dots,height_dots
     */
    public function hLine(float $xMm, float $yMm, float $widthMm, float $thicknessMm = 0.3): self
    {
        $this->lines[] = sprintf('LE%d,%d,%d,%d',
            $this->d($xMm),
            $this->d($yMm),
            $this->d($widthMm),
            max(1, $this->d($thicknessMm)),
        );
        return $this;
    }

    /** End of format — triggers printing */
    public function footer(): self
    {
        $this->lines[] = 'E';
        return $this;
    }

    public function build(): string
    {
        return implode("\r\n", $this->lines) . "\r\n";
    }
}
