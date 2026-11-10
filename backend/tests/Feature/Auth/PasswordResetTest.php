<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_link_is_sent_and_points_to_the_frontend(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->fromSpa()
            ->postJson('/api/forgot-password', ['email' => $user->email])
            ->assertOk();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;

            return str_starts_with($url, config('app.frontend_url').'/reset-password?')
                && str_contains($url, 'token='.$notification->token)
                && str_contains($url, 'email='.urlencode($user->email));
        });
    }

    public function test_unknown_email_gets_the_same_response_and_no_email(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $known = $this->fromSpa()->postJson('/api/forgot-password', ['email' => $user->email]);
        $unknown = $this->fromSpa()->postJson('/api/forgot-password', ['email' => 'nobody@example.com']);

        $unknown->assertOk();
        $this->assertSame($known->json(), $unknown->json());
        Notification::assertSentTimes(ResetPassword::class, 1);
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->fromSpa()
            ->postJson('/api/reset-password', [
                'token' => $token,
                'email' => $user->email,
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));
    }

    public function test_invalid_token_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->fromSpa()
            ->postJson('/api/reset-password', [
                'token' => 'not-a-real-token',
                'email' => $user->email,
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertFalse(Hash::check('new-secure-password', $user->fresh()->password));
    }

    public function test_password_must_be_confirmed(): void
    {
        $user = User::factory()->create();

        $this->fromSpa()
            ->postJson('/api/reset-password', [
                'token' => Password::createToken($user),
                'email' => $user->email,
                'password' => 'new-secure-password',
                'password_confirmation' => 'different',
            ])
            ->assertJsonValidationErrors('password');
    }
}
