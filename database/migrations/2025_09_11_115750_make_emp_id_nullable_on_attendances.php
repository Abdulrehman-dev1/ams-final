<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Drop existing FK (default name: attendances_emp_id_foreign)
        Schema::table('attendances', function (Blueprint $table) {
            try { $table->dropForeign(['emp_id']); } catch (\Throwable $e) {}
        });

        // Make emp_id nullable (no DBAL needed)
        DB::statement('ALTER TABLE `attendances` MODIFY `emp_id` INT UNSIGNED NULL');

        // Re-add FK with SET NULL on delete
        Schema::table('attendances', function (Blueprint $table) {
            $table->foreign('emp_id')
                  ->references('id')->on('employees')
                  ->onDelete('set null'); // or ->nullOnDelete() on newer Laravel
        });
    }

    public function down(): void
    {
        // Drop FK
        Schema::table('attendances', function (Blueprint $table) {
            try { $table->dropForeign(['emp_id']); } catch (\Throwable $e) {}
        });

        // Make emp_id NOT NULL again
        DB::statement('ALTER TABLE `attendances` MODIFY `emp_id` INT UNSIGNED NOT NULL');

        // Restore original FK (cascade as in your old migration)
        Schema::table('attendances', function (Blueprint $table) {
            $table->foreign('emp_id')
                  ->references('id')->on('employees')
                  ->onDelete('cascade');
        });
    }
};
