<?php

namespace App\Http\Middleware;

use App\Services\Authorization\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin shell access via admin.access permission (deny by default).
 * Optionally requires MFA enrollment for privileged roles.
 */
class AdminMiddleware
{
    public function __construct(
        protected AuditLogger $audit,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        if (! $user->isActiveAccount() || ! $user->hasPermission('admin.access')) {
            $this->audit->permissionDenied($user, 'admin.access');
            abort(403, 'Unauthorized access');
        }

        if ((bool) config('authorization.mfa.enforce_enrollment', false)
            && $user->requiresMfaEnrollment()
            && ! $user->hasMfaEnabled()
            && ! $request->routeIs('security.mfa.*')) {
            $this->audit->security('MFA_ENROLLMENT_REQUIRED', $user, 'medium', [
                'path' => $request->path(),
            ]);

            return redirect()->route('security.mfa.enroll')
                ->with('error', 'Enable multi-factor authentication to continue.');
        }

        return $next($request);
    }
}
