<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UseAdminGuard
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $previous = Auth::getDefaultDriver();
        Auth::shouldUse('admin');

        try {
            return $next($request);
        } finally {
            Auth::shouldUse($previous);
        }
    }
}
