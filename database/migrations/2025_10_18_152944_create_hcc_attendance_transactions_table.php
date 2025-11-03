<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHccAttendanceTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('hcc_attendance_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('person_code')->index();
            $table->string('full_name');
            $table->string('department')->nullable();
            $table->date('attendance_date')->index();
            $table->time('attendance_time');
            $table->string('device_id')->nullable();
            $table->string('device_name')->nullable();
            $table->string('device_serial')->nullable();
            $table->string('weekday')->nullable();
            $table->json('source_data');
            $table->timestamps();

            // Composite unique index to prevent duplicates
            $table->unique(['person_code', 'attendance_date', 'attendance_time', 'device_id'], 'unique_hcc_transaction');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('hcc_attendance_transactions');
    }
}
