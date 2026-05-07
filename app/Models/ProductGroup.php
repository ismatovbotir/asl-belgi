<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductGroup extends Model
{
    public $incrementing = false;  // IDs are the official ASL BELGISI group numbers
    public $timestamps   = false;

    protected $keyType  = 'int';
    protected $fillable = ['id', 'code', 'name_ru'];

    public function products(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Product::class, 'product_group_code', 'code');
    }

    public static function byCode(string $code): ?self
    {
        return static::where('code', $code)->first();
    }
}
