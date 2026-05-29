<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckActiveUser
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && !Auth::user()->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            \Filament\Notifications\Notification::make()
                ->title(__('Your account is deactivated. Please contact the administrator.'))
                ->danger()
                ->send();

            return redirect()->route('filament.admin.auth.login');
        }

        return $next($request);
    }
}
