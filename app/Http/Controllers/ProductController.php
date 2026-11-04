<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    private const NUMBERS = ['price', 'cost', 'tiktok_price', 'shopee_price', 'online_price'];

    private const FIELDS = [
        'name', 'image', 'quantity', 'price', 'cost', 'tiktok_price', 'shopee_price',
        'category_id', 'description', 'online_price', 'is_active', 'is_featured',
    ];

    public function index()
    {
        return response()->json(Product::with('images')->get());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, true);

        $product = Product::create($data);
        $this->syncImages($product, $request);

        return response()->json($product->load('images'), 201);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $data = $this->validated($request, false);

        $product->update($data);
        $this->syncImages($product, $request);

        return response()->json($product->load('images'));
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json([
            'message' => 'Product Delete Successful'
        ]);
    }

    private function validated(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes|required';

        $request->validate([
            'name' => $required . '|string|max:255',
            'image' => 'sometimes|nullable|string|max:2048',
            'quantity' => $required . '|integer|min:0',
            'price' => 'sometimes|nullable|numeric|min:0',
            'cost' => 'sometimes|nullable|numeric|min:0',
            'tiktok_price' => 'sometimes|nullable|numeric|min:0',
            'shopee_price' => 'sometimes|nullable|numeric|min:0',
            'online_price' => 'sometimes|nullable|numeric|min:0',
            'category_id' => 'sometimes|nullable|exists:categories,id',
            'description' => 'sometimes|nullable|string|max:5000',
            'is_active' => 'sometimes|boolean',
            'is_featured' => 'sometimes|boolean',
            'images' => 'sometimes|nullable|array|max:10',
            'images.*' => 'nullable|string|max:2048',
        ]);

        $data = $request->only(self::FIELDS);

        // The price columns can't be empty in the database
        foreach (self::NUMBERS as $key) {
            if (array_key_exists($key, $data) && $data[$key] === null) {
                $data[$key] = 0;
            }
        }

        return $data;
    }

    // "images" is the list of extra picture links for the shop gallery
    private function syncImages(Product $product, Request $request): void
    {
        if (! $request->has('images')) {
            return;
        }

        $product->images()->delete();

        $urls = collect($request->input('images', []))
            ->map(function ($url) {
                return trim((string) $url);
            })
            ->filter()
            ->unique()
            ->values();

        foreach ($urls as $index => $url) {
            $product->images()->create(['url' => $url, 'sort_order' => $index]);
        }
    }
}
