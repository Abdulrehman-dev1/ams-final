<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBaseSalaryToDailyEmployeesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('daily_employees', function (Blueprint $table) {
            $table->decimal('base_salary', 12, 2)->nullable()->after('group_name');
            $table->index('base_salary');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('daily_employees', function (Blueprint $table) {
            $table->dropIndex(['base_salary']);
            $table->dropColumn('base_salary');
        });
    }
}
