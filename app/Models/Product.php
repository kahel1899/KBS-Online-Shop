<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;
    protected $table='kookuproducts';

protected $fillable = [
    'image',
    'name',
    'quantity',
    'price',
    'cost',
    'tiktok_price',
    'shopee_price',
    'category_id',
    'slug',
    'description',
    'online_price',
    'is_active',
    'is_featured',
];

    protected $casts = [
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
    ];

    protected static function booted()
    {
        // New products get a URL-friendly slug, e.g. "gel-polish-red-12"
        static::created(function ($product) {
            if (! $product->slug) {
                $product->slug = (Str::slug($product->name) ?: 'product') . '-' . $product->id;
                $product->saveQuietly();
            }
        });
    }

    public function sales()
{
    return $this->hasMany(Sale::class);
}

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    // Published in the shop and priced for online selling
    public function scopePublished($query)
    {
        return $query->where('is_active', true)
            ->where('online_price', '>', 0);
    }

    // Published AND in stock: what shows in the shop listings
    public function scopeForShop($query)
    {
        return $query->published()->where('quantity', '>', 0);
    }
}
