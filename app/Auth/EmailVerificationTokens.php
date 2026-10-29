<?php

namespace App\Auth;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One-time email verification tokens.
 *
 * Tokens are 32 random bytes (base64url), stored only as a sha256 hash,
 * expire after 24 hours and can be used once. Issuing a new token for a
 * user revokes their earlier unused tokens.
 */
class EmailVerificationTokens
{
    public const TTL_HOURS = 24;

    private const TABLE = 'email_verification_tokens';

    public function issue(User $user): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $now = Carbon::now();

        DB::transaction(function () use ($user, $token, $now) {
            DB::table(self::TABLE)->where('user_id', $user->getKey())->whereNull('used_at')->delete();
            DB::table(self::TABLE)->insert([
                'user_id' => $user->getKey(),
                'token_hash' => self::hash($token),
                'expires_at' => $now->copy()->addHours(self::TTL_HOURS),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        return $token;
    }

    /**
     * Consume a token. Returns the user it belongs to, or null when the token
     * is unknown, expired or already used.
     */
    public function consume(string $token): ?User
    {
        if ($token === '' || strlen($token) > 128) {
            return null;
        }

        $now = Carbon::now();
        $row = DB::table(self::TABLE)->where('token_hash', self::hash($token))->first();
        if ($row === null || $row->used_at !== null || Carbon::parse($row->expires_at)->lessThanOrEqualTo($now)) {
            return null;
        }

        // Conditional update makes single use hold even for concurrent requests.
        $claimed = DB::table(self::TABLE)
            ->where('id', $row->id)
            ->whereNull('used_at')
            ->update(['used_at' => $now, 'updated_at' => $now]);

        return $claimed === 1 ? User::find($row->user_id) : null;
    }

    /**
     * The lookup is by sha256 digest, so response timing reveals nothing
     * useful about the plaintext token.
     */
    private static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
