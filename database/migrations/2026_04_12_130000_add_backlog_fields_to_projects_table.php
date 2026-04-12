<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->foreignId('period_id')
                ->nullable()
                ->after('project_spec_id')
                ->constrained('periods')
                ->nullOnDelete();

            $table->date('due_date')->nullable()->after('description');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium')->after('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropForeign(['period_id']);
            $table->dropColumn(['period_id', 'due_date', 'priority']);
        });
    }
};
