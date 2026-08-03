<?php

namespace App\Http\Middleware;

use App\Services\Authorization\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Require one or more permissions (OR). Deny by default.
 *
 * Usage: middleware('permission:products.view')
 *        middleware('permission:orders.update,orders.manage_any')
 */
class PermissionMiddleware
{
    public function __construct(
        protected AuditLogger $audit,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        if (! $user->isActiveAccount()) {
            abort(403, 'Account inactive.');
        }

        $needed = [];
        foreach ($permissions as $permission) {
            foreach (explode(',', $permission) as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $needed[] = $part;
                }
            }
        }

        if ($needed === [] || ! $user->hasAnyPermission(...$needed)) {
            $this->audit->permissionDenied($user, implode('|', $needed));
            abort(403, 'Forbidden.');
        }

        return $next($request);
    }
}
