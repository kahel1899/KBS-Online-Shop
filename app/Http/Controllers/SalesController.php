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
    'platform' => 'required|in:TikTok,Shopee,Walk-in,Online',
    'quantity' => 'required|integer|min:1',
    'selling_price' => 'required|numeric|min:0',
]);
        return DB::transaction(function () use ($request) {

            $product = Product::findOrFail($request->product_id);

            if ($product->quantity < $request->quantity) {
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
]);

            $product->decrement('quantity', $request->quantity);

            return response()->json([
                'message' => 'Sale recorded successfully.',
                'sale' => $sale,
                'remaining_stock' => $product->quantity,
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