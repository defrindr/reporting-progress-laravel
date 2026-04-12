<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Period;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        Storage::fake('local');

        $institution = Institution::create([
            'name' => 'SMK Testing',
            'type' => 'vocational',
        ]);

        $intern = User::factory()->create(['institution_id' => $institution->id]);
        $intern->assignRole('Intern');

        Period::create([
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
                'appendix' => UploadedFile::fake()->create('appendix.pdf', 20),
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
        $institution = Institution::create([
            'name' => 'Kampus Board',
            'type' => 'university',
        ]);

        $intern = User::factory()->create(['institution_id' => $institution->id]);
        $intern->assignRole('Intern');

        $otherIntern = User::factory()->create(['institution_id' => $institution->id]);
        $otherIntern->assignRole('Intern');

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
    }
}
