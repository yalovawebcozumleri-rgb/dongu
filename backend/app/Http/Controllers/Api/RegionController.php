<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Province;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class RegionController extends Controller
{
    public function provinces(): JsonResponse
    {
        $provinces = Cache::rememberForever('regions.provinces.v1', fn () => Province::query()->orderBy('name')->get(['id', 'name'])
        );

        return response()->json(['data' => $provinces]);
    }

    public function districts(Province $province): JsonResponse
    {
        $districts = Cache::rememberForever("regions.province.{$province->id}.districts.v1", fn () => $province->districts()->orderBy('name')->get(['id', 'province_id', 'name'])
        );

        return response()->json(['data' => $districts]);
    }
}
