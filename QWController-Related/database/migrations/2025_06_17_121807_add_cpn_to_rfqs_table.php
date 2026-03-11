<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('rfqs', function (Blueprint $table) {
            $table->string('cpn')->nullable()->after('MPN');
        });
    }

    public function down()
    {
        Schema::table('rfqs', function (Blueprint $table) {
            $table->dropColumn('cpn');
        });
    }

};
