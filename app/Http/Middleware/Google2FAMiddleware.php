<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Google2FAMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        // If user is logged in and has 2FA enabled
        if ($user && $user->google2fa_enabled) {
            // Check if 2FA is verified in this session
            if (!session('2fa_verified')) {
                // Allow access to 2FA verification routes to avoid infinite loop
                if (!$request->is('2fa/*')) {
                    return redirect()->route('2fa.verify');
                }
            }
        }

        return $next($request);
    }
}
