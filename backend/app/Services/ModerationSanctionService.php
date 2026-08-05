<?php

namespace App\Services;

use App\Models\MessageReport;
use App\Models\ModerationSanction;
use App\Models\User;
use App\Models\UserReport;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class ModerationSanctionService
{
    public const ACTIONS = [
        ModerationSanction::WARNING,
        ModerationSanction::MESSAGE_24H,
        ModerationSanction::MESSAGE_7D,
        ModerationSanction::MESSAGE_30D,
        ModerationSanction::ACCOUNT_24H,
        ModerationSanction::ACCOUNT_7D,
        ModerationSanction::ACCOUNT_30D,
        ModerationSanction::ACCOUNT_INDEFINITE,
        ModerationSanction::RECORD_ONLY,
    ];

    public const DIRECT_ACTIONS = [
        ModerationSanction::RECORD_ONLY,
        ModerationSanction::WARNING,
        ModerationSanction::MESSAGE_24H,
        ModerationSanction::MESSAGE_7D,
        ModerationSanction::MESSAGE_30D,
        ModerationSanction::ACCOUNT_24H,
        ModerationSanction::ACCOUNT_7D,
        ModerationSanction::ACCOUNT_30D,
        ModerationSanction::ACCOUNT_INDEFINITE,
        ModerationSanction::ACCOUNT_CLOSED,
        'restore',
    ];

    public const LABELS = [
        ModerationSanction::RECORD_ONLY => 'Yalnızca yönetici notu ekle',
        ModerationSanction::WARNING => 'Kullanıcıya uyarı ver',
        ModerationSanction::MESSAGE_24H => 'Mesajlaşmayı 24 saat kısıtla',
        ModerationSanction::MESSAGE_7D => 'Mesajlaşmayı 7 gün kısıtla',
        ModerationSanction::MESSAGE_30D => 'Mesajlaşmayı 30 gün kısıtla',
        ModerationSanction::ACCOUNT_24H => 'Hesabı 24 saat askıya al',
        ModerationSanction::ACCOUNT_7D => 'Hesabı 7 gün askıya al',
        ModerationSanction::ACCOUNT_30D => 'Hesabı 30 gün askıya al',
        ModerationSanction::ACCOUNT_INDEFINITE => 'Hesabı süresiz askıya al',
        ModerationSanction::ACCOUNT_CLOSED => 'Hesabı kapat',
        'restore' => 'Hesabı yeniden aç ve kısıtlamaları kaldır',
    ];

    public function resolve(MessageReport $report, User $admin, string $resolution, ?string $action, string $note, bool $removeMessage): void
    {
        DB::transaction(function () use ($report, $admin, $resolution, $action, $note, $removeMessage) {
            $report = MessageReport::query()->with('message')->lockForUpdate()->findOrFail($report->id);
            $this->revokeReportSanctions($report, $admin, 'Moderasyon kararı değiştirildi.');

            if ($resolution !== MessageReport::CONFIRMED) {
                $this->restoreMessageIfPossible($report);
                $report->update(['status' => $resolution, 'enforcement_action' => null, 'remove_message' => false, 'resolution_note' => $note ?: null, 'resolved_by_admin_id' => $resolution === MessageReport::PENDING ? null : $admin->id, 'resolved_at' => $resolution === MessageReport::PENDING ? null : now()]);
                return;
            }

            $sanction = ModerationSanction::create([
                'user_id' => $report->message->sender_id,
                'message_report_id' => $report->id,
                'action' => $action,
                'reason' => $note,
                'starts_at' => now(),
                'ends_at' => $this->endsAt($action),
                'applied_by_admin_id' => $admin->id,
            ]);
            $report->update(['status' => MessageReport::CONFIRMED, 'enforcement_action' => $action, 'remove_message' => $removeMessage, 'resolution_note' => $note, 'resolved_by_admin_id' => $admin->id, 'resolved_at' => now()]);
            $removeMessage ? $report->message->update(['moderated_at' => now(), 'moderated_by_admin_id' => $admin->id, 'moderation_report_id' => $report->id]) : $this->restoreMessageIfPossible($report);
            if (str_starts_with((string) $action, 'account_suspension_')) $sanction->user->tokens()->delete();
        });

        $report->refresh()->load('message');
        $this->sendResolutionNotifications($report);
    }

    public function applyDirect(User $target, User $admin, string $action, string $reason): void
    {
        DB::transaction(function () use ($target, $admin, $action, $reason) {
            $target = User::query()->lockForUpdate()->findOrFail($target->id);

            if ($action === 'restore') {
                ModerationSanction::query()
                    ->where('user_id', $target->id)
                    ->whereNull('revoked_at')
                    ->where(fn ($query) => $query
                        ->where('action', 'like', 'account_suspension_%')
                        ->orWhere('action', 'like', 'message_restriction_%')
                        ->orWhere('action', ModerationSanction::ACCOUNT_CLOSED))
                    ->update(['revoked_at' => now(), 'revoked_by_admin_id' => $admin->id, 'revoke_reason' => $reason]);
                $target->update(['status' => 'active']);
                return;
            }

            if ($target->status === 'closed') {
                throw new HttpResponseException(response()->json(['message' => 'Kapalı hesaba yeni yaptırım uygulanamaz. Önce hesabı yeniden aç.'], 422));
            }

            if (str_starts_with($action, 'account_suspension_') || $action === ModerationSanction::ACCOUNT_CLOSED) {
                $this->revokeActiveByPrefix($target, $admin, 'account_suspension_', 'Yeni hesap kararı uygulandı.');
            }
            if (str_starts_with($action, 'message_restriction_')) {
                $this->revokeActiveByPrefix($target, $admin, 'message_restriction_', 'Yeni mesajlaşma kararı uygulandı.');
            }

            ModerationSanction::create([
                'user_id' => $target->id,
                'message_report_id' => null,
                'action' => $action,
                'reason' => $reason,
                'starts_at' => now(),
                'ends_at' => $this->endsAt($action),
                'applied_by_admin_id' => $admin->id,
            ]);

            if ($action === ModerationSanction::ACCOUNT_CLOSED) {
                $target->update(['status' => 'closed']);
            }
            if (str_starts_with($action, 'account_suspension_') || $action === ModerationSanction::ACCOUNT_CLOSED) {
                $target->tokens()->delete();
            }
        });

        $this->sendDirectNotification($target, $action, $reason);
    }

    public function resolveUserReport(UserReport $report, User $admin, string $resolution, ?string $action, string $note): void
    {
        $revokedRestriction = false;

        DB::transaction(function () use ($report, $admin, $resolution, $action, $note, &$revokedRestriction) {
            $report = UserReport::query()->with('reportedUser')->lockForUpdate()->findOrFail($report->id);
            $previous = ModerationSanction::query()->where('user_report_id', $report->id)->whereNull('revoked_at')->get();
            $revokedRestriction = $previous->contains(fn (ModerationSanction $sanction) => $sanction->isActive());
            ModerationSanction::query()->where('user_report_id', $report->id)->whereNull('revoked_at')->update([
                'revoked_at' => now(),
                'revoked_by_admin_id' => $admin->id,
                'revoke_reason' => 'Kullanıcı bildirimi kararı değiştirildi.',
            ]);

            if ($resolution === UserReport::CONFIRMED) {
                ModerationSanction::create([
                    'user_id' => $report->reported_user_id,
                    'user_report_id' => $report->id,
                    'message_report_id' => null,
                    'action' => $action,
                    'reason' => $note,
                    'starts_at' => now(),
                    'ends_at' => $this->endsAt($action),
                    'applied_by_admin_id' => $admin->id,
                ]);
                if (str_starts_with((string) $action, 'account_suspension_')) {
                    $report->reportedUser->tokens()->delete();
                }
            }

            $pending = $resolution === UserReport::PENDING;
            $report->update([
                'status' => $resolution,
                'enforcement_action' => $resolution === UserReport::CONFIRMED ? $action : null,
                'resolution_note' => $note ?: null,
                'resolved_by_admin_id' => $pending ? null : $admin->id,
                'resolved_at' => $pending ? null : now(),
            ]);
        });

        $report = $report->fresh()->load(['reportedUser', 'reporter']);
        $notifications = app(UserNotificationService::class);
        $confirmed = $resolution === UserReport::CONFIRMED;
        $notifications->create($report->reporter_id, 'moderation_report_result', 'Kullanıcı bildirimin incelendi', $confirmed ? 'Bildirdiğin kullanıcı incelendi ve gerekli işlem uygulandı.' : ($resolution === UserReport::DISMISSED ? 'Bildirdiğin kullanıcı incelendi; ihlal doğrulanmadı.' : 'Kullanıcı bildirimi yeniden incelemeye alındı.'), ['route' => 'notifications'], "user-report:{$report->id}:result:{$resolution}:".now()->timestamp, null, false);

        if ($confirmed && $action !== ModerationSanction::RECORD_ONLY) {
            $this->sendDirectNotification($report->reportedUser, (string) $action, $note);
        } elseif ($revokedRestriction) {
            $notifications->create($report->reported_user_id, 'moderation_action', 'Hesap kısıtlaman kaldırıldı', 'Önceki moderasyon kararı değiştirildiği için bu bildirime bağlı kısıtlama kaldırıldı.', ['route' => 'notifications'], "user-report:{$report->id}:revoked:".now()->timestamp, null, false);
        }
    }
    public function assertMessagingAllowed(User $user): void
    {
        $this->assertAccountAllowed($user);
        if ($sanction = $this->activeFor($user, 'message_restriction_')) {
            $this->deny($this->restrictionMessage('Mesajlaşman', $sanction), $sanction);
        }
    }

    public function assertAccountAllowed(User $user): void
    {
        if ($user->status !== 'active') {
            $message = $user->status === 'closed'
                ? 'Bu hesap yönetici kararıyla kapatıldı. Destek ekibiyle iletişime geçebilirsin.'
                : 'Bu hesap şu anda kullanıma kapalı. Destek ekibiyle iletişime geçebilirsin.';
            throw new HttpResponseException(response()->json(['message' => $message, 'moderation' => ['action' => $user->status]], 403));
        }
        if ($sanction = $this->activeFor($user, 'account_suspension_')) {
            $this->deny($this->restrictionMessage('Hesabın', $sanction), $sanction);
        }
    }

    public function activeFor(User $user, string $actionPrefix): ?ModerationSanction
    {
        return ModerationSanction::query()->where('user_id', $user->id)->where('action', 'like', $actionPrefix.'%')->whereNull('revoked_at')->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))->latest('id')->first();
    }

    private function revokeActiveByPrefix(User $target, User $admin, string $prefix, string $reason): void
    {
        ModerationSanction::query()->where('user_id', $target->id)->where('action', 'like', $prefix.'%')->whereNull('revoked_at')->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))->update(['revoked_at' => now(), 'revoked_by_admin_id' => $admin->id, 'revoke_reason' => $reason]);
    }

    private function revokeReportSanctions(MessageReport $report, User $admin, string $reason): void
    {
        ModerationSanction::query()->where('message_report_id', $report->id)->whereNull('revoked_at')->update(['revoked_at' => now(), 'revoked_by_admin_id' => $admin->id, 'revoke_reason' => $reason]);
    }

    private function restoreMessageIfPossible(MessageReport $report): void
    {
        $anotherRemoval = MessageReport::query()->where('conversation_message_id', $report->conversation_message_id)->whereKeyNot($report->id)->where('status', MessageReport::CONFIRMED)->where('remove_message', true)->exists();
        if (! $anotherRemoval && $report->message->moderation_report_id === $report->id) {
            $report->message->update(['moderated_at' => null, 'moderated_by_admin_id' => null, 'moderation_report_id' => null]);
        }
    }

    private function sendResolutionNotifications(MessageReport $report): void
    {
        if ($report->status === MessageReport::PENDING) return;
        $notifications = app(UserNotificationService::class);
        $confirmed = $report->status === MessageReport::CONFIRMED;
        $body = $confirmed ? 'Bildirdiğin mesaj incelendi ve gerekli işlem uygulandı.' : 'Bildirdiğin mesaj incelendi; ihlal doğrulanmadı.';
        $notifications->create($report->reporter_id, 'moderation_report_result', 'Bildirimin incelendi', $body, ['route' => 'notifications'], "message-report:{$report->id}:result:{$report->status}", null, false);
        if (! $confirmed || $report->enforcement_action === ModerationSanction::RECORD_ONLY) return;

        [$title, $actionBody] = $this->userNotificationCopy($report);
        $notifications->create($report->message->sender_id, 'moderation_action', $title, $actionBody, ['route' => 'notifications'], "message-report:{$report->id}:action:{$report->enforcement_action}", null, false);
    }

    private function sendDirectNotification(User $target, string $action, string $reason): void
    {
        if ($action === ModerationSanction::RECORD_ONLY || $action === 'restore') return;
        $title = $action === ModerationSanction::WARNING ? 'Topluluk kuralları uyarısı' : (str_starts_with($action, 'message_restriction_') ? 'Mesajlaşma kısıtlaması uygulandı' : ($action === ModerationSanction::ACCOUNT_CLOSED ? 'Hesabın kapatıldı' : 'Hesabın askıya alındı'));
        $body = self::LABELS[$action].'. Gerekçe: '.$reason;
        app(UserNotificationService::class)->create($target->id, 'moderation_action', $title, $body, ['route' => 'notifications'], 'direct-moderation:'.$target->id.':'.$action.':'.now()->timestamp, null, false);
    }

    private function userNotificationCopy(MessageReport $report): array
    {
        $action = $report->enforcement_action;
        if ($action === ModerationSanction::WARNING) return ['Topluluk kuralları uyarısı', 'Bir mesajının kuralları ihlal ettiği doğrulandı. Tekrarı hesabına kısıtlama uygulanmasına neden olabilir.'];
        $sanction = ModerationSanction::query()->where('message_report_id', $report->id)->where('action', $action)->latest('id')->first();
        $until = $sanction?->ends_at?->format('d.m.Y H:i');
        if (str_starts_with((string) $action, 'message_restriction_')) return ['Mesajlaşma kısıtlaması uygulandı', $until ? "Mesajlaşman {$until} tarihine kadar kısıtlandı." : 'Mesajlaşman yönetici kararıyla kısıtlandı.'];
        return ['Hesabın askıya alındı', $until ? "Hesabın {$until} tarihine kadar askıya alındı." : 'Hesabın süresiz olarak askıya alındı.'];
    }

    private function restrictionMessage(string $subject, ModerationSanction $sanction): string
    {
        return $sanction->ends_at ? "{$subject} {$sanction->ends_at->format('d.m.Y H:i')} tarihine kadar kısıtlandı. Gerekçe: {$sanction->reason}" : "{$subject} yönetici kararıyla süresiz olarak kısıtlandı. Gerekçe: {$sanction->reason}";
    }

    private function deny(string $message, ModerationSanction $sanction): never
    {
        throw new HttpResponseException(response()->json(['message' => $message, 'moderation' => ['action' => $sanction->action, 'endsAt' => $sanction->ends_at?->toIso8601String()]], 403));
    }

    private function endsAt(?string $action)
    {
        return match ($action) {
            ModerationSanction::MESSAGE_24H, ModerationSanction::ACCOUNT_24H => now()->addDay(),
            ModerationSanction::MESSAGE_7D, ModerationSanction::ACCOUNT_7D => now()->addDays(7),
            ModerationSanction::MESSAGE_30D, ModerationSanction::ACCOUNT_30D => now()->addDays(30),
            default => null,
        };
    }
}
