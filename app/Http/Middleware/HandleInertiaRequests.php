<?php

namespace App\Http\Middleware;

use App\Models\CashRegisterSession;
use App\Models\Tenant;
use App\Models\User;
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
        $user?->loadMissing(['tenant', 'roles', 'permissions']);

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'roles' => $user ? $user->getRoleNames()->values()->all() : [],
                'permissions' => $user ? $user->getAllPermissions()->pluck('name')->values()->all() : [],
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
            'cashSessionDuty' => $this->cashSessionDuty($user),
        ];
    }

    /**
     * @return array{must_close: bool, count: int, message: string|null}
     */
    private function cashSessionDuty(?User $user): array
    {
        $empty = ['must_close' => false, 'count' => 0, 'message' => null];

        if ($user === null || ! $user->canApproveCashSessions()) {
            return $empty;
        }

        if ($user->isSuperAdmin() && ! app()->bound('current_tenant_id')) {
            $tenant = Tenant::query()->orderBy('created_at')->first();
            if ($tenant) {
                app()->instance('current_tenant_id', (string) $tenant->id);
            }
        }

        $count = CashRegisterSession::query()->rejectedOpen()->count();

        if ($count === 0) {
            return $empty;
        }

        return [
            'must_close' => true,
            'count' => $count,
            'message' => 'Clôturez la session de caisse (demande rejetée) avant de vous déconnecter.',
        ];
    }
}
