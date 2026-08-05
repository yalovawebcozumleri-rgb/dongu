<?php

namespace App\Http\Controllers\Api;

use App\Events\ConversationChanged;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\PickupRequest;
use App\Models\User;
use App\Models\UserBlock;
use App\Services\ProfileAvatarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserBlockController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = UserBlock::query()
            ->where('blocker_id', $request->user()->id)
            ->with('blocked:id,name,avatar_path')
            ->latest()
            ->get()
            ->map(fn (UserBlock $block) => [
                'id' => $block->blocked->id,
                'name' => $block->blocked->name,
                'avatarUrl' => $block->blocked->avatar_path ? app(ProfileAvatarService::class)->url($block->blocked->avatar_path, true) : null,
                'blockedAt' => $block->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $items]);
    }

    public function store(Request $request, User $user): JsonResponse
    {
        $blocker = $request->user();
        abort_if($blocker->id === $user->id, 422, 'Kendini engelleyemezsin.');

        $block = DB::transaction(function () use ($blocker, $user) {
            $block = UserBlock::firstOrCreate([
                'blocker_id' => $blocker->id,
                'blocked_id' => $user->id,
            ]);

            $requests = PickupRequest::query()
                ->where(fn ($query) => $query
                    ->where(fn ($pair) => $pair
                        ->where('buyer_id', $blocker->id)
                        ->where('seller_id', $user->id))
                    ->orWhere(fn ($pair) => $pair
                        ->where('buyer_id', $user->id)
                        ->where('seller_id', $blocker->id)))
                ->whereIn('status', [PickupRequest::INQUIRY, PickupRequest::PENDING, PickupRequest::ACCEPTED])
                ->lockForUpdate()
                ->get();

            foreach ($requests as $pickupRequest) {
                if ($pickupRequest->status === PickupRequest::ACCEPTED) {
                    $pickupRequest->listing()->update(['status' => Listing::STATUS_ACTIVE]);
                }
                $pickupRequest->update([
                    'status' => PickupRequest::CANCELLED,
                    'delivery_code' => null,
                    'cancelled_by_user_id' => $blocker->id,
                    'cancelled_at' => now(),
                ]);
                $pickupRequest->messages()->create([
                    'sender_id' => null,
                    'type' => 'system',
                    'body' => 'Kullanıcı engelleme nedeniyle bu görüşme iletişime kapatıldı.',
                ]);
            }

            return $block;
        });

        PickupRequest::query()
            ->where(fn ($query) => $query
                ->where(fn ($pair) => $pair->where('buyer_id', $blocker->id)->where('seller_id', $user->id))
                ->orWhere(fn ($pair) => $pair->where('buyer_id', $user->id)->where('seller_id', $blocker->id)))
            ->get(['id', 'buyer_id', 'seller_id'])
            ->each(function (PickupRequest $pickupRequest) {
                ConversationChanged::dispatch($pickupRequest->buyer_id, $pickupRequest->id, 'status');
                ConversationChanged::dispatch($pickupRequest->seller_id, $pickupRequest->id, 'status');
            });

        return response()->json(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'avatarUrl' => $user->avatar_path ? app(ProfileAvatarService::class)->url($user->avatar_path, true) : null,
            'blocked' => true,
            'blockedAt' => $block->created_at?->toIso8601String(),
        ]], $block->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, User $user)
    {
        UserBlock::query()
            ->where('blocker_id', $request->user()->id)
            ->where('blocked_id', $user->id)
            ->delete();

        return response()->noContent();
    }
}