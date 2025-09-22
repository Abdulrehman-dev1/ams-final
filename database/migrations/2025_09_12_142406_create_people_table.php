<?php

// database/migrations/2025_09_12_000000_create_persons_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('persons', function (Blueprint $t) {
            $t->id();

            // IDs / codes
            $t->string('person_id', 64)->nullable()->index();   // Hik personId
            $t->string('person_code', 64)->nullable()->unique(); // Hik personCode (employee no.)
            $t->string('group_id', 64)->nullable()->index();     // Hik groupId (department)

            // Basic info
            $t->string('first_name')->nullable();
            $t->string('last_name')->nullable();
            $t->unsignedTinyInteger('gender')->nullable(); // 0=female,1=male,2=unknown
            $t->string('phone', 64)->nullable();
            $t->string('email', 128)->nullable();
            $t->string('description', 256)->nullable();
            $t->string('head_pic_url')->nullable();

            // Validity period (store raw ms + parsed)
            $t->unsignedBigInteger('start_ms')->nullable();
            $t->unsignedBigInteger('end_ms')->nullable();
            $t->dateTimeTz('start_at')->nullable();
            $t->dateTimeTz('end_at')->nullable();

            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('persons');
    }
};
