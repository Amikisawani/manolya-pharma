<?php

use App\Http\Middleware\EnsureTenant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Render / Cloudflare terminent le TLS : sans ça Laravel génère des URLs http://
        // (assets Vite / Ziggy) → contenu mixte bloqué par le navigateur.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'tenant' => EnsureTenant::class,
            'super_admin' => \App\Http\Middleware\EnsureSuperAdmin::class,
            'deny_super_admin' => \App\Http\Middleware\DenySuperAdminFromPharmacy::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $renderForbidden = function (Request $request, string $message = '') {
            if ($request->is('api/*') || ($request->expectsJson() && ! $request->header('X-Inertia'))) {
                return null;
            }

            $friendly = 'Votre rôle ne permet pas d’ouvrir cette page. Demandez au propriétaire si vous avez besoin d’un accès.';
            if ($message === '' || $message === 'This action is unauthorized.') {
                $message = $friendly;
            }

            return Inertia::render('Errors/Forbidden', [
                'message' => $message,
            ])->toResponse($request)->setStatusCode(403);
        };

        $exceptions->render(function (AuthorizationException $e, Request $request) use ($renderForbidden) {
            return $renderForbidden($request, $e->getMessage());
        });

        $exceptions->render(function (HttpException $e, Request $request) use ($renderForbidden) {
            if ($e->getStatusCode() !== 403) {
                return null;
            }

            return $renderForbidden($request, $e->getMessage());
        });
    })->create();
