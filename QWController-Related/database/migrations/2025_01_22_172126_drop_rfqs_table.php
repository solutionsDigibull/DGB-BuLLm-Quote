<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('rfqs');
    }
    public function down(): void
    {
        Schema::create('rfqs', function (Blueprint $table) {
            $table->id(); 
            $table->string('project_id'); 
            $table->string('part_number_id'); 
            $table->unsignedBigInteger('supplier_id'); 
            $table->integer('volume_batch_quantity')->nullable(); 
            $table->integer('volume_eau_quantity')->nullable(); 
            $table->integer('unit_of_measure')->nullable(); 
            $table->string('currency')->nullable(); 
            $table->decimal('monthly_exchange_rate', 10, 2)->nullable(); 
            $table->decimal('freight_cost', 10, 2)->nullable(); 
            $table->string('inco_terms')->nullable(); 
            $table->date('effective_from_date')->nullable(); 
            $table->date('expiry_date')->nullable(); 
            $table->boolean('bid_response_type')->default(false); 
            $table->string('delivery_confidence')->nullable(); 
            $table->boolean('consolidated_order')->default(false); 
            $table->decimal('price', 10, 2)->nullable()->after('consolidated_order');
            $table->unsignedBigInteger('bom_id')->after('supplier_id');
            $table->timestamps();
            $table->foreign('part_number_id')->references('part_number_id')->on('parts')->onDelete('cascade'); 
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');
        });
    }
};
