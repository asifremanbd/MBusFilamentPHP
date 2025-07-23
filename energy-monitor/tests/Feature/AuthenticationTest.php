<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user login with admin role.
     */
    public function test_admin_can_login(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $response = $this->post('/login', [
            'email' => 'admin@test.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($admin);
    }

    /**
     * Test user login with operator role.
     */
    public function test_operator_can_login(): void
    {
        $operator = User::factory()->create([
            'email' => 'operator@test.com',
            'password' => bcrypt('password'),
            'role' => 'operator',
        ]);

        $response = $this->post('/login', [
            'email' => 'operator@test.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($operator);
    }

    /**
     * Test login with invalid credentials.
     */
    public function test_login_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'user@test.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->post('/login', [
            'email' => 'user@test.com',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    /**
     * Test admin middleware.
     */
    public function test_admin_middleware(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $operator = User::factory()->create([
            'role' => 'operator',
        ]);

        // Admin should be able to access admin routes
        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertStatus(200);

        // Operator should not be able to access admin routes
        $this->actingAs($operator)
            ->get('/admin/users')
            ->assertStatus(403);
    }

    /**
     * Test role middleware.
     */
    public function test_role_middleware(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $operator = User::factory()->create([
            'role' => 'operator',
        ]);

        // Test admin role route
        $this->actingAs($admin)
            ->get('/admin')
            ->assertStatus(200);

        // Test operator role route
        $this->actingAs($operator)
            ->get('/operator')
            ->assertStatus(200);

        // Test admin accessing operator route
        $this->actingAs($admin)
            ->get('/operator')
            ->assertStatus(200); // Admin should be able to access all routes

        // Test operator accessing admin route
        $this->actingAs($operator)
            ->get('/admin')
            ->assertStatus(403); // Operator should not be able to access admin routes
    }

    /**
     * Test logout functionality.
     */
    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        $this->assertAuthenticatedAs($user);

        $response = $this->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }
}
