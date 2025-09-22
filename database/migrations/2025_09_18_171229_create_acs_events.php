<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('acs_events', function (Blueprint $table) {
            $table->id();

            // Uniqueness
            $table->string('record_guid', 191)->unique();

            // Core event fields
            $table->string('element_id', 191)->nullable();
            $table->string('element_name', 191)->nullable();
            $table->unsignedInteger('element_type')->nullable();
            $table->string('area_id', 191)->nullable();
            $table->string('area_name', 191)->nullable();
            $table->string('device_id', 191)->nullable();
            $table->string('device_name', 191)->nullable();
            $table->string('card_reader_id', 191)->nullable();
            $table->string('card_reader_name', 191)->nullable();
            $table->unsignedBigInteger('dev_serial_no')->nullable();
            $table->unsignedInteger('event_type')->nullable();
            $table->unsignedInteger('event_main_type')->nullable();
            $table->unsignedTinyInteger('swipe_auth_result')->nullable();
            $table->unsignedTinyInteger('direction')->nullable();
            $table->unsignedTinyInteger('attendance_status')->nullable();
            $table->unsignedTinyInteger('masks_status')->nullable();
            $table->unsignedTinyInteger('has_camera_snap_pic')->nullable();
            $table->unsignedTinyInteger('has_dev_video_record')->nullable();

            // Card number can exceed 64-bit -> store as string
            $table->string('card_number', 64)->nullable();

            // Person (flatten)
            $table->string('person_id', 191)->nullable();
            $table->string('person_code', 191)->nullable();
            $table->string('first_name', 191)->nullable();
            $table->string('last_name', 191)->nullable();
            $table->string('full_name', 191)->nullable();
            $table->string('full_path', 191)->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('photo_url')->nullable();

            // Times
            $table->dateTime('occur_time_utc')->nullable();
            $table->string('device_time_tz', 50)->nullable(); // raw device time string w/ tz
            $table->dateTime('record_time_utc')->nullable();

            // Derived (for fast filters/reporting)
            $table->dateTime('occur_time_pk')->nullable();
            $table->date('occur_date_pk')->nullable();

            // JSON payloads
            $table->json('acs_snap_pics')->nullable();
            $table->json('temperature_info')->nullable();
            $table->mediumText('associated_camera_list')->nullable();
            $table->longText('raw_payload')->nullable();

            // Indexes for speed
            $table->index('occur_time_utc');
            $table->index('occur_date_pk');
            $table->index('person_id');
            $table->index('person_code');
            $table->index('card_number');
            $table->index('event_type');

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('acs_events');
    }
};
