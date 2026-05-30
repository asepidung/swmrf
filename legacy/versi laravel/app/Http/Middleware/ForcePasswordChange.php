<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class ForcePasswordChange
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check() && Auth::user()->must_change_password) {
            $allowedRoutes = [
                'filament.admin.pages.force-change-password',
                'filament.admin.auth.logout',
            ];

            if (! in_array(Route::currentRouteName(), $allowedRoutes)) {
                return redirect()->route('filament.admin.pages.force-change-password');
            }
        }

        return $next($request);
    }
}
