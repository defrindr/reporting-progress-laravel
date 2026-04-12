<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Period;
use App\Models\User;
use App\Support\SprintWindow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_login_auto_creates_sprint_for_user_institution(): void
    {
        Carbon::setTestNow('2026-04-12 09:00:00');

        $institution = Institution::create([
            'name' => 'SMKN Login Sprint',
            'type' => 'university',
        ]);

        $user = User::factory()->create([
            'email' => 'autosprint@example.com',
            'password' => Hash::make('password123'),
            'institution_id' => $institution->id,
        ]);

        [$startDate, $endDate] = SprintWindow::resolveRange(Carbon::now(), true);

        $this->post('/login', [
            'email' => 'autosprint@example.com',
            'password' => 'password123',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);

        $this->assertDatabaseHas('periods', [
            'institution_id' => $institution->id,
            'type' => Period::TYPE_SPRINT,
            'start_date' => $startDate->toDateString().' 00:00:00',
            'end_date' => $endDate->toDateString().' 00:00:00',
        ]);

        Carbon::setTestNow();
    }

    public function test_user_can_update_password_from_profile_menu(): void
    {
        $user = User::factory()->create([
            'email' => 'updatepass@example.com',
            'password' => Hash::make('old-password'),
        ]);

        $this->actingAs($user)
            ->put('/profile/password', [
                'current_password' => 'old-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertRedirect();

        $user->refresh();
        $this->assertTrue(Hash::check('new-password-123', $user->password));

        $this->post('/logout')->assertRedirect('/login');

        $this->post('/login', [
            'email' => 'updatepass@example.com',
            'password' => 'new-password-123',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_reuses_existing_overlapping_sprint_for_same_institution(): void
    {
        Carbon::setTestNow('2026-04-12 09:00:00');

        $institution = Institution::create([
            'name' => 'SMKN Overlap Sprint',
            'type' => 'university',
        ]);

        $user = User::factory()->create([
            'email' => 'overlap-sprint@example.com',
            'password' => Hash::make('password123'),
            'institution_id' => $institution->id,
        ]);

        [$startDate, $endDate] = SprintWindow::resolveRange(Carbon::now(), true);

        Period::create([
            'institution_id' => $institution->id,
            'type' => Period::TYPE_SPRINT,
            'name' => 'Sprint Overlap Existing',
            'start_date' => $startDate->copy()->subDay()->toDateString(),
            'end_date' => $endDate->copy()->subDay()->toDateString(),
            'holidays' => [],
        ]);

        $this->post('/login', [
            'email' => 'overlap-sprint@example.com',
            'password' => 'password123',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);

        $this->assertSame(
            1,
            Period::query()
                ->where('institution_id', $institution->id)
                ->where('type', Period::TYPE_SPRINT)
                ->count()
        );

        Carbon::setTestNow();
    }
}
