<?php

namespace App\Providers;

use App\Domain\Ai\Contracts\AnomalyPort;
use App\Domain\Ai\Contracts\ForecastingPort;
use App\Domain\Ai\Contracts\ReplenishmentPort;
use App\Infrastructure\Ai\HeuristicReplenishmentAdapter;
use App\Infrastructure\Ai\NullAnomalyAdapter;
use App\Infrastructure\Ai\NullForecastingAdapter;
use App\Infrastructure\Ocr\HttpOcrGateway;
use App\Infrastructure\Ocr\LocalExtractOcrGateway;
use App\Infrastructure\Ocr\OcrGateway;
use App\Infrastructure\Sms\RoutingSmsGateway;
use App\Infrastructure\Sms\SmsGateway;
use App\Models\AuditRecord;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Policies\AuditRecordPolicy;
use App\Policies\ProductPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\SalePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ForecastingPort::class, NullForecastingAdapter::class);
        $this->app->bind(ReplenishmentPort::class, HeuristicReplenishmentAdapter::class);
        $this->app->bind(AnomalyPort::class, NullAnomalyAdapter::class);
        $this->app->bind(SmsGateway::class, RoutingSmsGateway::class);
        $this->app->bind(OcrGateway::class, function ($app) {
            return match (config('services.ocr.driver', 'local')) {
                'http' => $app->make(HttpOcrGateway::class),
                default => $app->make(LocalExtractOcrGateway::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Sale::class, SalePolicy::class);
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(AuditRecord::class, AuditRecordPolicy::class);
    }
}
