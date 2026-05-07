<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'external_id', 'gtin',
        'name', 'name_ru', 'name_uz',
        'inn', 'brand',
        'product_group', 'product_group_code',
        'category', 'category_code',
        'tnved_code', 'package_type',
        'attributes', 'synced_at',
    ];

    protected $casts = [
        'attributes' => 'array',
        'synced_at'  => 'datetime',
    ];
}
