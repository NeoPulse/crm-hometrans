<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * Redirect authenticated users away from guest-only routes.
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        // Default to the web guard when no guard is specified.
        $guards = empty($guards) ? ['web'] : $guards;

        foreach ($guards as $guard) {
            // Skip guest-only pages when a user session already exists.
            if (Auth::guard($guard)->check()) {
                return redirect()->route('home');
            }
        }

        return $next($request);
    }
}
