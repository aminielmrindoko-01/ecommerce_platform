<?php

namespace App\Services\Catalog;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Authorization\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Product catalog mutations with lifecycle, ownership, inventory, and audit.
 */
class ProductCatalogService
{
    public const STATUSES = [
        'draft',
        'pending_review',
        'published',
        'unpublished',
        'suspended',
        'archived',
    ];

    public function __construct(
        protected AuditLogger $audit,
        protected InventoryService $inventory,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor, ?Vendor $forcedVendor = null): Product
    {
        return DB::transaction(function () use ($data, $actor, $forcedVendor) {
            $vendorId = $forcedVendor?->id ?? (int) ($data['vendor_id'] ?? 0);
            if ($vendorId <= 0 || ! Vendor::query()->whereKey($vendorId)->exists()) {
                throw new InvalidArgumentException('A valid vendor is required.');
            }

            // Vendors cannot assign another store.
            if ($forcedVendor && (int) $vendorId !== (int) $forcedVendor->id) {
                throw new InvalidArgumentException('Unauthorized vendor assignment.');
            }

            $status = $this->normalizeStatus($data['status'] ?? 'draft', $actor, creating: true);
            $stock = max(0, (int) ($data['stock'] ?? 0));

            $product = new Product([
                'category_id' => $data['category_id'] ?? null,
                'name' => $data['name'],
                'slug' => $this->uniqueSlug((string) ($data['slug'] ?? $data['name'])),
                'brand' => $data['brand'] ?? null,
                'price' => $data['price'],
                'compare_at_price' => $data['compare_at_price'] ?? null,
                'description' => $data['description'] ?? null,
                'sku' => $this->normalizeSku($data['sku'] ?? null),
                'specs' => $data['specs'] ?? null,
                'is_featured' => (bool) ($data['is_featured'] ?? false),
                'is_new' => (bool) ($data['is_new'] ?? false),
                'reorder_level' => max(0, (int) ($data['reorder_level'] ?? 5)),
            ]);
            $product->vendor_id = $vendorId;
            $product->stock = 0;
            $product->reserved_quantity = 0;
            $product->status = $status;
            $product->published_at = $status === 'published' ? now() : null;

            if (($data['image'] ?? null) instanceof UploadedFile) {
                $product->image = $this->storeImage($data['image']);
            }

            $product->save();

            if ($stock > 0) {
                $this->inventory->setAvailable(
                    $product,
                    $stock,
                    'Initial stock on product create',
                    $actor,
                    InventoryMovement::TYPE_INITIAL,
                );
            }

            $this->audit->log(
                action: 'PRODUCT_CREATED',
                actor: $actor,
                resourceType: 'product',
                resourceId: $product->id,
                newValues: [
                    'name' => $product->name,
                    'status' => $product->status,
                    'vendor_id' => $product->vendor_id,
                    'sku' => $product->sku,
                    'price' => $product->price,
                ],
            );

            return $product->fresh(['vendor', 'category']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Product $product, array $data, User $actor, bool $allowVendorIdChange = false): Product
    {
        return DB::transaction(function () use ($product, $data, $actor, $allowVendorIdChange) {
            $before = $product->only([
                'name', 'status', 'price', 'category_id', 'sku', 'brand', 'description', 'reorder_level',
            ]);

            if ($allowVendorIdChange && isset($data['vendor_id'])) {
                $vendorId = (int) $data['vendor_id'];
                if (! Vendor::query()->whereKey($vendorId)->exists()) {
                    throw new InvalidArgumentException('Invalid vendor.');
                }
                $product->vendor_id = $vendorId;
            }

            foreach (['name', 'brand', 'description', 'price', 'compare_at_price', 'category_id'] as $field) {
                if (array_key_exists($field, $data)) {
                    $product->{$field} = $data[$field];
                }
            }

            if (array_key_exists('sku', $data)) {
                $product->sku = $this->normalizeSku($data['sku'], $product->id);
            }
            if (array_key_exists('specs', $data)) {
                $product->specs = $data['specs'];
            }
            if (array_key_exists('reorder_level', $data)) {
                $product->reorder_level = max(0, (int) $data['reorder_level']);
            }
            if (array_key_exists('is_featured', $data)) {
                $product->is_featured = (bool) $data['is_featured'];
            }
            if (array_key_exists('is_new', $data)) {
                $product->is_new = (bool) $data['is_new'];
            }
            if (array_key_exists('slug', $data) && filled($data['slug'])) {
                $product->slug = $this->uniqueSlug((string) $data['slug'], $product->id);
            }

            if (($data['image'] ?? null) instanceof UploadedFile) {
                $old = $product->image;
                $product->image = $this->storeImage($data['image']);
                $this->deleteStoredImage($old);
            }

            $product->save();

            // Stock changes require inventory.adjust — handled by caller or explicit stock key + permission.
            if (array_key_exists('stock', $data) && $actor->hasPermission('inventory.adjust')) {
                $this->inventory->setAvailable(
                    $product->fresh(),
                    max(0, (int) $data['stock']),
                    'Stock updated via product edit',
                    $actor,
                );
            }

            $this->audit->log(
                action: 'PRODUCT_UPDATED',
                actor: $actor,
                resourceType: 'product',
                resourceId: $product->id,
                oldValues: $before,
                newValues: $product->fresh()->only([
                    'name', 'status', 'price', 'category_id', 'sku', 'brand', 'description', 'reorder_level',
                ]),
            );

            return $product->fresh(['vendor', 'category']);
        });
    }

    public function publish(Product $product, User $actor): Product
    {
        if (! $actor->hasPermission('products.publish') && ! $actor->hasPermission('products.manage_any')) {
            throw new InvalidArgumentException('Missing publish permission.');
        }

        $before = $product->status;
        $product->status = 'published';
        $product->published_at = $product->published_at ?? now();
        $product->save();

        $this->audit->log(
            action: 'PRODUCT_PUBLISHED',
            actor: $actor,
            resourceType: 'product',
            resourceId: $product->id,
            oldValues: ['status' => $before],
            newValues: ['status' => 'published'],
        );

        return $product->fresh();
    }

    public function unpublish(Product $product, User $actor): Product
    {
        if (! $actor->hasPermission('products.unpublish') && ! $actor->hasPermission('products.manage_any')) {
            throw new InvalidArgumentException('Missing unpublish permission.');
        }

        $before = $product->status;
        $product->status = 'unpublished';
        $product->save();

        $this->audit->log(
            action: 'PRODUCT_UNPUBLISHED',
            actor: $actor,
            resourceType: 'product',
            resourceId: $product->id,
            oldValues: ['status' => $before],
            newValues: ['status' => 'unpublished'],
        );

        return $product->fresh();
    }

    public function setStatus(Product $product, string $status, User $actor): Product
    {
        $status = $this->normalizeStatus($status, $actor, creating: false);
        $before = $product->status;

        if ($status === 'published') {
            return $this->publish($product, $actor);
        }
        if ($status === 'unpublished' && in_array($before, ['published', 'pending_review'], true)) {
            return $this->unpublish($product, $actor);
        }

        $product->status = $status;
        if ($status === 'archived') {
            $product->published_at = $product->published_at;
        }
        $product->save();

        $this->audit->log(
            action: 'PRODUCT_UPDATED',
            actor: $actor,
            resourceType: 'product',
            resourceId: $product->id,
            oldValues: ['status' => $before],
            newValues: ['status' => $status],
            reason: 'status_change',
        );

        return $product->fresh();
    }

    public function archive(Product $product, User $actor): void
    {
        DB::transaction(function () use ($product, $actor) {
            $before = $product->only(['name', 'status']);
            $product->status = 'archived';
            $product->save();
            $product->delete(); // soft delete

            $this->audit->log(
                action: 'PRODUCT_DELETED',
                actor: $actor,
                resourceType: 'product',
                resourceId: $product->id,
                oldValues: $before,
                newValues: ['status' => 'archived', 'soft_deleted' => true],
            );
        });
    }

    protected function normalizeStatus(string $status, User $actor, bool $creating): string
    {
        $status = strtolower(trim($status));
        if (! in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Invalid product status.');
        }

        if ($status === 'published' && ! $actor->hasPermission('products.publish') && ! $actor->hasPermission('products.manage_any')) {
            if ($creating) {
                return 'pending_review';
            }

            throw new InvalidArgumentException('Cannot publish without permission.');
        }

        if (in_array($status, ['suspended', 'archived'], true)
            && ! $actor->hasPermission('products.manage_any')
            && ! $actor->hasPermission('products.delete')) {
            throw new InvalidArgumentException('Cannot set this status.');
        }

        return $status;
    }

    protected function normalizeSku(mixed $sku, ?int $ignoreId = null): ?string
    {
        if ($sku === null || $sku === '') {
            return null;
        }

        $sku = strtoupper(trim((string) $sku));
        if (strlen($sku) > 64) {
            throw new InvalidArgumentException('SKU is too long.');
        }

        $exists = Product::withTrashed()
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('sku', $sku)
            ->exists();

        if ($exists) {
            throw new InvalidArgumentException('SKU must be unique.');
        }

        return $sku;
    }

    protected function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: 'product';
        $slug = $base.'-'.Str::lower(Str::random(5));
        $i = 0;
        while (
            Product::withTrashed()
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $i++;
            $slug = $base.'-'.Str::lower(Str::random(5)).($i > 1 ? '-'.$i : '');
        }

        return $slug;
    }

    protected function storeImage(UploadedFile $file): string
    {
        $mime = $file->getMimeType() ?: '';
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (! in_array($mime, $allowed, true)) {
            throw new InvalidArgumentException('Invalid image type.');
        }

        // Randomized storage name — do not trust client filename.
        return $file->store('products', 'public');
    }

    protected function deleteStoredImage(?string $path): void
    {
        if (! $path || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }
        if (str_starts_with($path, 'products/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
