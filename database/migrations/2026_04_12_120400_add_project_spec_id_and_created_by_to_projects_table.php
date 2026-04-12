<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->foreignId('project_spec_id')
                ->nullable()
                ->after('id')
                ->constrained('project_specs')
                ->nullOnDelete();

            $table->foreignId('created_by')
                ->nullable()
                ->after('assignee_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropForeign(['project_spec_id']);
            $table->dropForeign(['created_by']);
            $table->dropColumn(['project_spec_id', 'created_by']);
        });
    }
};
