<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Institution;
use App\Models\Period;
use App\Models\Project;
use App\Models\ProjectSpec;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (['Admin', 'Supervisor', 'Intern'] as $name) {
            Role::findOrCreate($name, 'web');
        }

        $institution = Institution::firstOrCreate([
            'name' => 'SMKN 1 Jenangan',
        ], [
            'type' => 'university',
        ]);

        $admin = User::firstOrCreate([
            'email' => 'admin@example.com',
        ], [
            'name' => 'System Admin',
            'password' => 'password',
            'institution_id' => $institution->id,
        ]);

        $admin->assignRole('Admin');

        $internMap = [
            'a' => 'Intern A',
            'b' => 'Intern B',
            'c' => 'Intern C',
            'd' => 'Intern D',
            'e' => 'Intern E',
        ];

        $internIds = [];
        foreach ($internMap as $code => $name) {
            $intern = User::firstOrCreate([
                'email' => sprintf('%s@smkn1jenangan.local', $code),
            ], [
                'name' => $name,
                'password' => 'password',
                'institution_id' => $institution->id,
            ]);

            $intern->assignRole('Intern');
            $internIds[$code] = $intern->id;
        }

        $internshipPeriod = Period::firstOrCreate([
            'institution_id' => $institution->id,
            'type' => Period::TYPE_INTERNSHIP,
            'name' => 'Magang SMKN 1 Jenangan period Mei-Januari 2027',
        ], [
            'start_date' => '2026-05-01',
            'end_date' => '2027-01-31',
            'holidays' => ['2026-08-17', '2026-12-25', '2027-01-01'],
        ]);

        $internshipPeriod->interns()->syncWithoutDetaching(array_values($internIds));

        $sprintWeeks = [
            ['week' => 1, 'start' => '2027-04-01', 'end' => '2027-04-07'],
            ['week' => 2, 'start' => '2027-04-08', 'end' => '2027-04-14'],
            ['week' => 3, 'start' => '2027-04-15', 'end' => '2027-04-22'],
            ['week' => 4, 'start' => '2027-04-23', 'end' => '2027-04-30'],
        ];

        $sprints = [];
        foreach ($sprintWeeks as $weekData) {
            $sprints[$weekData['week']] = Period::firstOrCreate([
                'institution_id' => $institution->id,
                'type' => Period::TYPE_SPRINT,
                'name' => sprintf('Sprint April 2027 - Week %d', $weekData['week']),
            ], [
                'start_date' => $weekData['start'],
                'end_date' => $weekData['end'],
                'holidays' => [],
            ]);
        }

        $project = ProjectSpec::firstOrCreate([
            'title' => 'rencanain.id',
        ], [
            'specification' => 'Project utama untuk task mandatory dari supervisor/admin dan task tambahan dari intern.',
            'created_by' => $admin->id,
        ]);

        $project->assignedInterns()->syncWithoutDetaching(array_values($internIds));

        $mandatoryCarryTask = Project::firstOrCreate([
            'project_spec_id' => $project->id,
            'title' => 'Mandatory - Integrasi Dashboard KPI',
        ], [
            'period_id' => $sprints[4]->id,
            'description' => 'Task mandatory dari admin. Task ini belum selesai di week 3 dan dibawa ke week 4.',
            'assignee_id' => $internIds['a'],
            'created_by' => $admin->id,
            'due_date' => '2027-04-29',
            'priority' => 'high',
            'status' => 'doing',
        ]);

        Project::firstOrCreate([
            'project_spec_id' => $project->id,
            'title' => 'Mandatory - Setup Initial Tracking',
        ], [
            'period_id' => $sprints[3]->id,
            'description' => 'Task mandatory dari admin yang selesai di week 3.',
            'assignee_id' => $internIds['b'],
            'created_by' => $admin->id,
            'due_date' => '2027-04-21',
            'priority' => 'medium',
            'status' => 'done',
        ]);

        Comment::firstOrCreate([
            'commentable_type' => Project::class,
            'commentable_id' => $mandatoryCarryTask->id,
            'body' => 'Sprint pindah: Sprint April 2027 - Week 3 -> Sprint April 2027 - Week 4 karena task belum selesai.',
        ], [
            'user_id' => $admin->id,
        ]);

        foreach ($internIds as $code => $internId) {
            Project::firstOrCreate([
                'project_spec_id' => $project->id,
                'title' => sprintf('Task Tambahan Intern %s', strtoupper($code)),
            ], [
                'period_id' => $sprints[4]->id,
                'description' => sprintf('Task detail tambahan yang dideklarasikan intern %s.', strtoupper($code)),
                'assignee_id' => $internId,
                'created_by' => $internId,
                'due_date' => '2027-04-30',
                'priority' => 'medium',
                'status' => 'todo',
            ]);
        }

        if ((bool) env('SEED_LARGE_DATASET', false)) {
            $this->call(LargeDatasetSeeder::class);
        }
    }
}
