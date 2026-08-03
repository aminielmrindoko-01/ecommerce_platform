<?php

namespace App\Http\Middleware;

use App\Services\Authorization\AuditLogger;
use App\Services\Security\StepUpAuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Require recent password confirmation for sensitive mutations.
 */
class RequireStepUpMiddleware
{
    public function __construct(
        protected StepUpAuthService $stepUp,
        protected AuditLogger $audit,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        if ($this->stepUp->isSatisfied()) {
            return $next($request);
        }

        $this->audit->security('STEP_UP_REQUIRED', $user, 'medium', [
            'path' => $request->path(),
            'method' => $request->method(),
        ]);

        if ($request->expectsJson()) {
            abort(403, 'Recent authentication required for this action.');
        }

        $request->session()->put('step_up.intended', $request->fullUrl());

        return redirect()->route('security.step-up');
    }
}
