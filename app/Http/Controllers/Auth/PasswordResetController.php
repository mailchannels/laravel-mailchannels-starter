<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/**
 * Password reset on Laravel's built-in password broker: tokens are stored
 * hashed in password_reset_tokens, expire after 60 minutes
 * (config/auth.php) and are deleted once used.
 */
class PasswordResetController extends Controller
{
    public const SENT_MESSAGE = 'If an account exists for that address, we have emailed a password reset link.';

    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'string', 'email', 'max:254']]);

        // The broker's result (sent, unknown user, throttled) is deliberately
        // ignored: every request gets the same response, so the form cannot
        // be used to discover which addresses have accounts.
        Password::sendResetLink(['email' => Str::lower((string) $request->input('email'))]);

        return back()->with('status', self::SENT_MESSAGE);
    }

    public function edit(Request $request): View
    {
        return view('auth.reset-password', [
            'token' => (string) $request->query('token', ''),
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password, // hashed by the model's "hashed" cast
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'This password reset link is invalid or has expired.']);
        }

        return redirect()->route('login')->with('status', 'Your password has been reset. You can now sign in.');
    }
}
