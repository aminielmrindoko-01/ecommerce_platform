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
 * Restricts access to vendor accounts with a linked store + vendor.access.
 */
class VendorMiddleware
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isActiveAccount()) {
            abort(403, 'Unauthorized vendor access');
        }

        // Marketplace identity + capability + ownership anchor (approved store).
        if ($user->role !== 'vendor' || ! $user->hasPermission('vendor.access') || ! $user->vendor) {
            abort(403, 'Unauthorized vendor access');
        }

        if (! $user->vendor->canSell()) {
            abort(403, 'Vendor store is not approved for selling.');
        }

        return $next($request);
    }
}
