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
        Schema::create('daily_target_project_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_target_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('total_calls');
            $table->timestamps();

            $table->unique(['daily_target_id', 'project_id'], 'target_project_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_target_project_calls');
    }
};
