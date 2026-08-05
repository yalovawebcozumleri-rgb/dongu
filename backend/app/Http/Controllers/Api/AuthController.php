<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\LoginCodeMail;
use App\Models\LoginCode;
use App\Models\User;
use App\Services\ModerationSanctionService;
use App\Services\AccountDeletionService;
use App\Services\ProfileAvatarService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function requestCode(Request $request, ModerationSanctionService $sanctions): JsonResponse
    {
        $validated = $request->validate([
            'intent' => ['required', Rule::in([LoginCode::INTENT_LOGIN, LoginCode::INTENT_REGISTER])],
            'email' => ['required', 'email:rfc', 'max:255'],
            'name' => ['nullable', 'required_if:intent,register', 'string', 'min:3', 'max:80'],
            'terms_accepted' => ['nullable', 'accepted_if:intent,register'],
            'terms_version' => ['nullable', 'string', Rule::in([config('legal.documents.terms.version')])],
            'privacy_notice_version' => ['nullable', 'string', Rule::in([config('legal.documents.privacy.version')])],
        ]);

        $email = mb_strtolower(trim($validated['email']));
        $user = User::where('email', $email)->first();

        if ($validated['intent'] === LoginCode::INTENT_REGISTER && $user) {
            throw ValidationException::withMessages(['email' => 'Bu e-posta zaten kayıtlı. Giriş yapmayı deneyebilirsin.']);
        }
        if ($validated['intent'] === LoginCode::INTENT_LOGIN && ! $user) {
            throw ValidationException::withMessages(['email' => 'Bu e-posta ile kayıtlı hesap bulunamadı.']);
        }
        if ($user && $user->role !== User::ROLE_USER) {
            throw ValidationException::withMessages(['email' => 'Bu hesap işletme veya yönetim paneline aittir; mobil uygulama girişi için kullanılamaz.']);
        }
        if ($user) $sanctions->assertAccountAllowed($user);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $loginCode = DB::transaction(function () use ($email, $validated, $request, $code) {
            LoginCode::where('email', $email)->whereNull('consumed_at')->update(['consumed_at' => now()]);

            return LoginCode::create([
                'email' => $email,
                'intent' => $validated['intent'],
                'pending_name' => isset($validated['name']) ? trim($validated['name']) : null,
                'terms_accepted' => (bool) ($validated['terms_accepted'] ?? false),
                'terms_version' => $validated['terms_version'] ?? config('legal.documents.terms.version'),
                'privacy_notice_version' => $validated['privacy_notice_version'] ?? config('legal.documents.privacy.version'),
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(10),
                'requested_ip' => $request->ip(),
            ]);
        });

        try {
            Mail::to($email)->send(new LoginCodeMail($code));
        } catch (\Throwable $error) {
            $loginCode->delete();
            report($error);

            return response()->json(['message' => 'Giriş kodu gönderilemedi. E-posta ayarlarını kontrol et.'], 503);
        }

        return response()->json([
            'message' => '6 haneli giriş kodu e-posta adresine gönderildi.',
            'data' => ['email' => $email, 'expires_in' => 600],
        ], 202);
    }

    public function verifyCode(Request $request, ModerationSanctionService $sanctions): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc'],
            'code' => ['required', 'digits:6'],
            'device_name' => ['required', 'string', 'max:120'],
        ]);
        $email = mb_strtolower(trim($validated['email']));
        $loginCode = LoginCode::where('email', $email)->whereNull('consumed_at')->latest('id')->first();

        if (! $loginCode || $loginCode->expires_at->isPast()) {
            throw ValidationException::withMessages(['code' => 'Kod geçersiz veya süresi dolmuş. Yeni kod iste.']);
        }
        if ($loginCode->attempts >= LoginCode::MAX_ATTEMPTS) {
            return response()->json(['message' => 'Bu doğrulama kodu için 5 kez hatalı giriş yapıldı. Güvenliğin için bu kod kapatıldı; yeni bir kod isteyebilirsin.'], 429);
        }
        if (! Hash::check($validated['code'], $loginCode->code_hash)) {
            $loginCode->increment('attempts');
            throw ValidationException::withMessages(['code' => 'Girdiğin kod hatalı.']);
        }

        [$user, $created] = DB::transaction(function () use ($loginCode, $email, $sanctions) {
            $lockedCode = LoginCode::lockForUpdate()->findOrFail($loginCode->id);
            if ($lockedCode->consumed_at) {
                throw ValidationException::withMessages(['code' => 'Bu kod daha önce kullanılmış.']);
            }

            $user = User::where('email', $email)->first();
            $created = false;
            if (! $user && $lockedCode->intent === LoginCode::INTENT_REGISTER) {
                $user = User::create([
                    'name' => $lockedCode->pending_name,
                    'email' => $email,
                    'password' => null,
                    'status' => 'active',
                    'role' => User::ROLE_USER,
                    'email_verified_at' => now(),
                    'terms_accepted_at' => now(),
                    'terms_version' => $lockedCode->terms_version,
                    'privacy_notice_acknowledged_at' => now(),
                    'privacy_notice_version' => $lockedCode->privacy_notice_version,
                    'profile_completed_at' => now(),
                ]);
                $created = true;
                event(new Registered($user));
            }
            if (! $user) {
                throw ValidationException::withMessages(['email' => 'Bu hesapla giriş yapılamıyor.']);
            }
            if ($user->role !== User::ROLE_USER) {
                throw ValidationException::withMessages(['email' => 'Bu hesap mobil uygulama girişi için kullanılamaz.']);
            }
            $sanctions->assertAccountAllowed($user);
            if (! $user->email_verified_at) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            $lockedCode->update(['consumed_at' => now()]);

            return [$user, $created];
        });

        return response()->json(['data' => [
            'user' => $this->userData($user),
            'token' => $user->createToken($validated['device_name'], ['mobile'])->plainTextToken,
        ]], $created ? 201 : 200);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => ['user' => $this->userData($request->user())]]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);
        $validated = $request->validate(['name' => ['required', 'string', 'min:3', 'max:80']]);
        $request->user()->update(['name' => trim($validated['name']), 'profile_completed_at' => now()]);

        return response()->json(['data' => ['user' => $this->userData($request->user()->fresh())]]);
    }

    public function destroyAccount(Request $request, AccountDeletionService $deletion): JsonResponse
    {
        $validated = $request->validate(['confirmation' => ['required', Rule::in(['HESABIMI SİL'])]]);
        $deletion->delete($request->user(), 'mobile');

        return response()->json(['message' => 'Hesabın ve kişisel verilerin silindi.']);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Oturum kapatıldı.']);
    }

    private function userData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'email_verified' => $user->email_verified_at !== null,
            'created_at' => $user->created_at?->toIso8601String(),
            'avatar_url' => $user->avatar_path ? app(ProfileAvatarService::class)->url($user->avatar_path).'?v='.($user->updated_at?->timestamp ?? 0) : null,
            'avatar_thumbnail_url' => $user->avatar_path ? app(ProfileAvatarService::class)->url($user->avatar_path, true).'?v='.($user->updated_at?->timestamp ?? 0) : null,
            'rating' => $user->rating !== null ? (float) $user->rating : null,
            'rating_count' => (int) $user->rating_count,
            'completed_transactions' => $user->completed_transactions,
            'ranking_name_visible' => (bool) $user->ranking_name_visible,
            'profile_complete' => $user->profile_completed_at !== null,
        ];
    }
}
