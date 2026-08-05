<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceUsagePolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MarketplaceUsagePolicyController extends Controller
{
    public function edit(): Response
    {
        $policy = MarketplaceUsagePolicy::current()->load('updatedBy:id,name,email');

        return Inertia::render('Admin/UsagePolicies/Edit', [
            'policy' => $policy->only($policy->getFillablePolicyFields()),
            'meta' => [
                'updatedAt' => $policy->updated_at,
                'updatedBy' => $policy->updatedBy?->only('id', 'name', 'email'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $fields = (new MarketplaceUsagePolicy)->getFillablePolicyFields();
        $rules = collect($fields)->mapWithKeys(fn ($field) => [$field => ['required', 'integer', $field === 'contact_cooldown_seconds' ? 'min:0' : 'min:1', 'max:10000']])->all();
        $rules['new_account_pickup_limit'][] = 'lte:new_account_contact_limit';
        $rules['pickup_24h_limit'][] = 'lte:contact_24h_limit';
        $rules['new_account_message_conversation_limit'][] = 'lte:new_account_contact_limit';
        $rules['message_conversation_24h_limit'][] = 'lte:contact_24h_limit';
        $rules['messages_per_minute'][] = 'lte:messages_per_hour';
        $rules['messages_per_hour'][] = 'lte:messages_per_24h';
        $validated = $request->validate($rules, [
            'required' => ':attribute alanı zorunludur.',
            'integer' => ':attribute tam sayı olmalıdır.',
            'min' => ':attribute en az :min olmalıdır.',
            'max' => ':attribute en fazla :max olabilir.',
            'new_account_pickup_limit.lte' => 'Yeni hesap talep limiti, yeni hesap toplam görüşme limitinden büyük olamaz.',
            'pickup_24h_limit.lte' => 'Normal hesap talep limiti, normal hesap toplam görüşme limitinden büyük olamaz.',
            'new_account_message_conversation_limit.lte' => 'Yeni hesap mesaj görüşmesi limiti, yeni hesap toplam görüşme limitinden büyük olamaz.',
            'message_conversation_24h_limit.lte' => 'Normal hesap mesaj görüşmesi limiti, normal hesap toplam görüşme limitinden büyük olamaz.',
            'messages_per_minute.lte' => 'Dakikalık mesaj limiti, saatlik mesaj limitinden büyük olamaz.',
            'messages_per_hour.lte' => 'Saatlik mesaj limiti, günlük mesaj limitinden büyük olamaz.',
        ], [
            'new_account_hours' => 'Yeni hesap dönemi',
            'new_account_listing_limit' => 'Yeni hesap ilan limiti',
            'listing_24h_limit' => 'Normal hesap ilan limiti',
            'active_listing_limit' => 'Aktif ilan kontenjanı',
            'new_account_pickup_limit' => 'Yeni hesap talep limiti',
            'pickup_24h_limit' => 'Normal hesap talep limiti',
            'active_pickup_limit' => 'Aktif talep kontenjanı',
            'listing_pending_pickup_limit' => 'İlan başına bekleyen alıcı',
            'new_account_contact_limit' => 'Yeni hesap toplam görüşme limiti',
            'contact_24h_limit' => 'Normal hesap toplam görüşme limiti',
            'new_account_message_conversation_limit' => 'Yeni hesap mesaj görüşmesi limiti',
            'message_conversation_24h_limit' => 'Normal hesap mesaj görüşmesi limiti',
            'same_seller_contact_24h_limit' => 'Aynı satıcıyla yeni görüşme limiti',
            'contact_cooldown_seconds' => 'Görüşmeler arası bekleme süresi',
            'messages_per_minute' => 'Dakikalık mesaj limiti',
            'messages_per_hour' => 'Saatlik mesaj limiti',
            'messages_per_24h' => 'Günlük mesaj limiti',
            'unanswered_message_limit' => 'Yanıtsız mesaj limiti',
        ]);
        MarketplaceUsagePolicy::current()->update([...$validated, 'updated_by' => $request->user()->id]);
        return back()->with('success', 'Kullanım ve spam sınırları güncellendi. Değişiklikler hemen uygulanıyor.');
    }
}
