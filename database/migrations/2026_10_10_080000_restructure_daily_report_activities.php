<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A report row of a project or a platform now holds inbound calls, comments and message replies side by side,
     * and the report gains order processing in place of its own message replies total.
     */
    public function up(): void
    {
        Schema::table('daily_reports', function (Blueprint $table) {
            $table->unsignedInteger('order_processing')->default(0)->after('inbound_calls_note');
            $table->text('order_processing_note')->nullable()->after('order_processing');
        });

        foreach (['daily_report_project_calls' => 'total_calls', 'daily_report_platform_replies' => 'total_replies'] as $name => $old) {
            $this->reportRows($name, $old, $old === 'total_calls' ? 'inbound_calls' : 'comments');
        }

        foreach (['daily_target_project_calls' => 'total_calls', 'daily_target_platform_replies' => 'total_replies'] as $name => $old) {
            Schema::table($name, function (Blueprint $table) {
                // null means no (approximate) target was set for that activity
                $table->unsignedInteger('inbound_calls')->nullable();
                $table->unsignedInteger('comments')->nullable();
                $table->unsignedInteger('message_replies')->nullable();
            });

            DB::table($name)->update([$old === 'total_calls' ? 'inbound_calls' : 'comments' => DB::raw($old)]);

            Schema::table($name, function (Blueprint $table) use ($old) {
                $table->dropColumn($old);
            });
        }

        Schema::table('daily_reports', function (Blueprint $table) {
            $table->dropColumn(['message_replies', 'message_replies_note']);
        });

        Schema::table('daily_targets', function (Blueprint $table) {
            $table->dropColumn('message_replies');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_targets', function (Blueprint $table) {
            $table->unsignedInteger('message_replies')->nullable()->after('inbound_calls');
        });

        Schema::table('daily_reports', function (Blueprint $table) {
            $table->unsignedInteger('message_replies')->default(0)->after('inbound_calls_note');
            $table->text('message_replies_note')->nullable()->after('message_replies');
        });

        foreach (['daily_target_project_calls' => 'total_calls', 'daily_target_platform_replies' => 'total_replies'] as $name => $old) {
            Schema::table($name, function (Blueprint $table) use ($old) {
                $table->unsignedInteger($old)->nullable();
            });

            DB::table($name)->update([$old => DB::raw($old === 'total_calls' ? 'inbound_calls' : 'comments')]);

            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn(['inbound_calls', 'comments', 'message_replies']);
            });
        }

        foreach (['daily_report_project_calls' => 'total_calls', 'daily_report_platform_replies' => 'total_replies'] as $name => $old) {
            Schema::table($name, function (Blueprint $table) use ($old) {
                $table->unsignedInteger($old)->default(0)->after($old === 'total_calls' ? 'project_id' : 'social_platform_id');
            });

            DB::table($name)->update([$old => DB::raw($old === 'total_calls' ? 'inbound_calls' : 'comments')]);

            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn(['inbound_calls', 'comments', 'message_replies']);
            });
        }

        Schema::table('daily_reports', function (Blueprint $table) {
            $table->dropColumn(['order_processing', 'order_processing_note']);
        });
    }

    /**
     * Add the three counts to a report child table, carry the old count over and drop it.
     */
    private function reportRows(string $name, string $old, string $into): void
    {
        Schema::table($name, function (Blueprint $table) {
            $table->unsignedInteger('inbound_calls')->default(0);
            $table->unsignedInteger('comments')->default(0);
            $table->unsignedInteger('message_replies')->default(0);
        });

        DB::table($name)->update([$into => DB::raw($old)]);

        Schema::table($name, function (Blueprint $table) use ($old) {
            $table->dropColumn($old);
        });
    }
};
