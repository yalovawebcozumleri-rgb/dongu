<?php

namespace Tests\Feature\Api;

use App\Models\AdvertisementPlacementSetting;
use App\Models\MarketplaceUsageEvent;
use App\Models\MarketplaceUsagePolicy;
use App\Services\MarketplaceUsagePolicyService;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Models\RewardedAdClaim;
use App\Models\RewardedUsageGrant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RewardedUsageRightTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_earn_an_extra_listing_right_and_completion_is_idempotent(): void
    {
        $user = User::factory()->create(['status' => 'active', 'created_at' => now()->subDay()]);
        Sanctum::actingAs($user, ['mobile']);

        $baseLimit = $this->getJson('/api/v1/usage-policy')->assertOk()->json('data.listings.limit');
        $challenge = $this->postJson('/api/v1/rewarded-rights/listing_daily/challenge', ['platform' => 'android'])
            ->assertOk()
            ->assertJsonPath('data.clientCompletionAllowed', true)
            ->assertJsonPath('data.adMobAndroidUnitId', 'ca-app-pub-3940256099942544/5224354917')
            ->assertJsonPath('data.adEnvironment', 'test')
            ->assertJsonPath('data.offer.rewardKey', 'listing_daily')
            ->json('data.token');

        $claim = RewardedAdClaim::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('usage_bonus', $claim->reward_type);
        $this->assertSame('ek_hak', $claim->expected_reward_item);

        $this->postJson('/api/v1/rewarded-rights/listing_daily/complete', ['token' => $challenge])
            ->assertOk()
            ->assertJsonPath('data.activeBonus', 1);
        $this->postJson('/api/v1/rewarded-rights/listing_daily/complete', ['token' => $challenge])->assertOk();

        $this->assertDatabaseCount('rewarded_usage_grants', 1);
        $this->assertSame(1, (int) RewardedUsageGrant::query()->where('user_id', $user->id)->sum('amount'));
        $this->getJson('/api/v1/usage-policy')
            ->assertOk()
            ->assertJsonPath('data.listings.bonus', 1)
            ->assertJsonPath('data.listings.limit', $baseLimit)
            ->assertJsonPath('data.listings.remaining', $baseLimit + 1);
    }

    public function test_admin_can_disable_one_reward_without_removing_the_other_reward_definitions(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($user, ['mobile']);
        $setting = AdvertisementPlacementSetting::forKey('rewarded_extra_rights');
        $settings = $setting->settings;
        $settings['rewards']['listing_daily']['enabled'] = false;
        $setting->update(['settings' => $settings]);

        $this->getJson('/api/v1/usage-policy')
            ->assertOk()
            ->assertJsonPath('data.listings.rewardOffer', null)
            ->assertJsonPath('data.pickups.rewardOffer.rewardKey', 'pickup_daily');
        $this->postJson('/api/v1/rewarded-rights/listing_daily/challenge', ['platform' => 'android'])->assertNotFound();
    }

    public function test_reward_challenge_enforces_admin_daily_ad_limit(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($user, ['mobile']);

        $first = $this->postJson('/api/v1/rewarded-rights/listing_daily/challenge', ['platform' => 'android'])->assertOk()->json('data.token');
        $this->postJson('/api/v1/rewarded-rights/listing_daily/complete', ['token' => $first])->assertOk();
        $second = $this->postJson('/api/v1/rewarded-rights/listing_daily/challenge', ['platform' => 'android'])->assertOk()->json('data.token');
        $this->postJson('/api/v1/rewarded-rights/listing_daily/complete', ['token' => $second])->assertOk();

        $this->postJson('/api/v1/rewarded-rights/listing_daily/challenge', ['platform' => 'android'])
            ->assertTooManyRequests();
    }

    public function test_pending_challenges_cannot_be_completed_past_the_admin_daily_limit(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($user, ['mobile']);

        $tokens = collect(range(1, 3))->map(fn () => $this
            ->postJson('/api/v1/rewarded-rights/listing_daily/challenge', ['platform' => 'android'])
            ->assertOk()
            ->json('data.token'));

        $this->postJson('/api/v1/rewarded-rights/listing_daily/complete', ['token' => $tokens[0]])->assertOk();
        $this->postJson('/api/v1/rewarded-rights/listing_daily/complete', ['token' => $tokens[1]])->assertOk();
        $this->postJson('/api/v1/rewarded-rights/listing_daily/complete', ['token' => $tokens[2]])->assertTooManyRequests();

        $this->assertDatabaseCount('rewarded_usage_grants', 2);
    }
    public function test_extra_right_is_not_consumed_by_eligibility_check_and_is_consumed_once_by_real_action(): void
    {
        $user = User::factory()->create(['status' => 'active', 'created_at' => now()->subDay()]);
        Sanctum::actingAs($user, ['mobile']);
        MarketplaceUsagePolicy::current()->update(['listing_24h_limit' => 1]);
        MarketplaceUsageEvent::create([
            'user_id' => $user->id,
            'event_type' => MarketplaceUsageEvent::LISTING_CREATED,
            'created_at' => now()->subHour(),
        ]);

        $token = $this->postJson('/api/v1/rewarded-rights/listing_daily/challenge', ['platform' => 'android'])
            ->assertOk()
            ->json('data.token');
        $this->postJson('/api/v1/rewarded-rights/listing_daily/complete', ['token' => $token])->assertOk();

        $service = app(MarketplaceUsagePolicyService::class);
        $service->assertListingAllowed($user, false);
        $this->assertSame(1, (int) RewardedUsageGrant::query()->sum('remaining_amount'));

        $service->assertListingAllowed($user);
        $this->assertSame(0, (int) RewardedUsageGrant::query()->sum('remaining_amount'));

        $this->expectException(HttpResponseException::class);
        $service->assertListingAllowed($user);
    }

    public function test_failed_or_unfinished_ad_attempts_do_not_use_the_daily_ad_allowance(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($user, ['mobile']);

        $this->postJson('/api/v1/rewarded-rights/listing_daily/challenge', ['platform' => 'android'])->assertOk();
        $this->postJson('/api/v1/rewarded-rights/listing_daily/challenge', ['platform' => 'android'])->assertOk();
        $this->postJson('/api/v1/rewarded-rights/listing_daily/challenge', ['platform' => 'android'])->assertOk();

        $this->getJson('/api/v1/rewarded-rights/listing_daily/status')
            ->assertOk()
            ->assertJsonPath('data.adsUsed', 0)
            ->assertJsonPath('data.adsRemaining', 2);
    }

    public function test_rewarded_and_usage_policy_rate_limits_are_isolated_by_operation(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($user, ['mobile']);

        $identifier = sha1((string) $user->getAuthIdentifier());
        $legacyKey = $identifier;
        $usagePolicyKey = 'usage-policy:'.$identifier;

        foreach ([$legacyKey, $usagePolicyKey] as $key) {
            RateLimiter::clear($key);
        }

        for ($attempt = 0; $attempt < 60; $attempt++) {
            RateLimiter::hit($legacyKey, 3600);
        }

        $this->getJson('/api/v1/usage-policy')->assertOk();

        $token = $this->postJson('/api/v1/rewarded-rights/listing_daily/challenge', ['platform' => 'android'])
            ->assertOk()
            ->json('data.token');

        for ($attempt = 1; $attempt < 60; $attempt++) {
            $this->postJson('/api/v1/rewarded-rights/listing_daily/challenge', ['platform' => 'web'])
                ->assertUnprocessable();
        }

        $this->postJson('/api/v1/rewarded-rights/listing_daily/challenge', ['platform' => 'android'])
            ->assertTooManyRequests();

        $this->postJson('/api/v1/rewarded-rights/pickup_daily/challenge', ['platform' => 'android'])
            ->assertOk();

        $this->getJson('/api/v1/rewarded-rights/listing_daily/status')->assertOk();
        $this->postJson('/api/v1/rewarded-rights/listing_daily/complete', ['token' => $token])->assertOk();
    }

    public function test_invalid_platform_and_unknown_reward_are_rejected(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($user, ['mobile']);

        $this->postJson('/api/v1/rewarded-rights/listing_daily/challenge', ['platform' => 'web'])
            ->assertUnprocessable();
        $this->postJson('/api/v1/rewarded-rights/not_a_reward/challenge', ['platform' => 'android'])
            ->assertNotFound();
    }
}