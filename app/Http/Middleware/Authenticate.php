<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

/**
 * Custom Authenticate middleware for a pure-API application.
 *
 * Returning null from redirectTo() tells Laravel to throw an
 * AuthenticationException instead of performing an HTTP redirect,
 * which is then caught by the exception handler and returned as a
 * proper 401 JSON response. This eliminates the "Route [login] not
 * defined" error that occurs in apps with no traditional login page.
 */
class Authenticate extends Middleware
{
    protected function redirectTo(Request $request): ?string
    {
        return null;
    }
}
