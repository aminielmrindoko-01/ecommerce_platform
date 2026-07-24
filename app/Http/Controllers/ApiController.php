<?php

/**
 * |--------------------------------------------------------------------------
 * | Lightweight JSON APIs for storefront JS
 * |--------------------------------------------------------------------------
 * | Powers typeahead search and recently-viewed product hydration from
 * | localStorage IDs without full page reloads.
 */

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public JSON endpoints consumed by resources/js/app.js.
 *
 * @package App\Http\Controllers
 */
class ApiController extends Controller
{
    /**
     * Typeahead suggestions for the header search box (min 2 chars, max 8 hits).
     *
     * Ordered by sold_count so popular matches surface first.
     */
    public function searchSuggest(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $products = Product::query()
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('brand', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            })
            ->orderByDesc('sold_count')
            ->take(8)
            ->get(['id', 'name', 'brand', 'price']);

        return response()->json(
            $products->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'brand' => $p->brand,
                'price' => $p->price,
                'url' => route('products.show', $p->id),
            ])
        );
    }

    /**
     * Hydrate recently-viewed cards from a comma-separated id list (max 12).
     *
     * Results are re-ordered to match the client-supplied id sequence.
     */
    public function recentProducts(Request $request): JsonResponse
    {
        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->take(12)
            ->values();

        if ($ids->isEmpty()) {
            return response()->json([]);
        }

        $products = Product::whereIn('id', $ids)->get()->sortBy(fn ($p) => $ids->search($p->id));

        return response()->json(
            $products->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'brand' => $p->brand,
                'price' => $p->price,
                'image' => $p->image_url,
                'url' => route('products.show', $p->id),
            ])->values()
        );
    }
}
