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
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_id')->nullable()->unique()->after('profile_image');
            $table->unsignedBigInteger('reporting_user_id')->nullable()->after('employee_id');
            $table
                ->enum('usages_sector', ['corporate', 'field'])
                ->default('field')
                ->nullable();

            $table->foreign('reporting_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['reporting_user_id']);
            $table->dropColumn(['employee_id', 'reporting_user_id']);
        });
    }
};
