<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveUnitOfMeasureFromRfqTable extends Migration
{
    public function up()
    {
        Schema::table('rfq', function (Blueprint $table) {
            $table->dropColumn('unit_of_measure');
        });
    }

    public function down()
    {
        Schema::table('rfq', function (Blueprint $table) {
            $table->string('unit_of_measure')->nullable();
        });
    }
}
