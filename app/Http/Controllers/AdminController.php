<?php

/**
 * |--------------------------------------------------------------------------
 * | Admin operations console
 * |--------------------------------------------------------------------------
 * | Dashboard aggregates and admin management screens.
 * | Routes require auth + admin.access + module permissions.
 */

namespace App\Http\Controllers;

use App\Http\Requests\Admin\UpdateOrderItemFulfillmentRequest;
use App\Http\Requests\Admin\UpdateOrderPaymentRequest;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\Role;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Authorization\AuditLogger;
use App\Services\Authorization\RoleAssignmentService;
use App\Services\OrderFulfillmentSummary;
use App\Services\OrderItemFulfillmentService;
use App\Services\PaymentGatewayManager;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;

class AdminController extends Controller
{
    public function __construct(
        protected AuditLogger $audit,
    ) {}

    public function dashboard(): View
    {
        $totalUsers = User::count();
        $totalProducts = Product::count();
        $activeProducts = Product::query()->where('status', Product::STATUS_PUBLISHED)->count();
        $pendingProducts = Product::query()->where('status', Product::STATUS_PENDING_REVIEW)->count();
        $totalCategories = Category::count();
        $totalVendors = Vendor::count();
        $totalOrders = Order::count();
        $totalCustomers = User::query()->where('role', 'customer')->count();

        $revenue = Order::whereIn('status', [
            'paid',
            'shipped',
            'completed',
        ])->sum('total_price');

        $pendingOrders = Order::where('status', 'pending')->count();
        $lowStock = Product::query()
            ->where('stock', '>', 0)
            ->whereColumn('stock', '<=', 'reorder_level')
            ->count();
        $outOfStock = Product::query()->where('stock', '<=', 0)->count();
        $avgRating = round((float) Product::avg('rating_avg'), 2);

        $recentProducts = Product::with('vendor')->latest()->take(5)->get();
        $recentOrders = Order::with('user')->latest()->take(5)->get();

        $ordersByStatus = Order::select(
            'status',
            DB::raw('COUNT(*) as total')
        )
            ->groupBy('status')
            ->pluck('total', 'status');

        $salesLast7 = Order::select(
            DB::raw('DATE(created_at) as day'),
            DB::raw('SUM(total_price) as total')
        )
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day');

        $chartLabels = collect(range(6, 0))
            ->map(fn ($i) => now()->subDays($i)->format('Y-m-d'));

        $chartData = $chartLabels
            ->map(fn ($day) => (float) ($salesLast7[$day] ?? 0));

        return view('admin.dashboard', compact(
            'totalUsers',
            'totalProducts',
            'activeProducts',
            'pendingProducts',
            'totalCategories',
            'totalCustomers',
            'totalVendors',
            'totalOrders',
            'revenue',
            'pendingOrders',
            'lowStock',
            'outOfStock',
            'avgRating',
            'recentProducts',
            'recentOrders',
            'ordersByStatus',
            'chartLabels',
            'chartData'
        ));
    }

    public function vendors(): View
    {
        $vendors = Vendor::withCount('products')
            ->latest()
            ->get();

        return view('admin.vendors', compact('vendors'));
    }

    public function toggleVendorVerification($id): RedirectResponse
    {
        $vendor = Vendor::findOrFail($id);
        $actor = auth()->user();
        $before = (bool) $vendor->is_verified;
        $willApprove = ! $before;

        // Middleware ORs approve|suspend for route entry; enforce direction here.
        if ($willApprove) {
            abort_unless($actor?->hasPermission('vendors.approve'), 403);
        } else {
            abort_unless($actor?->hasPermission('vendors.suspend'), 403);
        }

        $vendor->is_verified = $willApprove;
        $vendor->save();

        $this->audit->log(
            action: $vendor->is_verified ? 'VENDOR_APPROVED' : 'VENDOR_SUSPENDED',
            actor: $actor,
            resourceType: 'vendor',
            resourceId: $vendor->id,
            oldValues: ['is_verified' => $before],
            newValues: ['is_verified' => $vendor->is_verified],
            category: 'security',
        );

        return redirect()
            ->route('admin.vendors')
            ->with(
                'success',
                $vendor->is_verified
                    ? 'Vendor verified.'
                    : 'Vendor verification removed.'
            );
    }

    public function users(): View
    {
        $users = User::with('roles')->latest()->paginate(20);
        $assignableRoles = Role::query()->orderBy('display_name')->get();

        return view('admin.users', compact('users', 'assignableRoles'));
    }

    public function updateUserRole(
        Request $request,
        $id,
        RoleAssignmentService $roles,
    ): RedirectResponse {
        $user = User::findOrFail($id);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot change your own role.');
        }

        $validated = $request->validate([
            'role' => 'required|in:admin,vendor,customer',
            'rbac_role' => 'nullable|string|max:64',
        ]);

        try {
            $rbacRole = $validated['rbac_role'] ?? null;
            if (! $rbacRole) {
                $rbacRole = (string) (config('authorization.legacy_role_map.'.$validated['role']) ?? 'customer');
            }

            // Ordinary admins cannot assign super_admin via free-text.
            if ($rbacRole === 'super_admin' && ! auth()->user()?->isSuperAdmin()) {
                return back()->with('error', 'Only a Super Admin can assign the Super Admin role.');
            }

            $roles->syncRoles(auth()->user(), $user, [$rbacRole], $validated['role']);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'User role updated successfully.');
    }

    public function orders(
        OrderFulfillmentSummary $summary,
        OrderItemFulfillmentService $fulfillment,
        PaymentService $payments,
        PaymentGatewayManager $gateways
    ): View {
        $orders = Order::with([
            'user',
            'items.product.vendor',
            'latestPaymentTransaction',
        ])
            ->latest()
            ->paginate(20);

        $orders->getCollection()->transform(function (Order $order) use ($summary, $fulfillment, $payments, $gateways) {
            $order->setAttribute('fulfillment_summary', $summary->summarize($order));
            $order->setAttribute('fulfillment_summary_label', $summary->label($order->fulfillment_summary));

            $allowedByItem = [];
            foreach ($order->items as $item) {
                $allowedByItem[$item->id] = $fulfillment->allowedTransitions($item, 'admin');
            }
            $order->setAttribute('admin_allowed_by_item', $allowedByItem);

            $paymentStatus = $order->payment_status ?: 'pending';
            $order->setAttribute('admin_allowed_payments', $payments->allowedTransitions($paymentStatus));

            $tx = $order->latestPaymentTransaction;
            if ($tx) {
                $order->setAttribute('admin_payment_init', $gateways->initialize($order, $tx)->toArray());
            } else {
                $method = (string) ($order->payment_method ?: 'unknown');
                $label = (string) config("payments.methods.{$method}.label", $method);
                $order->setAttribute('admin_payment_init', [
                    'status' => 'coming_soon',
                    'provider' => 'stub',
                    'method_key' => $method,
                    'method_label' => $label,
                    'headline' => 'Payment Service Coming Soon',
                    'message' => 'Online payment is currently unavailable. No payment has been charged.',
                    'metadata' => [],
                ]);
            }
            $order->setAttribute('admin_payment_gateway_label', $gateways->activeGatewayDisplayName());

            return $order;
        });

        $activePaymentGateway = $gateways->activeGatewayDisplayName();

        return view('admin.orders', compact('orders', 'activePaymentGateway'));
    }

    public function updateOrderPayment(
        UpdateOrderPaymentRequest $request,
        Order $order,
        PaymentService $payments
    ): RedirectResponse {
        try {
            $payments->transitionOrderPayment(
                $order,
                $request->validated('payment_status'),
                $request->user(),
                $request->validated('reason'),
                'manual',
                $request->validated('provider_reference')
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->audit->log(
            action: 'ORDER_PAYMENT_UPDATED',
            actor: $request->user(),
            resourceType: 'order',
            resourceId: $order->id,
            newValues: ['payment_status' => $request->validated('payment_status')],
            reason: $request->validated('reason'),
        );

        return back()->with('success', 'Payment status updated.');
    }

    public function updateOrderStatus(Request $request, $id): RedirectResponse
    {
        $order = Order::findOrFail($id);

        $request->validate([
            'status' => 'required|in:pending,shipped,completed',
        ]);

        $before = $order->status;
        $order->status = $request->status;
        $order->save();

        $this->audit->log(
            action: 'ORDER_STATUS_CHANGED',
            actor: auth()->user(),
            resourceType: 'order',
            resourceId: $order->id,
            oldValues: ['status' => $before],
            newValues: ['status' => $order->status],
        );

        return back()->with('success', 'Order status updated successfully.');
    }

    public function updateItemFulfillment(
        UpdateOrderItemFulfillmentRequest $request,
        Order $order,
        OrderItem $orderItem,
        OrderItemFulfillmentService $fulfillment
    ): RedirectResponse {
        abort_unless((int) $orderItem->order_id === (int) $order->id, 404);

        $this->authorize('updateFulfillment', $orderItem);

        try {
            $fulfillment->transition(
                $orderItem,
                $request->validated('fulfillment_status'),
                $request->user(),
                'admin',
                $request->validated('reason')
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Item fulfillment updated.');
    }

    public function coupons(): View
    {
        $coupons = Coupon::latest()->get();

        return view('admin.coupons', compact('coupons'));
    }

    public function reviews(): View
    {
        $reviews = Review::with(['product', 'user', 'moderator'])
            ->latest()
            ->paginate(20);

        return view('admin.reviews', compact('reviews'));
    }

    public function moderateReview(Request $request, Review $review): RedirectResponse
    {
        $this->authorize('moderate', $review);

        $validated = $request->validate([
            'status' => 'required|in:PENDING,APPROVED,REJECTED,HIDDEN,FLAGGED',
            'moderation_reason' => 'nullable|string|max:500',
        ]);

        $permissionMap = [
            'APPROVED' => 'reviews.approve',
            'REJECTED' => 'reviews.reject',
            'HIDDEN' => 'reviews.hide',
            'FLAGGED' => 'reviews.flag',
            'PENDING' => 'reviews.restore',
        ];

        $needed = $permissionMap[$validated['status']] ?? 'reviews.moderate';
        abort_unless($request->user()->hasPermission($needed) || $request->user()->hasPermission('reviews.moderate'), 403);

        $before = $review->status;
        // Never overwrite customer content fields here.
        $review->forceFill([
            'status' => $validated['status'],
            'moderation_reason' => $validated['moderation_reason'] ?? null,
            'moderated_at' => now(),
            'moderated_by' => $request->user()->id,
        ])->save();

        $this->audit->log(
            action: 'REVIEW_'.$validated['status'],
            actor: $request->user(),
            resourceType: 'review',
            resourceId: $review->id,
            oldValues: ['status' => $before],
            newValues: ['status' => $validated['status']],
            reason: $validated['moderation_reason'] ?? null,
        );

        return back()->with('success', 'Review moderation updated.');
    }

    public function auditLogs(): View
    {
        $logs = AuditLog::with('actor')
            ->orderByDesc('id')
            ->paginate(40);

        return view('admin.audit-logs', compact('logs'));
    }

    public function securityEvents(): View
    {
        $events = SecurityEvent::with('actor')
            ->orderByDesc('id')
            ->paginate(40);

        return view('admin.security-events', compact('events'));
    }

    public function roles(): View
    {
        $roles = Role::withCount(['permissions', 'users'])
            ->orderBy('display_name')
            ->get();

        return view('admin.roles', compact('roles'));
    }
}
