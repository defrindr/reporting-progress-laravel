<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Period;
use App\Models\Project;
use App\Models\ProjectSpec;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminProjectBacklogFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Admin', 'Supervisor', 'Intern'] as $name) {
            Role::findOrCreate($name, 'web');
        }
    }

    public function test_admin_can_manage_project_backlog_and_activate_sprint(): void
    {
        $institution = Institution::query()->create([
            'name' => 'Universitas Sprint',
            'type' => 'university',
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $intern = User::factory()->create([
            'institution_id' => $institution->id,
        ]);
        $intern->assignRole('Intern');

        Period::query()->create([
            'institution_id' => $institution->id,
            'type' => Period::TYPE_INTERNSHIP,
            'name' => 'Internship Aktif',
            'start_date' => now()->copy()->subDays(10)->toDateString(),
            'end_date' => now()->copy()->addDays(20)->toDateString(),
        ]);

        $period = Period::query()->create([
            'institution_id' => $institution->id,
            'type' => Period::TYPE_SPRINT,
            'name' => 'Sprint 1',
            'start_date' => now()->copy()->subDay()->toDateString(),
            'end_date' => now()->copy()->addDays(6)->toDateString(),
        ]);

        $this->actingAs($admin)
            ->post('/admin/projects', [
                'name' => 'Project Refactor Flow',
                'description' => 'Project untuk flow backlog-sprint',
                'intern_ids' => [$intern->id],
            ])
            ->assertRedirect();

        $project = ProjectSpec::query()
            ->where('title', 'Project Refactor Flow')
            ->firstOrFail();

        $this->actingAs($admin)
            ->post("/admin/projects/{$project->id}/backlogs", [
                'title' => 'Task Backlog A',
                'description' => 'Task pertama',
                'due_date' => '2026-01-20',
                'priority' => 'high',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post("/admin/projects/{$project->id}/backlogs", [
                'title' => 'Task Backlog B',
                'description' => 'Task kedua',
                'due_date' => '2026-01-25',
                'priority' => 'medium',
            ])
            ->assertRedirect();

        $backlogA = Project::query()->where('title', 'Task Backlog A')->firstOrFail();
        $backlogB = Project::query()->where('title', 'Task Backlog B')->firstOrFail();

        $this->assertDatabaseHas('projects', [
            'id' => $backlogA->id,
            'assignee_id' => null,
            'status' => 'todo',
        ]);

        $this->assertDatabaseHas('projects', [
            'id' => $backlogB->id,
            'assignee_id' => null,
            'status' => 'todo',
        ]);

        $this->actingAs($admin)
            ->patch("/admin/projects/{$project->id}/activate-sprint", [
                'backlog_ids' => [$backlogA->id],
                'assignees' => [
                    $backlogA->id => $intern->id,
                ],
            ])
            ->assertRedirect();

        $backlogA->refresh();
        $this->assertNotNull($backlogA->period_id);

        $this->assertDatabaseHas('periods', [
            'id' => $backlogA->period_id,
            'institution_id' => $institution->id,
            'type' => Period::TYPE_SPRINT,
        ]);

        $this->assertDatabaseHas('projects', [
            'id' => $backlogA->id,
            'period_id' => $backlogA->period_id,
            'assignee_id' => $intern->id,
        ]);

        $this->assertDatabaseHas('projects', [
            'id' => $backlogB->id,
            'period_id' => null,
        ]);

        $this->actingAs($admin)
            ->patch("/admin/projects/{$project->id}/activate-sprint", [
                'backlog_ids' => [$backlogB->id],
                'assignees' => [
                    $backlogB->id => $intern->id,
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'id' => $backlogA->id,
            'period_id' => $backlogA->period_id,
            'assignee_id' => $intern->id,
        ]);

        $backlogB->refresh();
        $this->assertDatabaseHas('projects', [
            'id' => $backlogB->id,
            'period_id' => $backlogA->period_id,
        ]);
    }
}
