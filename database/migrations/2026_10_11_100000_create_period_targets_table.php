<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The target of a user for a whole month and one activity, for when the KPI is not set day by day.
     */
    public function up(): void
    {
        Schema::create('period_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // the first day of the month the target is for
            $table->date('period_month')->index();
            // outbound_calls, inbound_calls, or platform:{id}:{count} and project:{id}:{count}
            $table->string('activity', 64);
            $table->unsignedInteger('target');
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'period_month', 'activity']);
        });

        Schema::table('kpi_snapshots', function (Blueprint $table) {
            // the activities of the score with their weight and achievement, as they stood when the month was closed
            $table->json('lines')->nullable()->after('score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kpi_snapshots', function (Blueprint $table) {
            $table->dropColumn('lines');
        });

        Schema::dropIfExists('period_targets');
    }
};
