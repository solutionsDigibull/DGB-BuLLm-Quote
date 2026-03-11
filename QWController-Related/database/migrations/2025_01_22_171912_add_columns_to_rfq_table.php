<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsToRfqTable extends Migration
{
    public function up()
    {
        Schema::table('rfq', function (Blueprint $table) {
            $table->date('effective_from_date')->nullable(); 
            $table->date('expiry_date')->nullable(); 
            $table->boolean('bid_response_type')->default(false); 
            $table->string('delivery_confidence')->nullable(); 
            $table->boolean('consolidated_order')->default(false); 
            $table->decimal('unit_price', 10, 2)->nullable();
        });
    }

    public function down()
    {
        Schema::table('rfq', function (Blueprint $table) {
            $table->dropColumn([
                'effective_from_date', 
                'expiry_date', 
                'bid_response_type', 
                'delivery_confidence', 
                'consolidated_order', 
                'unit_price'
            ]);
        });
    }
}
