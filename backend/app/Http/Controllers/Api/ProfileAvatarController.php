<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ProfileAvatarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileAvatarController extends Controller
{
    public function store(Request $request, ProfileAvatarService $avatars): JsonResponse
    {
        $validated = $request->validate([
            'avatar' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=64,min_height=64,max_width=4096,max_height=4096'],
        ], [
            'avatar.required' => 'Bir profil fotoğrafı seçmelisin.',
            'avatar.image' => 'Seçtiğin dosya geçerli bir fotoğraf değil.',
            'avatar.mimes' => 'Profil fotoğrafı JPEG, PNG veya WebP olmalıdır.',
            'avatar.max' => 'Profil fotoğrafı en fazla 5 MB olabilir.',
            'avatar.dimensions' => 'Profil fotoğrafı en az 64×64, en fazla 4096×4096 piksel olmalıdır.',
        ]);

        try {
            $user = $avatars->store($request->user(), $validated['avatar']);
        } catch (\RuntimeException $error) {
            throw \Illuminate\Validation\ValidationException::withMessages(['avatar' => $error->getMessage()]);
        }
        return response()->json(['message' => 'Profil fotoğrafın güncellendi.', 'data' => ['user' => $this->user($user, $avatars)]]);
    }

    public function destroy(Request $request, ProfileAvatarService $avatars): JsonResponse
    {
        $user = $avatars->remove($request->user());
        return response()->json(['message' => 'Profil fotoğrafın kaldırıldı.', 'data' => ['user' => $this->user($user, $avatars)]]);
    }

    private function user(User $user, ProfileAvatarService $avatars): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'email_verified' => $user->email_verified_at !== null,
            'created_at' => $user->created_at?->toIso8601String(),
            'profile_complete' => $user->profile_completed_at !== null,
            'avatar_url' => $user->avatar_path ? $avatars->url($user->avatar_path).'?v='.($user->updated_at?->timestamp ?? 0) : null,
            'avatar_thumbnail_url' => $user->avatar_path ? $avatars->url($user->avatar_path, true).'?v='.($user->updated_at?->timestamp ?? 0) : null,
        ];
    }
}
