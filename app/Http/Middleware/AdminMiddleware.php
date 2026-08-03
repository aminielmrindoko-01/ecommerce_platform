<?php

namespace App\Http\Middleware;

use App\Services\Authorization\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin shell access via admin.access permission (deny by default).
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

        return $next($request);
    }
}
