<?php

/**
 * |--------------------------------------------------------------------------
 * | Authentication
 * |--------------------------------------------------------------------------
 * | Session-based login/register/logout. Login/register POSTs are throttled
 * | at the route layer. Admins land on admin.dashboard after successful login.
 */

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Customer/admin session authentication flows.
 *
 * @package App\Http\Controllers
 */
class AuthController extends Controller
{
    /**
     * Display the login form.
     */
    public function showLogin(): View
    {
        return view('login');
    }

    /**
     * Attempt credentials, regenerate session (session-fixation mitigation), redirect by role.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            $user = Auth::user();
            if ($user && $user->role === 'admin') {
                return redirect()->route('admin.dashboard');
            }

            return redirect()->intended(route('home'));
        }

        return back()->withErrors([
            'email' => 'Invalid login details',
        ]);
    }

    /**
     * Display the registration form.
     */
    public function showRegister(): View
    {
        return view('register');
    }

    /**
     * Create a customer account (role forced to customer — privilege escalation guard).
     */
    public function register(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'customer',
        ]);

        return redirect('/login')
            ->with('success', 'Account created successfully. Please login.');
    }

    /**
     * Log out, invalidate session, and rotate CSRF token.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
