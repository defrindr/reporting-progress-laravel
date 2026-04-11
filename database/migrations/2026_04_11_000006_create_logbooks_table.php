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
        Schema::create('logbooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('period_id')
                ->constrained('periods')
                ->cascadeOnDelete();
            $table->date('report_date');
            $table->text('done_tasks');
            $table->text('next_tasks');
            $table->enum('status', ['draft', 'submitted', 'approved'])->default('draft');
            $table->timestamps();

            $table->unique(['user_id', 'report_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logbooks');
    }
};
