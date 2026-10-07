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
        Schema::create('kpi_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // the first day of the month the score is for
            $table->date('period_month')->index();
            $table->unsignedInteger('target_total')->default(0);
            $table->unsignedInteger('actual_total')->default(0);
            // null means no target was set in the month
            $table->decimal('pct', 8, 1)->nullable();
            $table->decimal('score', 5, 1)->nullable();
            $table->unsignedSmallInteger('target_days')->default(0);
            $table->unsignedSmallInteger('worked')->default(0);
            $table->unsignedSmallInteger('absent')->default(0);
            $table->decimal('leave', 4, 1)->default(0);
            $table->timestamp('generated_at');
            $table->timestamps();

            // one frozen score per user per month
            $table->unique(['user_id', 'period_month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kpi_snapshots');
    }
};
