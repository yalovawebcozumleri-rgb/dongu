<?php

namespace Tests\Feature\Api;

use App\Models\Listing;
use App\Models\PickupRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_events_create_deduplicated_notifications_with_read_state(): void
    {
        $seller = User::factory()->create(['status' => 'active']);
        $buyer = User::factory()->create(['status' => 'active']);
        $listing = $this->listing($seller);

        Sanctum::actingAs($buyer, ['mobile']);
        $pickupId = $this->postJson("/api/v1/listings/{$listing->id}/pickup-requests", ['intent' => 'pickup'])
            ->assertCreated()->json('data.id');

        Sanctum::actingAs($seller, ['mobile']);
        $sellerNotifications = $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('meta.unreadCount', 1)
            ->assertJsonPath('data.0.type', 'pickup_request')
            ->assertJsonPath('data.0.data.conversationId', $pickupId);
        $notificationId = $sellerNotifications->json('data.0.id');
        $this->patchJson("/api/v1/notifications/{$notificationId}/read")
            ->assertOk()->assertJsonPath('data.read', true);
        $this->getJson('/api/v1/notifications/unread-count')
            ->assertOk()->assertJsonPath('data.unreadCount', 0);

        $this->postJson("/api/v1/pickup-requests/{$pickupId}/accept")->assertOk();

        Sanctum::actingAs($buyer, ['mobile']);
        $this->getJson('/api/v1/notifications?unread=1')
            ->assertOk()
            ->assertJsonPath('meta.unreadCount', 1)
            ->assertJsonPath('data.0.type', 'pickup_accepted');

        $clientId = (string) Str::uuid();
        $this->postJson("/api/v1/pickup-requests/{$pickupId}/messages", ['message' => 'Yola çıkıyorum.', 'client_id' => $clientId])->assertCreated();
        $this->postJson("/api/v1/pickup-requests/{$pickupId}/messages", ['message' => 'Yola çıkıyorum.', 'client_id' => $clientId])->assertOk();

        Sanctum::actingAs($seller, ['mobile']);
        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('meta.unreadCount', 1)
            ->assertJsonPath('data.0.type', 'new_message');
        $this->assertDatabaseCount('conversation_messages', 4);
        $this->assertDatabaseCount('user_notifications', 3);

        $this->patchJson('/api/v1/notifications/read-all')
            ->assertOk()->assertJsonPath('data.unreadCount', 0);
        $this->getJson('/api/v1/notifications/unread-count')
            ->assertJsonPath('data.unreadCount', 0);
    }

    public function test_delivery_review_notifications_and_preferences_are_real_and_user_scoped(): void
    {
        $seller = User::factory()->create(['status' => 'active']);
        $buyer = User::factory()->create(['status' => 'active']);
        $listing = $this->listing($seller);

        Sanctum::actingAs($buyer, ['mobile']);
        $pickupId = $this->postJson("/api/v1/listings/{$listing->id}/pickup-requests", ['intent' => 'pickup'])
            ->assertCreated()->json('data.id');
        Sanctum::actingAs($seller, ['mobile']);
        $this->postJson("/api/v1/pickup-requests/{$pickupId}/accept")->assertOk();
        Sanctum::actingAs($buyer, ['mobile']);
        $code = $this->getJson('/api/v1/conversations')->json('data.0.deliveryCode');
        Sanctum::actingAs($seller, ['mobile']);
        $this->postJson("/api/v1/pickup-requests/{$pickupId}/complete", ['code' => $code])->assertOk();

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'delivery_completed');
        Sanctum::actingAs($buyer, ['mobile']);
        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'delivery_completed');
        $this->postJson("/api/v1/pickup-requests/{$pickupId}/review", ['rating' => 5])->assertOk();

        Sanctum::actingAs($seller, ['mobile']);
        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'review_received')
            ->assertJsonPath('data.0.body', $buyer->name.' sana 5 yıldız verdi.');

        $this->getJson('/api/v1/notification-preferences')
            ->assertOk()
            ->assertJsonPath('data.messagesEnabled', true)
            ->assertJsonPath('data.marketingEnabled', false);
        $this->patchJson('/api/v1/notification-preferences', [
            'messagesEnabled' => false,
            'pickupRequestsEnabled' => true,
            'deliveryEnabled' => true,
            'reviewsEnabled' => false,
            'listingUpdatesEnabled' => true,
            'marketingEnabled' => false,
        ])->assertOk()
            ->assertJsonPath('data.messagesEnabled', false)
            ->assertJsonPath('data.reviewsEnabled', false);
        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $seller->id,
            'messages_enabled' => false,
            'reviews_enabled' => false,
        ]);
    }

    public function test_notification_records_are_private(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $notification = $first->userNotifications()->create([
            'type' => 'new_message',
            'title' => 'Yeni mesaj',
            'body' => 'Özel bildirim',
        ]);
        Sanctum::actingAs($second, ['mobile']);

        $this->patchJson("/api/v1/notifications/{$notification->id}/read")->assertNotFound();
        $this->getJson('/api/v1/notifications')->assertOk()->assertJsonCount(0, 'data');
    }

    private function listing(User $seller): Listing
    {
        $listing = Listing::create([
            'user_id' => $seller->id,
            'status' => Listing::STATUS_ACTIVE,
            'public_area' => 'Kadıköy, İstanbul',
            'approximate_latitude' => 40.991,
            'approximate_longitude' => 29.027,
            'description' => 'Bildirim testi için yeterince uzun açıklama.',
            'published_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
        $listing->materials()->create(['type' => 'pet', 'quantity' => 25, 'unit_price' => 0.60]);
        $listing->privateLocation()->create(['latitude' => '40.9910000', 'longitude' => '29.0270000', 'address' => 'Test açık adresi']);
        return $listing;
    }
}
