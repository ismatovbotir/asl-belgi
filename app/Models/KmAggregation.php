<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KmAggregation extends Model
{
    protected $fillable = [
        'km_order_id', 'code', 'type', 'parent_id',
        'status', 'codes_count', 'closed_at',
    ];

    protected $casts = [
        'closed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(KmOrder::class, 'km_order_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function codes(): HasMany
    {
        return $this->hasMany(KmCode::class, 'aggregation_id');
    }
}
