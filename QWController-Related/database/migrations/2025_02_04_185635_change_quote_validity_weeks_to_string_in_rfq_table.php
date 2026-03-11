<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeQuoteValidityWeeksToStringInRfqTable extends Migration
{
    public function up()
    {
        Schema::table('rfqs', function (Blueprint $table) {
            // Change the column type from integer to string
            $table->string('quote_validity_weeks')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('rfqs', function (Blueprint $table) {
            // Revert back to integer if needed
            $table->integer('quote_validity_weeks')->nullable()->change();
        });
    }
}
