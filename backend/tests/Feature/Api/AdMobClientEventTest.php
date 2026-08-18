<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Log;
use Mockery;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class AdMobClientEventTest extends TestCase
{
    public function test_mobile_can_report_a_sanitized_ad_failure(): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('log')
            ->once()
            ->with('warning', 'mobile_ad_event', Mockery::on(fn (array $context) =>
                $context['event'] === 'rewarded_load_failed'
                && $context['platform'] === 'ios'
                && $context['environment'] === 'test'
                && isset($context['ipHash'])
            ));
        Log::shouldReceive('channel')->once()->with('admob')->andReturn($logger);

        $this->postJson('/api/v1/admob/client-events', [
            'event' => 'rewarded_load_failed',
            'platform' => 'ios',
            'environment' => 'test',
            'format' => 'rewarded',
            'placement' => 'listing_rewarded_boost',
            'errorCode' => 'googleMobileAds/no-fill',
            'errorMessage' => 'No ad inventory',
        ])->assertStatus(202)->assertJsonPath('accepted', true);
    }

    public function test_mobile_ad_event_rejects_invalid_environments(): void
    {
        $this->postJson('/api/v1/admob/client-events', [
            'event' => 'sdk_initialize_failed',
            'platform' => 'ios',
            'environment' => 'invalid',
            'format' => 'sdk',
        ])->assertUnprocessable();
    }
}