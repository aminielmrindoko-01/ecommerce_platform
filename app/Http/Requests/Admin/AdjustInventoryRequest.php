<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class AdjustInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');
        if (! $product instanceof Product) {
            $product = Product::query()->find($this->route('product'));
        }

        return $product instanceof Product && (bool) $this->user()?->can('adjustInventory', $product);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'delta' => 'required|integer|not_in:0',
            'reason' => 'required|string|min:3|max:500',
            'type' => 'nullable|in:adjustment,damage,return',
        ];
    }
}
