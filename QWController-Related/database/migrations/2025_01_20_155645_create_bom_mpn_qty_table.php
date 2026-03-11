<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBomMpnQtyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bom_mpn_qty', function (Blueprint $table) {
            $table->id();  
            $table->unsignedBigInteger('bom_id'); 
            $table->unsignedBigInteger('project_id');  
            $table->string('mpn');  
            $table->integer('quantity');  
            $table->foreign('bom_id')->references('id')->on('bom_uploads')->onDelete('cascade');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bom_mpn_qty');
    }
}
