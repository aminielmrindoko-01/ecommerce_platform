<?php

/**
 * |--------------------------------------------------------------------------
 * | Admin gate
 * |--------------------------------------------------------------------------
 * | Hard-fails non-admin users with HTTP 403. Applied on AdminController
 * | constructor in addition to route-level auth middleware.
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts access to users whose `role` column is exactly `admin`.
 *
 * @package App\Http\Middleware
 */
class AdminMiddleware
{
    /**
     * Abort unless the authenticated user has the admin role.
     *
     * @param  Closure(Request): (Response)  $next
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check() || auth()->user()->role !== 'admin') {
            abort(403, 'Unauthorized access');
        }

        return $next($request);
    }
}
