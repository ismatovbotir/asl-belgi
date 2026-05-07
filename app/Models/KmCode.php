<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KmCode extends Model
{
    public $timestamps = false;

    // Lifecycle statuses
    public const STATUS_AVAILABLE      = 'available';
    public const STATUS_PRINTED        = 'printed';
    public const STATUS_APPLIED        = 'applied';
    public const STATUS_AGGREGATED     = 'aggregated';
    public const STATUS_IN_CIRCULATION = 'in_circulation';
    public const STATUS_SPOILED        = 'spoiled';
    public const STATUS_WITHDRAWN      = 'withdrawn';

    protected $fillable = [
        'km_order_id', 'km_order_item_id', 'aggregation_id',
        'code', 'cis',
        'gtin', 'serial_number', 'expiry_date', 'batch',
        'status', 'label_printed', 'printed_at', 'applied_at',
    ];

    protected $casts = [
        'label_printed' => 'boolean',
        'expiry_date'   => 'date',
        'printed_at'    => 'datetime',
        'applied_at'    => 'datetime',
        'created_at'    => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(KmOrder::class, 'km_order_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(KmOrderItem::class, 'km_order_item_id');
    }

    public function aggregation(): BelongsTo
    {
        return $this->belongsTo(KmAggregation::class, 'aggregation_id');
    }
}
