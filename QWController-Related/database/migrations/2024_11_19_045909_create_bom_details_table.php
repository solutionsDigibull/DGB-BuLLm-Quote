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
        Schema::create('bom_details', function (Blueprint $table) {
            $table->id(); 
            $table->unsignedBigInteger('bom_upload_id')->nullable(); 
            $table->json('json_data')->nullable(); 
            $table->integer('fields_count')->nullable(); 
            $table->integer('empty_fields')->nullable(); 
            $table->integer('non_empty_fields')->nullable(); 
            $table->decimal('completion_percentage', 5, 2)->nullable(); 
            $table->timestamps(); 
            // Add foreign key constraint
            $table->foreign('bom_upload_id')->references('id')->on('bom_uploads')->onDelete('cascade');
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bom_details');
    }
};
