<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Services\Security\MfaService;
use App\Services\Security\StepUpAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class MfaController extends Controller
{
    public function challengeForm(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('mfa.pending_user_id')) {
            return redirect()->route('login');
        }

        return view('security.mfa-challenge');
    }

    public function challenge(Request $request, MfaService $mfa): RedirectResponse
    {
        $userId = $request->session()->get('mfa.pending_user_id');
        $user = \App\Models\User::query()->find($userId);
        if (! $user) {
            $request->session()->forget('mfa.pending_user_id');

            return redirect()->route('login')->withErrors(['email' => 'Session expired. Please sign in again.']);
        }

        $data = $request->validate(['code' => 'required|string|max:64']);

        if (! $mfa->verifyLogin($user, $data['code'])) {
            return back()->withErrors(['code' => 'Invalid authentication code.']);
        }

        auth()->login($user, (bool) $request->session()->pull('mfa.remember', false));
        $request->session()->forget('mfa.pending_user_id');
        $request->session()->regenerate();

        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }
        if ($user->isVendor() && $user->vendor) {
            return redirect()->route('vendor.dashboard');
        }

        return redirect()->intended(route('home'));
    }

    public function enrollForm(Request $request, MfaService $mfa): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->isActiveAccount(), 403);

        if ($user->hasMfaEnabled()) {
            return redirect()->route('account.security')->with('success', 'MFA is already enabled.');
        }

        $enrollment = $mfa->beginEnrollment($user);
        $request->session()->put('mfa.enrollment_started', true);

        return view('security.mfa-enroll', [
            'otpauthUri' => $enrollment['otpauth_uri'],
            'secret' => $enrollment['secret'],
            'recoveryCodes' => $enrollment['recovery_codes'],
        ]);
    }

    public function enrollConfirm(Request $request, MfaService $mfa, StepUpAuthService $stepUp): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->isActiveAccount(), 403);

        $data = $request->validate(['code' => 'required|string']);

        try {
            $mfa->confirmEnrollment($user, $data['code']);
            session([StepUpAuthService::SESSION_KEY => now()->timestamp]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        $request->session()->forget('mfa.enrollment_started');

        return redirect()->route('account.security')->with('success', 'MFA enabled. Store your recovery codes securely.');
    }

    public function disable(Request $request, MfaService $mfa, StepUpAuthService $stepUp): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->hasMfaEnabled(), 403);

        $data = $request->validate([
            'code' => 'required|string',
            'password' => 'required|string',
        ]);

        try {
            $stepUp->confirm($user, $data['password']);
            $mfa->disable($user, $data['code'], true);
        } catch (InvalidArgumentException|\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        return back()->with('success', 'MFA disabled.');
    }
}
