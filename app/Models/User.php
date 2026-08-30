<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // ← Add this import


class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable,HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'mfa_code',
        'mfa_code_expires_at',
        'mfa_attempts',
        'mfa_locked_until',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'mfa_code',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'mfa_code_expires_at' => 'datetime',
            'mfa_locked_until' => 'datetime',
        ];
    }

    public function hasVerifiedEmail()
    {
        return !is_null($this->email_verified_at);
    }

    /**
     * Mark email as verified
     */
    public function markEmailAsVerified()
    {
        $this->email_verified_at = now();
        $this->save();
    }

    /**
     * Generate MFA code
     */
    public function generateMfaCode()
    {
        $this->mfa_code = rand(100000, 999999);
        $this->mfa_code_expires_at = now()->addMinutes(10);
        $this->mfa_attempts = 0;
        $this->mfa_locked_until = null;
        $this->save();
        
        return $this->mfa_code;
    }

    /**
     * Verify MFA code with rate limiting
     */
    public function verifyMfaCode($code)
    {
        // Check if locked
        if ($this->isMfaLocked()) {
            return [
                'success' => false,
                'message' => 'Too many failed attempts. Please try again later.',
                'locked' => true
            ];
        }

        // Check if code exists
        if (!$this->mfa_code || !$this->mfa_code_expires_at) {
            return [
                'success' => false,
                'message' => 'No MFA code found. Please request a new one.',
                'locked' => false
            ];
        }

        // Check if expired
        if ($this->mfa_code_expires_at->isPast()) {
            return [
                'success' => false,
                'message' => 'MFA code has expired. Please request a new one.',
                'locked' => false
            ];
        }

        // Check if code matches
        if ($this->mfa_code == $code) {
            // Reset attempts on success
            $this->mfa_attempts = 0;
            $this->clearMfaCode();
            return [
                'success' => true,
                'message' => 'MFA verification successful',
                'locked' => false
            ];
        }

        // Increment failed attempts
        $this->mfa_attempts = ($this->mfa_attempts ?? 0) + 1;
        
        // Lock after 5 failed attempts
        if ($this->mfa_attempts >= 5) {
            $this->mfa_locked_until = now()->addMinutes(15);
            $this->save();
            return [
                'success' => false,
                'message' => 'Too many failed attempts. Account locked for 15 minutes.',
                'locked' => true
            ];
        }
        
        $this->save();
        
        $remainingAttempts = 5 - $this->mfa_attempts;
        return [
            'success' => false,
            'message' => "Invalid code. {$remainingAttempts} attempts remaining.",
            'locked' => false,
            'remaining_attempts' => $remainingAttempts
        ];
    }

    /**
     * Check if MFA is locked
     */
    public function isMfaLocked()
    {
        if (!$this->mfa_locked_until) {
            return false;
        }
        
        if ($this->mfa_locked_until->isPast()) {
            $this->mfa_locked_until = null;
            $this->mfa_attempts = 0;
            $this->save();
            return false;
        }
        
        return true;
    }

    /**
     * Get remaining lock time in seconds
     */
    public function getMfaLockTimeRemaining()
    {
        if (!$this->mfa_locked_until) {
            return 0;
        }
        
        if ($this->mfa_locked_until->isPast()) {
            $this->mfa_locked_until = null;
            $this->mfa_attempts = 0;
            $this->save();
            return 0;
        }
        
        return now()->diffInSeconds($this->mfa_locked_until);
    }

    /**
     * Clear MFA code
     */
    public function clearMfaCode()
    {
        $this->mfa_code = null;
        $this->mfa_code_expires_at = null;
        $this->save();
    }

    /**
     * Check if MFA is required (always true for this implementation)
     */
    public function isMfaRequired()
    {
        return true; // All users require MFA
    }
}
