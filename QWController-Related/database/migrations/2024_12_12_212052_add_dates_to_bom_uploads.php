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
        Schema::table('bom_uploads', function (Blueprint $table) {
			$table->date('sourcing_commodity_update_receive_date')->nullable()->comment('Date when the commodity update was received');
			$table->date('rfq_launch_date')->nullable()->comment('Date when the RFQ was launched');
			$table->date('sourcing_cbom_initial_commit_date')->nullable()->comment('Initial commit date for the sourcing CBOM');
			$table->date('commodity_date')->nullable()->comment('Date associated with the commodity update');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bom_uploads', function (Blueprint $table) {
			$table->dropColumn([
				'sourcing_commodity_update_receive_date',
				'rfq_launch_date',
				'sourcing_cbom_initial_commit_date',
				'commodity_date',
			]);
        });
    }
};
