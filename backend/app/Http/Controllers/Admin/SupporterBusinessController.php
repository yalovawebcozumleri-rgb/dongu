<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupporterBusiness;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SupporterBusinessController extends Controller
{
    public function index(): Response
    {
        $today = now()->toDateString();
        return Inertia::render('Admin/Supporters/Index', [
            'supporters' => SupporterBusiness::query()->with('owner:id,name,email,status')
                ->withSum(['dailyStats as impressions' => fn ($query) => $query], 'impressions')
                ->withSum(['dailyStats as detail_views' => fn ($query) => $query], 'detail_views')
                ->withSum(['dailyStats as cta_clicks' => fn ($query) => $query], 'cta_clicks')
                ->with(['dailyStats' => fn ($query) => $query->where('stat_date', $today)])
                ->orderByDesc('is_active')->orderByDesc('priority')->latest('id')->get()
                ->map(fn (SupporterBusiness $business) => $this->resource($business)),
            'scopeOptions' => [
                ['value' => 'district', 'label' => 'İlçe'], ['value' => 'province', 'label' => 'İl'], ['value' => 'nationwide', 'label' => 'Türkiye geneli'],
            ],
            'ctaOptions' => [
                ['value' => 'whatsapp', 'label' => 'WhatsApp'], ['value' => 'phone', 'label' => 'Telefon'],
                ['value' => 'website', 'label' => 'Web sitesi'], ['value' => 'instagram', 'label' => 'Instagram'],
                ['value' => 'directions', 'label' => 'Yol tarifi'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateBusiness($request, null, true);
        $logoPath = $request->file('logo')?->store('supporters', 'public');
        try {
            DB::transaction(function () use ($validated, $logoPath, $request): void {
                $owner = User::create([
                    'name' => trim($validated['accountName']), 'email' => mb_strtolower(trim($validated['accountEmail'])),
                    'password' => Hash::make($validated['accountPassword']), 'role' => User::ROLE_SUPPORTER, 'status' => 'active',
                ]);
                $owner->forceFill(['email_verified_at' => now()])->save();
                SupporterBusiness::create($this->payload($validated, $request, $logoPath) + [
                    'owner_user_id' => $owner->id, 'created_by_admin_id' => $request->user()->id,
                    'slug' => $this->uniqueSlug($validated['name']),
                ]);
            });
        } catch (Throwable $exception) {
            if ($logoPath) Storage::disk('public')->delete($logoPath);
            throw $exception;
        }
        return back()->with('success', 'Destekçi işletme ve panel hesabı oluşturuldu.');
    }

    public function update(Request $request, SupporterBusiness $supporter): RedirectResponse
    {
        $validated = $this->validateBusiness($request, $supporter, false);
        $newLogoPath = $request->file('logo')?->store('supporters', 'public');
        $oldLogoPath = $supporter->logo_path;
        try {
            DB::transaction(function () use ($validated, $newLogoPath, $request, $supporter): void {
                $supporter->update($this->payload($validated, $request, $newLogoPath ?? $supporter->logo_path));
                if ($supporter->owner) {
                    $account = ['name' => trim($validated['accountName']), 'email' => mb_strtolower(trim($validated['accountEmail']))];
                    if (! empty($validated['accountPassword'])) $account['password'] = Hash::make($validated['accountPassword']);
                    $supporter->owner->update($account);
                }
            });
        } catch (Throwable $exception) {
            if ($newLogoPath) Storage::disk('public')->delete($newLogoPath);
            throw $exception;
        }
        if ($newLogoPath && $oldLogoPath) Storage::disk('public')->delete($oldLogoPath);
        return back()->with('success', 'Destekçi bilgileri güncellendi.');
    }

    public function destroy(SupporterBusiness $supporter): RedirectResponse
    {
        $supporter->update(['is_active' => false]);
        $supporter->owner?->update(['status' => 'suspended']);
        $supporter->delete();
        return back()->with('success', 'Destekçi yayından kaldırıldı; geçmiş istatistikleri korundu.');
    }

    private function validateBusiness(Request $request, ?SupporterBusiness $supporter, bool $creating): array
    {
        $ownerId = $supporter?->owner_user_id;
        return $request->validate([
            'name' => ['required', 'string', 'max:120'], 'cardSummary' => ['required', 'string', 'max:180'],
            'detailTitle' => ['required', 'string', 'max:160'], 'detailBody' => ['required', 'string', 'max:3000'],
            'targetScope' => ['required', Rule::in(SupporterBusiness::SCOPES)],
            'provinceCode' => ['nullable', 'required_unless:targetScope,nationwide', 'string', 'max:10'],
            'provinceName' => ['nullable', 'required_unless:targetScope,nationwide', 'string', 'max:80'],
            'districtCode' => ['nullable', 'required_if:targetScope,district', 'string', 'max:20'],
            'districtName' => ['nullable', 'required_if:targetScope,district', 'string', 'max:100'],
            'ctaType' => ['required', Rule::in(SupporterBusiness::CTA_TYPES)], 'ctaLabel' => ['required', 'string', 'max:40'],
            'ctaValue' => ['required', 'string', 'max:500'], 'priority' => ['required', 'integer', 'min:0', 'max:1000'],
            'startsAt' => ['nullable', 'date'], 'endsAt' => ['nullable', 'date', 'after:startsAt'], 'isActive' => ['required', 'boolean'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'accountName' => ['required', 'string', 'max:120'],
            'accountEmail' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($ownerId)],
            'accountPassword' => [$creating ? 'required' : 'nullable', 'string', 'min:8', 'max:72'],
        ], [], [
            'name' => 'işletme adı', 'cardSummary' => 'kart açıklaması', 'detailTitle' => 'detay başlığı',
            'detailBody' => 'detay metni', 'provinceCode' => 'il kodu', 'provinceName' => 'il',
            'districtCode' => 'ilçe kodu', 'districtName' => 'ilçe', 'ctaLabel' => 'buton metni',
            'ctaValue' => 'yönlendirme bilgisi', 'accountName' => 'hesap yetkilisi',
            'accountEmail' => 'hesap e-postası', 'accountPassword' => 'panel şifresi',
        ]);
    }

    private function payload(array $data, Request $request, ?string $logoPath): array
    {
        $nationwide = $data['targetScope'] === 'nationwide';
        $district = $data['targetScope'] === 'district';
        return [
            'name' => trim($data['name']), 'logo_path' => $logoPath, 'card_summary' => trim($data['cardSummary']),
            'detail_title' => trim($data['detailTitle']), 'detail_body' => trim($data['detailBody']),
            'target_scope' => $data['targetScope'], 'province_code' => $nationwide ? null : trim($data['provinceCode']),
            'province_name' => $nationwide ? null : trim($data['provinceName']), 'district_code' => $district ? trim($data['districtCode']) : null,
            'district_name' => $district ? trim($data['districtName']) : null, 'cta_type' => $data['ctaType'],
            'cta_label' => trim($data['ctaLabel']), 'cta_value' => trim($data['ctaValue']), 'priority' => $data['priority'],
            'starts_at' => $data['startsAt'] ?? null, 'ends_at' => $data['endsAt'] ?? null, 'is_active' => $data['isActive'],
        ];
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'destekci';
        $slug = $base; $counter = 2;
        while (SupporterBusiness::withTrashed()->where('slug', $slug)->exists()) $slug = $base.'-'.$counter++;
        return $slug;
    }

    private function resource(SupporterBusiness $business): array
    {
        $today = $business->dailyStats->first();
        return [
            'id' => $business->id, 'name' => $business->name, 'slug' => $business->slug,
            'logoUrl' => $business->logo_path ? url("/api/v1/supporters/{$business->id}/logo") : null,
            'cardSummary' => $business->card_summary, 'detailTitle' => $business->detail_title, 'detailBody' => $business->detail_body,
            'targetScope' => $business->target_scope, 'provinceCode' => $business->province_code, 'provinceName' => $business->province_name,
            'districtCode' => $business->district_code, 'districtName' => $business->district_name,
            'ctaType' => $business->cta_type, 'ctaLabel' => $business->cta_label, 'ctaValue' => $business->cta_value,
            'priority' => $business->priority, 'isActive' => $business->is_active,
            'startsAt' => $business->starts_at?->format('Y-m-d\TH:i'), 'endsAt' => $business->ends_at?->format('Y-m-d\TH:i'),
            'accountName' => $business->owner?->name, 'accountEmail' => $business->owner?->email, 'accountStatus' => $business->owner?->status,
            'today' => ['impressions' => (int) ($today?->impressions ?? 0), 'uniqueReach' => (int) ($today?->unique_reach ?? 0)],
            'totals' => ['impressions' => (int) ($business->impressions ?? 0), 'detailViews' => (int) ($business->detail_views ?? 0), 'ctaClicks' => (int) ($business->cta_clicks ?? 0)],
        ];
    }
}
