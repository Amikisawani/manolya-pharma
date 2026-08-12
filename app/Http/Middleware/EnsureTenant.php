<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenant
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (! $user->is_active) {
            abort(403, 'Compte désactivé.');
        }

        if ($user->locked_until !== null && $user->locked_until->isFuture()) {
            abort(403, 'Compte temporairement verrouillé.');
        }

        if (filled($user->tenant_id)) {
            app()->instance('current_tenant_id', (string) $user->tenant_id);
        }

        return $next($request);
    }
}
