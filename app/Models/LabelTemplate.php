<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabelTemplate extends Model
{
    protected $fillable = ['name', 'width_mm', 'height_mm', 'elements'];

    protected $casts = [
        'elements'  => 'array',
        'width_mm'  => 'float',
        'height_mm' => 'float',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(KmOrder::class);
    }

    public function el(string $key): array
    {
        $el = $this->elements[$key] ?? [];
        return $this->normalizeEl($key, $el);
    }

    private function normalizeEl(string $key, array $el): array
    {
        return match ($key) {
            'name' => [
                'visible'   => $el['visible']   ?? true,
                'x1'        => $el['x1']        ?? ($el['x']  ?? 27),
                'y1'        => $el['y1']        ?? ($el['y']  ?? 1),
                'x2'        => $el['x2']        ?? (($el['x'] ?? 27) + ($el['width']  ?? 32)),
                'y2'        => $el['y2']        ?? (($el['y'] ?? 1)  + 15),
                'font_size' => $el['font_size'] ?? 7.5,
                'bold'      => $el['bold']      ?? true,
            ],
            'ean13' => [
                'visible'   => $el['visible']   ?? true,
                'x1'        => $el['x1']        ?? ($el['x']  ?? 27),
                'y1'        => $el['y1']        ?? ($el['y']  ?? 19),
                'x2'        => $el['x2']        ?? (($el['x'] ?? 27) + ($el['width']  ?? 32)),
                'y2'        => $el['y2']        ?? (($el['y'] ?? 19) + ($el['height'] ?? 18)),
                'font_size' => $el['font_size'] ?? 4,
            ],
            'batch' => [
                'visible'   => $el['visible']   ?? true,
                'x1'        => $el['x1']        ?? ($el['x']  ?? 27),
                'y1'        => $el['y1']        ?? ($el['y']  ?? 16),
                'x2'        => $el['x2']        ?? (($el['x'] ?? 27) + 32),
                'y2'        => $el['y2']        ?? (($el['y'] ?? 16) + 3),
                'font_size' => $el['font_size'] ?? 5,
            ],
            'page_number' => [
                'visible'   => $el['visible']   ?? true,
                'x1'        => $el['x1']        ?? ($el['x']  ?? 27),
                'y1'        => $el['y1']        ?? ($el['y']  ?? 37),
                'x2'        => $el['x2']        ?? (($el['x'] ?? 27) + 15),
                'y2'        => $el['y2']        ?? (($el['y'] ?? 37) + 2),
                'font_size' => $el['font_size'] ?? 5,
            ],
            default => $el,
        };
    }

    public static function defaults(): array
    {
        return [
            'datamatrix'  => ['visible' => true, 'x'  => 1,  'y'  => 7.5, 'size' => 25],
            'name'        => ['visible' => true, 'x1' => 27, 'y1' => 1,  'x2' => 59, 'y2' => 16, 'font_size' => 7.5, 'bold' => true],
            'ean13'       => ['visible' => true, 'x1' => 27, 'y1' => 19, 'x2' => 59, 'y2' => 37, 'font_size' => 4],
            'batch'       => ['visible' => true, 'x1' => 27, 'y1' => 16, 'x2' => 59, 'y2' => 19, 'font_size' => 5],
            'page_number' => ['visible' => true, 'x1' => 27, 'y1' => 37, 'x2' => 42, 'y2' => 39, 'font_size' => 5],
        ];
    }
}
