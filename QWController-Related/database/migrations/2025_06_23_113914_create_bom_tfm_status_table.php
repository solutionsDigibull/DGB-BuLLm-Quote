<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bom_tfm_status', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bom_id');
            $table->string('status'); // e.g., "Scrub BoM Uploaded", "Validation Completed"
            $table->integer('created_by')->nullable(); // E.g., id of who created the entry
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bom_tfm_status');
    }
};
