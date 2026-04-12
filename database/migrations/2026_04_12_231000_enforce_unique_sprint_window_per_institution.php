<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $duplicateGroups = DB::table('periods')
                ->select([
                    'institution_id',
                    'type',
                    'start_date',
                    'end_date',
                    DB::raw('MIN(id) as keep_id'),
                    DB::raw('COUNT(*) as total_rows'),
                ])
                ->where('type', 'sprint')
                ->whereNotNull('institution_id')
                ->groupBy('institution_id', 'type', 'start_date', 'end_date')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            foreach ($duplicateGroups as $group) {
                $keepId = (int) $group->keep_id;

                $duplicateIds = DB::table('periods')
                    ->where('type', 'sprint')
                    ->where('institution_id', $group->institution_id)
                    ->whereDate('start_date', (string) $group->start_date)
                    ->whereDate('end_date', (string) $group->end_date)
                    ->where('id', '!=', $keepId)
                    ->pluck('id')
                    ->map(static fn ($id): int => (int) $id)
                    ->values()
                    ->all();

                if ($duplicateIds === []) {
                    continue;
                }

                DB::table('projects')
                    ->whereIn('period_id', $duplicateIds)
                    ->update(['period_id' => $keepId]);

                DB::table('logbooks')
                    ->whereIn('period_id', $duplicateIds)
                    ->update(['period_id' => $keepId]);

                DB::table('periods')
                    ->whereIn('id', $duplicateIds)
                    ->delete();
            }

            $institutionIds = DB::table('periods')
                ->where('type', 'sprint')
                ->whereNotNull('institution_id')
                ->distinct()
                ->pluck('institution_id')
                ->map(static fn ($id): int => (int) $id)
                ->values()
                ->all();

            foreach ($institutionIds as $institutionId) {
                $sprints = DB::table('periods')
                    ->where('type', 'sprint')
                    ->where('institution_id', $institutionId)
                    ->orderBy('start_date')
                    ->orderBy('id')
                    ->get(['id', 'start_date', 'end_date'])
                    ->map(static function ($row): array {
                        return [
                            'id' => (int) $row->id,
                            'start_date' => (string) $row->start_date,
                            'end_date' => (string) $row->end_date,
                        ];
                    })
                    ->all();

                $canonical = [];

                foreach ($sprints as $sprint) {
                    $overlapIndex = null;

                    foreach ($canonical as $index => $keep) {
                        if ($sprint['start_date'] <= $keep['end_date'] && $sprint['end_date'] >= $keep['start_date']) {
                            $overlapIndex = $index;
                            break;
                        }
                    }

                    if ($overlapIndex === null) {
                        $canonical[] = $sprint;

                        continue;
                    }

                    $keep = $canonical[$overlapIndex];
                    $keepId = (int) $keep['id'];
                    $duplicateId = (int) $sprint['id'];

                    DB::table('projects')
                        ->where('period_id', $duplicateId)
                        ->update(['period_id' => $keepId]);

                    DB::table('logbooks')
                        ->where('period_id', $duplicateId)
                        ->update(['period_id' => $keepId]);

                    $mergedStart = min($keep['start_date'], $sprint['start_date']);
                    $mergedEnd = max($keep['end_date'], $sprint['end_date']);

                    DB::table('periods')
                        ->where('id', $keepId)
                        ->update([
                            'start_date' => $mergedStart,
                            'end_date' => $mergedEnd,
                            'updated_at' => now(),
                        ]);

                    DB::table('periods')
                        ->where('id', $duplicateId)
                        ->delete();

                    $canonical[$overlapIndex]['start_date'] = $mergedStart;
                    $canonical[$overlapIndex]['end_date'] = $mergedEnd;
                }
            }
        });

        Schema::table('periods', function (Blueprint $table): void {
            $table->unique(
                ['institution_id', 'type', 'start_date', 'end_date'],
                'periods_inst_type_start_end_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('periods', function (Blueprint $table): void {
            $table->dropUnique('periods_inst_type_start_end_unique');
        });
    }
};
