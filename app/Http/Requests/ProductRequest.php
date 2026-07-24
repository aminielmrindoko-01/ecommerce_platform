<?php

/**
 * |--------------------------------------------------------------------------
 * | Product create/update validation
 * |--------------------------------------------------------------------------
 * | Authorization: any authenticated user may submit (route also requires auth).
 * | Image uploads capped at 2MB; vendor_id must exist.
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request rules for ProductController store/update.
 *
 * @package App\Http\Requests
 */
class ProductRequest extends FormRequest
{
    /**
     * Allow only signed-in users to mutate products via this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
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
            'image' => 'nullable|image|max:2048',
        ];
    }
}
