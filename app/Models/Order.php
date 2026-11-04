<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_UNPAID = 'unpaid';
    public const PAYMENT_PENDING_VERIFICATION = 'pending_verification';
    public const PAYMENT_PAID = 'paid';
    public const PAYMENT_REJECTED = 'rejected';

    public const METHOD_COD = 'cod';
    public const METHOD_GCASH = 'gcash';

    protected $fillable = [
        'order_number',
        'user_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'shipping_name',
        'shipping_phone',
        'shipping_line1',
        'shipping_line2',
        'shipping_barangay',
        'shipping_city',
        'shipping_province',
        'shipping_postal_code',
        'subtotal',
        'shipping_fee',
        'total',
        'status',
        'payment_method',
        'payment_status',
        'notes',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    // Example: KBS-261103-7QX2M
    public static function makeNumber(): string
    {
        do {
            $number = 'KBS-' . date('ymd') . '-' . strtoupper(Str::random(5));
        } while (static::where('order_number', $number)->exists());

        return $number;
    }
}
