<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ], 201);
    }

    /**
     * Login user
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = User::where('email', $request->email)->firstOrFail();
         $code = $user->generateMfaCode();
        $this->sendMfaEmail($user, $code);

        return response()->json([
            'success' => true,
            'message' => 'MFA code sent to your email. Please verify to complete login.',
            'data' => [
                'requires_mfa' => true,
                'email' => $user->email,
                'user_id' => $user->id,
                'expires_in' => '2 minutes',
            ]
        ]);
    }


    public function verifyMfa(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'code' => 'required|string|size:6',
        ]);

        $user = User::where('email', $request->email)->firstOrFail();

        // Verify MFA code
        $verification = $user->verifyMfaCode($request->code);

        if (!$verification['success']) {
            return response()->json([
                'success' => false,
                'message' => $verification['message'],
                'locked' => $verification['locked'] ?? false,
                'remaining_attempts' => $verification['remaining_attempts'] ?? null,
                'lock_time' => $verification['locked'] ? $user->getMfaLockTimeRemaining() : null,
            ], 422);
        }

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
                'is_verified' => $user->hasVerifiedEmail(),
            ]
        ]);
    }

    /**
     * Resend MFA code
     */
    public function resendMfaCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->firstOrFail();

        if ($user->isMfaLocked()) {
            return response()->json([
                'success' => false,
                'message' => 'Account is locked due to too many failed attempts.',
                'locked' => true,
                'lock_time' => $user->getMfaLockTimeRemaining(),
            ], 429);
        }

        // Generate new code
        $code = $user->generateMfaCode();
        $this->sendMfaEmail($user, $code);

        return response()->json([
            'success' => true,
            'message' => 'New MFA code sent to your email',
            'expires_in' => '2 minutes'
        ]);
    }

    /**
     * Send MFA code via email
     */
    private function sendMfaEmail($user, $code)
    {
        try {
            Mail::send('emails.mfa-code', [
                'code' => $code,
                'user' => $user,
                'expires_in' => '10 minutes'
            ], function ($message) use ($user) {
                $message->to($user->email)
                        ->subject('🔐 Your MFA Verification Code')
                        ->from(config('mail.from.address'), config('mail.from.name'));
            });
        } catch (\Exception $e) {
            \Log::error('MFA email error: ' . $e->getMessage());
            throw new \Exception('Failed to send MFA code. Please try again.');
        }
    }


    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Get authenticated user
     */
    public function user(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $request->user()
        ]);
    }

    /**
     * Refresh token
     */
    public function refresh(Request $request)
    {
        $user = $request->user();
        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ]);
    }
}