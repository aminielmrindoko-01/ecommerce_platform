<?php

/**
 * |--------------------------------------------------------------------------
 * | Admin operations console
 * |--------------------------------------------------------------------------
 * | All actions require auth + AdminMiddleware. Dashboard aggregates use
 * | grouped queries for charts; list pages paginate where volume matters.
 */

namespace App\Http\Controllers;

use App\Http\Middleware\AdminMiddleware;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Admin dashboard KPIs and CRUD-ish management screens.
 *
 * @package App\Http\Controllers
 */
class AdminController extends Controller
{
    /**
     * Apply auth + admin role middleware to every action on this controller.
     */
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(AdminMiddleware::class);
    }

    /**
     * KPI cards, recent lists, order status breakdown, and 7-day sales chart series.
     *
     * Revenue counts only paid/shipped/completed orders (pending excluded).
     */
    public function dashboard(): View
    {
        $totalUsers = User::count();
        $totalProducts = Product::count();
        $totalVendors = Vendor::count();
        $totalOrders = Order::count();
        $revenue = Order::whereIn('status', ['paid', 'shipped', 'completed'])->sum('total_price');
        $pendingOrders = Order::where('status', 'pending')->count();
        $lowStock = Product::where('stock', '<', 10)->count();
        $avgRating = round((float) Product::avg('rating_avg'), 2);

        $recentProducts = Product::with('vendor')->latest()->take(5)->get();
        $recentOrders = Order::with('user')->latest()->take(5)->get();

        $ordersByStatus = Order::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $salesLast7 = Order::select(DB::raw('DATE(created_at) as day'), DB::raw('SUM(total_price) as total'))
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day');

        // Fill missing calendar days with 0 so the chart has a continuous X axis.
        $chartLabels = collect(range(6, 0))->map(fn ($i) => now()->subDays($i)->format('Y-m-d'));
        $chartData = $chartLabels->map(fn ($day) => (float) ($salesLast7[$day] ?? 0));

        return view('admin.dashboard', compact(
            'totalUsers',
            'totalProducts',
            'totalVendors',
            'totalOrders',
            'revenue',
            'pendingOrders',
            'lowStock',
            'avgRating',
            'recentProducts',
            'recentOrders',
            'ordersByStatus',
            'chartLabels',
            'chartData'
        ));
    }

    /**
     * Paginated product catalog for admins (with vendor + category).
     */
    public function products(): View
    {
        $products = Product::with(['vendor', 'category'])->latest()->paginate(20);

        return view('admin.products', compact('products'));
    }

    /**
     * Permanently delete a product by id.
     *
     * @param  int|string  $id
     */
    public function destroyProduct($id): RedirectResponse
    {
        Product::findOrFail($id)->delete();

        return redirect()->route('admin.products')->with('success', 'Product removed successfully.');
    }

    /**
     * Vendor directory with product counts.
     */
    public function vendors(): View
    {
        $vendors = Vendor::withCount('products')->latest()->get();

        return view('admin.vendors', compact('vendors'));
    }

    /**
     * Flip vendor `is_verified` flag (trust badge in shop UI).
     *
     * @param  int|string  $id
     */
    public function toggleVendorVerification($id): RedirectResponse
    {
        $vendor = Vendor::findOrFail($id);
        $vendor->is_verified = ! $vendor->is_verified;
        $vendor->save();

        $message = $vendor->is_verified ? 'Vendor verified.' : 'Vendor verification removed.';

        return redirect()->route('admin.vendors')->with('success', $message);
    }

    /**
     * Paginated user list for role management.
     */
    public function users(): View
    {
        $users = User::latest()->paginate(20);

        return view('admin.users', compact('users'));
    }

    /**
     * Update another user's role. Admins cannot change their own role (lockout guard).
     *
     * @param  int|string  $id
     */
    public function updateUserRole(Request $request, $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot change your own role.');
        }

        $request->validate([
            'role' => 'required|in:admin,vendor,customer',
        ]);

        $user->role = $request->role;
        $user->save();

        return back()->with('success', 'User role updated successfully.');
    }

    /**
     * Paginated orders with buyer relation.
     */
    public function orders(): View
    {
        $orders = Order::with('user')->latest()->paginate(20);

        return view('admin.orders', compact('orders'));
    }

    /**
     * Update fulfillment/payment lifecycle status.
     *
     * @param  int|string  $id
     */
    public function updateOrderStatus(Request $request, $id): RedirectResponse
    {
        $order = Order::findOrFail($id);

        $request->validate([
            'status' => 'required|in:pending,paid,shipped,completed',
        ]);

        $order->status = $request->status;
        $order->save();

        return back()->with('success', 'Order status updated successfully.');
    }

    /**
     * Categories ordered by merchandising sort_order.
     */
    public function categories(): View
    {
        $categories = Category::withCount('products')->orderBy('sort_order')->get();

        return view('admin.categories', compact('categories'));
    }

    /**
     * Coupon inventory (read-only list in current UI).
     */
    public function coupons(): View
    {
        $coupons = Coupon::latest()->get();

        return view('admin.coupons', compact('coupons'));
    }

    /**
     * Recent product reviews for moderation overview.
     */
    public function reviews(): View
    {
        $reviews = Review::with(['product', 'user'])->latest()->paginate(20);

        return view('admin.reviews', compact('reviews'));
    }

    /**
     * Stock overview sorted low→high to surface replenishment needs.
     */
    public function inventory(): View
    {
        $products = Product::with('vendor')->orderBy('stock')->paginate(25);

        return view('admin.inventory', compact('products'));
    }
}
