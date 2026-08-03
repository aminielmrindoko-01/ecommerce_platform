<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Operations\DisputeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class DisputeController extends Controller
{
    public function index(): View
    {
        $disputes = Dispute::query()
            ->with(['order', 'vendor', 'orderItem'])
            ->where('customer_id', auth()->id())
            ->latest()
            ->paginate(15);

        return view('account.disputes.index', compact('disputes'));
    }

    public function show(Dispute $dispute, DisputeService $disputes): View
    {
        try {
            $disputes->assertCanAccess($dispute, auth()->user());
        } catch (InvalidArgumentException) {
            abort(403);
        }
        abort_unless((int) $dispute->customer_id === (int) auth()->id(), 403);
        $dispute->load(['messages.author', 'order', 'vendor', 'orderItem']);

        return view('account.disputes.show', compact('dispute'));
    }

    public function create(Order $order): View
    {
        abort_unless((int) $order->user_id === (int) auth()->id(), 403);
        $order->load(['items.product', 'items.vendor']);

        return view('account.disputes.create', compact('order'));
    }

    public function store(Request $request, Order $order, DisputeService $disputes): RedirectResponse
    {
        abort_unless((int) $order->user_id === (int) auth()->id(), 403);

        $data = $request->validate([
            'order_item_id' => ['nullable', 'integer'],
            'subject' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:5000'],
        ]);

        $item = null;
        if (! empty($data['order_item_id'])) {
            $item = OrderItem::query()->whereKey($data['order_item_id'])->firstOrFail();
            abort_unless((int) $item->order_id === (int) $order->id, 404);
        }

        try {
            $dispute = $disputes->open(
                $order,
                auth()->user(),
                $data['subject'],
                $data['description'],
                $item,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['dispute' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('account.disputes.show', $dispute)
            ->with('success', 'Dispute opened.');
    }

    public function respond(Request $request, Dispute $dispute, DisputeService $disputes): RedirectResponse
    {
        abort_unless((int) $dispute->customer_id === (int) auth()->id(), 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'evidence_ref' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $disputes->respond($dispute, auth()->user(), $data['body'], $data['evidence_ref'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['dispute' => $e->getMessage()]);
        }

        return back()->with('success', 'Response added.');
    }
}
