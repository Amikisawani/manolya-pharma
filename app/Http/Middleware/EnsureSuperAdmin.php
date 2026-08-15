<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->guest(route('admin.login'));
        }

        if (! $user->isSuperAdmin()) {
            // Caissier / owner qui a suivi un lien /admin → retour app pharmacie
            return redirect()->route('dashboard');
        }

        if (! $user->is_active) {
            abort(403, 'Compte désactivé.');
        }

        return $next($request);
    }
}
