<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * @param  string  ...$roles  Allowed roles (admin, student, security_guard). Passed by route middleware.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        $allowed = array_map('trim', $roles);
        if ($allowed === [] || ! in_array($request->user()->role, $allowed, true)) {
            abort(403, 'Unauthorized.');
        }

        return $next($request);
    }
}
