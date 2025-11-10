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
        Schema::table('daily_employees', function (Blueprint $table) {
            $table->boolean('is_enabled')->default(true)->after('group_name');
            $table->index('is_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_employees', function (Blueprint $table) {
            $table->dropIndex(['is_enabled']);
            $table->dropColumn('is_enabled');
        });
    }
};
