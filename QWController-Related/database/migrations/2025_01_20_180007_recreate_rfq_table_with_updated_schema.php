<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RecreateRfqTableWithUpdatedSchema extends Migration
{
    public function up()
    {
        Schema::dropIfExists('rfq');

        Schema::create('rfq', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bom_id');
            $table->unsignedBigInteger('project_id');
            $table->string('FG')->nullable();
            $table->string('MPN')->nullable();
            $table->string('corrected_MPN')->nullable();
            $table->string('commodity')->nullable();
            $table->integer('no_bid')->nullable();
            $table->string('part_description');
            $table->string('request_uom');
            $table->integer('EAU')->nullable();
            $table->integer('proto_qty')->nullable();
            $table->decimal('proto_pricing', 15, 2)->nullable();
            $table->integer('vol_qty')->nullable();
            $table->string('quoted_uom')->nullable();
            $table->decimal('vol_pricing', 15, 2)->nullable();
            $table->integer('vol_moq')->nullable();
            $table->integer('package_qty')->nullable();
            $table->string('uploaded_through')->nullable();
            $table->integer('SPQ')->nullable();
            $table->string('lead_times')->nullable();
            $table->integer('quote_validity_weeks')->nullable();
            $table->text('rfq_comment')->nullable();
            $table->string('requested_delivery_timeline')->nullable();
            $table->boolean('is_L1')->default(false);
            $table->string('part_number_id')->nullable();
            $table->string('unit_of_measure')->nullable();
            $table->string('currency')->nullable();
            $table->decimal('monthly_exchange_rate', 10, 4)->nullable();
            $table->decimal('freight_cost', 15, 2)->nullable();
            $table->string('inco_terms')->nullable();
            $table->timestamps();
            $table->foreign('bom_id')->references('id')->on('bom_uploads')->onDelete('cascade');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('part_number_id')->references('part_number_id')->on('parts')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('rfq');
    }
}
