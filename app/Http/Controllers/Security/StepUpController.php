<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Services\Security\StepUpAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StepUpController extends Controller
{
    public function form(Request $request): View
    {
        return view('security.step-up');
    }

    public function confirm(Request $request, StepUpAuthService $stepUp): RedirectResponse
    {
        $data = $request->validate([
            'password' => 'required|string',
        ]);

        $stepUp->confirm($request->user(), $data['password']);

        // Never trust client-supplied redirect targets (open-redirect prevention).
        $intended = $this->safeInternalPath(
            $request->session()->pull('step_up.intended')
        );

        return redirect()->to($intended)->with('success', 'Identity re-confirmed.');
    }

    /**
     * Allow only relative same-origin paths (leading slash, not protocol-relative).
     */
    protected function safeInternalPath(mixed $path): string
    {
        $fallback = route('account.security', absolute: false);

        if (! is_string($path) || $path === '') {
            return $fallback;
        }

        if (! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return $fallback;
        }

        // Block scheme-smuggling and backslash tricks.
        if (preg_match('/[\s\\\\]/', $path) || str_contains($path, '://')) {
            return $fallback;
        }

        return $path;
    }
}
