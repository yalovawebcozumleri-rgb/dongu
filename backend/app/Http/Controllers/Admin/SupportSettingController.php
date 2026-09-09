<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupportSettingController extends Controller
{
    public function edit()
    {
        return Inertia::render('Admin/SupportSettings', ['phone' => SupportSetting::phone()]);
    }

    public function update(Request $request)
    {
        $request->validate(['phone' => ['required', 'string', 'max:30']]);
        // Strip formatting only, never silently remove letters or accept URLs.
        $phone = preg_replace('/[\s()+-]/u', '', (string) $request->input('phone'));
        if (preg_match('/^05[0-9]{9}$/', $phone)) $phone = '9'.$phone;
        $request->merge(['phone' => $phone]);
        $validated = $request->validate(['phone' => ['required', 'regex:/^[1-9][0-9]{7,14}$/']], [
            'phone.required' => 'WhatsApp destek numarasını girin.',
            'phone.regex' => 'Ülke koduyla geçerli bir numara girin. Örnek: +90 541 334 22 19.',
        ]);
        SupportSetting::updateOrCreate(['id' => 1], [
            'whatsapp_phone' => $validated['phone'],
            'changed_by_user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'WhatsApp destek numarası güncellendi.');
    }
}
