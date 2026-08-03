<?php

/**
 * |--------------------------------------------------------------------------
 * | Buyer account area
 * |--------------------------------------------------------------------------
 * | Auth-only profile, orders, addresses, security, notifications stub,
 * | and wishlist. Ownership checks use abort_unless on bound models.
 */

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Order;
use App\Models\Wishlist;
use App\Services\OrderFulfillmentSummary;
use App\Services\PaymentGatewayManager;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Authenticated customer account pages and mutations.
 *
 * @package App\Http\Controllers
 */
class AccountController extends Controller
{
    /**
     * Account overview with counts and recent orders.
     */
    public function index(): View
    {
        $user = auth()->user()->loadCount(['orders', 'wishlists', 'addresses']);
        $recentOrders = Order::where('user_id', $user->id)->latest()->take(5)->get();

        return view('account.index', compact('user', 'recentOrders'));
    }

    /**
     * Paginated order history with line items + products (eager-loaded).
     */
    public function orders(): View
    {
        $orders = Order::with('items.product')
            ->where('user_id', auth()->id())
            ->latest()
            ->paginate(10);

        return view('account.orders', compact('orders'));
    }

    /**
     * Single order detail — owner only.
     * Items are grouped by vendor for multi-vendor fulfillment visibility.
     */
    public function showOrder(
        Order $order,
        OrderFulfillmentSummary $summary,
        PaymentGatewayManager $gateways,
        PaymentService $payments
    ): View {
        abort_unless($order->user_id === auth()->id(), 403);
        $this->authorize('view', $order);
        $order->load(['items.product.vendor', 'latestPaymentTransaction']);

        $itemsByVendor = $order->items
            ->groupBy(fn ($item) => $item->product?->vendor_id ?? 0)
            ->map(function ($items) {
                $vendor = $items->first()?->product?->vendor;

                return [
                    'vendor' => $vendor,
                    'store_name' => $vendor?->store_name ?? 'Seller',
                    'items' => $items,
                ];
            })
            ->values();

        $fulfillmentSummary = $summary->summarize($order);
        $fulfillmentSummaryLabel = $summary->label($fulfillmentSummary);

        $transaction = $order->latestPaymentTransaction
            ?? $payments->ensurePendingTransaction($order, 'stub');
        $paymentInit = $gateways->initialize($order, $transaction)->toArray();

        return view('account.order-show', compact(
            'order',
            'itemsByVendor',
            'fulfillmentSummary',
            'fulfillmentSummaryLabel',
            'paymentInit'
        ));
    }

    /**
     * Address book for the current user.
     */
    public function addresses(): View
    {
        $addresses = Address::where('user_id', auth()->id())->latest()->get();

        return view('account.addresses', compact('addresses'));
    }

    /**
     * Create an address; clearing other defaults when is_default is set.
     *
     * Country is currently hardcoded to Tanzania to match checkout persistence.
     */
    public function storeAddress(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => 'required|string|max:40',
            'full_name' => 'required|string|max:120',
            'phone' => 'required|string|max:40',
            'line1' => 'required|string|max:180',
            'line2' => 'nullable|string|max:180',
            'city' => 'required|string|max:80',
            'region' => 'nullable|string|max:80',
            'postal_code' => 'nullable|string|max:20',
            'is_default' => 'nullable|boolean',
        ]);

        if ($data['is_default'] ?? false) {
            Address::where('user_id', auth()->id())->update(['is_default' => false]);
        }

        $address = new Address([
            ...$data,
            'country' => 'Tanzania',
            'is_default' => (bool) ($data['is_default'] ?? false),
        ]);
        $address->user_id = auth()->id();
        $address->save();

        return back()->with('success', 'Address saved.');
    }

    /**
     * Delete an owned address only.
     */
    public function destroyAddress(Address $address): RedirectResponse
    {
        abort_unless($address->user_id === auth()->id(), 403);
        $this->authorize('delete', $address);
        $address->delete();

        return back()->with('success', 'Address removed.');
    }

    /**
     * Password change form.
     */
    public function security(): View
    {
        return view('account.security');
    }

    /**
     * Update profile fields; avatar uploads go to public disk `avatars/`.
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'phone' => 'nullable|string|max:40',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:2048',
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($data);

        return back()->with('success', 'Profile updated.');
    }

    /**
     * Change password after verifying the current password hash.
     *
     * New password is hashed via the User model's `hashed` cast.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        $user = auth()->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->with('error', 'Current password is incorrect.');
        }

        $user->update(['password' => $data['password']]);

        return back()->with('success', 'Password updated.');
    }

    /**
     * Static notification placeholders (no notification store yet).
     */
    /**
     * Real database notifications for the authenticated user only.
     */
    public function notifications(): View
    {
        $user = auth()->user();
        $notifications = $user->notifications()->latest()->paginate(20);
        $unreadCount = $user->unreadNotifications()->count();

        return view('account.notifications', compact('notifications', 'unreadCount'));
    }

    /**
     * Mark one owned notification as read.
     */
    public function markNotificationRead(string $notification): RedirectResponse
    {
        /** @var DatabaseNotification $row */
        $row = auth()->user()
            ->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        $row->markAsRead();

        return back()->with('success', 'Notification marked as read.');
    }

    /**
     * Mark all owned notifications as read.
     */
    public function markAllNotificationsRead(): RedirectResponse
    {
        auth()->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'All notifications marked as read.');
    }

    /**
     * Wishlist products for the signed-in user.
     */
    public function wishlist(): View
    {
        $items = Wishlist::with('product.vendor')
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        return view('account.wishlist', compact('items'));
    }
}
