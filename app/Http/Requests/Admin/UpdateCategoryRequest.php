<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category instanceof Category && (bool) $this->user()?->can('update', $category);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $category = $this->route('category');
        $id = $category instanceof Category ? $category->id : null;

        return [
            'name' => 'required|string|max:120',
            'slug' => ['nullable', 'string', 'max:140', Rule::unique('categories', 'slug')->ignore($id)],
            'description' => 'nullable|string|max:2000',
            'parent_id' => 'nullable|exists:categories,id',
            'sort_order' => 'nullable|integer|min:0|max:99999',
            'is_active' => 'sometimes|boolean',
            'icon' => 'nullable|string|max:40',
        ];
    }
}
