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
        Schema::table('rfqs', function (Blueprint $table) {
            $table->boolean('customer_price_control')->nullable();
            $table->boolean('centum_price_control')->nullable();
            $table->boolean('other_price_control')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rfqs', function (Blueprint $table) {
            $table->dropColumn('customer_price_control');
            $table->dropColumn('centum_price_control');
            $table->dropColumn('other_price_control');
        });
    }
};