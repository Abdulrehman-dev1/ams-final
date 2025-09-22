<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // Identity
            $table->string('first_name')->nullable()->after('id');
            $table->string('last_name')->nullable();
            $table->string('full_name')->nullable();
            $table->string('person_code', 64)->nullable()->index();
            $table->string('group_name')->nullable();

            // Mapping Hik "date" -> your existing attendance_date (already in table)
            $table->unsignedTinyInteger('weekday')->nullable();
            $table->string('timetable_name')->nullable();

            // Check-in/out
            $table->date('check_in_date')->nullable();
            $table->time('check_in_time')->nullable();
            $table->date('check_out_date')->nullable();
            $table->time('check_out_time')->nullable();

            // Clock-in/out + device/source/area
            $table->date('clock_in_date')->nullable();
            $table->time('clock_in_time')->nullable();
            $table->unsignedTinyInteger('clock_in_source')->nullable();
            $table->string('clock_in_device')->nullable();
            $table->string('clock_in_area')->nullable();

            $table->date('clock_out_date')->nullable();
            $table->time('clock_out_time')->nullable();
            $table->unsignedTinyInteger('clock_out_source')->nullable();
            $table->string('clock_out_device')->nullable();
            $table->string('clock_out_area')->nullable();

            // Status + durations (HH:mm stored as strings)
            $table->unsignedTinyInteger('attendance_status_code')->nullable();
            $table->string('work_duration', 8)->nullable();
            $table->string('absence_duration', 8)->nullable();
            $table->string('late_duration', 8)->nullable();
            $table->string('early_duration', 8)->nullable();
            $table->string('break_duration', 8)->nullable();
            $table->string('leave_duration', 8)->nullable();
            $table->string('overtime_duration', 8)->nullable();
            $table->string('workday_overtime_duration', 8)->nullable();
            $table->string('weekend_overtime_duration', 8)->nullable();
            $table->string('leave_types')->nullable();

            // Unique pair to avoid duplicates per person per day
            $table->unique(['person_code','attendance_date'], 'att_person_date_unique');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // drop unique if exists
            try { $table->dropUnique('att_person_date_unique'); } catch (\Throwable $e) {}

            $table->dropColumn([
                'first_name','last_name','full_name','person_code','group_name',
                'weekday','timetable_name',
                'check_in_date','check_in_time','check_out_date','check_out_time',
                'clock_in_date','clock_in_time','clock_in_source','clock_in_device','clock_in_area',
                'clock_out_date','clock_out_time','clock_out_source','clock_out_device','clock_out_area',
                'attendance_status_code','work_duration','absence_duration','late_duration','early_duration',
                'break_duration','leave_duration','overtime_duration','workday_overtime_duration',
                'weekend_overtime_duration','leave_types',
            ]);
        });
    }
};
