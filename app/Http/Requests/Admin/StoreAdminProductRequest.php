<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use App\Services\Catalog\ProductCatalogService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdminProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Product::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'brand' => 'nullable|string|max:80',
            'sku' => 'nullable|string|max:64',
            'price' => 'required|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'reorder_level' => 'nullable|integer|min:0|max:1000000',
            'description' => 'nullable|string|max:20000',
            'vendor_id' => 'required|exists:vendors,id',
            'category_id' => 'nullable|exists:categories,id',
            'status' => ['nullable', Rule::in(ProductCatalogService::STATUSES)],
            'is_featured' => 'sometimes|boolean',
            'is_new' => 'sometimes|boolean',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:2048',
        ];
    }
}
