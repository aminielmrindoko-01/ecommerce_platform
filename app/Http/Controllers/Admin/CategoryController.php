<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\Catalog\CategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class CategoryController extends Controller
{
    public function __construct(
        protected CategoryService $categories,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', Category::class);

        $roots = Category::query()
            ->with(['children.children', 'products'])
            ->withCount('products')
            ->roots()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $flat = Category::query()->orderBy('name')->get();

        return view('admin.categories.index', compact('roots', 'flat'));
    }

    public function create(): View
    {
        $this->authorize('create', Category::class);

        return view('admin.categories.create', [
            'parents' => Category::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        try {
            $this->categories->create($request->validated(), $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['category' => $e->getMessage()]);
        }

        return redirect()->route('admin.categories.index')->with('success', 'Category created.');
    }

    public function edit(Category $category): View
    {
        $this->authorize('update', $category);

        return view('admin.categories.edit', [
            'category' => $category,
            'parents' => Category::query()->where('id', '!=', $category->id)->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        try {
            $this->categories->update($category, $request->validated(), $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['category' => $e->getMessage()]);
        }

        return redirect()->route('admin.categories.index')->with('success', 'Category updated.');
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        try {
            $reassign = $request->input('reassign_to');
            $this->categories->delete(
                $category,
                $request->user(),
                $reassign !== null && $reassign !== '' ? (int) $reassign : null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.categories.index')->with('success', 'Category deleted.');
    }
}
