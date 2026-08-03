<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdjustInventoryRequest;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Services\Catalog\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class InventoryController extends Controller
{
    public function __construct(
        protected InventoryService $inventory,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('inventory.view'), 403);

        $query = Product::query()->with('vendor')->orderBy('stock');

        if ($search = trim((string) $request->get('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('sku', 'like', '%'.$search.'%');
            });
        }

        if ($request->get('stock') === 'low') {
            $query->whereColumn('stock', '<=', 'reorder_level')->where('stock', '>', 0);
        } elseif ($request->get('stock') === 'out') {
            $query->where('stock', '<=', 0);
        }

        $products = $query->paginate(25)->withQueryString();

        return view('admin.inventory.index', compact('products'));
    }

    public function history(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('inventory.history'), 403);

        $movements = InventoryMovement::query()
            ->with(['product:id,name,sku', 'actor:id,name'])
            ->latest('created_at')
            ->paginate(40);

        return view('admin.inventory.history', compact('movements'));
    }

    public function adjust(AdjustInventoryRequest $request, Product $product): RedirectResponse
    {
        $type = (string) ($request->input('type') ?: 'adjustment');

        try {
            $this->inventory->adjust(
                $product,
                (int) $request->input('delta'),
                (string) $request->input('reason'),
                $request->user(),
                $type,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['delta' => $e->getMessage()]);
        }

        return back()->with('success', 'Inventory adjusted.');
    }
}
