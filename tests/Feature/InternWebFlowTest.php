<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Period;
use App\Models\Project;
use App\Models\ProjectSpec;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InternWebFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Admin', 'Supervisor', 'Intern'] as $name) {
            Role::findOrCreate($name, 'web');
        }
    }

    public function test_intern_can_submit_logbook_via_web_form(): void
    {
        $institution = Institution::create([
            'name' => 'SMK Testing',
            'type' => 'university',
        ]);

        $intern = User::factory()->create(['institution_id' => $institution->id]);
        $intern->assignRole('Intern');

        Period::create([
            'institution_id' => $institution->id,
            'type' => Period::TYPE_INTERNSHIP,
            'name' => 'Batch Web',
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
            'holidays' => [],
        ]);

        $this->actingAs($intern)
            ->post('/logbook', [
                'report_date' => '2026-02-10',
                'done_tasks' => 'Selesai setup',
                'next_tasks' => 'Lanjut API',
                'appendix_link' => 'https://drive.google.com/file/d/abc123/view',
            ])
            ->assertRedirect('/logbook');

        $this->assertDatabaseHas('logbooks', [
            'user_id' => $intern->id,
            'report_date' => '2026-02-10 00:00:00',
            'status' => 'submitted',
        ]);
    }

    public function test_intern_cannot_submit_logbook_on_holiday(): void
    {
        $institution = Institution::create([
            'name' => 'Kampus Holiday',
            'type' => 'university',
        ]);

        $intern = User::factory()->create(['institution_id' => $institution->id]);
        $intern->assignRole('Intern');

        Period::create([
            'institution_id' => $institution->id,
            'type' => Period::TYPE_INTERNSHIP,
            'name' => 'Batch Holiday',
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
            'holidays' => ['2026-03-10'],
        ]);

        $this->actingAs($intern)
            ->from('/logbook')
            ->post('/logbook', [
                'report_date' => '2026-03-10',
                'done_tasks' => 'Ini harus gagal',
                'next_tasks' => 'Ini harus gagal',
            ])
            ->assertRedirect('/logbook')
            ->assertSessionHasErrors('report_date');
    }

    public function test_intern_can_advance_own_project_and_comment(): void
    {
        Carbon::setTestNow('2026-04-15 10:00:00');

        $institution = Institution::create([
            'name' => 'Kampus Board',
            'type' => 'university',
        ]);

        $intern = User::factory()->create(['institution_id' => $institution->id]);
        $intern->assignRole('Intern');

        $otherIntern = User::factory()->create(['institution_id' => $institution->id]);
        $otherIntern->assignRole('Intern');

        Period::create([
            'institution_id' => $institution->id,
            'type' => Period::TYPE_INTERNSHIP,
            'name' => 'Periode Aktif Board',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'holidays' => [],
        ]);

        $project = Project::create([
            'title' => 'Task Intern',
            'description' => 'Task milik intern',
            'assignee_id' => $intern->id,
            'status' => 'todo',
        ]);

        $foreignProject = Project::create([
            'title' => 'Task Orang Lain',
            'description' => 'Bukan milik intern ini',
            'assignee_id' => $otherIntern->id,
            'status' => 'todo',
        ]);

        $this->actingAs($intern)
            ->patch('/projects/'.$project->id.'/advance')
            ->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => 'doing',
        ]);

        $this->actingAs($intern)
            ->post('/projects/'.$project->id.'/comment', [
                'body' => 'Update progress terbaru',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('comments', [
            'commentable_id' => $project->id,
            'commentable_type' => Project::class,
            'user_id' => $intern->id,
        ]);

        $this->actingAs($intern)
            ->patch('/projects/'.$foreignProject->id.'/advance')
            ->assertStatus(403);

        Carbon::setTestNow();
    }

    public function test_intern_can_create_self_task_with_project_and_due_date(): void
    {
        Carbon::setTestNow('2026-04-15 10:00:00');

        $institution = Institution::create([
            'name' => 'Kampus Sprint',
            'type' => 'university',
        ]);

        $admin = User::factory()->create(['institution_id' => $institution->id]);
        $admin->assignRole('Admin');

        $intern = User::factory()->create(['institution_id' => $institution->id]);
        $intern->assignRole('Intern');

        Period::create([
            'institution_id' => $institution->id,
            'type' => Period::TYPE_INTERNSHIP,
            'name' => 'Periode Aktif Sprint',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'holidays' => [],
        ]);

        $project = ProjectSpec::create([
            'title' => 'rencanain.id',
            'specification' => 'Project test intern create task',
            'created_by' => $admin->id,
        ]);
        $project->assignedInterns()->sync([$intern->id]);

        $sprint = Period::create([
            'institution_id' => $institution->id,
            'type' => Period::TYPE_SPRINT,
            'name' => 'Sprint Week 4',
            'start_date' => '2027-04-23',
            'end_date' => '2027-04-30',
            'holidays' => [],
        ]);

        $this->actingAs($intern)
            ->post('/projects/tasks', [
                'project_spec_id' => $project->id,
                'title' => 'Task Tambahan Intern',
                'description' => 'Task detail tambahan',
                'due_date' => '2027-04-29',
                'priority' => 'high',
                'sprint_id' => $sprint->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'project_spec_id' => $project->id,
            'title' => 'Task Tambahan Intern',
            'assignee_id' => $intern->id,
            'created_by' => $intern->id,
            'due_date' => '2027-04-29 00:00:00',
            'period_id' => $sprint->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_intern_can_generate_weekly_resume_from_board_tasks_with_local_ai(): void
    {
        $institution = Institution::create([
            'name' => 'Kampus Resume',
            'type' => 'university',
        ]);

        $admin = User::factory()->create(['institution_id' => $institution->id]);
        $admin->assignRole('Admin');

        $intern = User::factory()->create(['institution_id' => $institution->id]);
        $intern->assignRole('Intern');

        $projectSpec = ProjectSpec::create([
            'title' => 'Resume Project',
            'specification' => 'Project untuk test resume logbook',
            'created_by' => $admin->id,
        ]);
        $projectSpec->assignedInterns()->sync([$intern->id]);

        Project::create([
            'project_spec_id' => $projectSpec->id,
            'title' => 'Task Selesai Weekly',
            'description' => 'Task yang selesai minggu ini',
            'assignee_id' => $intern->id,
            'created_by' => $intern->id,
            'due_date' => '2027-04-29',
            'priority' => 'high',
            'status' => 'done',
            'created_at' => Carbon::parse('2027-04-28 09:00:00'),
            'updated_at' => Carbon::parse('2027-04-28 18:00:00'),
        ]);

        Project::create([
            'project_spec_id' => $projectSpec->id,
            'title' => 'Task Lanjutan Weekly',
            'description' => 'Task yang masih berjalan minggu ini',
            'assignee_id' => $intern->id,
            'created_by' => $intern->id,
            'due_date' => '2027-04-30',
            'priority' => 'medium',
            'status' => 'doing',
            'created_at' => Carbon::parse('2027-04-29 10:00:00'),
            'updated_at' => Carbon::parse('2027-04-29 16:00:00'),
        ]);

        $response = $this->actingAs($intern)
            ->get('/logbook/task-resume?report_date=2027-04-29&scope=weekly&use_ai=1')
            ->assertOk();

        $response->assertJsonPath('meta.mode', 'weekly');
        $response->assertJsonPath('meta.generator', 'ai-local');

        $this->assertStringContainsString('Task Selesai Weekly', (string) $response->json('done_tasks'));
        $this->assertStringContainsString('Task Lanjutan Weekly', (string) $response->json('next_tasks'));
    }
}
