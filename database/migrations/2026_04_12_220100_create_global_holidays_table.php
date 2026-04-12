<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_holidays', function (Blueprint $table): void {
            $table->id();
            $table->date('holiday_date');
            $table->string('name');
            $table->string('country_code', 2)->default('ID');
            $table->unsignedSmallInteger('year');
            $table->string('source')->default('nager');
            $table->timestamps();

            $table->unique(['holiday_date', 'country_code']);
            $table->index(['country_code', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_holidays');
    }
};