<?php

namespace Database\Seeders;

use App\Models\Period;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class LargeDatasetSeeder extends Seeder
{
    private const DEFAULT_UNIVERSITY_COUNT = 30;

    private const DEFAULT_VOCATIONAL_COUNT = 12;

    private const DEFAULT_SUPERVISOR_COUNT = 120;

    private const DEFAULT_INTERN_COUNT = 12000;

    private const DEFAULT_PROJECT_SPEC_COUNT = 3500;

    private const DEFAULT_ASSIGNMENTS_PER_SPEC = 3;

    private const DEFAULT_TASK_COUNT = 30000;

    private const DEFAULT_LOGBOOK_COUNT = 10000;

    private const DEFAULT_COMMENT_COUNT = 14000;

    public function run(): void
    {
        @ini_set('memory_limit', (string) env('SEED_MEMORY_LIMIT', '512M'));

        $tag = $this->seedTag();

        if ($this->hasSeededTag($tag)) {
            $this->command?->warn("Large dataset with tag [{$tag}] already exists. Use a different SEED_DATA_TAG.");

            return;
        }

        $this->command?->info("Seeding large dataset tag [{$tag}]...");

        $roleIds = $this->ensureRoles();
        $now = now()->toDateTimeString();

        $counts = [
            'universities' => $this->envInt('SEED_BULK_UNIVERSITIES', self::DEFAULT_UNIVERSITY_COUNT),
            'vocationals' => $this->envInt('SEED_BULK_VOCATIONALS', self::DEFAULT_VOCATIONAL_COUNT),
            'supervisors' => $this->envInt('SEED_BULK_SUPERVISORS', self::DEFAULT_SUPERVISOR_COUNT),
            'interns' => $this->envInt('SEED_BULK_INTERNS', self::DEFAULT_INTERN_COUNT),
            'projects' => $this->envInt(
                'SEED_BULK_PROJECTS',
                $this->envInt('SEED_BULK_PROJECT_SPECS', self::DEFAULT_PROJECT_SPEC_COUNT)
            ),
            'assignments_per_project' => $this->envInt(
                'SEED_BULK_ASSIGNMENTS_PER_PROJECT',
                $this->envInt('SEED_BULK_ASSIGNMENTS_PER_SPEC', self::DEFAULT_ASSIGNMENTS_PER_SPEC)
            ),
            'backlogs' => $this->envInt(
                'SEED_BULK_BACKLOGS',
                $this->envInt('SEED_BULK_TASKS', self::DEFAULT_TASK_COUNT)
            ),
            'logbooks' => $this->envInt('SEED_BULK_LOGBOOKS', self::DEFAULT_LOGBOOK_COUNT),
            'comments' => $this->envInt('SEED_BULK_COMMENTS', self::DEFAULT_COMMENT_COUNT),
        ];

        $universityIds = $this->seedInstitutions(
            tag: $tag,
            universityCount: $counts['universities'],
            vocationalCount: $counts['vocationals'],
            now: $now,
        );

        $this->seedPeriods(tag: $tag, universityIds: $universityIds, now: $now);
        $this->command?->line('- periods seeded');

        $adminId = $this->ensureAdmin(universityIds: $universityIds);

        [$supervisorIds, $internRows] = $this->seedUsers(
            tag: $tag,
            supervisorCount: $counts['supervisors'],
            internCount: $counts['interns'],
            universityIds: $universityIds,
            now: $now,
        );
        $internIds = array_map(static fn (array $row): int => $row['id'], $internRows);
        $this->command?->line('- users seeded');

        $this->seedRoleAssignments(
            roleIds: $roleIds,
            adminId: $adminId,
            supervisorIds: $supervisorIds,
            internRows: $internRows,
        );
        $this->command?->line('- roles assigned');

        [$specIds, $assignedInternBySpec] = $this->seedProjectSpecsAndAssignments(
            tag: $tag,
            specCount: $counts['projects'],
            assignmentsPerSpec: $counts['assignments_per_project'],
            creatorIds: array_values(array_unique(array_merge([$adminId], $supervisorIds))),
            internIds: $internIds,
            now: $now,
        );
        $this->command?->line('- projects seeded');

        $internInstitutionById = [];
        foreach ($internRows as $internRow) {
            $internInstitutionById[$internRow['id']] = $internRow['institution_id'];
        }

        $periodIdsByInstitution = $this->periodIdsByInstitution($tag);

        $taskIds = $this->seedBacklogs(
            tag: $tag,
            backlogCount: $counts['backlogs'],
            specIds: $specIds,
            assignedInternBySpec: $assignedInternBySpec,
            internIds: $internIds,
            internInstitutionById: $internInstitutionById,
            periodIdsByInstitution: $periodIdsByInstitution,
        );
        $this->command?->line('- backlogs seeded');

        $this->seedLogbooks(
            tag: $tag,
            logbookCount: $counts['logbooks'],
            internRows: $internRows,
        );
        $this->command?->line('- logbooks seeded');

        unset($specIds, $assignedInternBySpec, $internRows);

        $actorIds = array_values(array_unique(array_merge(
            [$adminId],
            $supervisorIds,
            array_slice($internIds, 0, min(5000, count($internIds)))
        )));

        $this->seedComments(
            tag: $tag,
            commentCount: $counts['comments'],
            taskIds: $taskIds,
            actorIds: $actorIds,
        );
        $this->command?->line('- comments seeded');

        $this->createSeedMarker(
            tag: $tag,
            institutionId: $universityIds[0] ?? null,
        );

        $this->command?->info('Large dataset generated successfully.');
        $this->command?->line('Summary:');
        $this->command?->line('- institutions: '.DB::table('institutions')->where('name', 'like', $tag.'-%')->count());
        $this->command?->line('- users: '.DB::table('users')->where('email', 'like', '%.'.$tag.'@demo.local')->count());
        $this->command?->line('- projects: '.DB::table('project_specs')->where('title', 'like', $tag.'-project-%')->count());
        $this->command?->line('- backlogs: '.DB::table('projects')->where('title', 'like', $tag.'-backlog-%')->count());
        $this->command?->line('- logbooks: '.DB::table('logbooks')->where('done_tasks', 'like', '['.$tag.'%')->count());
        $this->command?->line('- comments: '.DB::table('comments')->where('body', 'like', '['.$tag.'%')->count());
    }

    /**
     * @return array<string, int>
     */
    private function ensureRoles(): array
    {
        foreach (['Admin', 'Supervisor', 'Intern'] as $name) {
            Role::findOrCreate($name, 'web');
        }

        $raw = Role::query()
            ->whereIn('name', ['Admin', 'Supervisor', 'Intern'])
            ->pluck('id', 'name')
            ->all();

        $roles = [];
        foreach ($raw as $name => $id) {
            $roles[(string) $name] = (int) $id;
        }

        return $roles;
    }

    /**
     * @return array<int, int>
     */
    private function seedInstitutions(string $tag, int $universityCount, int $vocationalCount, string $now): array
    {
        $rows = [];

        for ($i = 1; $i <= $universityCount; $i++) {
            $rows[] = [
                'name' => sprintf('%s-university-%03d', $tag, $i),
                'type' => 'university',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        for ($i = 1; $i <= $vocationalCount; $i++) {
            $rows[] = [
                'name' => sprintf('%s-vocational-%03d', $tag, $i),
                'type' => 'vocational',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertChunked('institutions', $rows);

        $rawIds = DB::table('institutions')
            ->where('type', 'university')
            ->where('name', 'like', $tag.'-university-%')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        return array_map('intval', $rawIds);
    }

    /**
     * @param  array<int, int>  $universityIds
     */
    private function seedPeriods(string $tag, array $universityIds, string $now): void
    {
        $rows = [];

        foreach ($universityIds as $idx => $institutionId) {
            $rows[] = [
                'institution_id' => $institutionId,
                'type' => Period::TYPE_INTERNSHIP,
                'name' => sprintf('%s-internship-%03d', $tag, $idx + 1),
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'holidays' => json_encode(['2026-01-01', '2026-05-01'], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $sprintStart = Carbon::create(2026, 1, 1);
            for ($week = 1; $week <= 16; $week++) {
                $start = $sprintStart->copy()->addDays(($week - 1) * 7);
                $end = $start->copy()->addDays(6);

                $rows[] = [
                    'institution_id' => $institutionId,
                    'type' => Period::TYPE_SPRINT,
                    'name' => sprintf('%s-sprint-%03d-w%02d', $tag, $idx + 1, $week),
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'holidays' => json_encode([], JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $this->insertChunked('periods', $rows);
    }

    /**
     * @return array<int, array<int, int>>
     */
    private function periodIdsByInstitution(string $tag): array
    {
        $rows = DB::table('periods')
            ->where('type', Period::TYPE_SPRINT)
            ->where('name', 'like', $tag.'-sprint-%')
            ->orderBy('id')
            ->get(['id', 'institution_id']);

        $periodIdsByInstitution = [];

        foreach ($rows as $row) {
            if (! $row->institution_id) {
                continue;
            }

            $institutionId = (int) $row->institution_id;
            $periodIdsByInstitution[$institutionId] ??= [];
            $periodIdsByInstitution[$institutionId][] = (int) $row->id;
        }

        return $periodIdsByInstitution;
    }

    /**
     * @param  array<int, int>  $universityIds
     */
    private function ensureAdmin(array $universityIds): int
    {
        $institutionId = $universityIds[array_rand($universityIds)] ?? null;

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'System Admin',
                'password' => Hash::make('password'),
                'institution_id' => $institutionId,
            ]
        );

        if (! $admin->institution_id && $institutionId) {
            $admin->update(['institution_id' => $institutionId]);
        }

        return (int) $admin->id;
    }

    /**
     * @param  array<int, int>  $universityIds
     * @return array{0: array<int, int>, 1: array<int, array{id: int, institution_id: int}>}
     */
    private function seedUsers(string $tag, int $supervisorCount, int $internCount, array $universityIds, string $now): array
    {
        $password = Hash::make('password');

        $supervisorRows = [];
        for ($i = 1; $i <= $supervisorCount; $i++) {
            $supervisorRows[] = [
                'institution_id' => $universityIds[array_rand($universityIds)],
                'name' => sprintf('%s Supervisor %04d', strtoupper($tag), $i),
                'email' => sprintf('supervisor%04d.%s@demo.local', $i, $tag),
                'email_verified_at' => $now,
                'password' => $password,
                'remember_token' => bin2hex(random_bytes(5)),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($supervisorRows) >= 1200) {
                DB::table('users')->insert($supervisorRows);
                $supervisorRows = [];
            }
        }

        if ($supervisorRows !== []) {
            DB::table('users')->insert($supervisorRows);
        }

        $internRows = [];
        for ($i = 1; $i <= $internCount; $i++) {
            $internRows[] = [
                'institution_id' => $universityIds[array_rand($universityIds)],
                'name' => sprintf('%s Intern %05d', strtoupper($tag), $i),
                'email' => sprintf('intern%05d.%s@demo.local', $i, $tag),
                'email_verified_at' => $now,
                'password' => $password,
                'remember_token' => bin2hex(random_bytes(5)),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($internRows) >= 1200) {
                DB::table('users')->insert($internRows);
                $internRows = [];
            }
        }

        if ($internRows !== []) {
            DB::table('users')->insert($internRows);
        }

        $supervisorIds = DB::table('users')
            ->where('email', 'like', 'supervisor%.'.$tag.'@demo.local')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $internData = [];
        $internCursor = DB::table('users')
            ->where('email', 'like', 'intern%.'.$tag.'@demo.local')
            ->orderBy('id')
            ->select('id', 'institution_id')
            ->cursor();

        foreach ($internCursor as $row) {
            $internData[] = [
                'id' => (int) $row->id,
                'institution_id' => (int) $row->institution_id,
            ];
        }

        return [array_map('intval', $supervisorIds), $internData];
    }

    /**
     * @param  array<string, int>  $roleIds
     * @param  array<int, int>  $supervisorIds
     * @param  array<int, array{id: int, institution_id: int}>  $internRows
     */
    private function seedRoleAssignments(array $roleIds, int $adminId, array $supervisorIds, array $internRows): void
    {
        $table = config('permission.table_names.model_has_roles', 'model_has_roles');
        $usesTeams = (bool) config('permission.teams', false);
        $teamKey = config('permission.column_names.team_foreign_key', 'team_id');

        $rows = [];

        $rows[] = $this->roleRow(
            roleId: $roleIds['Admin'],
            modelId: $adminId,
            usesTeams: $usesTeams,
            teamKey: $teamKey,
        );

        foreach ($supervisorIds as $supervisorId) {
            $rows[] = $this->roleRow(
                roleId: $roleIds['Supervisor'],
                modelId: $supervisorId,
                usesTeams: $usesTeams,
                teamKey: $teamKey,
            );

            if (count($rows) >= 1500) {
                DB::table($table)->insertOrIgnore($rows);
                $rows = [];
            }
        }

        foreach ($internRows as $internRow) {
            $rows[] = $this->roleRow(
                roleId: $roleIds['Intern'],
                modelId: $internRow['id'],
                usesTeams: $usesTeams,
                teamKey: $teamKey,
            );

            if (count($rows) >= 1500) {
                DB::table($table)->insertOrIgnore($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table($table)->insertOrIgnore($rows);
        }
    }

    /**
     * @param  array<int, int>  $creatorIds
     * @param  array<int, int>  $internIds
     * @return array{0: array<int, int>, 1: array<int, array<int, int>>}
     */
    private function seedProjectSpecsAndAssignments(
        string $tag,
        int $specCount,
        int $assignmentsPerSpec,
        array $creatorIds,
        array $internIds,
        string $now
    ): array {
        $specRows = [];

        for ($i = 1; $i <= $specCount; $i++) {
            $specRows[] = [
                'title' => sprintf('%s-project-%05d', $tag, $i),
                'specification' => sprintf('[%s] Project %05d untuk simulasi flow backlog-sprint.', $tag, $i),
                'created_by' => $creatorIds[array_rand($creatorIds)],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertChunked('project_specs', $specRows);

        $specIds = DB::table('project_specs')
            ->where('title', 'like', $tag.'-project-%')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $specIds = array_map('intval', $specIds);

        $pivotRows = [];
        $assignedInternBySpec = [];

        $internCount = count($internIds);
        $perSpec = max(1, min($assignmentsPerSpec, $internCount));

        foreach ($specIds as $specId) {
            $picked = [];

            while (count($picked) < $perSpec) {
                $picked[$internIds[random_int(0, $internCount - 1)]] = true;
            }

            $assignedInternBySpec[$specId] = array_map('intval', array_keys($picked));

            foreach ($assignedInternBySpec[$specId] as $internId) {
                $pivotRows[] = [
                    'project_spec_id' => $specId,
                    'user_id' => $internId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($pivotRows, 2000) as $chunk) {
            DB::table('project_spec_user')->insertOrIgnore($chunk);
        }

        return [$specIds, $assignedInternBySpec];
    }

    /**
     * @param  array<int, int>  $specIds
     * @param  array<int, array<int, int>>  $assignedInternBySpec
     * @param  array<int, int>  $internIds
     * @return array<int, int>
     */
    private function seedBacklogs(
        string $tag,
        int $backlogCount,
        array $specIds,
        array $assignedInternBySpec,
        array $internIds,
        array $internInstitutionById,
        array $periodIdsByInstitution
    ): array
    {
        $rows = [];
        $specCount = count($specIds);

        for ($i = 1; $i <= $backlogCount; $i++) {
            $specId = $specIds[random_int(0, $specCount - 1)];
            $assignedInterns = $assignedInternBySpec[$specId] ?? $internIds;
            $assigneeId = $assignedInterns[array_rand($assignedInterns)];

            $createdAt = Carbon::now()
                ->subDays(random_int(0, 120))
                ->setTime(random_int(8, 20), random_int(0, 59));

            $dueDate = $createdAt->copy()->addDays(random_int(5, 45))->toDateString();
            $institutionId = $internInstitutionById[$assigneeId] ?? null;
            $periodOptions = $institutionId ? ($periodIdsByInstitution[$institutionId] ?? []) : [];
            $periodId = ($periodOptions !== [] && random_int(1, 100) <= 40)
                ? $periodOptions[array_rand($periodOptions)]
                : null;

            $rows[] = [
                'project_spec_id' => $specId,
                'period_id' => $periodId,
                'title' => sprintf('%s-backlog-%06d', $tag, $i),
                'description' => sprintf('Backlog #%06d generated for project detail and sprint activation flow.', $i),
                'assignee_id' => $assigneeId,
                'created_by' => $assigneeId,
                'due_date' => $dueDate,
                'priority' => $this->randomPriority(),
                'status' => $this->randomTaskStatus(),
                'created_at' => $createdAt->toDateTimeString(),
                'updated_at' => $createdAt->toDateTimeString(),
            ];

            if (count($rows) >= 1200) {
                DB::table('projects')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('projects')->insert($rows);
        }

        $taskIds = DB::table('projects')
            ->where('title', 'like', $tag.'-backlog-%')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        return array_map('intval', $taskIds);
    }

    /**
     * @param  array<int, array{id: int, institution_id: int}>  $internRows
     */
    private function seedLogbooks(string $tag, int $logbookCount, array $internRows): void
    {
        $periodsByInstitution = Period::query()
            ->where('type', Period::TYPE_INTERNSHIP)
            ->whereNotNull('institution_id')
            ->orderBy('start_date')
            ->get(['id', 'institution_id', 'start_date', 'end_date'])
            ->groupBy('institution_id');

        $rows = [];
        $maxRows = min($logbookCount, count($internRows));

        for ($i = 0; $i < $maxRows; $i++) {
            $intern = $internRows[$i];
            $periods = $periodsByInstitution->get($intern['institution_id']);

            if (! $periods || $periods->isEmpty()) {
                continue;
            }

            $period = $periods->random();
            $start = Carbon::parse((string) $period->start_date);
            $end = Carbon::parse((string) $period->end_date);
            $reportDate = $start->copy()->addDays(random_int(0, max(1, $start->diffInDays($end))));

            $rows[] = [
                'user_id' => $intern['id'],
                'period_id' => (int) $period->id,
                'report_date' => $reportDate->toDateString(),
                'done_tasks' => sprintf('[%s] Done tasks sample %05d', $tag, $i + 1),
                'next_tasks' => sprintf('[%s] Next tasks sample %05d', $tag, $i + 1),
                'appendix_link' => random_int(1, 100) <= 40
                    ? sprintf('https://drive.google.com/file/d/%s-%05d/view', $tag, $i + 1)
                    : null,
                'status' => $this->randomLogbookStatus(),
                'created_at' => $reportDate->copy()->setTime(18, 0)->toDateTimeString(),
                'updated_at' => $reportDate->copy()->setTime(18, 0)->toDateTimeString(),
            ];
        }

        $this->insertChunked('logbooks', $rows);
    }

    /**
     * @param  array<int, int>  $taskIds
     * @param  array<int, int>  $actorIds
     */
    private function seedComments(string $tag, int $commentCount, array $taskIds, array $actorIds): void
    {
        if ($taskIds === [] || $actorIds === []) {
            return;
        }

        $rows = [];
        $taskCount = count($taskIds);
        $actorCount = count($actorIds);

        for ($i = 1; $i <= $commentCount; $i++) {
            $createdAt = Carbon::now()
                ->subDays(random_int(0, 90))
                ->setTime(random_int(8, 21), random_int(0, 59));

            $rows[] = [
                'body' => sprintf('[%s] Progress update %05d', $tag, $i),
                'user_id' => $actorIds[random_int(0, $actorCount - 1)],
                'commentable_id' => $taskIds[random_int(0, $taskCount - 1)],
                'commentable_type' => Project::class,
                'created_at' => $createdAt->toDateTimeString(),
                'updated_at' => $createdAt->toDateTimeString(),
            ];

            if (count($rows) >= 600) {
                DB::table('comments')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('comments')->insert($rows);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function roleRow(int $roleId, int $modelId, bool $usesTeams, string $teamKey): array
    {
        $row = [
            'role_id' => $roleId,
            'model_type' => User::class,
            'model_id' => $modelId,
        ];

        if ($usesTeams) {
            $row[$teamKey] = null;
        }

        return $row;
    }

    private function randomTaskStatus(): string
    {
        $roll = random_int(1, 100);

        if ($roll <= 45) {
            return 'todo';
        }

        if ($roll <= 80) {
            return 'doing';
        }

        return 'done';
    }

    private function randomPriority(): string
    {
        $roll = random_int(1, 100);

        if ($roll <= 25) {
            return 'low';
        }

        if ($roll <= 65) {
            return 'medium';
        }

        if ($roll <= 90) {
            return 'high';
        }

        return 'critical';
    }

    private function randomLogbookStatus(): string
    {
        $roll = random_int(1, 100);

        if ($roll <= 45) {
            return 'submitted';
        }

        if ($roll <= 75) {
            return 'draft';
        }

        return 'approved';
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function insertChunked(string $table, array $rows, int $chunkSize = 1200): void
    {
        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    private function envInt(string $key, int $default): int
    {
        $value = env($key, $default);

        return max(1, (int) $value);
    }

    private function seedTag(): string
    {
        $tag = (string) env('SEED_DATA_TAG', 'bulk');
        $tag = strtolower(trim($tag));

        return preg_replace('/[^a-z0-9\-]/', '-', $tag) ?: 'bulk';
    }

    private function hasSeededTag(string $tag): bool
    {
        return User::query()
            ->where('email', sprintf('seed-marker.%s@demo.local', $tag))
            ->exists();
    }

    private function createSeedMarker(string $tag, ?int $institutionId): void
    {
        User::query()->firstOrCreate(
            ['email' => sprintf('seed-marker.%s@demo.local', $tag)],
            [
                'name' => sprintf('seed-marker-%s', $tag),
                'password' => Hash::make('password'),
                'institution_id' => $institutionId,
            ]
        );
    }
}
