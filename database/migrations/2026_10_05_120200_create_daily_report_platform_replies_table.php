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
        Schema::create('daily_report_platform_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_platform_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('total_replies')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['daily_report_id', 'social_platform_id'], 'report_platform_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_report_platform_replies');
    }
};
