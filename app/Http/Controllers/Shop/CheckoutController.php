<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    private const PHONE = ['required', 'string', 'regex:/^[0-9+\-\s()]{7,20}$/'];

    public function store(Request $request, OrderService $orders)
    {
        $data = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => self::PHONE,
            'shipping_name' => 'required|string|max:255',
            'shipping_phone' => self::PHONE,
            'shipping_line1' => 'required|string|max:255',
            'shipping_line2' => 'nullable|string|max:255',
            'shipping_barangay' => 'nullable|string|max:255',
            'shipping_city' => 'required|string|max:255',
            'shipping_province' => 'required|string|max:255',
            'shipping_postal_code' => 'nullable|string|max:10',
            'payment_method' => 'required|in:cod,gcash',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1|max:50',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1|max:99',
        ]);

        // Guests can check out; a logged-in customer's order is linked to them
        $order = $orders->place($data, $request->user('sanctum'));

        return response()->json([
            'message' => 'Order placed.',
            'order' => Present::order($order),
        ], 201);
    }

    // Guests look up an order with its number and the phone used at checkout
    public function track(Request $request)
    {
        $order = $this->findOrder($request);

        return response()->json(['order' => Present::order($order)]);
    }

    public function pay(Request $request, OrderService $orders)
    {
        $order = $this->findOrder($request);

        $data = $request->validate([
            'reference_number' => 'required|string|min:6|max:100',
        ]);

        $order = $orders->submitReference($order, trim($data['reference_number']));

        return response()->json([
            'message' => 'Thanks! We will check your payment shortly.',
            'order' => Present::order($order),
        ]);
    }

    public function cancel(Request $request, OrderService $orders)
    {
        $order = $this->findOrder($request);

        $order = $orders->cancelByCustomer($order);

        return response()->json([
            'message' => 'Your order was cancelled.',
            'order' => Present::order($order),
        ]);
    }

    private function findOrder(Request $request): Order
    {
        $number = strtoupper(trim((string) $request->input('order_number')));
        $order = $number !== '' ? Order::where('order_number', $number)->first() : null;

        $user = $request->user('sanctum');
        $owner = $order && $user && (int) $order->user_id === (int) $user->id;

        if (! $order || (! $owner && ! $this->phoneMatches($order, (string) $request->input('phone')))) {
            // Same answer for "no such order" and "wrong phone"
            throw ValidationException::withMessages([
                'order_number' => "We couldn't find an order with those details.",
            ]);
        }

        return $order;
    }

    private function phoneMatches(Order $order, string $phone): bool
    {
        $given = $this->lastDigits($phone);

        if (strlen($given) < 7) {
            return false;
        }

        return $given === $this->lastDigits($order->customer_phone)
            || $given === $this->lastDigits($order->shipping_phone);
    }

    private function lastDigits(?string $phone): string
    {
        return substr(preg_replace('/\D+/', '', (string) $phone), -10);
    }
}
