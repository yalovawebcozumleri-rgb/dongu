<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\PickupRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AccountDeletionService
{
    public function delete(User $user, string $source): void
    {
        $userId = $user->id;
        $email = $user->email;
        $avatarPaths = array_values(array_filter([
            $user->avatar_path,
            $user->avatar_path ? app(ProfileAvatarService::class)->thumbnailPath($user->avatar_path) : null,
        ]));
        $listingPhotoPaths = DB::table('listing_photos')
            ->join('listings', 'listings.id', '=', 'listing_photos.listing_id')
            ->where('listings.user_id', $userId)
            ->pluck('listing_photos.path')->all();

        DB::transaction(function () use ($user, $userId, $email, $source): void {
            PickupRequest::query()
                ->where(fn ($query) => $query->where('buyer_id', $userId)->orWhere('seller_id', $userId))
                ->whereIn('status', [PickupRequest::INQUIRY, PickupRequest::PENDING, PickupRequest::ACCEPTED])
                ->update([
                    'status' => PickupRequest::CANCELLED,
                    'cancelled_by_user_id' => $userId,
                    'cancelled_at' => now(),
                    'delivery_code' => null,
                ]);

            Listing::query()->where('user_id', $userId)
                ->whereIn('status', [Listing::STATUS_ACTIVE, Listing::STATUS_RESERVED])
                ->update(['status' => Listing::STATUS_CANCELLED, 'expires_at' => now()]);

            $listingIds = Listing::withTrashed()->where('user_id', $userId)->pluck('id');
            DB::table('listing_private_locations')->whereIn('listing_id', $listingIds)->delete();
            DB::table('listing_photos')->whereIn('listing_id', $listingIds)->delete();
            Listing::withTrashed()->where('user_id', $userId)->update([
                'description' => 'Hesabını silen kullanıcıya ait ilan.',
                'boosted_until' => null,
            ]);

            DB::table('conversation_messages')->where('sender_id', $userId)
                ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('message_reports')->whereColumn('message_reports.conversation_message_id', 'conversation_messages.id'))
                ->update(['body' => 'Bu mesaj, hesabını silen kullanıcı tarafından kaldırıldı.', 'client_id' => null]);
            DB::table('reviews')->where('reviewer_id', $userId)->update(['comment' => null]);
            DB::table('message_reports')->where('reporter_id', $userId)->update(['details' => null]);

            foreach (['user_addresses', 'listing_favorites', 'user_notifications', 'notification_preferences', 'push_tokens', 'conversation_user_states'] as $table) {
                DB::table($table)->where('user_id', $userId)->delete();
            }
            DB::table('user_blocks')->where('blocker_id', $userId)->orWhere('blocked_id', $userId)->delete();
            DB::table('personal_access_tokens')->where('tokenable_type', User::class)->where('tokenable_id', $userId)->delete();
            DB::table('login_codes')->where('email', $email)->delete();

            DB::table('account_deletion_audits')->insert([
                'user_id' => $userId,
                'source' => $source,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $user->forceFill([
                'name' => 'Silinen kullanıcı',
                'email' => 'deleted-'.Str::uuid().'@deleted.invalid',
                'email_verified_at' => null,
                'password' => null,
                'phone' => null,
                'avatar_path' => null,
                'avatar_key' => null,
                'status' => 'deleted',
                'profile_completed_at' => null,
                'ranking_name_visible' => false,
                'remember_token' => null,
            ])->save();
        });

        Storage::disk('public')->delete(array_values(array_unique([...$avatarPaths, ...$listingPhotoPaths])));
    }
}

