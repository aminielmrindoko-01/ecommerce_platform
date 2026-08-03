<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentReconciliation;
use App\Models\PaymentRefund;
use App\Models\PaymentTransaction;
use App\Services\Payments\RefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Admin payment / transaction dashboard and refunds.
 */
class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(
            $request->user()?->hasPermission('payments.view')
            || $request->user()?->hasPermission('transactions.view'),
            403
        );

        $query = PaymentTransaction::query()
            ->with(['order.user'])
            ->latest('id');

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }
        if ($provider = $request->string('provider')->toString()) {
            $query->where('provider', $provider);
        }
        if ($search = trim($request->string('q')->toString())) {
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', '%'.$search.'%')
                    ->orWhere('provider_reference', 'like', '%'.$search.'%')
                    ->orWhereHas('order', function ($oq) use ($search) {
                        $oq->where('order_number', 'like', '%'.$search.'%')
                            ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', '%'.$search.'%')
                                ->orWhere('email', 'like', '%'.$search.'%'));
                    });
            });
        }

        $transactions = $query->paginate(20)->withQueryString();

        return view('admin.payments.index', compact('transactions'));
    }

    public function refunds(Request $request): View
    {
        abort_unless(
            $request->user()?->hasPermission('refunds.create')
            || $request->user()?->hasPermission('payments.view'),
            403
        );

        $refunds = PaymentRefund::query()
            ->with(['order.user', 'paymentTransaction', 'actor'])
            ->latest('id')
            ->paginate(20);

        return view('admin.payments.refunds', compact('refunds'));
    }

    public function storeRefund(Request $request, PaymentTransaction $payment, RefundService $refunds): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('refunds.create'), 403);

        $data = $request->validate([
            'amount' => ['required', 'string', 'max:32'],
            'reason' => ['required', 'string', 'max:500'],
            'provider_reference' => ['nullable', 'string', 'max:128'],
        ]);

        foreach (['status', 'currency', 'order_id', 'user_id'] as $forbidden) {
            if ($request->exists($forbidden)) {
                abort(422, 'Invalid refund payload.');
            }
        }

        try {
            $refunds->refund(
                $payment->order()->firstOrFail(),
                $data['amount'],
                $request->user(),
                $data['reason'],
                $data['provider_reference'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Refund completed.');
    }

    public function reconciliations(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('payments.view'), 403);

        $rows = PaymentReconciliation::query()
            ->with(['order', 'paymentTransaction'])
            ->latest('id')
            ->paginate(20);

        return view('admin.payments.reconciliations', compact('rows'));
    }
}
