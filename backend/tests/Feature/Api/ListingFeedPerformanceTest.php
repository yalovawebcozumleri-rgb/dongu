<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('performance')]
class ListingFeedPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_stays_paginated_and_query_bounded_with_two_thousand_listings(): void
    {
        $seller = User::factory()->create();
        $now = now();

        foreach (array_chunk(range(1, 2000), 250) as $chunk) {
            DB::table('listings')->insert(array_map(fn (int $number) => [
                'user_id' => $seller->id,
                'status' => 'active',
                'public_area' => 'Yük testi bölgesi',
                'approximate_latitude' => 40.9900 + (($number % 20) / 10000),
                'approximate_longitude' => 29.0250 + (($number % 20) / 10000),
                'description' => 'Yük testi için oluşturulan geçerli ilan açıklaması.',
                'published_at' => $now->copy()->subSeconds($number),
                'expires_at' => $now->copy()->addDays(30),
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk));
        }

        DB::table('listings')->orderBy('id')->select('id')->chunk(500, function ($listings) use ($now) {
            DB::table('listing_materials')->insert($listings->map(fn ($listing) => [
                'listing_id' => $listing->id,
                'type' => 'pet',
                'quantity' => 10,
                'unit_price' => 0.50,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        });

        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount += 1;
        });
        $startedAt = microtime(true);

        $response = $this->getJson('/api/v1/listings?latitude=40.9912&longitude=29.0275&radius=10&sort=distance&per_page=20');
        $elapsedSeconds = microtime(true) - $startedAt;

        $response->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.total', 2000)
            ->assertJsonPath('meta.last_page', 100);
        $this->assertLessThanOrEqual(8, $queryCount, "Ana ilan akışı {$queryCount} sorgu çalıştırdı.");
        $this->assertLessThan(8.0, $elapsedSeconds, "Ana ilan akışı {$elapsedSeconds} saniye sürdü.");
    }

    public function test_feed_indexes_are_present(): void
    {
        $listingIndexes = collect(DB::select('SHOW INDEX FROM listings'))->pluck('Key_name');
        $materialIndexes = collect(DB::select('SHOW INDEX FROM listing_materials'))->pluck('Key_name');

        $this->assertContains('listing_feed_geo_idx', $listingIndexes);
        $this->assertContains('listing_feed_recent_idx', $listingIndexes);
        $this->assertContains('listing_material_filter_idx', $materialIndexes);
    }
}
