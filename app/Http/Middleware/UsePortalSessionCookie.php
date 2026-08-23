<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pharmacie et admin ont chacun leur cookie de session.
 * Une connexion n’écrase plus l’autre dans le même navigateur.
 */
class UsePortalSessionCookie
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $original = config('session.cookie');

        config([
            'session.cookie' => self::isAdminPortal($request)
                ? config('session.admin_cookie')
                : config('session.pharmacy_cookie'),
        ]);

        try {
            return $next($request);
        } finally {
            config(['session.cookie' => $original]);
        }
    }

    public static function isAdminPortal(Request $request): bool
    {
        return $request->is('admin')
            || $request->is('admin/*')
            || $request->is('setup')
            || $request->is('setup/*');
    }
}
