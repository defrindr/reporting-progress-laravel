<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->index('project_spec_id', 'idx_projects_project_spec_id');
            $table->index('period_id', 'idx_projects_period_id');
        });

        Schema::table('periods', function (Blueprint $table): void {
            $table->index('type', 'idx_periods_type');
            $table->index(['institution_id', 'type'], 'idx_periods_institution_type');
        });

        Schema::table('logbooks', function (Blueprint $table): void {
            $table->index('period_id', 'idx_logbooks_period_id');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropIndex('idx_projects_project_spec_id');
            $table->dropIndex('idx_projects_period_id');
        });

        Schema::table('periods', function (Blueprint $table): void {
            $table->dropIndex('idx_periods_type');
            $table->dropIndex('idx_periods_institution_type');
        });

        Schema::table('logbooks', function (Blueprint $table): void {
            $table->dropIndex('idx_logbooks_period_id');
        });
    }
};
