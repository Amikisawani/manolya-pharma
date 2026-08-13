<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $user?->loadMissing('tenant');

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'currency' => [
                'code' => $user?->tenant?->default_currency ?? config('currency.default', 'CDF'),
                'symbol' => config('currency.symbol', 'Fc'),
                'rates' => config('currency.rates', ['USD' => 2350, 'EUR' => 2702.5]),
            ],
            'sentry' => [
                'environment' => config('sentry.environment') ?: config('app.env'),
                'release' => config('sentry.release'),
            ],
            'sentry' => [
                'environment' => config('sentry.environment') ?: config('app.env'),
                'release' => config('sentry.release'),
            ],
        ];
    }
}
