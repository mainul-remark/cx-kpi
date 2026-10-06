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
        Schema::create('daily_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('report_date')->index();
            $table->unsignedInteger('outbound_calls')->default(0);
            $table->text('outbound_calls_note')->nullable();
            $table->unsignedInteger('inbound_calls')->default(0);
            $table->text('inbound_calls_note')->nullable();
            $table->unsignedInteger('message_replies')->default(0);
            $table->text('message_replies_note')->nullable();
            $table->timestamps();

            // one report per user per day
            $table->unique(['user_id', 'report_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_reports');
    }
};
