<?php

namespace App\Http\Controllers;

use App\Mail\AccountDeletionCodeMail;
use App\Models\LoginCode;
use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountDeletionController extends Controller
{
    public function create(): View
    {
        return view('account-deletion.create');
    }

    public function requestCode(Request $request): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'email:rfc', 'max:255']]);
        $email = mb_strtolower(trim($validated['email']));
        $user = User::where('email', $email)->where('role', User::ROLE_USER)->where('status', 'active')->first();

        if ($user) {
            LoginCode::where('email', $email)->where('intent', LoginCode::INTENT_DELETE)->whereNull('consumed_at')->update(['consumed_at' => now()]);
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            LoginCode::create([
                'email' => $email,
                'intent' => LoginCode::INTENT_DELETE,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(10),
                'requested_ip' => $request->ip(),
            ]);
            Mail::to($email)->send(new AccountDeletionCodeMail($code));
        }

        return back()->withInput(['email' => $email])->with('code_sent', true);
    }

    public function destroy(Request $request, AccountDeletionService $deletion): View
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc'],
            'code' => ['required', 'digits:6'],
            'confirmation' => ['accepted'],
        ]);
        $email = mb_strtolower(trim($validated['email']));
        $loginCode = LoginCode::where('email', $email)->where('intent', LoginCode::INTENT_DELETE)->whereNull('consumed_at')->latest('id')->first();

        if (! $loginCode || $loginCode->expires_at->isPast() || $loginCode->attempts >= LoginCode::MAX_ATTEMPTS) {
            throw ValidationException::withMessages(['code' => 'Kod geçersiz veya süresi dolmuş. Yeni kod iste.']);
        }
        if (! Hash::check($validated['code'], $loginCode->code_hash)) {
            $loginCode->increment('attempts');
            throw ValidationException::withMessages(['code' => 'Girdiğin kod hatalı.']);
        }

        $user = User::where('email', $email)->where('role', User::ROLE_USER)->where('status', 'active')->firstOrFail();
        $loginCode->update(['consumed_at' => now()]);
        $deletion->delete($user, 'web');

        return view('account-deletion.completed');
    }
}

