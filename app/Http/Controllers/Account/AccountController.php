<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\OrderService;
use App\Support\Present;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function orders(Request $request)
    {
        $orders = $request->user()
            ->orders()
            ->with('items', 'payments')
            ->latest('id')
            ->get()
            ->map(function ($order) {
                return Present::order($order);
            })
            ->values();

        return response()->json($orders);
    }

    public function show(Request $request, $id)
    {
        $order = $request->user()->orders()->with('items', 'payments')->findOrFail($id);

        return response()->json(['order' => Present::order($order)]);
    }

    public function cancel(Request $request, OrderService $orders, $id)
    {
        $order = $request->user()->orders()->findOrFail($id);

        return response()->json([
            'message' => 'Your order was cancelled.',
            'order' => Present::order($orders->cancelByCustomer($order)),
        ]);
    }
}
