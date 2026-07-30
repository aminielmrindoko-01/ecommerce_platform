<?php

/**
 * |--------------------------------------------------------------------------
 * | Role middleware (placeholder)
 * |--------------------------------------------------------------------------
 * | Registered for future role-based route constraints. Currently a no-op
 * | pass-through — admin checks use AdminMiddleware instead.
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Intended for role-parameterized authorization; presently does not enforce roles.
 *
 * @package App\Http\Middleware
 */
class RoleMiddleware
{
    /**
     * Pass the request through unchanged.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
}
