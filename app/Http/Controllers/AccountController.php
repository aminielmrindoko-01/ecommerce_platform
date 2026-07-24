<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Order;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AccountController extends Controller
{
    public function index()
    {
        $user = auth()->user()->loadCount(['orders', 'wishlists', 'addresses']);
        $recentOrders = Order::where('user_id', $user->id)->latest()->take(5)->get();

        return view('account.index', compact('user', 'recentOrders'));
    }

    public function orders()
    {
        $orders = Order::with('items.product')
            ->where('user_id', auth()->id())
            ->latest()
            ->paginate(10);

        return view('account.orders', compact('orders'));
    }

    public function showOrder(Order $order)
    {
        abort_unless($order->user_id === auth()->id(), 403);
        $order->load('items.product');

        return view('account.order-show', compact('order'));
    }

    public function addresses()
    {
        $addresses = Address::where('user_id', auth()->id())->latest()->get();

        return view('account.addresses', compact('addresses'));
    }

    public function storeAddress(Request $request)
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

        Address::create([
            ...$data,
            'user_id' => auth()->id(),
            'country' => 'Tanzania',
            'is_default' => (bool) ($data['is_default'] ?? false),
        ]);

        return back()->with('success', 'Address saved.');
    }

    public function destroyAddress(Address $address)
    {
        abort_unless($address->user_id === auth()->id(), 403);
        $address->delete();

        return back()->with('success', 'Address removed.');
    }

    public function security()
    {
        return view('account.security');
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'phone' => 'nullable|string|max:40',
            'avatar' => 'nullable|image|max:2048',
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

    public function updatePassword(Request $request)
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

    public function notifications()
    {
        $notifications = [
            ['title' => 'Welcome to SANA Market', 'body' => 'Explore deals from verified sellers today.', 'time' => 'Just now'],
            ['title' => 'Flash sale reminder', 'body' => 'Electronics flash sale ends tonight.', 'time' => '2h ago'],
        ];

        return view('account.notifications', compact('notifications'));
    }

    public function wishlist()
    {
        $items = Wishlist::with('product.vendor')
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        return view('account.wishlist', compact('items'));
    }
}
