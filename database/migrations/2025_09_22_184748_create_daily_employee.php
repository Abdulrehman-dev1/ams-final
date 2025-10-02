<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('daily_employees', function (Blueprint $table) {
            $table->id();

            // Hik identifiers
            $table->string('person_id')->unique()->index();   // "personId" (unique)
            $table->string('group_id')->nullable()->index();  // "groupId"

            // Person info
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('full_name')->nullable();
            $table->unsignedTinyInteger('gender')->nullable(); // Hik: 1/2 etc.
            $table->string('phone')->nullable();
            $table->string('email')->nullable();

            $table->string('person_code')->nullable()->index(); // "personCode"
            $table->text('description')->nullable();

            // Dates (epoch ms → timestamp)
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();

            // Media
            $table->text('head_pic_url')->nullable();

            // Optional
            $table->string('group_name')->nullable();

            // Keep last raw payload for debugging/diff
            $table->json('raw_payload')->nullable();

            $table->timestamps();

            // helpful composite index for search
            $table->index(['person_code','full_name']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('daily_employees');
    }
};
