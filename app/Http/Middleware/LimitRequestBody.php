<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LimitRequestBody
{
    public function handle(Request $request, Closure $next): Response
    {
        $maximum = (int) config('security.max_request_bytes');
        $length = (int) $request->server('CONTENT_LENGTH', 0);

        if ($length > $maximum) {
            return response()->json(['message' => 'Request body is too large.'], 413);
        }

        return $next($request);
    }
}
