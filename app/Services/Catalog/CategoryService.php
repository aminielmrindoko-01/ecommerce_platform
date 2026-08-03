<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\User;
use App\Services\Authorization\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Category hierarchy management with cycle prevention.
 */
class CategoryService
{
    public const MAX_DEPTH = 4;

    public function __construct(
        protected AuditLogger $audit,
    ) {}

    /**
     * @param  array{name:string,slug?:?string,description?:?string,parent_id?:?int,sort_order?:int,is_active?:bool,icon?:?string,image?:?string}  $data
     */
    public function create(array $data, ?User $actor = null): Category
    {
        return DB::transaction(function () use ($data, $actor) {
            $parentId = isset($data['parent_id']) ? (int) $data['parent_id'] : null;
            if ($parentId) {
                $this->assertValidParent(null, $parentId);
            }

            $slug = $this->uniqueSlug($data['slug'] ?? $data['name']);

            $category = Category::query()->create([
                'name' => $data['name'],
                'slug' => $slug,
                'description' => $data['description'] ?? null,
                'parent_id' => $parentId ?: null,
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'is_active' => (bool) ($data['is_active'] ?? true),
                'icon' => $data['icon'] ?? null,
                'image' => $data['image'] ?? null,
            ]);

            $this->audit->log(
                action: 'CATEGORY_CREATED',
                actor: $actor,
                resourceType: 'category',
                resourceId: $category->id,
                newValues: $category->only(['name', 'slug', 'parent_id', 'is_active', 'sort_order']),
            );

            return $category;
        });
    }

    /**
     * @param  array{name?:string,slug?:?string,description?:?string,parent_id?:?int,sort_order?:int,is_active?:bool,icon?:?string,image?:?string}  $data
     */
    public function update(Category $category, array $data, ?User $actor = null): Category
    {
        return DB::transaction(function () use ($category, $data, $actor) {
            $before = $category->only(['name', 'slug', 'parent_id', 'is_active', 'sort_order', 'description']);

            if (array_key_exists('parent_id', $data)) {
                $parentId = $data['parent_id'] !== null && $data['parent_id'] !== ''
                    ? (int) $data['parent_id']
                    : null;
                if ($parentId) {
                    $this->assertValidParent($category->id, $parentId);
                }
                $category->parent_id = $parentId;
            }

            if (isset($data['name'])) {
                $category->name = $data['name'];
            }
            if (array_key_exists('slug', $data) && filled($data['slug'])) {
                $category->slug = $this->uniqueSlug((string) $data['slug'], $category->id);
            }
            if (array_key_exists('description', $data)) {
                $category->description = $data['description'];
            }
            if (array_key_exists('sort_order', $data)) {
                $category->sort_order = (int) $data['sort_order'];
            }
            if (array_key_exists('is_active', $data)) {
                $category->is_active = (bool) $data['is_active'];
            }
            if (array_key_exists('icon', $data)) {
                $category->icon = $data['icon'];
            }
            if (array_key_exists('image', $data) && $data['image'] !== null) {
                $category->image = $data['image'];
            }

            $category->save();

            $this->audit->log(
                action: 'CATEGORY_UPDATED',
                actor: $actor,
                resourceType: 'category',
                resourceId: $category->id,
                oldValues: $before,
                newValues: $category->only(['name', 'slug', 'parent_id', 'is_active', 'sort_order', 'description']),
            );

            return $category->fresh();
        });
    }

    public function delete(Category $category, ?User $actor = null, ?int $reassignTo = null): void
    {
        DB::transaction(function () use ($category, $actor, $reassignTo) {
            if ($category->children()->exists()) {
                throw new InvalidArgumentException('Cannot delete a category that has child categories. Move or delete children first.');
            }

            $productCount = $category->products()->count();
            if ($productCount > 0) {
                if (! $reassignTo) {
                    throw new InvalidArgumentException('Category has products. Provide a reassignment category or move products first.');
                }
                if ((int) $reassignTo === (int) $category->id) {
                    throw new InvalidArgumentException('Cannot reassign products to the same category.');
                }
                if (! Category::query()->whereKey($reassignTo)->exists()) {
                    throw new InvalidArgumentException('Reassignment category not found.');
                }
                $category->products()->update(['category_id' => $reassignTo]);
            }

            $id = $category->id;
            $snapshot = $category->only(['name', 'slug', 'parent_id']);
            $category->delete();

            $this->audit->log(
                action: 'CATEGORY_DELETED',
                actor: $actor,
                resourceType: 'category',
                resourceId: $id,
                oldValues: $snapshot,
                newValues: ['reassigned_to' => $reassignTo],
            );
        });
    }

    protected function assertValidParent(?int $categoryId, int $parentId): void
    {
        if ($categoryId !== null && $categoryId === $parentId) {
            throw new InvalidArgumentException('A category cannot be its own parent.');
        }

        $parent = Category::query()->find($parentId);
        if (! $parent) {
            throw new InvalidArgumentException('Parent category not found.');
        }

        // Walk ancestors to detect cycles and enforce max depth.
        $depth = 1;
        $cursor = $parent;
        $seen = [$parentId => true];
        while ($cursor->parent_id) {
            $nextId = (int) $cursor->parent_id;
            if ($categoryId !== null && $nextId === $categoryId) {
                throw new InvalidArgumentException('Circular category hierarchy is not allowed.');
            }
            if (isset($seen[$nextId])) {
                throw new InvalidArgumentException('Circular category hierarchy is not allowed.');
            }
            $seen[$nextId] = true;
            $depth++;
            if ($depth >= self::MAX_DEPTH) {
                throw new InvalidArgumentException('Category hierarchy cannot exceed '.self::MAX_DEPTH.' levels.');
            }
            $cursor = Category::query()->find($nextId);
            if (! $cursor) {
                break;
            }
        }
    }

    protected function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: 'category';
        $slug = $base;
        $i = 1;
        while (
            Category::query()
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
