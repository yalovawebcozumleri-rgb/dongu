<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ProfileAvatarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileAvatarController extends Controller
{
    public function store(Request $request, ProfileAvatarService $avatars): JsonResponse
    {
        $validated = $request->validate([
            'avatar_key' => ['required', 'string', Rule::in(User::AVATAR_KEYS)],
        ], [
            'avatar_key.required' => 'Bir avatar seçmelisin.',
            'avatar_key.in' => 'Seçtiğin avatar geçerli değil.',
        ]);

        $user = $request->user();
        if ($user->avatar_path) {
            $user = $avatars->remove($user);
        }

        $user->forceFill(['avatar_key' => $validated['avatar_key']])->save();

        return response()->json([
            'message' => 'Avatarın güncellendi.',
            'data' => ['user' => $this->user($user->fresh())],
        ]);
    }

    private function user(User $user): array
    {
        $avatar = $user->avatarReference();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'email_verified' => $user->email_verified_at !== null,
            'created_at' => $user->created_at?->toIso8601String(),
            'profile_complete' => $user->profile_completed_at !== null,
            'avatar_url' => $avatar,
            'avatar_thumbnail_url' => $avatar,
        ];
    }
}