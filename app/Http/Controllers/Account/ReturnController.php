<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Services\Operations\ReturnEligibilityService;
use App\Services\Operations\ReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class ReturnController extends Controller
{
    public function index(): View
    {
        $returns = ReturnRequest::query()
            ->with(['items.orderItem', 'order', 'vendor'])
            ->where('customer_id', auth()->id())
            ->latest()
            ->paginate(15);

        return view('account.returns.index', compact('returns'));
    }

    public function show(ReturnRequest $return): View
    {
        abort_unless((int) $return->customer_id === (int) auth()->id(), 403);
        $return->load(['items.orderItem', 'order', 'vendor', 'paymentRefund']);

        return view('account.returns.show', compact('return'));
    }

    public function create(Order $order, OrderItem $item, ReturnEligibilityService $eligibility): View
    {
        abort_unless((int) $order->user_id === (int) auth()->id(), 403);
        abort_unless((int) $item->order_id === (int) $order->id, 404);

        $eval = $eligibility->evaluate($order, $item, auth()->user(), 1);

        return view('account.returns.create', [
            'order' => $order,
            'item' => $item,
            'eligibility' => $eval,
        ]);
    }

    public function store(Request $request, Order $order, OrderItem $item, ReturnService $returns): RedirectResponse
    {
        abort_unless((int) $order->user_id === (int) auth()->id(), 403);
        abort_unless((int) $item->order_id === (int) $order->id, 404);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:1000'],
            'reason_code' => ['nullable', 'string', 'max:40'],
            // Never trust client customer_id / vendor_id / amounts.
        ]);

        try {
            $ret = $returns->request(
                $order,
                $item,
                auth()->user(),
                (int) $data['quantity'],
                $data['reason'],
                $data['reason_code'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['return' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('account.returns.show', $ret)
            ->with('success', 'Return request submitted.');
    }

    public function cancel(ReturnRequest $return, ReturnService $returns): RedirectResponse
    {
        abort_unless((int) $return->customer_id === (int) auth()->id(), 403);

        try {
            $returns->cancel($return, auth()->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['return' => $e->getMessage()]);
        }

        return back()->with('success', 'Return cancelled.');
    }
}
