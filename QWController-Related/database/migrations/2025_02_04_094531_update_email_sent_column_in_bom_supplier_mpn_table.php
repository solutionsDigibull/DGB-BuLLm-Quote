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
        // Step 1: Rename the existing column to preserve data temporarily
        Schema::table('bom_supplier_mpn', function (Blueprint $table) {
            $table->renameColumn('email_sent', 'email_sent_old');
        });

        // Step 2: Add the new JSON column
        Schema::table('bom_supplier_mpn', function (Blueprint $table) {
            $table->json('email_sent')->nullable()->after('email_status');
        });

        // Step 3: Migrate existing timestamp data into JSON format
        DB::table('bom_supplier_mpn')->whereNotNull('email_sent_old')->get()->each(function ($record) {
            DB::table('bom_supplier_mpn')
                ->where('id', $record->id)
                ->update(['email_sent' => json_encode([$record->email_sent_old])]);
        });

        // Step 4: Remove the old timestamp column
        Schema::table('bom_supplier_mpn', function (Blueprint $table) {
            $table->dropColumn('email_sent_old');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bom_supplier_mpn', function (Blueprint $table) {
            $table->timestamp('email_sent_old')->nullable();
        });

        // Restore the first timestamp from JSON back to the old column
        DB::table('bom_supplier_mpn')->whereNotNull('email_sent')->get()->each(function ($record) {
            $timestamps = json_decode($record->email_sent, true);
            DB::table('bom_supplier_mpn')
                ->where('id', $record->id)
                ->update(['email_sent_old' => $timestamps[0] ?? null]);
        });

        // Remove the JSON column and rename the old column back
        Schema::table('bom_supplier_mpn', function (Blueprint $table) {
            $table->dropColumn('email_sent');
            $table->renameColumn('email_sent_old', 'email_sent');
        });
    }
};
