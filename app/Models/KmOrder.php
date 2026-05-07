<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KmOrder extends Model
{
    protected $fillable = [
        'name', 'external_order_id', 'product_group', 'release_method_type',
        'status', 'total_codes_requested', 'total_codes_downloaded',
        'raw_data', 'closed_at', 'pdf_path',
    ];

    protected $casts = [
        'raw_data'   => 'array',
        'closed_at'  => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(KmOrderItem::class);
    }

    public function codes(): HasMany
    {
        return $this->hasMany(KmCode::class);
    }

    public function aggregations(): HasMany
    {
        return $this->hasMany(KmAggregation::class);
    }

    public function isDone(): bool
    {
        return in_array($this->status, ['DOWNLOADED', 'DONE', 'CLOSED']);
    }

    public function hasReadyBuffers(): bool
    {
        return $this->items()->where('status', 'READY')->exists();
    }
}
