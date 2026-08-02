<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Base controller for HTTP actions in the SANA Market app.
 *
 * Auth/admin gates are applied via route groups; policies use AuthorizesRequests.
 *
 * @package App\Http\Controllers
 */
abstract class Controller
{
    use AuthorizesRequests;
}
