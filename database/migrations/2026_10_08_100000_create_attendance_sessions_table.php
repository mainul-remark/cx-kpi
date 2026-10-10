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
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // the day the session belongs to, in the attendance timezone
            $table->date('work_date');
            $table->dateTime('checked_in_at');
            $table->dateTime('checked_out_at')->nullable();

            // optional geo-location, left empty when the user denied it or the browser has none
            $table->decimal('check_in_lat', 10, 7)->nullable();
            $table->decimal('check_in_lng', 10, 7)->nullable();
            $table->float('check_in_accuracy')->nullable();
            $table->decimal('check_out_lat', 10, 7)->nullable();
            $table->decimal('check_out_lng', 10, 7)->nullable();
            $table->float('check_out_accuracy')->nullable();

            $table->string('check_in_ip', 45)->nullable();
            $table->string('check_out_ip', 45)->nullable();
            $table->string('check_in_device')->nullable();
            $table->string('check_out_device')->nullable();

            // manual: the user left, auto: the system closed a forgotten session, admin: a manager closed it
            $table->string('close_reason', 20)->nullable();
            $table->dateTime('auto_closed_at')->nullable();
            // when the user saw the "you forgot to check out" warning
            $table->dateTime('acknowledged_at')->nullable();

            $table->foreignId('adjusted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('adjustment_note')->nullable();
            $table->timestamps();

            // 1 while the session is still open, NULL once closed; NULLs never collide in a unique index
            $table->unsignedTinyInteger('open_flag')->nullable()
                ->storedAs('CASE WHEN checked_out_at IS NULL AND auto_closed_at IS NULL THEN 1 ELSE NULL END');

            $table->index(['user_id', 'work_date']);
            $table->index('work_date');
            // a user can have only one open session
            $table->unique(['user_id', 'open_flag']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions');
    }
};
