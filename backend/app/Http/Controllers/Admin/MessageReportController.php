<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConversationMessage;
use App\Models\MessageReport;
use App\Services\ModerationSanctionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MessageReportController extends Controller
{
    private const PAGE_SIZES = [50, 100, 250, 500];
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'confirmed', 'dismissed'])],
            'reason' => ['nullable', Rule::in(['spam', 'harassment', 'fraud', 'personal_data', 'other'])],
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', Rule::in(self::PAGE_SIZES)],
        ]);

        $perPage = (int) ($filters['per_page'] ?? 50);

        $reports = MessageReport::query()
            ->with([
                'message:id,pickup_request_id,sender_id,body,created_at',
                'message.sender:id,name,email',
                'message.pickupRequest:id,listing_id,buyer_id,seller_id,status',
                'message.pickupRequest.listing:id,public_area',
                'reporter:id,name,email',
                'resolvedBy:id,name,email',
            ])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['reason'] ?? null, fn ($query, $reason) => $query->where('reason', $reason))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $term = trim($search);
                $query->where(fn ($query) => $query->where('id', 'like', "%{$term}%")
                    ->orWhereHas('message', fn ($message) => $message->where('body', 'like', "%{$term}%")
                        ->orWhereHas('sender', fn ($user) => $user->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")))
                    ->orWhereHas('reporter', fn ($user) => $user->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")));
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (MessageReport $report) => $this->summary($report));

        return Inertia::render('Admin/MessageReports/Index', [
            'reports' => $reports,
            'filters' => ['status' => $filters['status'] ?? '', 'reason' => $filters['reason'] ?? '', 'search' => $filters['search'] ?? '', 'per_page' => $perPage],
            'pageSizes' => self::PAGE_SIZES,
            'counts' => [
                'all' => MessageReport::count(),
                'pending' => MessageReport::where('status', MessageReport::PENDING)->count(),
                'confirmed' => MessageReport::where('status', MessageReport::CONFIRMED)->count(),
                'dismissed' => MessageReport::where('status', MessageReport::DISMISSED)->count(),
            ],
        ]);
    }

    public function show(MessageReport $messageReport): Response
    {
        $messageReport->load([
            'message.sender:id,name,email,status',
            'message.pickupRequest.listing:id,public_area,description',
            'message.pickupRequest.buyer:id,name,email',
            'message.pickupRequest.seller:id,name,email',
            'reporter:id,name,email',
            'resolvedBy:id,name,email',
            'sanctions.appliedBy:id,name,email',
            'sanctions.revokedBy:id,name,email',
        ]);
        $message = $messageReport->message;
        $before = ConversationMessage::query()->where('pickup_request_id', $message->pickup_request_id)->where('id', '<', $message->id)->latest('id')->limit(3)->get()->reverse();
        $after = ConversationMessage::query()->where('pickup_request_id', $message->pickup_request_id)->where('id', '>', $message->id)->oldest('id')->limit(3)->get();
        $context = $before->concat([$message])->concat($after)->map(fn (ConversationMessage $item) => [
            'id' => $item->id,
            'body' => $item->body,
            'type' => $item->type,
            'sender_id' => $item->sender_id,
            'is_reported' => $item->id === $message->id,
            'created_at' => $item->created_at?->format('d.m.Y H:i'),
        ])->values();

        return Inertia::render('Admin/MessageReports/Show', [
            'report' => [...$this->summary($messageReport),
                'details' => $messageReport->details,
                'resolution_note' => $messageReport->resolution_note,
                'resolved_by' => $messageReport->resolvedBy?->only('id', 'name', 'email'),
                'resolved_at' => $messageReport->resolved_at?->format('d.m.Y H:i'),
                'sanctions' => $messageReport->sanctions->sortByDesc('id')->values()->map(fn ($sanction) => [
                    'id' => $sanction->id, 'action' => $sanction->action, 'reason' => $sanction->reason,
                    'starts_at' => $sanction->starts_at?->format('d.m.Y H:i'), 'ends_at' => $sanction->ends_at?->format('d.m.Y H:i'),
                    'revoked_at' => $sanction->revoked_at?->format('d.m.Y H:i'), 'revoke_reason' => $sanction->revoke_reason,
                    'applied_by' => $sanction->appliedBy?->name, 'revoked_by' => $sanction->revokedBy?->name,
                ]),
                'conversation' => [
                    'id' => $message->pickup_request_id,
                    'status' => $message->pickupRequest->status,
                    'buyer' => $message->pickupRequest->buyer->only('id', 'name', 'email'),
                    'seller' => $message->pickupRequest->seller->only('id', 'name', 'email'),
                    'listing' => $message->pickupRequest->listing?->only('id', 'public_area', 'description'),
                ],
                'context' => $context,
            ],
        ]);
    }

    public function update(Request $request, MessageReport $messageReport, ModerationSanctionService $sanctions): RedirectResponse
    {
        $validated = $request->validate([
            'resolution' => ['required', Rule::in(['pending', 'confirmed', 'dismissed'])],
            'note' => ['nullable', 'string', 'max:1000', Rule::requiredIf(fn () => $request->string('resolution')->toString() === 'confirmed')],
            'enforcement_action' => ['nullable', Rule::requiredIf(fn () => $request->string('resolution')->toString() === 'confirmed'), Rule::in(ModerationSanctionService::ACTIONS)],
            'remove_message' => ['nullable', 'boolean'],
        ]);
        $sanctions->resolve($messageReport, $request->user(), $validated['resolution'], $validated['enforcement_action'] ?? null, trim((string) ($validated['note'] ?? '')), (bool) ($validated['remove_message'] ?? false));

        return back()->with('success', $validated['resolution'] === MessageReport::PENDING ? 'Bildirim yeniden inceleme sırasına alındı ve aktif yaptırımı geri alındı.' : 'Moderasyon kararı ve yaptırım kaydedildi.');
    }

    private function summary(MessageReport $report): array
    {
        return [
            'id' => $report->id,
            'reason' => $report->reason,
            'status' => $report->status,
            'enforcement_action' => $report->enforcement_action,
            'remove_message' => (bool) $report->remove_message,
            'message' => ['id' => $report->message->id, 'body' => $report->message->body, 'created_at' => $report->message->created_at?->format('d.m.Y H:i')],
            'reported_user' => $report->message->sender?->only('id', 'name', 'email'),
            'reporter' => $report->reporter->only('id', 'name', 'email'),
            'conversation_id' => $report->message->pickup_request_id,
            'listing_area' => $report->message->pickupRequest->listing?->public_area,
            'created_at' => $report->created_at?->format('d.m.Y H:i'),
        ];
    }
}
