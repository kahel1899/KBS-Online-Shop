<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Services\OrderService;
use App\Support\Present;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with('items', 'payments')->orderByDesc('id');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($payment = $request->query('payment_status')) {
            $query->where('payment_status', $payment);
        }

        $search = trim((string) $request->query('search'));
        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], mb_strtolower($search)) . '%';

            $query->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(order_number) LIKE ?', [$like])
                  ->orWhereRaw('LOWER(customer_name) LIKE ?', [$like])
                  ->orWhereRaw('LOWER(customer_phone) LIKE ?', [$like]);
            });
        }

        $page = $query->paginate(15);

        return response()->json(Present::page($page, function ($order) {
            return Present::order($order);
        }));
    }

    public function show($id)
    {
        return response()->json(['order' => Present::order(Order::findOrFail($id))]);
    }

    public function updateStatus(Request $request, OrderService $orders, $id)
    {
        $data = $request->validate([
            'status' => 'required|in:confirmed,shipped,completed,cancelled',
        ]);

        $order = $orders->updateStatus(Order::findOrFail($id), $data['status']);

        return response()->json(['order' => Present::order($order)]);
    }

    public function reviewPayment(Request $request, OrderService $orders, $id)
    {
        $data = $request->validate([
            'action' => 'required|in:verify,reject',
            'admin_note' => 'nullable|string|max:500',
        ]);

        $payment = Payment::findOrFail($id);

        $order = $orders->reviewPayment(
            $payment,
            $data['action'],
            $data['admin_note'] ?? null,
            $request->user()
        );

        return response()->json(['order' => Present::order($order)]);
    }

    // Small counters for the admin menu
    public function summary()
    {
        return response()->json([
            'pending_orders' => Order::where('status', Order::STATUS_PENDING)->count(),
            'to_verify' => Order::where('payment_status', Order::PAYMENT_PENDING_VERIFICATION)
                ->where('status', '!=', Order::STATUS_CANCELLED)
                ->count(),
            'low_stock' => Product::where('quantity', '<=', 5)->count(),
        ]);
    }
}
