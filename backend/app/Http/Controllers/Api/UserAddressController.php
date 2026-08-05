<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserAddressRequest;
use App\Http\Resources\UserAddressResource;
use App\Models\UserAddress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class UserAddressController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return UserAddressResource::collection(
            $request->user()->addresses()->orderByDesc('is_default')->latest()->get()
        );
    }

    public function store(StoreUserAddressRequest $request): UserAddressResource
    {
        $address = DB::transaction(function () use ($request) {
            $validated = $request->validated();
            $makeDefault = ($validated['is_default'] ?? false) || ! $request->user()->addresses()->exists();

            if ($makeDefault) {
                $request->user()->addresses()->update(['is_default' => false]);
            }

            return $request->user()->addresses()->create([
                ...$validated,
                'is_default' => $makeDefault,
            ]);
        });

        return new UserAddressResource($address);
    }

    public function update(StoreUserAddressRequest $request, UserAddress $address): UserAddressResource
    {
        abort_unless($address->user_id === $request->user()->id, 403);

        DB::transaction(function () use ($request, $address) {
            $validated = $request->validated();
            if ($validated['is_default'] ?? false) {
                $request->user()->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
            }
            $address->update($validated);
        });

        return new UserAddressResource($address->refresh());
    }

    public function destroy(Request $request, UserAddress $address)
    {
        abort_unless($address->user_id === $request->user()->id, 403);

        DB::transaction(function () use ($request, $address) {
            $wasDefault = $address->is_default;
            $address->delete();

            if ($wasDefault) {
                $request->user()->addresses()->latest()->first()?->update(['is_default' => true]);
            }
        });

        return response()->json(['message' => 'Adres silindi.']);
    }
}