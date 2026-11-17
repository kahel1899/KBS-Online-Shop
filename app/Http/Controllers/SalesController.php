<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:kookuproducts,id',
            'variant_id' => 'nullable|integer',
            'platform' => 'required|in:TikTok,Shopee,Walk-in,Online',
            'quantity' => 'required|integer|min:1',
            'selling_price' => 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($request) {

            $product = Product::lockForUpdate()->findOrFail($request->product_id);
            $variants = $product->variants()->lockForUpdate()->get();

            $variant = null;

            if ($variants->isNotEmpty()) {
                $variant = $variants->firstWhere('id', (int) $request->variant_id);

                if (! $variant) {
                    return response()->json([
                        'message' => 'Please choose an option for this product.'
                    ], 422);
                }

                $available = (int) $variant->quantity;
            } else {
                $available = (int) $product->quantity;
            }

            if ($available < (int) $request->quantity) {
                return response()->json([
                    'message' => 'Not enough stock.'
                ], 422);
            }

            $sale = Sale::create([
                'product_id' => $product->id,
                'platform' => $request->platform,
                'quantity' => $request->quantity,
                'selling_price' => $request->selling_price,
                'unit_cost' => $product->cost,
                'variant_name' => $variant ? $variant->name : null,
            ]);

            if ($variant) {
                $variant->decrement('quantity', $request->quantity);
                $product->syncQuantity();
                $remaining = $variant->quantity;
            } else {
                $product->decrement('quantity', $request->quantity);
                $remaining = $product->quantity;
            }

            return response()->json([
                'message' => 'Sale recorded successfully.',
                'sale' => $sale,
                'remaining_stock' => $remaining,
            ], 201);
        });
    }

    public function index()
{
    $sales = Sale::with('product')
        ->latest()
        ->get();

    return response()->json($sales);
}
}