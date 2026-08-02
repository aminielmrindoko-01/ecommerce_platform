<?php

/**
 * |--------------------------------------------------------------------------
 * | Role middleware
 * |--------------------------------------------------------------------------
 * | Restricts access to users whose `role` matches one of the given roles.
 * | Admin routes prefer the dedicated `admin` alias (AdminMiddleware).
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role-parameterized authorization for route groups.
 *
 * @package App\Http\Middleware
 */
class RoleMiddleware
{
    /**
     * Abort unless the authenticated user has one of the allowed roles.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string  ...$roles
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ($roles !== [] && ! in_array($user->role, $roles, true))) {
            abort(403, 'Unauthorized access');
        }

        return $next($request);
    }
}
