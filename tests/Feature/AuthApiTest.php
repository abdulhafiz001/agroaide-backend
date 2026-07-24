<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_returns_token_and_profile(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'fullName' => 'Test Farmer',
            'email' => 'farmer@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'crops' => ['Maize', 'Cassava'],
            'farmLatitude' => 9.05,
            'farmLongitude' => 7.49,
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['token', 'profile' => ['email', 'fullName']]);

        $this->assertDatabaseHas('users', ['email' => 'farmer@example.com']);
    }

    public function test_login_with_email_returns_token(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'password123',
            'name' => 'Login Farmer',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'identifier' => 'login@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'profile']);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_me_returns_profile_when_authenticated(): void
    {
        $user = User::factory()->create([
            'email' => 'me@example.com',
            'name' => 'Me Farmer',
        ]);
        $token = $user->createToken('mobile-app')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('profile.email', 'me@example.com');
    }
}
