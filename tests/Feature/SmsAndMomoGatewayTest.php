<?php

namespace Tests\Feature;

use App\Infrastructure\Payments\MobileMoneyGatewayResolver;
use App\Infrastructure\Payments\MobileMoneyStubGateway;
use App\Infrastructure\Payments\OrangeMoneyGateway;
use App\Infrastructure\Sms\AirtelSmsGateway;
use App\Infrastructure\Sms\LogSmsGateway;
use App\Infrastructure\Sms\OrangeSmsGateway;
use App\Infrastructure\Sms\RoutingSmsGateway;
use App\Models\Alert;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\CriticalAlertNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SmsAndMomoGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_routing_sms_auto_picks_orange_prefix(): void
    {
        $routing = app(RoutingSmsGateway::class);
        config(['services.sms.driver' => 'auto']);

        $this->assertInstanceOf(OrangeSmsGateway::class, $routing->resolve('+243890000000'));
        $this->assertInstanceOf(AirtelSmsGateway::class, $routing->resolve('+243990000000'));
    }

    public function test_log_sms_gateway_returns_success(): void
    {
        $result = app(LogSmsGateway::class)->send('+243810000000', 'Test Manolya');

        $this->assertTrue($result->successful);
        $this->assertSame('log', $result->provider);
    }

    public function test_critical_alert_notification_includes_sms_channel_when_enabled(): void
    {
        $this->seed();
        config(['services.sms.enabled' => true]);

        $owner = User::query()->where('email', 'owner@manolya.test')->firstOrFail();
        $this->assertNotEmpty($owner->phone);

        $alert = Alert::query()->create([
            'tenant_id' => $owner->tenant_id,
            'type' => 'stockout',
            'severity' => 'critical',
            'title' => 'Rupture test',
            'body' => 'Produit en rupture',
            'status' => 'open',
            'raised_at' => now(),
        ]);

        Notification::fake();
        $owner->notify(new CriticalAlertNotification($alert));

        Notification::assertSentTo(
            $owner,
            CriticalAlertNotification::class,
            function (CriticalAlertNotification $notification, array $channels): bool {
                return in_array('mail', $channels, true)
                    && in_array(SmsChannel::class, $channels, true);
            }
        );
    }

    public function test_momo_resolver_returns_orange_and_stub(): void
    {
        $resolver = app(MobileMoneyGatewayResolver::class);

        $this->assertInstanceOf(OrangeMoneyGateway::class, $resolver->resolve('orange'));
        $this->assertInstanceOf(MobileMoneyStubGateway::class, $resolver->resolve('stub'));
        $this->assertInstanceOf(MobileMoneyStubGateway::class, $resolver->resolve(null));
    }
}
