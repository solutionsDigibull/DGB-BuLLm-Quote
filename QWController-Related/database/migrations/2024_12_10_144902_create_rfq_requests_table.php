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
        Schema::create('rfq_requests', function (Blueprint $table) {
            $table->id(); 
            $table->unsignedBigInteger('bom_id'); 
            $table->unsignedBigInteger('commodity_id')->nullable(); 
            $table->json('manufacturer_id')->nullable(); 
            $table->json('supplier_id')->nullable(); 
            $table->unsignedBigInteger('email_template_id'); 
            $table->date('due_date')->nullable(); 
            $table->enum('status', ['Pending', 'Submitted'])->nullable(); 
            $table->timestamps(); 

            // Define foreign keys
            $table->foreign('bom_id')->references('id')->on('bom_uploads')->onDelete('cascade');
            $table->foreign('email_template_id')->references('id')->on('email_templates')->onDelete('cascade');
            $table->foreign('commodity_id')->references('id')->on('commodities')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rfq_requests');
    }
};
