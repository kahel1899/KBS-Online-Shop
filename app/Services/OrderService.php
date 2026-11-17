<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    // Where an order may go next
    public const TRANSITIONS = [
        Order::STATUS_PENDING => [Order::STATUS_CONFIRMED, Order::STATUS_CANCELLED],
        Order::STATUS_CONFIRMED => [Order::STATUS_SHIPPED, Order::STATUS_CANCELLED],
        Order::STATUS_SHIPPED => [Order::STATUS_COMPLETED, Order::STATUS_CANCELLED],
        Order::STATUS_COMPLETED => [],
        Order::STATUS_CANCELLED => [],
    ];

    public function shippingFee(float $subtotal): float
    {
        $freeFrom = (float) config('shop.free_shipping_min');

        if ($freeFrom > 0 && $subtotal >= $freeFrom) {
            return 0.0;
        }

        return (float) config('shop.shipping_fee');
    }

    /**
     * Create an order from a checkout form. Prices and stock are always
     * checked here on the server, never trusted from the browser.
     */
    public function place(array $data, ?User $user): Order
    {
        return DB::transaction(function () use ($data, $user) {
            // The same product (and same variant) twice becomes one line
            $lines = [];
            foreach ($data['items'] as $item) {
                $productId = (int) $item['product_id'];
                $variantId = ! empty($item['variant_id']) ? (int) $item['variant_id'] : 0;
                $key = $productId . ':' . $variantId;

                if (! isset($lines[$key])) {
                    $lines[$key] = ['product_id' => $productId, 'variant_id' => $variantId, 'quantity' => 0];
                }
                $lines[$key]['quantity'] += (int) $item['quantity'];
            }

            $productIds = collect($lines)->pluck('product_id')->unique()->values()->all();

            // Lock the rows so two customers can't buy the last item together
            $products = Product::whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');
            $variants = ProductVariant::whereIn('product_id', $productIds)->lockForUpdate()->get()->keyBy('id');

            $subtotal = 0.0;
            $prepared = [];

            foreach ($lines as $line) {
                $product = $products->get($line['product_id']);

                if (! $product || ! $product->is_active || (float) $product->online_price <= 0) {
                    throw ValidationException::withMessages([
                        'items' => 'An item in your cart is no longer available. Please review your cart.',
                    ]);
                }

                $variant = null;
                $productHasVariants = $variants->where('product_id', $product->id)->isNotEmpty();

                if ($productHasVariants) {
                    $variant = $variants->get($line['variant_id']);

                    if (! $variant || (int) $variant->product_id !== (int) $product->id) {
                        throw ValidationException::withMessages([
                            'items' => "Please choose an option for {$product->name}.",
                        ]);
                    }
                } elseif ($line['variant_id'] !== 0) {
                    throw ValidationException::withMessages([
                        'items' => 'An item in your cart is no longer available. Please review your cart.',
                    ]);
                }

                $label = $variant ? "{$product->name} ({$variant->name})" : $product->name;
                $stock = $variant ? (int) $variant->quantity : (int) $product->quantity;

                if ($stock < $line['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => $stock > 0
                            ? "Only {$stock} left of {$label}."
                            : "{$label} is sold out.",
                    ]);
                }

                $price = ($variant && $variant->price !== null)
                    ? (float) $variant->price
                    : (float) $product->online_price;

                $subtotal += round($price * $line['quantity'], 2);

                $prepared[] = [
                    'product' => $product,
                    'variant' => $variant,
                    'quantity' => $line['quantity'],
                    'price' => $price,
                ];
            }

            $shippingFee = $this->shippingFee($subtotal);
            $total = round($subtotal + $shippingFee, 2);

            $order = Order::create([
                'order_number' => Order::makeNumber(),
                'user_id' => $user ? $user->id : null,
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'] ?? null,
                'customer_phone' => $data['customer_phone'],
                'shipping_name' => $data['shipping_name'],
                'shipping_phone' => $data['shipping_phone'],
                'shipping_line1' => $data['shipping_line1'],
                'shipping_line2' => $data['shipping_line2'] ?? null,
                'shipping_barangay' => $data['shipping_barangay'] ?? null,
                'shipping_city' => $data['shipping_city'],
                'shipping_province' => $data['shipping_province'],
                'shipping_postal_code' => $data['shipping_postal_code'] ?? null,
                'subtotal' => $subtotal,
                'shipping_fee' => $shippingFee,
                'total' => $total,
                'status' => Order::STATUS_PENDING,
                'payment_method' => $data['payment_method'],
                'payment_status' => Order::PAYMENT_UNPAID,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($prepared as $row) {
                $product = $row['product'];
                $variant = $row['variant'];

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'variant_id' => $variant ? $variant->id : null,
                    'variant_name' => $variant ? $variant->name : null,
                    'product_name' => $product->name,
                    'product_image' => $product->image,
                    'unit_price' => $row['price'],
                    'unit_cost' => (float) $product->cost,
                    'quantity' => $row['quantity'],
                    'line_total' => round($row['price'] * $row['quantity'], 2),
                ]);

                if ($variant) {
                    $variant->decrement('quantity', $row['quantity']);
                    $product->syncQuantity();
                } else {
                    $product->decrement('quantity', $row['quantity']);
                }
            }

            Payment::create([
                'order_id' => $order->id,
                'method' => $order->payment_method,
                'amount' => $total,
                'status' => 'pending',
            ]);

            return $order->load('items', 'payments');
        });
    }

    public function updateStatus(Order $order, string $status): Order
    {
        return DB::transaction(function () use ($order, $status) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            $allowed = self::TRANSITIONS[$order->status] ?? [];

            if (! in_array($status, $allowed, true)) {
                throw ValidationException::withMessages([
                    'status' => "An order that is {$order->status} can't be changed to {$status}.",
                ]);
            }

            if ($status === Order::STATUS_CANCELLED) {
                $this->restock($order);

                if ($order->payment_status === Order::PAYMENT_PAID) {
                    $order->payment_status = 'refund_due';
                } else {
                    $order->payment_status = Order::PAYMENT_UNPAID;
                    $order->payments()->where('status', 'pending')->update(['status' => 'rejected']);
                }
            } else {
                if ($order->payment_method === Order::METHOD_GCASH
                    && $order->payment_status !== Order::PAYMENT_PAID) {
                    throw ValidationException::withMessages([
                        'status' => 'Verify the GCash payment first.',
                    ]);
                }

                if ($status === Order::STATUS_COMPLETED) {
                    $this->recordSales($order);

                    // Cash on delivery is paid once the parcel is handed over
                    if ($order->payment_status !== Order::PAYMENT_PAID) {
                        $order->payment_status = Order::PAYMENT_PAID;
                        $order->payments()->where('status', '!=', 'verified')->update([
                            'status' => 'verified',
                            'verified_at' => now(),
                        ]);
                    }
                }
            }

            $order->status = $status;
            $order->save();

            return $order->load('items', 'payments');
        });
    }

    // A customer may only cancel before the shop has started working on it
    public function cancelByCustomer(Order $order): Order
    {
        $cancellable = $order->status === Order::STATUS_PENDING
            && in_array($order->payment_status, [Order::PAYMENT_UNPAID, Order::PAYMENT_REJECTED], true);

        if (! $cancellable) {
            throw ValidationException::withMessages([
                'order' => 'This order can no longer be cancelled online. Please contact the shop.',
            ]);
        }

        return $this->updateStatus($order, Order::STATUS_CANCELLED);
    }

    // Manual GCash: the customer sends the reference number from their receipt
    public function submitReference(Order $order, string $reference): Order
    {
        $allowed = $order->payment_method === Order::METHOD_GCASH
            && in_array($order->status, [Order::STATUS_PENDING, Order::STATUS_CONFIRMED], true)
            && $order->payment_status !== Order::PAYMENT_PAID;

        if (! $allowed) {
            throw ValidationException::withMessages([
                'reference_number' => 'A payment reference cannot be added to this order.',
            ]);
        }

        return DB::transaction(function () use ($order, $reference) {
            $payment = $order->payments()->latest('id')->first();

            if (! $payment) {
                $payment = new Payment(['order_id' => $order->id, 'method' => 'gcash', 'amount' => $order->total]);
            }

            $payment->reference_number = $reference;
            $payment->status = 'pending';
            $payment->admin_note = null;
            $payment->save();

            $order->payment_status = Order::PAYMENT_PENDING_VERIFICATION;
            $order->save();

            return $order->load('items', 'payments');
        });
    }

    // Admin checks the GCash receipt and accepts or rejects it
    public function reviewPayment(Payment $payment, string $action, ?string $note, User $admin): Order
    {
        return DB::transaction(function () use ($payment, $action, $note, $admin) {
            $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();

            if ($order->status === Order::STATUS_CANCELLED || $payment->status !== 'pending') {
                throw ValidationException::withMessages([
                    'action' => 'This payment can no longer be reviewed.',
                ]);
            }

            if ($action === 'verify') {
                $payment->status = 'verified';
                $payment->verified_by = $admin->id;
                $payment->verified_at = now();
                $order->payment_status = Order::PAYMENT_PAID;
            } else {
                $payment->status = 'rejected';
                $order->payment_status = Order::PAYMENT_REJECTED;
            }

            $payment->admin_note = $note;
            $payment->save();
            $order->save();

            return $order->load('items', 'payments');
        });
    }

    private function restock(Order $order): void
    {
        foreach ($order->items as $item) {
            if ($item->variant_id) {
                $variant = ProductVariant::find($item->variant_id);

                if ($variant) {
                    $variant->increment('quantity', $item->quantity);

                    $product = Product::find($variant->product_id);
                    if ($product) {
                        $product->syncQuantity();
                    }
                }
            } elseif ($item->product_id && ! $item->variant_name) {
                Product::where('id', $item->product_id)->increment('quantity', $item->quantity);
            }
        }
    }

    // Completed orders count as sales, so the admin dashboard includes them
    private function recordSales(Order $order): void
    {
        foreach ($order->items as $item) {
            if (! $item->product_id) {
                continue;
            }

            Sale::create([
                'product_id' => $item->product_id,
                'platform' => 'Online',
                'variant_name' => $item->variant_name,
                'quantity' => $item->quantity,
                'selling_price' => $item->unit_price,
                'unit_cost' => $item->unit_cost,
                'platform_fee' => 0,
            ]);
        }
    }
}
