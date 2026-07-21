<?php

namespace App\Http\Middleware;

use App\Services\NotificationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class ShareNotifikasi
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()) {
            View::share('notifikasi', app(NotificationService::class)->for($request->user()));
        }

        return $next($request);
    }
}
