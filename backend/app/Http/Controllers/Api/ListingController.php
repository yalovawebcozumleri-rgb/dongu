<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreListingRequest;
use App\Http\Resources\ListingResource;
use App\Models\Listing;
use App\Models\ListingMaterial;
use App\Models\PickupRequest;
use App\Models\UserBlock;
use App\Models\User;
use App\Models\MarketplaceUsageEvent;
use App\Models\Province;
use App\Services\MarketplaceUsagePolicyService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ListingController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'latitude' => ['nullable', 'required_with:longitude,radius', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude,radius', 'numeric', 'between:-180,180'],
            'radius' => ['nullable', 'numeric', 'min:1', 'max:50'],
            'province' => ['nullable', 'string', 'max:80'],
            'material' => ['nullable', 'string', 'in:pet,glass,aluminum'],
            'sort' => ['nullable', 'string', 'in:distance,newest,quantity_desc,price_asc,gain_desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $user = $request->user('sanctum');
        $relations = ['seller', 'materials', 'photos'];
        if ($user) {
            $relations['pickupRequests'] = fn ($query) => $query
                ->where('buyer_id', $user->id)
                ->latest('updated_at');
        }

        $query = Listing::query()
            ->with($relations)
            ->where('status', Listing::STATUS_ACTIVE)
            ->whereNotNull('published_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));

        if ($user) {
            $query->where('user_id', '!=', $user->id)
                ->whereNotIn('user_id', UserBlock::relatedUserIds($user->id));
        }

        if (! empty($filters['province'])) {
            $provinceName = Str::lower(Str::ascii(trim($filters['province'])));
            $provinceMap = Cache::rememberForever('regions.province.normalized-map.v1', fn () => Province::query()
                ->get(['id', 'name'])
                ->mapWithKeys(fn (Province $province) => [
                    Str::lower(Str::ascii($province->name)) => $province->id,
                ])
                ->all());
            $provinceId = $provinceMap[$provinceName] ?? null;

            abort_unless($provinceId, 422, 'Bulunduğun il belirlenemedi. Konumunu yeniden seçip tekrar dene.');
            $query->where('province_id', $provinceId);
        }

        if (! empty($filters['material'])) {
            $query->whereHas('materials', fn ($query) => $query->where('type', $filters['material']));
        }

        $hasLocation = isset($filters['latitude'], $filters['longitude']);
        if ($hasLocation) {
            $latitude = (float) $filters['latitude'];
            $longitude = (float) $filters['longitude'];
            $radius = (float) ($filters['radius'] ?? 10);
            $latitudeDelta = $radius / 111.045;
            $longitudeScale = max(0.01, cos(deg2rad($latitude)));
            $longitudeDelta = $radius / (111.045 * $longitudeScale);
            $distanceSql = '(6371 * ACOS(LEAST(1, COS(RADIANS(?)) * COS(RADIANS(approximate_latitude)) * COS(RADIANS(approximate_longitude) - RADIANS(?)) + SIN(RADIANS(?)) * SIN(RADIANS(approximate_latitude)))))';

            $query->whereBetween('approximate_latitude', [$latitude - $latitudeDelta, $latitude + $latitudeDelta])
                ->whereBetween('approximate_longitude', [$longitude - $longitudeDelta, $longitude + $longitudeDelta])
                ->select('listings.*')
                ->selectRaw("{$distanceSql} AS distance_km", [$latitude, $longitude, $latitude])
                ->having('distance_km', '<=', $radius);
        }

        if ($user) {
            $query->withExists(['favorites as is_favorited' => fn ($query) => $query->where('user_id', $user->id)]);
        }

        $query->orderByRaw('CASE WHEN boosted_until IS NOT NULL AND boosted_until > ? THEN 0 ELSE 1 END', [now()]);
        $sort = $filters['sort'] ?? ($hasLocation ? 'distance' : 'newest');
        match ($sort) {
            'distance' => $hasLocation
                ? $query->orderBy('distance_km')
                : $query->orderByDesc('published_at'),
            'newest' => $query->orderByDesc('published_at'),
            'quantity_desc' => $query
                ->withSum('materials as sort_quantity', 'quantity')
                ->orderByDesc('sort_quantity'),
            'price_asc' => $query
                ->addSelect(['sort_total_price' => ListingMaterial::query()
                    ->selectRaw('COALESCE(SUM(quantity * unit_price), 0)')
                    ->whereColumn('listing_id', 'listings.id')])
                ->orderBy('sort_total_price'),
            'gain_desc' => $query
                ->addSelect(['sort_gain' => ListingMaterial::query()
                    ->selectRaw('COALESCE(SUM(quantity * (1 - unit_price)), 0)')
                    ->whereColumn('listing_id', 'listings.id')])
                ->orderByDesc('sort_gain'),
        };

        return ListingResource::collection(
            $query->orderByDesc('listings.id')->paginate($filters['per_page'] ?? 20)
        );
    }

    public function show(Request $request, Listing $listing): ListingResource
    {
        abort_unless($listing->status === Listing::STATUS_ACTIVE && $listing->published_at, 404);
        $relations = ['seller', 'materials', 'photos'];
        if ($user = $request->user('sanctum')) {
            abort_if(UserBlock::existsBetween($user->id, $listing->user_id), 404);
            $relations['pickupRequests'] = fn ($query) => $query
                ->where('buyer_id', $user->id)
                ->latest('updated_at');
            $listing->loadExists(['favorites as is_favorited' => fn ($query) => $query->where('user_id', $user->id)]);
        }

        return new ListingResource($listing->load($relations));
    }

    public function mine(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:50']]);
        $listings = $request->user()->listings()
            ->with(['seller', 'materials', 'photos'])
            ->latest()
            ->paginate($filters['per_page'] ?? 20);

        return ListingResource::collection($listings);
    }

    public function store(StoreListingRequest $request, MarketplaceUsagePolicyService $usagePolicy): ListingResource
    {
        $validated = $request->validated();
        $storedPaths = [];

        try {
            $listing = DB::transaction(function () use ($request, $validated, &$storedPaths, $usagePolicy) {
                $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
                $usagePolicy->assertListingAllowed($user);
                $savedAddress = ! empty($validated['address_id'])
                    ? $request->user()->addresses()->findOrFail($validated['address_id'])
                    : null;
                $latitude = $savedAddress?->latitude ?? (string) $validated['latitude'];
                $longitude = $savedAddress?->longitude ?? (string) $validated['longitude'];
                $publicArea = $savedAddress?->public_area ?? trim($validated['public_area']);
                $exactAddress = $savedAddress?->full_address ?? trim($validated['exact_address']);
                $deliveryNotes = $savedAddress?->delivery_notes ?? ($validated['delivery_notes'] ?? null);

                $listing = $user->listings()->create([
                    'status' => Listing::STATUS_ACTIVE,
                    'public_area' => $publicArea,
                    'province_id' => $savedAddress?->province_id,
                    'district_id' => $savedAddress?->district_id,
                    'approximate_latitude' => round((float) $latitude, 3),
                    'approximate_longitude' => round((float) $longitude, 3),
                    'description' => trim($validated['description']),
                    'packaging_condition_confirmed_at' => now(),
                    'packaging_condition_version' => Listing::PACKAGING_CONDITION_VERSION,
                    'published_at' => now(),
                    'expires_at' => now()->addDays(config('marketplace.listing_lifetime_days')),
                ]);

                $listing->materials()->createMany(array_map(fn (array $material) => [
                    'type' => $material['type'],
                    'quantity' => $material['quantity'],
                    'unit_price' => $material['unit_price'],
                ], $validated['materials']));

                $listing->privateLocation()->create([
                    'latitude' => (string) $latitude,
                    'longitude' => (string) $longitude,
                    'address' => $exactAddress,
                    'delivery_notes' => $deliveryNotes ? trim($deliveryNotes) : null,
                ]);

                foreach ($request->file('photos', []) as $index => $photo) {
                    $path = $photo->store("listings/{$user->id}/{$listing->id}", 'public');
                    $storedPaths[] = $path;
                    $listing->photos()->create(['path' => $path, 'sort_order' => $index]);
                }

                $usagePolicy->record($user, MarketplaceUsageEvent::LISTING_CREATED, null, $listing);
                return $listing;
            });
        } catch (\Throwable $error) {
            Storage::disk('public')->delete($storedPaths);
            throw $error;
        }

        return new ListingResource($listing->load(['seller', 'materials', 'photos']));
    }

    public function renew(Request $request, Listing $listing): ListingResource
    {
        abort_unless($listing->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
        abort_unless($listing->status === Listing::STATUS_ACTIVE, 422);
        abort_if($listing->expires_at?->gt(now()->addDays(7)), 422, 'İlan yalnızca yayın süresinin son 7 gününde yenilenebilir.');

        $listing->update([
            'published_at' => now(),
            'expires_at' => now()->addDays(config('marketplace.listing_lifetime_days')),
        ]);

        return new ListingResource($listing->load(['seller', 'materials', 'photos']));
    }

    public function destroy(Request $request, Listing $listing)
    {
        abort_unless($listing->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
        abort_if($listing->pickupRequests()->whereIn('status', [PickupRequest::PENDING, PickupRequest::ACCEPTED])->exists(), 422, 'Açık alım talebi veya rezervasyonu bulunan ilan kaldırılamaz.');
        $paths = $listing->photos()->pluck('path')->all();
        $listing->delete();
        Storage::disk('public')->delete($paths);

        return response()->json(['message' => 'İlan kaldırıldı.']);
    }
}
