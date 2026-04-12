<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('periods', function (Blueprint $table): void {
            $table->foreignId('institution_id')
                ->nullable()
                ->after('id')
                ->constrained('institutions')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('periods', function (Blueprint $table): void {
            $table->dropForeign(['institution_id']);
            $table->dropColumn('institution_id');
        });
    }
};
