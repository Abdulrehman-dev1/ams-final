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
            // Location fields
            $table->decimal('latitude', 10, 8)->nullable()->after('head_pic_url');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            
            // Schedule fields
            $table->time('time_in')->default('09:00:00')->after('longitude');
            $table->time('time_out')->default('19:00:00')->after('time_in');
            
            // Index for location queries
            $table->index(['latitude', 'longitude']);
            $table->index('time_in');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_employees', function (Blueprint $table) {
            $table->dropIndex(['latitude', 'longitude']);
            $table->dropIndex(['time_in']);
            $table->dropColumn(['latitude', 'longitude', 'time_in', 'time_out']);
        });
    }
};
