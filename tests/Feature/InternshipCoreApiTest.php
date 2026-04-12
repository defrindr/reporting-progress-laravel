<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Period;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InternshipCoreApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Admin', 'Supervisor', 'Intern'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_user_can_register_with_email_and_get_intern_role(): void
    {
        $institution = Institution::create([
            'name' => 'Politeknik A',
            'type' => 'vocational',
        ]);

        Period::create([
            'institution_id' => $institution->id,
            'type' => Period::TYPE_INTERNSHIP,
            'name' => 'Periode Magang Politeknik A',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'holidays' => [],
        ]);

        $response = $this->postJson('/api/register', [
            'name' => 'User Baru',
            'email' => 'baru@example.com',
            'password' => 'password123',
            'institution_id' => $institution->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', ['email' => 'baru@example.com']);

        $user = User::where('email', 'baru@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('Intern'));
    }

    public function test_only_admin_can_create_institution(): void
    {
        $intern = User::factory()->create();
        $intern->assignRole('Intern');

        $this->actingAs($intern)
            ->postJson('/api/institutions', [
                'name' => 'Institusi X',
                'type' => 'university',
            ])
            ->assertForbidden();

        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)
            ->postJson('/api/institutions', [
                'name' => 'Institusi Y',
                'type' => 'university',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Institusi Y');
    }

    public function test_logbook_rejects_holiday_submission(): void
    {
        $institution = Institution::create([
            'name' => 'Universitas B',
            'type' => 'university',
        ]);

        $intern = User::factory()->create(['institution_id' => $institution->id]);
        $intern->assignRole('Intern');

        $period = Period::create([
            'institution_id' => $institution->id,
            'type' => Period::TYPE_INTERNSHIP,
            'name' => 'Magang Ganjil',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'holidays' => ['2026-02-02'],
        ]);
        $period->interns()->sync([$intern->id]);

        $this->actingAs($intern)
            ->post('/api/logbooks', [
                'report_date' => '2026-02-02',
                'done_tasks' => 'Sudah dikerjakan',
                'next_tasks' => 'Akan dikerjakan',
                'appendix_link' => 'https://drive.google.com/file/d/example/view',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cannot submit reports on holidays');
    }

    public function test_project_status_comment_activity_and_reporting_summary_work(): void
    {
        $institution = Institution::create([
            'name' => 'SMK C',
            'type' => 'vocational',
        ]);

        $period = Period::create([
            'institution_id' => $institution->id,
            'type' => Period::TYPE_INTERNSHIP,
            'name' => 'Batch 1',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'holidays' => [],
        ]);

        $admin = User::factory()->create(['institution_id' => $institution->id]);
        $admin->assignRole('Admin');

        $intern = User::factory()->create(['institution_id' => $institution->id]);
        $intern->assignRole('Intern');
        $period->interns()->sync([$intern->id]);

        $project = Project::create([
            'title' => 'Week 1 Laravel',
            'description' => 'Belajar dasar Laravel',
            'assignee_id' => $intern->id,
            'status' => 'todo',
        ]);

        $this->actingAs($admin)
            ->patchJson('/api/projects/'.$project->id.'/status', ['status' => 'doing'])
            ->assertOk();

        $this->actingAs($admin)
            ->patchJson('/api/projects/'.$project->id.'/status', ['status' => 'done'])
            ->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'subject_id' => $project->id,
            'subject_type' => Project::class,
        ]);

        $this->actingAs($intern)
            ->postJson('/api/projects/'.$project->id.'/comments', ['body' => 'Progress sudah sesuai target'])
            ->assertCreated();

        $this->assertDatabaseHas('comments', [
            'commentable_id' => $project->id,
            'commentable_type' => Project::class,
        ]);

        $this->actingAs($intern)
            ->postJson('/api/logbooks', [
                'report_date' => '2026-02-10',
                'done_tasks' => 'Selesai setup docker',
                'next_tasks' => 'Mulai API fitur',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('logbooks', [
            'period_id' => $period->id,
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => 'done',
        ]);

        $summary = $this->actingAs($admin)
            ->getJson('/api/reports/summary?period_id='.$period->id.'&institution_id='.$institution->id)
            ->assertOk();

        $summary->assertJsonPath('total_projects_done', 1);
        $summary->assertJsonPath('total_logbooks_approved', 0);
    }
}
