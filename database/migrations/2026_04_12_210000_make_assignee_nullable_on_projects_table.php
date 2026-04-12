<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->foreignId('assignee_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        $fallbackUserId = DB::table('users')->orderBy('id')->value('id');

        if ($fallbackUserId !== null) {
            DB::table('projects')
                ->whereNull('assignee_id')
                ->update(['assignee_id' => $fallbackUserId]);
        }

        Schema::table('projects', function (Blueprint $table): void {
            $table->foreignId('assignee_id')->nullable(false)->change();
        });
    }
};
