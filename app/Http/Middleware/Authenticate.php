<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Redirect unauthenticated users to the login page.
     */
    protected function redirectTo(Request $request): ?string
    {
        // Only redirect when the request expects a web response.
        if (! $request->expectsJson()) {
            return route('login');
        }

        return null;
    }
}
