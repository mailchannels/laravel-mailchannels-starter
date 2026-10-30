<?php

namespace App\Models;

use App\Auth\EmailVerificationTokens;
use App\Mail\ResetPasswordMail;
use App\Mail\VerifyEmailMail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Mail;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    /**
     * Called by Laravel's built-in Registered event listener. Instead of the
     * default signed URL, send a one-time token link to /verify.
     */
    public function sendEmailVerificationNotification(): void
    {
        $token = app(EmailVerificationTokens::class)->issue($this);
        $url = self::appUrl('/verify', ['token' => $token]);

        Mail::to($this->email)->send(new VerifyEmailMail($url));
    }

    /**
     * Called by Laravel's password broker with a freshly created token.
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $url = self::appUrl('/reset-password', ['token' => $token, 'email' => $this->email]);
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        Mail::to($this->email)->send(new ResetPasswordMail($url, $minutes));
    }

    /**
     * Links are always built from APP_URL, never from the request's Host
     * header, so a forged Host cannot redirect tokens to another site.
     *
     * @param  array<string, string>  $query
     */
    private static function appUrl(string $path, array $query): string
    {
        return rtrim((string) config('app.url'), '/').$path.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
