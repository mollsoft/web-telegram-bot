<?php

namespace Mollsoft\WebTelegramBot\Middleware;

use Illuminate\Support\Facades\App;
use Mollsoft\WebTelegramBot\Request;
use Closure;

class LiveMiddleware
{
    public function handle(Request $request, Closure $next, string $period, string $timeout = '3600')
    {
        $request->live(
            intval($period),
            intval($timeout),
        );

        return $next($request);
    }
}
