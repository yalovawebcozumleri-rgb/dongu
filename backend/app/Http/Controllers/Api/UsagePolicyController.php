<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MarketplaceUsagePolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsagePolicyController extends Controller
{
    public function __invoke(Request $request, MarketplaceUsagePolicyService $service): JsonResponse
    {
        return response()->json(['data' => $service->usage($request->user())]);
    }
}
