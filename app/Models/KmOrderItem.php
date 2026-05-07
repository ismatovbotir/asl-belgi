<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KmOrderItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'km_order_id', 'buffer_id', 'gtin', 'quantity',
        'status', 'codes_downloaded', 'raw_data',
    ];

    protected $casts = [
        'raw_data'   => 'array',
        'created_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(KmOrder::class, 'km_order_id');
    }

    public function codes(): HasMany
    {
        return $this->hasMany(KmCode::class);
    }
}
