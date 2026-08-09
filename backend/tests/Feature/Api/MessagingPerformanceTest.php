<?php

namespace Tests\Feature\Api;

use App\Models\MarketplaceUsagePolicy;
use App\Models\Listing;
use App\Models\MessageReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessagingPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_messages_are_cursor_paginated_idempotent_reportable_and_individually_hidden(): void
    {
        MarketplaceUsagePolicy::current()->update([
            'messages_per_minute' => 1000,
            'messages_per_hour' => 1000,
            'messages_per_24h' => 1000,
            'unanswered_message_limit' => 1000,
        ]);
        $seller = User::factory()->create(['status' => 'active']);
        $buyer = User::factory()->create(['status' => 'active']);
        $listing = $this->listing($seller);

        Sanctum::actingAs($buyer, ['mobile']);
        $conversationId = $this->postJson("/api/v1/listings/{$listing->id}/pickup-requests", ['intent' => 'pickup'])
            ->assertCreated()->json('data.id');
        $request = \App\Models\PickupRequest::findOrFail($conversationId);
        foreach (range(1, 65) as $number) {
            $request->messages()->create(['sender_id' => $buyer->id, 'type' => 'user', 'body' => "Mesaj {$number}"]);
        }

        $first = $this->getJson("/api/v1/pickup-requests/{$conversationId}/messages?per_page=30")
            ->assertOk()->assertJsonCount(30, 'data')->assertJsonPath('meta.hasMore', true);
        $cursor = $first->json('meta.nextCursor');
        $firstIds = collect($first->json('data'))->pluck('id');
        $second = $this->getJson("/api/v1/pickup-requests/{$conversationId}/messages?per_page=30&before_id={$cursor}")
            ->assertOk()->assertJsonCount(30, 'data');
        $this->assertEmpty($firstIds->intersect(collect($second->json('data'))->pluck('id')));

        $clientId = 'f9cf9d91-f1df-4c72-8d40-a8d5bfbf9977';
        $before = $request->messages()->count();
        $this->postJson("/api/v1/pickup-requests/{$conversationId}/messages", ['message' => 'Tek mesaj', 'client_id' => $clientId])->assertCreated();
        $this->postJson("/api/v1/pickup-requests/{$conversationId}/messages", ['message' => 'Tek mesaj', 'client_id' => $clientId])->assertOk();
        $this->assertSame($before + 1, $request->messages()->count());

        $this->deleteJson("/api/v1/pickup-requests/{$conversationId}/conversation")->assertUnprocessable();

        Sanctum::actingAs($seller, ['mobile']);
        $incomingId = $request->messages()->where('sender_id', $buyer->id)->latest('id')->value('id');
        $this->postJson("/api/v1/pickup-requests/{$conversationId}/messages/{$incomingId}/report", ['reason' => 'spam'])
            ->assertCreated()->assertJsonPath('data.reported', true);
        $this->assertDatabaseHas(MessageReport::class, ['conversation_message_id' => $incomingId, 'reporter_id' => $seller->id]);
        $this->postJson("/api/v1/pickup-requests/{$conversationId}/read", ['last_message_id' => $incomingId])->assertOk();
        $this->assertNotNull($request->messages()->findOrFail($incomingId)->read_at);
        $this->postJson("/api/v1/pickup-requests/{$conversationId}/messages", [
            'message' => 'Sat?c? yan?t?',
            'client_id' => 'de1fd778-c0a1-4cb0-9797-fd79c10b7d7b',
        ])->assertCreated();

        Sanctum::actingAs($buyer, ['mobile']);
        $this->postJson("/api/v1/pickup-requests/{$conversationId}/cancel")->assertOk();
        $this->deleteJson("/api/v1/pickup-requests/{$conversationId}/conversation")->assertOk();
        $this->postJson("/api/v1/pickup-requests/{$conversationId}/messages", ['message' => 'Gizlenen sohbeti geri açma'])
            ->assertUnprocessable();
        $this->assertDatabaseHas('conversation_user_states', [
            'pickup_request_id' => $conversationId,
            'user_id' => $buyer->id,
        ]);
        $this->assertSoftDeleted('user_notifications', [
            'user_id' => $buyer->id,
            'group_key' => "conversation:{$conversationId}",
        ]);
        $this->getJson('/api/v1/conversations')->assertOk()->assertJsonCount(0, 'data');

        Sanctum::actingAs($seller, ['mobile']);
        $this->getJson('/api/v1/conversations')->assertOk()->assertJsonCount(1, 'data');
    }

    private function listing(User $seller): Listing
    {
        $listing = Listing::create([
            'user_id' => $seller->id, 'status' => Listing::STATUS_ACTIVE,
            'public_area' => 'Kadıköy, İstanbul', 'approximate_latitude' => 40.991,
            'approximate_longitude' => 29.027, 'description' => 'Sayfalama testi',
            'published_at' => now(), 'expires_at' => now()->addDays(30),
        ]);
        $listing->materials()->create(['type' => 'pet', 'quantity' => 20, 'unit_price' => .75]);
        $listing->privateLocation()->create(['latitude' => '40.9912345', 'longitude' => '29.0271234', 'address' => 'Test adresi']);
        return $listing;
    }
}
