<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PreventAuthenticatedPageCaching
{
    public function handle(Request $request, Closure $next): Response
    {
        $wasAuthenticated = Auth::guard('web')->check() || Auth::guard('student')->check();
        $response = $next($request);

        if ($wasAuthenticated) {
            $response->headers->set('Cache-Control', 'no-store, private, max-age=0, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }
}
