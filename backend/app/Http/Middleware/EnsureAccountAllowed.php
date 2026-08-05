<?php

namespace App\Http\Middleware;

use App\Services\ModerationSanctionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountAllowed
{
    public function __construct(private readonly ModerationSanctionService $sanctions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isAdmin()) {
            $this->sanctions->assertAccountAllowed($user);
        }

        return $next($request);
    }
}
