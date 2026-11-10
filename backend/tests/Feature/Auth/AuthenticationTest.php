<?php

namespace Tests\Feature\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_log_in_and_receives_their_profile_with_role(): void
    {
        $user = User::factory()->projectManager()->create(['password' => 'secret-pass']);

        $this->fromSpa()
            ->postJson('/api/login', ['email' => $user->email, 'password' => 'secret-pass'])
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.role.slug', 'project_manager')
            ->assertJsonMissingPath('data.password');

        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_wrong_password_returns_a_generic_validation_error(): void
    {
        $user = User::factory()->create();

        $this->fromSpa()
            ->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => __('auth.failed')]);

        $this->assertGuest('web');
    }

    public function test_unknown_email_gets_the_same_error_as_a_wrong_password(): void
    {
        $this->fromSpa()
            ->postJson('/api/login', ['email' => 'nobody@example.com', 'password' => 'whatever'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => __('auth.failed')]);
    }

    public function test_deactivated_user_cannot_log_in_even_with_correct_password(): void
    {
        $user = User::factory()->inactive()->create(['password' => 'secret-pass']);

        $this->fromSpa()
            ->postJson('/api/login', ['email' => $user->email, 'password' => 'secret-pass'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Your account has been deactivated.');

        $this->assertGuest('web');
    }

    public function test_login_is_throttled_after_repeated_failures(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < LoginRequest::MAX_ATTEMPTS; $i++) {
            $this->fromSpa()->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])
                ->assertUnprocessable();
        }

        $this->fromSpa()
            ->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertStatus(429);
    }

    public function test_guest_cannot_fetch_the_current_user(): void
    {
        $this->fromSpa()->getJson('/api/user')->assertUnauthorized();
    }

    public function test_authenticated_user_can_fetch_their_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.role.slug', 'team_member');
    }

    public function test_user_can_log_out(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass']);
        $this->fromSpa()->postJson('/api/login', ['email' => $user->email, 'password' => 'secret-pass']);

        $this->fromSpa()->postJson('/api/logout')->assertNoContent();

        $this->assertGuest('web');
    }

    public function test_user_deactivated_mid_session_is_rejected_and_logged_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->forceFill(['is_active' => false])->save();

        $this->getJson('/api/user')
            ->assertForbidden()
            ->assertJsonPath('message', 'Your account has been deactivated.');

        $this->assertGuest('web');
    }
}
