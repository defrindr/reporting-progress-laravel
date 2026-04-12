<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_spec_user', function (Blueprint $table): void {
            $table->foreignId('project_spec_id')->constrained('project_specs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['project_spec_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_spec_user');
    }
};
