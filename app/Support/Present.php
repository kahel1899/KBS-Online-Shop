<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;

class Present
{
    public static function user(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
        ];
    }

    // What the public shop is allowed to see about a product
    public static function product(Product $product, bool $detail = false): array
    {
        $extra = $product->relationLoaded('images')
            ? $product->images->pluck('url')->values()->all()
            : [];

        $category = null;
        if ($product->relationLoaded('category') && $product->category && $product->category->is_active) {
            $category = [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'slug' => $product->category->slug,
            ];
        }

        $data = [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => (float) $product->online_price,
            'image' => $product->image ?: ($extra[0] ?? null),
            'stock' => (int) $product->quantity,
            'is_featured' => (bool) $product->is_featured,
            'category' => $category,
        ];

        if ($detail) {
            $data['description'] = $product->description;
            $data['images'] = array_values(array_filter(array_merge([$product->image], $extra)));
        }

        return $data;
    }

    public static function order(Order $order): array
    {
        $order->loadMissing('items', 'payments');

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status,
            'can_cancel' => $order->status === Order::STATUS_PENDING
                && in_array($order->payment_status, [Order::PAYMENT_UNPAID, Order::PAYMENT_REJECTED], true),
            'customer' => [
                'name' => $order->customer_name,
                'email' => $order->customer_email,
                'phone' => $order->customer_phone,
            ],
            'shipping' => [
                'name' => $order->shipping_name,
                'phone' => $order->shipping_phone,
                'line1' => $order->shipping_line1,
                'line2' => $order->shipping_line2,
                'barangay' => $order->shipping_barangay,
                'city' => $order->shipping_city,
                'province' => $order->shipping_province,
                'postal_code' => $order->shipping_postal_code,
            ],
            'subtotal' => (float) $order->subtotal,
            'shipping_fee' => (float) $order->shipping_fee,
            'total' => (float) $order->total,
            'notes' => $order->notes,
            'created_at' => optional($order->created_at)->toIso8601String(),
            'items' => $order->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'name' => $item->product_name,
                    'image' => $item->product_image,
                    'price' => (float) $item->unit_price,
                    'quantity' => (int) $item->quantity,
                    'line_total' => (float) $item->line_total,
                ];
            })->values()->all(),
            'payments' => $order->payments->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'method' => $payment->method,
                    'amount' => (float) $payment->amount,
                    'reference_number' => $payment->reference_number,
                    'status' => $payment->status,
                    'admin_note' => $payment->admin_note,
                    'verified_at' => optional($payment->verified_at)->toIso8601String(),
                ];
            })->values()->all(),
        ];
    }

    public static function page($paginator, callable $map): array
    {
        return [
            'data' => $paginator->getCollection()->map($map)->values()->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
