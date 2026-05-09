<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Printer extends Model
{
    protected $fillable = [
        'name', 'printer_type_id', 'parameters', 'is_default', 'is_active',
    ];

    protected $casts = [
        'parameters' => 'array',
        'is_default' => 'boolean',
        'is_active'  => 'boolean',
    ];

    public function printerType(): BelongsTo
    {
        return $this->belongsTo(PrinterType::class);
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->parameters[$key] ?? $default;
    }

    public static function getDefault(): ?self
    {
        return self::where('is_default', true)->where('is_active', true)->first();
    }
}
