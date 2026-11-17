<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    private const NUMBERS = ['price', 'cost', 'tiktok_price', 'shopee_price', 'online_price'];

    private const FIELDS = [
        'name', 'image', 'quantity', 'price', 'cost', 'tiktok_price', 'shopee_price',
        'category_id', 'description', 'online_price', 'is_active', 'is_featured', 'variant_label',
    ];

    public function index()
    {
        return response()->json(Product::with(['images', 'variants'])->get());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, true);

        $product = Product::create($data);
        $this->syncImages($product, $request);
        $this->syncVariants($product, $request);

        return response()->json($product->load('images', 'variants'), 201);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $data = $this->validated($request, false);

        $product->update($data);
        $this->syncImages($product, $request);
        $this->syncVariants($product, $request);

        return response()->json($product->load('images', 'variants'));
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
            'variant_label' => 'sometimes|nullable|string|max:40',
            'variants' => 'sometimes|nullable|array|max:50',
            'variants.*.id' => 'nullable|integer',
            'variants.*.name' => 'required|string|max:80',
            'variants.*.price' => 'nullable|numeric|min:0',
            'variants.*.quantity' => 'required|integer|min:0',
        ]);

        $data = $request->only(self::FIELDS);

        // The price columns can't be empty in the database
        foreach (self::NUMBERS as $key) {
            if (array_key_exists($key, $data) && $data[$key] === null) {
                $data[$key] = 0;
            }
        }

        // There is one price now: the online price. The older price column
        // follows it, and the other platform prices are no longer used.
        if (array_key_exists('online_price', $data)) {
            $data['price'] = $data['online_price'];
        }

        if ($creating) {
            foreach (['price', 'cost', 'tiktok_price', 'shopee_price'] as $key) {
                if (! array_key_exists($key, $data)) {
                    $data[$key] = 0;
                }
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

    // "variants" is the full list of options (shades, sizes...) for the product.
    // Rows with an id are updated, rows without are created, missing rows are removed.
    private function syncVariants(Product $product, Request $request): void
    {
        if (! $request->has('variants')) {
            return;
        }

        $keep = [];

        foreach (array_values($request->input('variants', []) ?? []) as $index => $row) {
            $attributes = [
                'name' => trim((string) $row['name']),
                'price' => (isset($row['price']) && $row['price'] !== '') ? (float) $row['price'] : null,
                'quantity' => (int) $row['quantity'],
                'sort_order' => $index,
            ];

            $existing = ! empty($row['id'])
                ? ProductVariant::where('product_id', $product->id)->where('id', $row['id'])->first()
                : null;

            if ($existing) {
                $existing->update($attributes);
                $keep[] = $existing->id;
            } else {
                $keep[] = $product->variants()->create($attributes)->id;
            }
        }

        ProductVariant::where('product_id', $product->id)->whereNotIn('id', $keep)->delete();

        $product->unsetRelation('variants');
        $product->syncQuantity();
    }
}
