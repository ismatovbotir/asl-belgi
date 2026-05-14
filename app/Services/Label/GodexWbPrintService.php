<?php

namespace App\Services\Label;

use App\Models\KmCode;
use App\Models\LabelTemplate;
use App\Models\Printer;
use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Communicates with the Godex WBPrint local Windows service.
 *
 * WBPrint acts as an HTTP bridge from web apps to physical Godex printers.
 * Default service URL: http://localhost:8080
 *
 * Two endpoints:
 *   POST /Send  — send EZPL commands to printer (Base64-encoded in 'Data')
 *   POST /Query — query printer status        (Base64-encoded in 'Data')
 *
 * Interface config field options:
 *   USB     → { Interface: 'USB',     USB: 'device_name_or_empty' }
 *   NETWORK → { Interface: 'NETWORK', IP: '192.168.x.x', Port: 9100 }
 *   COM     → { Interface: 'COM',     COM: 'COM1', BaudRate: 9600 }
 *   LPT     → { Interface: 'LPT',     LPT: 'LPT1' }
 */
class GodexWbPrintService
{
    private string $baseUrl;

    public function __construct(string $host = 'localhost', int $port = 8080)
    {
        $this->baseUrl = "http://{$host}:{$port}";
    }

    public static function fromPrinter(Printer $printer): self
    {
        return new self(
            $printer->param('wbprint_host', 'localhost'),
            (int) $printer->param('wbprint_port', 8080),
        );
    }

    /**
     * Send EZPL commands to the printer.
     * Returns true on HTTP 200, false otherwise.
     */
    public function send(array $interfaceConfig, string $ezpl): bool
    {
        $payload = array_merge($interfaceConfig, [
            'Data' => base64_encode($ezpl),
        ]);

        try {
            $response = Http::timeout(10)->post($this->baseUrl . '/Send', $payload);

            Log::channel('aslbelgisi')->info('WBPrint /Send', [
                'interface' => $interfaceConfig['Interface'] ?? '?',
                'status'    => $response->status(),
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::channel('aslbelgisi')->error('WBPrint /Send failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Query printer status.
     * Returns decoded response string from the printer.
     */
    public function query(array $interfaceConfig, string $queryData = ''): string
    {
        $payload = array_merge($interfaceConfig, [
            'Data' => base64_encode($queryData),
        ]);

        try {
            $response = Http::timeout(10)->post($this->baseUrl . '/Query', $payload);

            if ($response->successful()) {
                return base64_decode($response->body());
            }
        } catch (\Throwable $e) {
            Log::channel('aslbelgisi')->error('WBPrint /Query failed: ' . $e->getMessage());
        }

        return '';
    }

    /**
     * Build the interface config block from stored printer parameters.
     */
    public function buildInterfaceConfig(array $params): array
    {
        return match (strtoupper($params['interface'] ?? 'USB')) {
            'NETWORK' => [
                'Interface' => 'NETWORK',
                'IP'        => $params['ip'] ?? '',
                'Port'      => (int) ($params['printer_port'] ?? 9100),
            ],
            'COM' => [
                'Interface' => 'COM',
                'COM'       => $params['com_port'] ?? 'COM1',
                'BaudRate'  => (int) ($params['baud_rate'] ?? 9600),
            ],
            'LPT' => [
                'Interface' => 'LPT',
                'LPT'       => $params['lpt_port'] ?? 'LPT1',
            ],
            default => [
                'Interface' => 'USB',
                'USB'       => $params['usb_device'] ?? '',
            ],
        };
    }

    /**
     * Build an EPL2 label string from the fixed Godex 60×40 mm template.
     *
     * Field layout (all text is rotated 90° via the Y command):
     *   DataMatrix graphic  — XRB at dot (28, 34), data = $cis
     *   Text under DM       — Y at dot (18, 186)  = $textUnderDm
     *   Brand               — Y at dot (198, 31)  = $brand   (max ~34 chars)
     *   Product name        — Y at dot (198, 80)  = $name    (max ~85 chars)
     *   Model               — Y at dot (200, 132) = $model   (max ~40 chars)
     *   EAN-13 barcode      — BE at dot (230, 200) = $ean13
     */
    public function buildGodexTemplate(
        string $cis,
        string $textUnderDm,
        string $name,
        string $brand,
        string $model,
        string $ean13,
        int    $copies = 1,
    ): string {
        $cisLen = strlen($cis);

        $lines = [
            '^XSETCUT,DOUBLECUT,0',
            '^Q40,3',
            '^W60',
            '^H8',
            "^P{$copies}",
            '^S4',
            '^AD',
            '^C1',
            '^R0',
            '~Q+0',
            '^O0',
            '^D0',
            '^E18',
            '~R255',
            '^L',
            'Dy2-me-dd',
            'Th:m:s',
            "Y18,186,{$textUnderDm}",
            "Y198,31,{$brand}",
            "Y198,80,{$name}",
            "Y200,132,{$model}",
            "XRB28,34,4,0,{$cisLen}",
            "~1{$cis}",
            "BE,230,200,2,6,80,0,1,{$ean13}",
            'E',
        ];

        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * Print a label using the fixed Godex 60×40 mm template.
     */
    public function printGodexTemplate(
        array  $interfaceConfig,
        string $cis,
        string $textUnderDm,
        string $name,
        string $brand,
        string $model,
        string $ean13,
        int    $copies = 1,
    ): bool {
        $ezpl = $this->buildGodexTemplate($cis, $textUnderDm, $name, $brand, $model, $ean13, $copies);
        return $this->send($interfaceConfig, $ezpl);
    }

    /**
     * Print a single KM code label using a template or default layout.
     */
    public function printCode(
        array          $interfaceConfig,
        KmCode         $code,
        float          $widthMm,
        float          $heightMm,
        int            $dpi,
        ?Product       $product = null,
        ?LabelTemplate $tpl = null,
        int            $copies = 1,
    ): bool {
        $ezpl = $this->buildLabel($code, $widthMm, $heightMm, $dpi, $product, $tpl, $copies);
        return $this->send($interfaceConfig, $ezpl);
    }

    /**
     * Build an EZPL label string for a single KM code.
     * Uses LabelTemplate element positions if provided, otherwise sensible defaults.
     */
    public function buildLabel(
        KmCode         $code,
        float          $widthMm,
        float          $heightMm,
        int            $dpi,
        ?Product       $product = null,
        ?LabelTemplate $tpl = null,
        int            $copies = 1,
    ): string {
        $builder = new EzplBuilder($widthMm, $heightMm, $dpi);

        // Template element positions (mm), fallback to defaults matching PDF layout
        $dm    = $tpl?->el('datamatrix') ?? ['x' => 1,  'y' => 7.5, 'size' => 25];
        $nm    = $tpl?->el('name')        ?? ['visible' => true, 'x1' => 27, 'y1' => 1,  'x2' => $widthMm - 1, 'y2' => 16, 'font_size' => 7.5, 'bold' => true];
        $bt    = $tpl?->el('batch')       ?? ['visible' => true, 'x1' => 27, 'y1' => 16, 'font_size' => 5];
        $en    = $tpl?->el('ean13')       ?? ['visible' => true, 'x1' => 27, 'y1' => 19, 'x2' => $widthMm - 1, 'y2' => 37];

        $moduleSize = max(2, (int) round($dpi * ($dm['size'] ?? 25) / 25.4 / 22));

        $builder->header(copies: $copies);

        // GS1 DataMatrix — same structure as PDF template
        // chr(232) = FNC1 (GS1 start marker), chr(29) = GS separator between variable-length AIs
        $gs1 = '';
        if ($code->gtin && strlen($code->gtin) === 14) {
            $gs1 = chr(232) . '01' . $code->gtin;
            if ($code->serial_number)     $gs1 .= '21' . $code->serial_number     . chr(29);
            if ($code->verification_key)  $gs1 .= '91' . $code->verification_key  . chr(29);
            if ($code->verification_code) $gs1 .= '92' . $code->verification_code;
        } else {
            $gs1 = $code->cis ?? '';
        }

        $builder->dataMatrix($dm['x'] ?? 1, $dm['y'] ?? 7.5, $gs1, $moduleSize);

        // Product name
        if (!empty($nm['visible'])) {
            $name      = $product?->name ?? $code->gtin ?? '';
            $fontH     = max(12, $builder->d($nm['font_size'] ?? 7.5));
            $builder->text($nm['x1'] ?? 27, $nm['y1'] ?? 1, $name, $fontH, (bool) ($nm['bold'] ?? true));
        }

        // Batch / serial
        if (!empty($bt['visible']) && $code->batch) {
            $fontH = max(8, $builder->d($bt['font_size'] ?? 5));
            $builder->text($bt['x1'] ?? 27, $bt['y1'] ?? 16, 'Batch: ' . $code->batch, $fontH);
        }

        // EAN-13 barcode from GTIN
        if (!empty($en['visible']) && $code->gtin) {
            $ean = (strlen($code->gtin) === 14 && $code->gtin[0] === '0')
                ? substr($code->gtin, 1)
                : $code->gtin;
            $barH = ($en['y2'] ?? 37) - ($en['y1'] ?? 19);
            $builder->ean13($en['x1'] ?? 27, $en['y1'] ?? 19, $ean, $barH);
        }

        $builder->footer();

        return $builder->build();
    }
}
