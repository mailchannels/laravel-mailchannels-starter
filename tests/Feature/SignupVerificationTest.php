<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SignupVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function signUp(string $email = 'user@example.test'): string
    {
        $this->post('/signup', [
            'email' => $email, 'password' => 'correct-horse', 'password_confirmation' => 'correct-horse',
        ])->assertRedirect('/email/verify');

        $payloads = $this->sentPayloads();
        $this->assertCount(1, $payloads);

        return $this->linkFrom($payloads[0], '/verify');
    }

    public function test_signup_stores_unverified_user_and_sends_verification_email(): void
    {
        $link = $this->signUp();

        $user = User::firstWhere('email', 'user@example.test');
        $this->assertNull($user->email_verified_at);
        $this->assertNotSame('correct-horse', $user->password);
        $this->assertTrue(password_verify('correct-horse', $user->password));

        $payload = $this->sentPayloads()[0];
        $this->assertSame('user@example.test', $payload['personalizations'][0]['to'][0]['email']);
        $this->assertSame(config('mail.from.address'), $payload['from']['email']);
        $this->assertSame('Verify your email address', $payload['subject']);
        $this->assertStringStartsWith(config('app.url').'/verify?token=', $link);

        // Only the sha256 hash of the token is stored.
        $token = urldecode(substr($link, strpos($link, 'token=') + 6));
        $this->assertGreaterThanOrEqual(43, strlen($token));
        $this->assertSame(hash('sha256', $token), DB::table('email_verification_tokens')->value('token_hash'));
    }

    public function test_verification_link_marks_user_verified_and_is_single_use(): void
    {
        $link = $this->signUp();
        $path = parse_url($link, PHP_URL_PATH).'?'.parse_url($link, PHP_URL_QUERY);

        $this->get($path)->assertOk()->assertSee('Email verified');
        $this->assertNotNull(User::firstWhere('email', 'user@example.test')->email_verified_at);

        $this->get($path)->assertStatus(400)->assertSee('invalid or has expired');
    }

    public function test_verification_link_expires_after_24_hours(): void
    {
        $link = $this->signUp();
        $path = parse_url($link, PHP_URL_PATH).'?'.parse_url($link, PHP_URL_QUERY);

        $this->travel(24)->hours();
        $this->travel(1)->seconds();

        $this->get($path)->assertStatus(400);
        $this->assertNull(User::firstWhere('email', 'user@example.test')->email_verified_at);
    }

    public function test_unknown_token_is_rejected(): void
    {
        $this->get('/verify?token=not-a-real-token')->assertStatus(400);
        $this->get('/verify')->assertStatus(400);
    }

    public function test_dashboard_requires_verified_email(): void
    {
        $this->signUp();
        $this->get('/dashboard')->assertRedirect('/email/verify');
    }

    public function test_duplicate_email_is_rejected_case_insensitively(): void
    {
        User::factory()->create(['email' => 'user@example.test']);

        $this->post('/signup', [
            'email' => 'User@Example.TEST', 'password' => 'correct-horse', 'password_confirmation' => 'correct-horse',
        ])->assertSessionHasErrors('email');
        $this->assertSame([], $this->sentPayloads());
    }
}
