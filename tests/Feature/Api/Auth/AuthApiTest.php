<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    private function loginAndGetToken(User $user, string $password = 'password'): string
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => $password,
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email'],
                    'token',
                    'type',
                ],
            ]);

        return $response->json('data.token');
    }

    public function test_registers_user_and_returns_token(): void
    {
        $payload = [
            'name'     => 'Admin User',
            'email'    => 'admin@example.com',
            'password' => 'password',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response
            ->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email'],
                    'token',
                    'type',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@example.com',
        ]);
    }

    public function test_logs_in_existing_user_and_returns_token(): void
    {
        $user = User::factory()->create([
            'email'    => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'admin@example.com',
            'password' => 'password',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email'],
                    'token',
                    'type',
                ],
            ]);
    }

    public function test_login_with_invalid_credentials_returns_422(): void
    {
        $user = User::factory()->create([
            'email'    => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'admin@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create([
            'email'    => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        $token = $this->loginAndGetToken($user);

        $response = $this->getJson('/api/v1/auth/me', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.email', $user->email);
    }

    public function test_refresh_returns_new_token(): void
    {
        $user = User::factory()->create([
            'email'    => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        $oldToken = $this->loginAndGetToken($user);

        $response = $this->postJson('/api/v1/auth/refresh', [], [
            'Authorization' => 'Bearer '.$oldToken,
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email'],
                    'token',
                    'type',
                ],
            ]);

        $newToken = $response->json('data.token');

        $this->assertNotSame($oldToken, $newToken);
    }

    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create([
            'email'    => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        $token = $this->loginAndGetToken($user);

        // logout
        $logoutResponse = $this->postJson('/api/v1/auth/logout', [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $logoutResponse->assertOk();

        // el mismo token ya no debe funcionar
        $meResponse = $this->getJson('/api/v1/auth/me', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $meResponse->assertUnauthorized();
    }
}
