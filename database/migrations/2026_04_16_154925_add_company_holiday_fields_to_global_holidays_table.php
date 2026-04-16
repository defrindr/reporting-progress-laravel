<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('global_holidays', function (Blueprint $table) {
            $table->boolean('is_company_holiday')->default(false)->after('source');
            $table->text('description')->nullable()->after('is_company_holiday');
            $table->foreignId('created_by_admin_id')->nullable()->after('description')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('global_holidays', function (Blueprint $table) {});
    }
};
