<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restrict by RBAC role names (not legacy users.role).
 *
 * Prefer `permission:` middleware for new routes. This alias remains for
 * rare role-membership checks and fail-closes when no match.
 */
class RoleMiddleware
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isActiveAccount()) {
            abort(403, 'Unauthorized access');
        }

        if ($roles === []) {
            abort(403, 'Unauthorized access');
        }

        $needed = [];
        foreach ($roles as $role) {
            foreach (explode(',', $role) as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $needed[] = $part;
                }
            }
        }

        $assigned = $user->roleNames();
        if (count(array_intersect($needed, $assigned)) === 0) {
            abort(403, 'Unauthorized access');
        }

        return $next($request);
    }
}
