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
        Schema::table('daily_targets', function (Blueprint $table) {
            // an empty target is no target, as for the other approximate targets
            $table->unsignedInteger('solved_issues')->nullable()->after('inbound_calls');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_targets', function (Blueprint $table) {
            $table->dropColumn('solved_issues');
        });
    }
};
