<?php

namespace App\Http\Controllers;

use App\Http\Middleware\AdminMiddleware;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(AdminMiddleware::class);
    }

    public function dashboard()
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

    public function products()
    {
        $products = Product::with(['vendor', 'category'])->latest()->paginate(20);

        return view('admin.products', compact('products'));
    }

    public function destroyProduct($id)
    {
        Product::findOrFail($id)->delete();

        return redirect()->route('admin.products')->with('success', 'Product removed successfully.');
    }

    public function vendors()
    {
        $vendors = Vendor::withCount('products')->latest()->get();

        return view('admin.vendors', compact('vendors'));
    }

    public function toggleVendorVerification($id)
    {
        $vendor = Vendor::findOrFail($id);
        $vendor->is_verified = ! $vendor->is_verified;
        $vendor->save();

        $message = $vendor->is_verified ? 'Vendor verified.' : 'Vendor verification removed.';

        return redirect()->route('admin.vendors')->with('success', $message);
    }

    public function users()
    {
        $users = User::latest()->paginate(20);

        return view('admin.users', compact('users'));
    }

    public function updateUserRole(Request $request, $id)
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

    public function orders()
    {
        $orders = Order::with('user')->latest()->paginate(20);

        return view('admin.orders', compact('orders'));
    }

    public function updateOrderStatus(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        $request->validate([
            'status' => 'required|in:pending,paid,shipped,completed',
        ]);

        $order->status = $request->status;
        $order->save();

        return back()->with('success', 'Order status updated successfully.');
    }

    public function categories()
    {
        $categories = Category::withCount('products')->orderBy('sort_order')->get();

        return view('admin.categories', compact('categories'));
    }

    public function coupons()
    {
        $coupons = Coupon::latest()->get();

        return view('admin.coupons', compact('coupons'));
    }

    public function reviews()
    {
        $reviews = Review::with(['product', 'user'])->latest()->paginate(20);

        return view('admin.reviews', compact('reviews'));
    }

    public function inventory()
    {
        $products = Product::with('vendor')->orderBy('stock')->paginate(25);

        return view('admin.inventory', compact('products'));
    }
}
