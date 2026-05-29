<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class CheckPasswordChange
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();
            
            // Check if password is the default '1234'
            if (Hash::check('1234', $user->password)) {
                // If not already on the force-change-password page, redirect them.
                if (!$request->routeIs('filament.admin.pages.force-change-password') && 
                    !$request->routeIs('filament.admin.auth.logout')) {
                    return redirect()->route('filament.admin.pages.force-change-password');
                }
            }
        }

        return $next($request);
    }
}
