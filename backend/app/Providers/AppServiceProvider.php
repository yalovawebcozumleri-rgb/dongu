<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('rewarded-challenges', fn (Request $request) => Limit::perHour(60)
            ->by($this->rewardedRateLimitKey($request, 'challenge')));

        RateLimiter::for('rewarded-completions', fn (Request $request) => Limit::perHour(60)
            ->by($this->rewardedRateLimitKey($request, 'completion')));

        RateLimiter::for('rewarded-status', fn (Request $request) => Limit::perMinute(60)
            ->by($this->rewardedRateLimitKey($request, 'status')));
    }

    private function rewardedRateLimitKey(Request $request, string $operation): string
    {
        $actor = $request->user()
            ? 'user:'.$request->user()->getAuthIdentifier()
            : 'ip:'.$request->ip();
        $reward = (string) ($request->route('rewardKey') ?: 'listing-boost');

        return implode('|', [
            'rewarded',
            $operation,
            $actor,
            $reward,
        ]);
    }
}
