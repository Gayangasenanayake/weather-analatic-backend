<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureMfaVerified
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // All users must have verified email (MFA)
        if ($user && !$user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'MFA verification required. Please verify your email first.',
                'requires_mfa' => true,
                'email' => $user->email,
            ], 403);
        }

        // Check if MFA is locked
        if ($user && $user->isMfaLocked()) {
            return response()->json([
                'success' => false,
                'message' => 'MFA account is temporarily locked. Please try again later.',
                'locked' => true,
                'lock_time' => $user->getMfaLockTimeRemaining(),
            ], 429);
        }

        return $next($request);
    }
}