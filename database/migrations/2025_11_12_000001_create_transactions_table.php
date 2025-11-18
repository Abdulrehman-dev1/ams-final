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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('person_code')->index();
            $table->date('date')->index();

            $table->string('name')->nullable();
            $table->string('department')->nullable();
            $table->time('expected_in')->nullable();
            $table->time('check_in')->nullable();
            $table->time('expected_out')->nullable();
            $table->time('check_out')->nullable();

            $table->string('data_source')->nullable();
            $table->string('location')->nullable();
            $table->decimal('latitude', 11, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            $table->string('device_name')->nullable();
            $table->string('device_serial')->nullable();
            $table->string('device_id')->nullable();

            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);

            $table->timestamps();

            $table->unique(['person_code', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};

