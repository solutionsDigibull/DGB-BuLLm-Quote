<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddResentToEmailStatusEnumInBomSupplierMpnTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add a new temporary column with the updated enum values
        Schema::table('bom_supplier_mpn', function (Blueprint $table) {
            $table->enum('email_status_temp', ['pending', 'sent', 'failed', 'responded', 'resend'])
                ->default('pending');
        });

        // Copy data from the old column to the new column (if necessary, for safety)
        DB::table('bom_supplier_mpn')->update(['email_status_temp' => \DB::raw('email_status')]);

        // Drop the old column
        Schema::table('bom_supplier_mpn', function (Blueprint $table) {
            $table->dropColumn('email_status');
        });

        // Rename the new column to the original column name
        Schema::table('bom_supplier_mpn', function (Blueprint $table) {
            $table->renameColumn('email_status_temp', 'email_status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Add a new temporary column with the original enum values
        Schema::table('bom_supplier_mpn', function (Blueprint $table) {
            $table->enum('email_status_temp', ['pending', 'sent', 'failed', 'responded'])
                ->default('pending');
        });

        // Copy data back from the updated column to the old column (if necessary, for safety)
        DB::table('bom_supplier_mpn')->update(['email_status_temp' => \DB::raw('email_status')]);

        // Drop the updated column
        Schema::table('bom_supplier_mpn', function (Blueprint $table) {
            $table->dropColumn('email_status');
        });

        // Rename the temporary column back to the original name
        Schema::table('bom_supplier_mpn', function (Blueprint $table) {
            $table->renameColumn('email_status_temp', 'email_status');
        });
    }
}
