<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Logbook;
use App\Models\Period;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminEvaluationLabTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Admin', 'Supervisor', 'Intern'] as $name) {
            Role::findOrCreate($name, 'web');
        }
    }

    public function test_evaluation_lab_scores_zero_when_logbook_missing_on_required_day(): void
    {
        $institution = Institution::create([
            'name' => 'Institusi Evaluasi',
            'type' => 'university',
        ]);

        $admin = User::factory()->create([
            'institution_id' => $institution->id,
        ]);
        $admin->assignRole('Admin');

        $intern = User::factory()->create([
            'name' => 'Intern Evaluasi',
            'institution_id' => $institution->id,
        ]);
        $intern->assignRole('Intern');

        $period = Period::create([
            'institution_id' => $institution->id,
            'type' => Period::TYPE_INTERNSHIP,
            'name' => 'Periode Evaluasi April',
            'start_date' => '2026-04-14',
            'end_date' => '2026-04-16',
            'holidays' => ['2026-04-15'],
        ]);
        $period->interns()->sync([$intern->id]);

        Logbook::create([
            'user_id' => $intern->id,
            'period_id' => $period->id,
            'report_date' => '2026-04-14',
            'done_tasks' => 'Done task hari ini',
            'next_tasks' => 'Lanjut task besok',
            'status' => 'submitted',
        ]);

        $task = Project::create([
            'project_spec_id' => null,
            'period_id' => null,
            'title' => 'Task High Priority',
            'description' => 'Task untuk scoring eksperimen',
            'due_date' => '2026-04-16',
            'priority' => 'high',
            'assignee_id' => $intern->id,
            'created_by' => $admin->id,
            'status' => 'done',
        ]);

        $task->forceFill([
            'created_at' => Carbon::parse('2026-04-14 09:00:00'),
            'updated_at' => Carbon::parse('2026-04-14 17:00:00'),
        ])->saveQuietly();

        $response = $this->actingAs($admin)
            ->get('/admin/evaluation-lab?institution_id='.$institution->id.'&period_id='.$period->id.'&intern_id='.$intern->id);

        $response->assertOk();
        $response->assertSee('Evaluation Lab (Eksperimen)');

        $response->assertViewHas('rows', function ($rows) use ($intern): bool {
            $row = collect($rows)->firstWhere('intern_id', $intern->id);

            if (! is_array($row)) {
                return false;
            }

            return (int) $row['required_days'] === 2
                && (int) $row['submitted_days'] === 1
                && (int) $row['missing_days'] === 1
                && (int) $row['zero_days'] === 1
                && abs((float) $row['final_score'] - 3.5) < 0.0001;
        });

        $response->assertViewHas('detailRows', function ($detailRows): bool {
            $rows = collect($detailRows);

            return $rows->contains(static fn (array $row): bool => $row['date'] === '2026-04-16' && (float) $row['score'] === 0.0);
        });
    }
}
