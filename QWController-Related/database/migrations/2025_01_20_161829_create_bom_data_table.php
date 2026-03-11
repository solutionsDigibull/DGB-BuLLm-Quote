<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('bom_data', function (Blueprint $table) {
            $table->id();
            $table->string('FG')->nullable();
            $table->string('Level')->nullable();
            $table->string('Assembly')->nullable();
            $table->string('CPN')->nullable();
            $table->string('Description')->nullable();
            $table->string('MFR')->nullable();
            $table->string('MPN')->nullable();
            $table->string('Commodity')->nullable();
            $table->string('LN Commodity')->nullable();
            $table->string('Quantity')->nullable();
            $table->string('UOM')->nullable();
            $table->string('Part Status')->nullable();
            $table->string('CE Remarks')->nullable();
            $table->string('Drawing Reference')->nullable();
            $table->string('Reference Designators')->nullable();
            $table->string('Item #')->nullable();
            $table->string('Part Type')->nullable();
            $table->string('LTB Date')->nullable();
            $table->json('supplier_id')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('bom_data');
    }
};
