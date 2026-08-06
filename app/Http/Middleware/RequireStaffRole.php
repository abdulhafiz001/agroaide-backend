<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireStaffRole
{
    public function handle(Request $request, Closure $next, string $minimum = 'agronomist'): Response
    {
        $user = $request->user();
        $allowed = $user && ($minimum === 'admin' ? $user->isAdmin() : $user->isStaff());
        abort_unless($allowed, 403);

        return $next($request);
    }
}
