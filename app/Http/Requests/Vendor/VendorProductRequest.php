<?php

namespace App\Http\Requests\Vendor;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Vendor product create/update validation.
 *
 * Authorization uses ProductPolicy ownership. vendor_id is never accepted
 * from the request — controllers assign it from the authenticated vendor.
 */
class VendorProductRequest extends FormRequest
{
    /**
     * Vendor with a store may create; updates require ownership policy.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user || ! $user->isVendor() || ! $user->vendor) {
            return false;
        }

        $product = $this->route('product');

        if ($product instanceof Product) {
            return $user->can('update', $product);
        }

        return $user->can('create', Product::class);
    }

    /**
     * Catalog fields a vendor may submit. No vendor_id / ownership fields.
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
            'category_id' => 'nullable|exists:categories,id',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:2048',
        ];
    }
}
