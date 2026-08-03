# Phase 3 — Enterprise Admin Operations (Products / Categories / Inventory)

## Audit summary (pre-implementation)

**Existed:** Product/Category models, `products.stock`, admin read-only lists, shop+vendor product CRUD, ProductPolicy ownership, checkout `lockForUpdate` stock decrement, RBAC permissions for publish/categories/inventory (mostly unused by routes).

**Gaps closed:** product lifecycle status, soft archive, category hierarchy, inventory movement history, inventory adjust UI, audit events, admin search/filter CRUD under `/admin/*`.

**No duplicate catalog/auth systems.** Extended `products.stock` + append-only `inventory_movements`.

## Implementation plan (executed)

1. Migration: status/reorder/reserved/soft deletes, category parent/active, inventory_movements  
2. Services: ProductCatalogService, CategoryService, InventoryService  
3. Admin controllers + FormRequests + policies  
4. Vendor/shop wired through catalog service  
5. Real dashboard metrics  
6. Feature tests + full suite  

## Orders-phase integration point (reservation)

Checkout still decrements `products.stock` directly under row lock (existing behavior).

`InventoryService::reserve()` / `releaseReserved()` are implemented against `reserved_quantity` for the future flow:

Order created → reserve → payment confirm → commit sale → on cancel release reserved.

Do not double-count: when Orders phase adopts reservation, replace raw checkout decrement with these methods and emit movement types `reserve` / `release` / `sale`.

## Permissions used

`products.view|create|update|delete|publish|unpublish|manage_any`  
`categories.view|create|update|delete`  
`inventory.view|adjust|history`

## Audit events wired

`PRODUCT_CREATED`, `PRODUCT_UPDATED`, `PRODUCT_PUBLISHED`, `PRODUCT_UNPUBLISHED`, `PRODUCT_DELETED` (archive/soft-delete),  
`CATEGORY_CREATED`, `CATEGORY_UPDATED`, `CATEGORY_DELETED`,  
`INVENTORY_ADJUSTED`
