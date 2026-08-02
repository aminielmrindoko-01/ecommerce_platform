<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_as_customer_only(): void
    {
        $response = $this->post(route('register.submit'), [
            'name' => 'New Customer',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin', // should be ignored
        ]);

        $response->assertRedirect('/login');

        $user = User::where('email', 'new@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('customer', $user->role);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_user_can_login_and_logout(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->post(route('login.submit'), [
            'email' => 'login@example.com',
            'password' => 'password123',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'))->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_admin_login_redirects_to_dashboard(): void
    {
        User::factory()->admin()->create([
            'email' => 'admin-login@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->post(route('login.submit'), [
            'email' => 'admin-login@example.com',
            'password' => 'password123',
        ])->assertRedirect(route('admin.dashboard'));
    }

    public function test_registration_requires_confirmed_password(): void
    {
        $this->from(route('register'))
            ->post(route('register.submit'), [
                'name' => 'Bad Confirm',
                'email' => 'bad@example.com',
                'password' => 'password123',
                'password_confirmation' => 'mismatch',
            ])
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'bad@example.com']);
    }
}
