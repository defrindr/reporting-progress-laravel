<?php

return [
    // Jumlah minggu per sprint window (mis: 2 = sprint 2 minggu).
    'span_weeks' => (int) env('SPRINT_SPAN_WEEKS', 2),

    // Hari kerja ISO-8601: 1 = Senin, ..., 7 = Minggu.
    'workdays_iso' => array_values(array_filter(array_map(
        static fn (string $day): int => (int) trim($day),
        explode(',', (string) env('SPRINT_WORKDAYS_ISO', '1,2,3,4,5'))
    ), static fn (int $day): bool => $day >= 1 && $day <= 7)),

    // Acuan awal siklus sprint (disarankan hari Senin). Semua window sprint diturunkan dari tanggal ini.
    'reference_monday' => (string) env('SPRINT_REFERENCE_MONDAY', '2026-01-05'),
];
