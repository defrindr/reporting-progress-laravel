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

        $period = Period::query()->create([
            'institution_id' => $institution->id,
            'name' => 'Sprint 1',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
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
                'assignee_id' => $intern->id,
                'due_date' => '2026-01-20',
                'priority' => 'high',
                'status' => 'todo',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post("/admin/projects/{$project->id}/backlogs", [
                'title' => 'Task Backlog B',
                'description' => 'Task kedua',
                'assignee_id' => $intern->id,
                'due_date' => '2026-01-25',
                'priority' => 'medium',
                'status' => 'doing',
            ])
            ->assertRedirect();

        $backlogA = Project::query()->where('title', 'Task Backlog A')->firstOrFail();
        $backlogB = Project::query()->where('title', 'Task Backlog B')->firstOrFail();

        $this->actingAs($admin)
            ->patch("/admin/projects/{$project->id}/activate-sprint", [
                'period_id' => $period->id,
                'backlog_ids' => [$backlogA->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'id' => $backlogA->id,
            'period_id' => $period->id,
        ]);

        $this->assertDatabaseHas('projects', [
            'id' => $backlogB->id,
            'period_id' => null,
        ]);

        $this->actingAs($admin)
            ->patch("/admin/projects/{$project->id}/activate-sprint", [
                'period_id' => $period->id,
                'backlog_ids' => [$backlogB->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'id' => $backlogA->id,
            'period_id' => null,
        ]);

        $this->assertDatabaseHas('projects', [
            'id' => $backlogB->id,
            'period_id' => $period->id,
        ]);
    }
}
