<?php

namespace App\Http\Controllers\Auth;

use App\Auth\EmailVerificationTokens;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    /** "Check your inbox" page; Laravel's `verified` middleware redirects here. */
    public function notice(Request $request): View|RedirectResponse
    {
        return $request->user()->hasVerifiedEmail()
            ? redirect()->route('dashboard')
            : view('auth.verify-notice');
    }

    /** GET /verify?token=... from the emailed link. */
    public function verify(Request $request, EmailVerificationTokens $tokens): Response
    {
        $user = $tokens->consume((string) $request->query('token', ''));

        if ($user === null) {
            return response()->view('auth.verify-result', ['verified' => false], 400);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->view('auth.verify-result', ['verified' => true]);
    }

    /** Resend the verification email to the signed-in user. */
    public function resend(Request $request): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return back()->with('status', 'A new verification link has been sent.');
    }
}
