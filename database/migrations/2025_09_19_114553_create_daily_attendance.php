<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('daily_attendance', function (Blueprint $table) {
            $table->id();

            // Identity (flatten for now; can normalize later)
            $table->string('person_code', 64);
            $table->string('first_name', 191)->nullable();
            $table->string('last_name', 191)->nullable();
            $table->string('full_name', 191)->nullable();
            $table->string('group_name', 191)->nullable();
            $table->text('photo_url')->nullable();

            // Date (PK with person_code)
            $table->date('date');

            // Expected (from attendance report)
            $table->time('expected_in')->nullable();
            $table->time('expected_out')->nullable();

            // Actual (from ACS events, PK timezone)
            $table->dateTime('in_actual')->nullable();
            $table->dateTime('out_actual')->nullable();

            // Sources & provisional flags
            $table->string('in_source', 20)->nullable();   // Device|Mobile|Unknown
            $table->string('out_source', 20)->nullable();
            $table->boolean('in_source_provisional')->default(false);
            $table->boolean('out_source_provisional')->default(false);

            // Locations (if mobile provides)
            $table->text('location_in')->nullable();   // free text or JSON
            $table->text('location_out')->nullable();

            // Durations (minutes)
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('early_leave_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);

            // Traceability
            $table->json('raw_refs')->nullable();    // e.g., { "in_event_guid": "...", "out_event_guid": "...", "attendance_id": 123 }
            $table->dateTime('source_updated_at')->nullable();

            // Uniqueness & indexes
            $table->unique(['person_code', 'date']);
            $table->index('date');
            $table->index('person_code');

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('daily_attendance');
    }
};
