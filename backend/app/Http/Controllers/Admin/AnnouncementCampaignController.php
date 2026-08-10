<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\DispatchAnnouncementCampaign;
use App\Models\AnnouncementCampaign;
use App\Models\NotificationPreference;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementCampaignController extends Controller
{
    public function index(): Response
    {
        $campaigns = AnnouncementCampaign::query()->with(['createdBy:id,name,email', 'dispatches' => fn ($query) => $query->latest()->limit(5)])
            ->latest()->paginate(20);

        return Inertia::render('Admin/Announcements/Index', [
            'campaigns' => $campaigns,
            'audience' => [
                'activeUsers' => User::where('status', 'active')->count(),
                'marketingOptIns' => NotificationPreference::where('marketing_enabled', true)->count(),
            ],
            'limits' => ['dailyMaximumDays' => 31],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['marketing', 'system'])],
            'title' => ['required', 'string', 'max:100'],
            'body' => ['required', 'string', 'max:500'],
            'audience' => ['required', Rule::in(['all_active', 'selected'])],
            'targetUserIds' => ['nullable', 'array', 'max:500'],
            'targetUserIds.*' => ['integer', 'distinct', 'exists:users,id'],
            'pushEnabled' => ['required', 'boolean'],
            'recurrence' => ['required', Rule::in(['none', 'daily', 'weekly'])],
            'scheduledAt' => ['nullable', 'date'],
            'endsAt' => ['nullable', 'date'],
            'submitAction' => ['required', Rule::in(['draft', 'send_now', 'schedule'])],
        ], [
            'type.required' => 'Duyuru türü seçilmelidir.',
            'type.in' => 'Geçerli bir duyuru türü seçmelisin.',
            'title.required' => 'Başlık alanı zorunludur.',
            'title.string' => 'Başlık geçerli bir metin olmalıdır.',
            'title.max' => 'Başlık en fazla 100 karakter olabilir.',
            'body.required' => 'Mesaj alanı zorunludur.',
            'body.string' => 'Mesaj geçerli bir metin olmalıdır.',
            'body.max' => 'Mesaj en fazla 500 karakter olabilir.',
            'audience.required' => 'Hedef kitle seçilmelidir.',
            'audience.in' => 'Geçerli bir hedef kitle seçmelisin.',
            'targetUserIds.array' => 'Kullanıcı numaraları geçerli bir liste olmalıdır.',
            'targetUserIds.max' => 'Tek duyuruda en fazla 500 kullanıcı seçebilirsin.',
            'targetUserIds.*.integer' => 'Kullanıcı numaraları yalnızca sayı olmalıdır.',
            'targetUserIds.*.distinct' => 'Aynı kullanıcı numarası birden fazla kez girilemez.',
            'targetUserIds.*.exists' => 'Girilen kullanıcı numaralarından biri sistemde bulunamadı.',
            'pushEnabled.required' => 'Push bildirimi tercihi belirtilmelidir.',
            'pushEnabled.boolean' => 'Push bildirimi tercihi geçersizdir.',
            'recurrence.required' => 'Tekrar düzeni seçilmelidir.',
            'recurrence.in' => 'Geçerli bir tekrar düzeni seçmelisin.',
            'scheduledAt.date' => 'Planlanan tarih ve saat geçerli olmalıdır.',
            'endsAt.date' => 'Tekrar bitiş tarihi geçerli olmalıdır.',
            'submitAction.required' => 'Duyuru işlemi seçilmelidir.',
            'submitAction.in' => 'Geçerli bir duyuru işlemi seçmelisin.',
        ]);
        if ($validated['audience'] === 'selected' && empty($validated['targetUserIds'])) {
            throw ValidationException::withMessages(['targetUserIds' => 'Belirli kullanıcılar için en az bir kullanıcı numarası girmelisin.']);
        }
        if ($validated['submitAction'] === 'schedule' && empty($validated['scheduledAt'])) {
            throw ValidationException::withMessages(['scheduledAt' => 'Planlı gönderim için tarih ve saat seçmelisin.']);
        }
        if ($validated['recurrence'] !== 'none' && empty($validated['endsAt'])) {
            throw ValidationException::withMessages(['endsAt' => 'Tekrarlanan duyurular için bitiş tarihi zorunludur.']);
        }

        $start = $validated['submitAction'] === 'send_now' ? now() : (! empty($validated['scheduledAt']) ? Carbon::parse($validated['scheduledAt']) : null);
        $endsAt = ! empty($validated['endsAt']) ? Carbon::parse($validated['endsAt'])->endOfDay() : null;
        if ($start && $validated['submitAction'] === 'schedule' && $start->isPast()) {
            throw ValidationException::withMessages(['scheduledAt' => 'Planlama zamanı gelecekte olmalıdır.']);
        }
        if ($endsAt && $start && $endsAt->lte($start)) {
            throw ValidationException::withMessages(['endsAt' => 'Bitiş tarihi ilk gönderimden sonra olmalıdır.']);
        }
        if ($validated['recurrence'] === 'daily' && $start && $endsAt && $start->diffInDays($endsAt) > 31) {
            throw ValidationException::withMessages(['endsAt' => 'Günlük otomatik duyuru en fazla 31 günlük dönem için kurulabilir.']);
        }

        $status = match ($validated['submitAction']) {
            'draft' => AnnouncementCampaign::STATUS_DRAFT,
            'send_now' => AnnouncementCampaign::STATUS_SENDING,
            default => AnnouncementCampaign::STATUS_SCHEDULED,
        };
        $campaign = AnnouncementCampaign::create([
            'created_by_admin_id' => $request->user()->id,
            'type' => $validated['type'], 'title' => trim($validated['title']), 'body' => trim($validated['body']),
            'audience' => $validated['audience'], 'target_user_ids' => $validated['audience'] === 'selected' ? array_values($validated['targetUserIds']) : null,
            'push_enabled' => $validated['pushEnabled'], 'recurrence' => $validated['recurrence'], 'status' => $status,
            'scheduled_at' => $start, 'next_send_at' => $status === AnnouncementCampaign::STATUS_DRAFT ? null : $start, 'ends_at' => $endsAt,
        ]);
        if ($status === AnnouncementCampaign::STATUS_SENDING) DispatchAnnouncementCampaign::dispatch($campaign->id)->afterResponse();

        return back()->with('success', match ($status) {
            AnnouncementCampaign::STATUS_DRAFT => 'Duyuru taslak olarak kaydedildi.',
            AnnouncementCampaign::STATUS_SENDING => 'Duyuru gönderim kuyruğuna alındı.',
            default => 'Duyuru planlandı.',
        });
    }

    public function update(Request $request, AnnouncementCampaign $announcement): RedirectResponse
    {
        $validated = $request->validate(['action' => ['required', Rule::in(['send_now', 'pause', 'resume', 'cancel'])]]);
        match ($validated['action']) {
            'send_now' => $this->sendNow($announcement),
            'pause' => $announcement->update(['status' => AnnouncementCampaign::STATUS_PAUSED]),
            'resume' => $announcement->update(['status' => AnnouncementCampaign::STATUS_SCHEDULED, 'next_send_at' => $announcement->next_send_at?->isFuture() ? $announcement->next_send_at : now()]),
            'cancel' => $announcement->update(['status' => AnnouncementCampaign::STATUS_CANCELLED, 'next_send_at' => null]),
        };

        return back()->with('success', 'Duyuru durumu güncellendi.');
    }

    public function destroy(AnnouncementCampaign $announcement): RedirectResponse
    {
        abort_if($announcement->status === AnnouncementCampaign::STATUS_SENDING, 422, 'Gönderimi devam eden duyuru silinemez. Önce gönderimin tamamlanmasını beklemelisin.');

        $announcement->delete();

        return back()->with('success', 'Duyuru yönetim listesinden kaldırıldı. Teslim edilmiş kullanıcı bildirimleri korunmaya devam ediyor.');
    }

    private function sendNow(AnnouncementCampaign $campaign): void
    {
        abort_if($campaign->status === AnnouncementCampaign::STATUS_SENDING, 422, 'Duyuru zaten gönderiliyor.');
        $campaign->update(['status' => AnnouncementCampaign::STATUS_SENDING, 'next_send_at' => now()]);
        DispatchAnnouncementCampaign::dispatch($campaign->id)->afterResponse();
    }
}
