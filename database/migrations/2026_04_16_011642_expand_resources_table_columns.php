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
        Schema::table('resources', function (Blueprint $table) {
            $table->string('name',       191)->change();
            $table->string('controller', 191)->change();
            $table->string('action',     191)->change();
        });
    }

    public function down(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->string('name',        60)->change();
            $table->string('controller',  60)->change();
            $table->string('action',     100)->change();
        });
    }
};
