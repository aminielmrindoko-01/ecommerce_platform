<?php

/**
 * |--------------------------------------------------------------------------
 * | Product create/update validation
 * |--------------------------------------------------------------------------
 * | Authorization: admins only (routes also use auth + admin middleware).
 * | Image uploads capped at 2MB; vendor_id must exist.
 */

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request rules for ProductController store/update.
 *
 * @package App\Http\Requests
 */
class ProductRequest extends FormRequest
{
    /**
     * Only admins may create or update products.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user || ! $user->isAdmin()) {
            return false;
        }

        $productId = $this->route('id');

        if ($productId !== null) {
            $product = Product::find($productId);

            return $product !== null && $user->can('update', $product);
        }

        return $user->can('create', Product::class);
    }

    /**
     * Validation rules for product fields and optional image upload.
     *
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'brand' => 'nullable|string|max:80',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'vendor_id' => 'required|exists:vendors,id',
            'category_id' => 'nullable|exists:categories,id',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:2048',
        ];
    }
}
