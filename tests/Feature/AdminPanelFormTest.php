<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
            ->post('/admin/periods', [
                'institution_id' => $institutionId,
                'type' => 'internship',
                'name' => 'Periode Form',
                'start_date' => '2026-01-01',
                'end_date' => '2026-03-31',
                'holidays' => '2026-01-02,2026-01-03',
                'new_users' => [
                    ['name' => 'Intern Batch Satu', 'email' => 'batch1@example.com'],
                    ['name' => 'Intern Batch Dua', 'email' => 'batch2@example.com'],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('periods', ['name' => 'Periode Form']);
        $this->assertDatabaseHas('users', ['email' => 'batch1@example.com', 'institution_id' => $institutionId]);
        $this->assertDatabaseHas('users', ['email' => 'batch2@example.com', 'institution_id' => $institutionId]);

        $newUserIds = User::query()
            ->whereIn('email', ['batch1@example.com', 'batch2@example.com'])
            ->pluck('id')
            ->map(static fn (int $id): int => (int) $id)
            ->all();

        $csvResponse = $this->actingAs($admin)
            ->get('/admin/periods/new-users-csv?ids='.implode(',', $newUserIds));

        $csvResponse->assertOk();
        $this->assertStringContainsString('batch1@example.com', (string) $csvResponse->streamedContent());

        $periodCreatedIntern = User::query()->where('email', 'batch1@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('password123', $periodCreatedIntern->password));
        $this->assertTrue($periodCreatedIntern->hasRole('Intern'));

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
            ->post('/admin/projects', [
                'title' => 'Spec Week 1 Laravel',
                'specification' => 'Belajar Laravel dasar',
                'intern_ids' => [$intern->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('project_specs', ['title' => 'Spec Week 1 Laravel']);
        $this->assertDatabaseHas('project_spec_user', ['user_id' => $intern->id]);
    }

    public function test_admin_can_delete_user_even_when_user_is_task_assignee(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $institution = Institution::create([
            'name' => 'Universitas Delete Safe',
            'type' => 'university',
        ]);

        $intern = User::factory()->create([
            'institution_id' => $institution->id,
        ]);
        $intern->assignRole('Intern');

        $task = Project::create([
            'title' => 'Task FK User Delete',
            'description' => 'Task untuk memastikan delete user aman',
            'assignee_id' => $intern->id,
            'created_by' => $admin->id,
            'status' => 'todo',
        ]);

        $this->actingAs($admin)
            ->delete('/admin/users/'.$intern->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('users', [
            'id' => $intern->id,
        ]);

        $this->assertDatabaseHas('projects', [
            'id' => $task->id,
            'assignee_id' => null,
        ]);
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
