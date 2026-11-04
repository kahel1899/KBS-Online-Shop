<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Support\Present;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function settings()
    {
        return response()->json([
            'name' => config('shop.name'),
            'shipping_fee' => (float) config('shop.shipping_fee'),
            'free_shipping_min' => (float) config('shop.free_shipping_min'),
            'gcash_name' => config('shop.gcash_name'),
            'gcash_number' => config('shop.gcash_number'),
            'contact_phone' => config('shop.contact_phone'),
            'contact_email' => config('shop.contact_email'),
            'contact_facebook' => config('shop.contact_facebook'),
        ]);
    }

    public function categories()
    {
        $counts = Product::forShop()
            ->selectRaw('category_id, count(*) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        $categories = Category::active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function ($category) use ($counts) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'image' => $category->image,
                    'product_count' => (int) ($counts[$category->id] ?? 0),
                ];
            })
            ->values();

        return response()->json($categories);
    }

    public function products(Request $request)
    {
        $query = Product::forShop()->with('category');

        $search = trim((string) $request->query('search'));
        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], mb_strtolower($search)) . '%';

            $query->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(name) LIKE ?', [$like])
                  ->orWhereRaw('LOWER(description) LIKE ?', [$like]);
            });
        }

        $categorySlug = trim((string) $request->query('category'));
        if ($categorySlug !== '') {
            $query->whereHas('category', function ($q) use ($categorySlug) {
                $q->where('slug', $categorySlug)->where('is_active', true);
            });
        }

        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        switch ($request->query('sort')) {
            case 'price_asc':
                $query->orderBy('online_price')->orderByDesc('id');
                break;
            case 'price_desc':
                $query->orderByDesc('online_price')->orderByDesc('id');
                break;
            case 'name':
                $query->orderBy('name');
                break;
            default:
                $query->orderByDesc('id');
        }

        $perPage = max(1, min(48, (int) $request->query('per_page', 12)));

        $page = $query->paginate($perPage);

        return response()->json(Present::page($page, function ($product) {
            return Present::product($product);
        }));
    }

    public function show($slug)
    {
        $product = Product::published()
            ->with(['category', 'images'])
            ->where('slug', $slug)
            ->firstOrFail();

        $related = Product::forShop()
            ->with('category')
            ->where('id', '!=', $product->id)
            ->when($product->category_id, function ($q) use ($product) {
                $q->where('category_id', $product->category_id);
            })
            ->orderByDesc('id')
            ->limit(4)
            ->get()
            ->map(function ($item) {
                return Present::product($item);
            })
            ->values();

        return response()->json([
            'product' => Present::product($product, true),
            'related' => $related,
        ]);
    }

    // Fresh price and stock for what is sitting in a customer's cart
    public function cart(Request $request)
    {
        $ids = collect(explode(',', (string) $request->query('ids')))
            ->map(function ($id) {
                return (int) $id;
            })
            ->filter()
            ->unique()
            ->take(50)
            ->values()
            ->all();

        $products = Product::published()
            ->with('category')
            ->whereIn('id', $ids)
            ->get()
            ->map(function ($product) {
                return Present::product($product);
            })
            ->values();

        return response()->json($products);
    }
}
