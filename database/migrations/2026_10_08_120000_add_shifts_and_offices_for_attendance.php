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
        Schema::create('work_shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            // when the shift starts, as HH:MM in the attendance timezone
            $table->string('start_time', 5);
            // minutes after the start a check in is still on time
            $table->unsignedSmallInteger('grace_minutes')->default(15);
            $table->timestamps();
        });

        Schema::create('office_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            // how far from the point a check in still counts as at the office
            $table->unsignedInteger('radius_m')->default(200);
            // office addresses, comma separated; each one an IP or a CIDR range
            $table->text('allowed_ips')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            // a user without a shift follows the default one in config/attendance.php
            $table->foreignId('work_shift_id')->nullable()->after('is_active')->constrained('work_shifts')->nullOnDelete();
        });

        Schema::table('attendance_sessions', function (Blueprint $table) {
            // minutes after the shift start when the first check in of the day was late, 0 when on time, NULL when not judged
            $table->unsignedSmallInteger('late_minutes')->nullable()->after('check_in_device');
            // office: inside an office location, field: located elsewhere, unknown: no location to judge by
            $table->string('check_in_place', 10)->nullable()->after('late_minutes');
            $table->foreignId('office_location_id')->nullable()->after('check_in_place')->constrained('office_locations')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('office_location_id');
            $table->dropColumn(['late_minutes', 'check_in_place']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_shift_id');
        });

        Schema::dropIfExists('office_locations');
        Schema::dropIfExists('work_shifts');
    }
};
