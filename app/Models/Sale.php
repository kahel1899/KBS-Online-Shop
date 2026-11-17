<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

protected $fillable = [
    'product_id',
    'platform',
    'quantity',
    'selling_price',
    'unit_cost',
     'platform_fee',
    'variant_name',
];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}