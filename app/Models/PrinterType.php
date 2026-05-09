<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrinterType extends Model
{
    protected $fillable = ['name', 'slug', 'renderer_class', 'parameters_schema', 'description'];

    protected $casts = [
        'parameters_schema' => 'array',
    ];

    public function printers(): HasMany
    {
        return $this->hasMany(Printer::class);
    }
}
