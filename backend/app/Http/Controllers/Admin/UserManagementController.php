<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\ModerationSanction;
use App\Models\User;
use App\Services\ModerationSanctionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    private const PAGE_SIZES = [50, 100, 250, 500];
    private const FILTER_STATUSES = ['active', 'suspended', 'closed', 'inactive'];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(self::FILTER_STATUSES)],
            'per_page' => ['nullable', 'integer', Rule::in(self::PAGE_SIZES)],
        ]);
        $perPage = (int) ($filters['per_page'] ?? 50);

        $query = User::query()->where('role', User::ROLE_USER)
            ->withCount('listings')
            ->with(['moderationSanctions' => fn ($query) => $this->activeRestrictionQuery($query)])
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $term = trim($search);
                $query->where(fn (Builder $query) => $query->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%"));
            });

        $this->applyStatusFilter($query, $filters['status'] ?? null);

        $users = $query->latest('id')->paginate($perPage)->withQueryString()->through(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status,
            'account_state' => $this->accountState($user),
            'account_state_label' => $this->accountStateLabel($this->accountState($user)),
            'restriction_ends_at' => $this->currentRestriction($user)?->ends_at?->format('d.m.Y H:i'),
            'listings_count' => $user->listings_count,
            'completed_transactions' => $user->completed_transactions,
            'rating' => $user->rating,
            'rating_count' => $user->rating_count,
            'created_at' => $user->created_at?->format('d.m.Y H:i'),
        ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => ['search' => $filters['search'] ?? '', 'status' => $filters['status'] ?? '', 'per_page' => $perPage],
            'counts' => $this->counts(),
            'pageSizes' => self::PAGE_SIZES,
        ]);
    }

    public function show(User $user): Response
    {
        abort_if($user->isAdmin(), 404);
        $user->load([
            'listings' => fn ($query) => $query->with('materials')->latest('id')->limit(10),
            'moderationSanctions' => fn ($query) => $query->with(['appliedBy:id,name', 'revokedBy:id,name'])->latest('id'),
        ]);

        $state = $this->accountState($user);

        return Inertia::render('Admin/Users/Show', [
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status,
                'account_state' => $state,
                'account_state_label' => $this->accountStateLabel($state),
                'rating' => $user->rating,
                'rating_count' => $user->rating_count,
                'completed_transactions' => $user->completed_transactions,
                'created_at' => $user->created_at?->format('d.m.Y H:i'),
                'listings' => $user->listings->map(fn (Listing $listing) => ['id' => $listing->id, 'status' => $listing->status, 'public_area' => $listing->public_area, 'published_at' => $listing->published_at?->format('d.m.Y H:i')]),
                'sanctions' => $user->moderationSanctions->map(fn (ModerationSanction $sanction) => [
                    'id' => $sanction->id,
                    'action' => $sanction->action,
                    'label' => ModerationSanctionService::LABELS[$sanction->action] ?? $sanction->action,
                    'reason' => $sanction->reason,
                    'starts_at' => $sanction->starts_at?->format('d.m.Y H:i'),
                    'ends_at' => $sanction->ends_at?->format('d.m.Y H:i'),
                    'active' => $sanction->isActive(),
                    'revoked_at' => $sanction->revoked_at?->format('d.m.Y H:i'),
                    'revoke_reason' => $sanction->revoke_reason,
                    'applied_by' => $sanction->appliedBy?->name,
                    'revoked_by' => $sanction->revokedBy?->name,
                    'source' => $sanction->message_report_id ? 'Mesaj bildirimi #'.$sanction->message_report_id : 'Kullanıcı yönetimi',
                ]),
                'has_active_restriction' => $user->moderationSanctions->contains(fn (ModerationSanction $sanction) => $sanction->isActive()),
            ],
            'actions' => collect(ModerationSanctionService::DIRECT_ACTIONS)
                ->reject(fn (string $action) => $action === 'restore')
                ->map(fn (string $action) => ['value' => $action, 'label' => ModerationSanctionService::LABELS[$action]])
                ->values(),
        ]);
    }

    public function updateAccount(Request $request, User $user, ModerationSanctionService $sanctions): RedirectResponse
    {
        abort_if($user->isAdmin(), 404);
        $validated = $request->validate([
            'action' => ['required', Rule::in(ModerationSanctionService::DIRECT_ACTIONS)],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $sanctions->applyDirect($user, $request->user(), $validated['action'], trim($validated['reason']));

        return back()->with('success', $validated['action'] === 'restore'
            ? 'Hesap yeniden açıldı ve aktif kısıtlamalar kaldırıldı.'
            : (ModerationSanctionService::LABELS[$validated['action']] ?? 'Hesap işlemi').' başarıyla kaydedildi.');
    }

    private function counts(): array
    {
        $base = User::query()->where('role', User::ROLE_USER);
        $active = (clone $base);
        $this->applyStatusFilter($active, 'active');
        $suspended = (clone $base);
        $this->applyStatusFilter($suspended, 'suspended');

        return [
            'all' => $base->count(),
            'active' => $active->count(),
            'suspended' => $suspended->count(),
            'closed' => (clone $base)->where('status', 'closed')->count(),
        ];
    }

    private function applyStatusFilter(Builder $query, ?string $status): void
    {
        if ($status === 'active') {
            $query->where('status', 'active')->whereDoesntHave('moderationSanctions', fn (Builder $query) => $this->activeAccountRestrictionQuery($query));
        } elseif ($status === 'suspended') {
            $query->where('status', 'active')->whereHas('moderationSanctions', fn (Builder $query) => $this->activeAccountRestrictionQuery($query));
        } elseif ($status === 'closed') {
            $query->where('status', 'closed');
        } elseif ($status === 'inactive') {
            $query->whereNotIn('status', ['active', 'closed']);
        }
    }

    private function activeRestrictionQuery($query): void
    {
        $query->whereNull('revoked_at')
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->where(fn (Builder $query) => $query->where('action', 'like', 'account_suspension_%')->orWhere('action', 'like', 'message_restriction_%')->orWhere('action', ModerationSanction::ACCOUNT_CLOSED));
    }

    private function activeAccountRestrictionQuery($query): void
    {
        $query->whereNull('revoked_at')
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->where('action', 'like', 'account_suspension_%');
    }

    private function currentRestriction(User $user): ?ModerationSanction
    {
        return $user->moderationSanctions->first(fn (ModerationSanction $sanction) => $sanction->isActive());
    }

    private function accountState(User $user): string
    {
        if ($user->status === 'closed') return 'closed';
        if ($user->status !== 'active') return 'inactive';
        if ($user->moderationSanctions->contains(fn (ModerationSanction $sanction) => $sanction->isActive() && str_starts_with($sanction->action, 'account_suspension_'))) return 'suspended';
        return 'active';
    }

    private function accountStateLabel(string $state): string
    {
        return ['active' => 'Aktif', 'suspended' => 'Askıya alınmış', 'closed' => 'Kapatılmış', 'inactive' => 'Pasif'][$state] ?? 'Bilinmiyor';
    }
}
