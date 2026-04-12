<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminPanelFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Admin', 'Supervisor', 'Intern'] as $name) {
            Role::findOrCreate($name, 'web');
        }
    }

    public function test_admin_can_use_web_crud_for_core_master_data(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)
            ->post('/admin/institutions', [
                'name' => 'Universitas Form',
                'type' => 'university',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('institutions', ['name' => 'Universitas Form']);

        $this->actingAs($admin)
            ->post('/admin/roles', [
                'name' => 'Mentor',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('roles', ['name' => 'Mentor']);

        $institutionId = (int) \App\Models\Institution::query()->where('name', 'Universitas Form')->value('id');

        $this->actingAs($admin)
            ->post('/admin/users', [
                'name' => 'Intern Web',
                'email' => 'internweb@example.com',
                'password' => 'password123',
                'institution_id' => $institutionId,
                'roles' => ['Intern'],
            ])
            ->assertRedirect();

        $intern = User::where('email', 'internweb@example.com')->firstOrFail();
        $this->assertTrue($intern->hasRole('Intern'));

        $this->actingAs($admin)
            ->post('/admin/periods', [
                'institution_id' => $institutionId,
                'name' => 'Periode Form',
                'start_date' => '2026-01-01',
                'end_date' => '2026-03-31',
                'holidays' => '2026-01-02,2026-01-03',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('periods', ['name' => 'Periode Form']);

        $this->actingAs($admin)
            ->post('/admin/projects', [
                'title' => 'Spec Week 1 Laravel',
                'specification' => 'Belajar Laravel dasar',
                'intern_ids' => [$intern->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('project_specs', ['title' => 'Spec Week 1 Laravel']);
        $this->assertDatabaseHas('project_spec_user', ['user_id' => $intern->id]);
    }

    public function test_non_admin_cannot_access_admin_panel(): void
    {
        $intern = User::factory()->create();
        $intern->assignRole('Intern');

        $this->actingAs($intern)
            ->get('/admin/users')
            ->assertStatus(403);
    }
}
