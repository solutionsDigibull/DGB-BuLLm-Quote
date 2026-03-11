<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBomSupplierMpnTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bom_supplier_mpn', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bom_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('supplier_id');
            $table->json('mpn_json');
            $table->string('url');
            $table->enum('email_status', ['pending', 'sent', 'failed', 'responded'])->default('pending');
            $table->timestamp('email_sent')->nullable();
            $table->boolean('supplier_view')->default(false);
            $table->boolean('supplier_download')->default(false);
            $table->boolean('supplier_upload')->default(false);
            $table->timestamps();

            $table->foreign('bom_id')->references('id')->on('bom_uploads')->onDelete('cascade');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bom_supplier_mpn');
    }
}
