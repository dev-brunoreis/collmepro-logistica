<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class BlingToken extends Model
{
    protected $fillable = [
        'account_type',
        'access_token',
        'refresh_token',
        'expires_at',
        'scope',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'scope' => 'array',
    ];

    /**
     * Check if the token is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if the token will expire in the next 5 minutes
     */
    public function isExpiringSoon(): bool
    {
        return $this->expires_at->isBefore(now()->addMinutes(5));
    }

    /**
     * Get valid token for account type
     */
    public static function getValidToken(string $accountType): ?self
    {
        return self::where('account_type', $accountType)
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Update or create token for account type
     */
    public static function updateOrCreateToken(
        string $accountType,
        string $accessToken,
        ?string $refreshToken,
        int $expiresIn,
        ?array $scope = null
    ): self {
        $expiresAt = now()->addSeconds($expiresIn);

        return self::updateOrCreate(
            ['account_type' => $accountType],
            [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_at' => $expiresAt,
                'scope' => $scope,
            ]
        );
    }
}
