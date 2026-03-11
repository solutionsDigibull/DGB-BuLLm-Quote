<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterRfqsTablePrecision extends Migration
{
    public function up()
    {
        Schema::table('rfqs', function (Blueprint $table) {
            $table->decimal('proto_qty', 20, 10)->nullable()->change();
            $table->decimal('proto_pricing', 20, 10)->nullable()->change();
            $table->decimal('vol_qty', 20, 10)->nullable()->change();
            $table->decimal('vol_pricing', 20, 10)->nullable()->change();
            $table->decimal('vol_moq', 20, 10)->nullable()->change();
            $table->decimal('package_qty', 20, 10)->nullable()->change();
            $table->decimal('SPQ', 20, 10)->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('rfqs', function (Blueprint $table) {
            $table->integer('proto_qty')->nullable()->change();
            $table->decimal('proto_pricing', 15, 2)->nullable()->change();
            $table->integer('vol_qty')->nullable()->change();
            $table->decimal('vol_pricing', 15, 2)->nullable()->change();
            $table->integer('vol_moq')->nullable()->change();
            $table->integer('package_qty')->nullable()->change();
            $table->integer('SPQ')->nullable()->change();
        });
    }
}
