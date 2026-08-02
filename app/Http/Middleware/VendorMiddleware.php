<?php

/**
 * |--------------------------------------------------------------------------
 * | Vendor gate
 * |--------------------------------------------------------------------------
 * | Requires an authenticated vendor role with a linked vendor store.
 * | Admins are not admitted here — they use the admin console.
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts access to users with role=vendor and an owned Vendor record.
 *
 * @package App\Http\Middleware
 */
class VendorMiddleware
{
    /**
     * Abort unless the user is a vendor with a store for ownership checks.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isVendor() || ! $user->vendor) {
            abort(403, 'Unauthorized vendor access');
        }

        return $next($request);
    }
}
