<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSupporter
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isSupporter() && $request->user()?->status === 'active', 403);
        return $next($request);
    }
}
