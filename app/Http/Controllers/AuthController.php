<?php

/**
 * |--------------------------------------------------------------------------
 * | Authentication
 * |--------------------------------------------------------------------------
 * | Session-based login/register/logout with MFA challenge for privileged users.
 */

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Authorization\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Authorization\RoleAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
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

            // Materialize RBAC roles from legacy identity before permission checks.
            if ($user) {
                app(PermissionResolver::class)->materializeLegacyRole($user);
                $user->load('roles');
            }

            // Privileged accounts with MFA enabled must complete TOTP before session is trusted.
            if ($user && $user->hasMfaEnabled()) {
                Auth::logout();
                $request->session()->put('mfa.pending_user_id', $user->id);
                $request->session()->put('mfa.remember', $request->boolean('remember'));
                $request->session()->regenerate();

                return redirect()->route('security.mfa.challenge');
            }

            $request->session()->regenerate();

            app(AuditLogger::class)->log(
                action: 'LOGIN_SUCCESS',
                actor: $user,
                resourceType: 'user',
                resourceId: $user?->id,
                category: 'security',
            );

            if ($user
                && $user->requiresMfaEnrollment()
                && ! $user->hasMfaEnabled()
                && (bool) config('authorization.mfa.enforce_enrollment', false)) {
                return redirect()->route('security.mfa.enroll')
                    ->with('error', 'Multi-factor authentication enrollment is required for this account.');
            }

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

    public function showRegister(): View
    {
        return view('register');
    }

    public function register(Request $request, RoleAssignmentService $roles): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Never accept role/permissions from the client.
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
        ]);
        $user->forceFill(['role' => 'customer', 'is_active' => true])->save();
        $roles->ensureLegacyBridge($user);

        return redirect('/login')
            ->with('success', 'Account created successfully. Please login.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
