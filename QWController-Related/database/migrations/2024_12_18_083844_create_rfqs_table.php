<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rfqs', function (Blueprint $table) {
            $table->id(); // Auto-incrementing primary key
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
            $table->timestamps();
        
            // Foreign Key Constraints
            $table->foreign('part_number_id')->references('part_number_id')->on('parts')->onDelete('cascade'); 
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade'); 
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
		DB::statement('ALTER TABLE rfqs DROP CONSTRAINT IF EXISTS rfqs_supplier_id_foreign');
		DB::statement('ALTER TABLE rfqs DROP CONSTRAINT IF EXISTS rfqs_part_number_id_foreign');

        Schema::dropIfExists('rfqs');
    }
};
