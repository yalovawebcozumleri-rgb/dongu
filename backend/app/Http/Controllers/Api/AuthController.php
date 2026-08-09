<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\LoginCodeMail;
use App\Models\LoginCode;
use App\Models\User;
use App\Services\ModerationSanctionService;
use App\Services\AccountDeletionService;
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
            // Installed mobile builds can carry an older document version. The
            // server stamps the versions current when consent is recorded.
            'terms_version' => ['nullable', 'string', 'max:40'],
            'privacy_notice_version' => ['nullable', 'string', 'max:40'],
        ], [
            'intent.required' => 'İşlem türü belirtilmedi. Lütfen yeniden deneyin.',
            'intent.in' => 'Geçersiz işlem türü. Lütfen uygulamayı güncelleyip yeniden deneyin.',
            'email.required' => 'E-posta adresi gereklidir.',
            'email.email' => 'Geçerli bir e-posta adresi girin.',
            'name.required_if' => 'Ad ve soyad gereklidir.',
            'name.min' => 'Ad ve soyad en az 3 karakter olmalıdır.',
            'terms_accepted.accepted_if' => 'Devam etmek için Kullanım Şartları ve Gizlilik Politikası’nı kabul etmelisiniz.',
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

        if ($this->isGooglePlayReviewEmail($email)) {
            return response()->json([
                'message' => 'Google Play inceleme hesabı için yeniden kullanılabilir erişim kodunu gir.',
                'data' => ['email' => $email, 'expires_in' => null],
            ], 202);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $loginCode = DB::transaction(function () use ($email, $validated, $request, $code) {
            LoginCode::where('email', $email)->whereNull('consumed_at')->update(['consumed_at' => now()]);

            return LoginCode::create([
                'email' => $email,
                'intent' => $validated['intent'],
                'pending_name' => isset($validated['name']) ? trim($validated['name']) : null,
                'terms_accepted' => (bool) ($validated['terms_accepted'] ?? false),
                'terms_version' => config('legal.documents.terms.version'),
                'privacy_notice_version' => config('legal.documents.privacy.version'),
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

        if ($this->isGooglePlayReviewEmail($email)) {
            return $this->verifyGooglePlayReview($email, $validated['code'], $validated['device_name'], $sanctions);
        }

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

    private function isGooglePlayReviewEmail(string $email): bool
    {
        if (! config('services.google_play_review.enabled')) {
            return false;
        }

        $reviewEmail = mb_strtolower(trim((string) config('services.google_play_review.email')));

        return $reviewEmail !== '' && hash_equals($reviewEmail, $email);
    }

    private function verifyGooglePlayReview(string $email, string $code, string $deviceName, ModerationSanctionService $sanctions): JsonResponse
    {
        $codeHash = (string) config('services.google_play_review.code_hash');
        if ($codeHash === '' || ! Hash::check($code, $codeHash)) {
            throw ValidationException::withMessages(['code' => 'Girdiğin kod hatalı.']);
        }

        $user = User::where('email', $email)->first();
        if (! $user || $user->role !== User::ROLE_USER) {
            throw ValidationException::withMessages(['email' => 'Google Play inceleme hesabı kullanıma hazır değil.']);
        }

        $sanctions->assertAccountAllowed($user);
        if (! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return response()->json(['data' => [
            'user' => $this->userData($user),
            'token' => $user->createToken($deviceName, ['mobile'])->plainTextToken,
        ]]);
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
            'avatar_url' => $user->avatarReference(),
            'avatar_thumbnail_url' => $user->avatarReference(),
            'rating' => $user->rating !== null ? (float) $user->rating : null,
            'rating_count' => (int) $user->rating_count,
            'completed_transactions' => $user->completed_transactions,
            'ranking_name_visible' => (bool) $user->ranking_name_visible,
            'profile_complete' => $user->profile_completed_at !== null,
        ];
    }
}
