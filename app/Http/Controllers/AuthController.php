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
use App\Services\Authorization\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

            if ($user && ! $user->isActiveAccount()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                app(AuditLogger::class)->security('LOGIN_FAILED', $user, 'medium', [
                    'reason' => 'inactive_account',
                    'email' => $credentials['email'],
                ]);

                return back()->withErrors([
                    'email' => 'This account is inactive.',
                ]);
            }

            app(AuditLogger::class)->log(
                action: 'LOGIN_SUCCESS',
                actor: $user,
                resourceType: 'user',
                resourceId: $user?->id,
                category: 'security',
            );

            if ($user && $user->isAdmin()) {
                return redirect()->route('admin.dashboard');
            }

            if ($user && $user->isVendor() && $user->vendor) {
                return redirect()->route('vendor.dashboard');
            }

            return redirect()->intended(route('home'));
        }

        app(AuditLogger::class)->security('LOGIN_FAILED', null, 'low', [
            'email' => $credentials['email'],
        ]);

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
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
        ]);
        $user->forceFill(['role' => 'customer', 'is_active' => true])->save();

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
