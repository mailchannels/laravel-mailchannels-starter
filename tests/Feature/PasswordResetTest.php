<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\PasswordResetController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function requestReset(): string
    {
        $this->post('/forgot-password', ['email' => 'user@example.test'])
            ->assertSessionHas('status', PasswordResetController::SENT_MESSAGE);

        $payloads = $this->sentPayloads();
        $this->assertCount(1, $payloads);
        $this->assertSame('user@example.test', $payloads[0]['personalizations'][0]['to'][0]['email']);
        $this->assertSame('Reset your password', $payloads[0]['subject']);

        $link = $this->linkFrom($payloads[0], '/reset-password');
        $this->assertStringStartsWith(config('app.url').'/reset-password?token=', $link);
        parse_str(parse_url($link, PHP_URL_QUERY), $query);

        return $query['token'];
    }

    public function test_reset_email_is_sent_and_new_password_can_be_set_once(): void
    {
        $user = User::factory()->create(['email' => 'user@example.test']);
        $token = $this->requestReset();

        $this->get('/reset-password?token='.$token.'&email=user%40example.test')->assertOk()->assertSee($token);

        $form = ['token' => $token, 'email' => 'user@example.test', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'];
        $this->post('/reset-password', $form)->assertRedirect('/login');
        $this->assertTrue(Hash::check('new-password-1', $user->fresh()->password));

        // Single use.
        $form['password'] = $form['password_confirmation'] = 'new-password-2';
        $this->post('/reset-password', $form)->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('new-password-1', $user->fresh()->password));
    }

    public function test_reset_token_expires_after_one_hour(): void
    {
        $user = User::factory()->create(['email' => 'user@example.test']);
        $token = $this->requestReset();

        $this->travel(61)->minutes();

        $this->post('/reset-password', [
            'token' => $token, 'email' => 'user@example.test', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1',
        ])->assertSessionHasErrors('email');
        $this->assertFalse(Hash::check('new-password-1', $user->fresh()->password));
    }

    public function test_unknown_email_gets_identical_response_and_no_email(): void
    {
        $known = User::factory()->create(['email' => 'user@example.test']);

        $unknown = $this->post('/forgot-password', ['email' => 'nobody@example.test']);
        $unknown->assertRedirect()->assertSessionHas('status', PasswordResetController::SENT_MESSAGE);
        $this->assertSame([], $this->sentPayloads());

        $this->post('/forgot-password', ['email' => $known->email])
            ->assertRedirect()->assertSessionHas('status', PasswordResetController::SENT_MESSAGE);
        $this->assertCount(1, $this->sentPayloads());
    }
}
