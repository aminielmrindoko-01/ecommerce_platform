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
        return view('security.step-up', [
            'intended' => $request->session()->get('step_up.intended', url()->previous()),
        ]);
    }

    public function confirm(Request $request, StepUpAuthService $stepUp): RedirectResponse
    {
        $data = $request->validate([
            'password' => 'required|string',
            'intended' => 'nullable|string',
        ]);

        $stepUp->confirm($request->user(), $data['password']);

        $intended = $data['intended'] ?: $request->session()->pull('step_up.intended', route('account.security'));

        return redirect()->to($intended)->with('success', 'Identity re-confirmed.');
    }
}
