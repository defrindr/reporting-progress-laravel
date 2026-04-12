<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_accessible(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Masuk ke Akun');
    }

    public function test_guest_is_redirected_from_protected_pages(): void
    {
        $this->get('/projects/board')->assertRedirect('/login');
        $this->get('/logbook')->assertRedirect('/login');
    }

    public function test_user_can_login_and_logout_successfully(): void
    {
        $user = User::factory()->create([
            'email' => 'intern@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->post('/login', [
            'email' => 'intern@example.com',
            'password' => 'password123',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_login_fails_with_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'intern@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->from('/login')
            ->post('/login', [
                'email' => 'intern@example.com',
                'password' => 'wrong-password',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
