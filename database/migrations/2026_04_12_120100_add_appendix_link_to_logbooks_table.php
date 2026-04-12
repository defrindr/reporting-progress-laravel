<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logbooks', function (Blueprint $table): void {
            $table->string('appendix_link')->nullable()->after('next_tasks');
        });
    }

    public function down(): void
    {
        Schema::table('logbooks', function (Blueprint $table): void {
            $table->dropColumn('appendix_link');
        });
    }
};
