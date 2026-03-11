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
        Schema::table('bom_details', function (Blueprint $table) {
            $table->integer('total_fg_count')->nullable()->after('non_empty_fields');
            $table->integer('total_mpn_count')->nullable()->after('total_fg_count');
            $table->integer('total_cpn_count')->nullable()->after('total_mpn_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bom_details', function (Blueprint $table) {
            $table->dropColumn('total_fg_count');
            $table->dropColumn('total_mpn_count');
            $table->dropColumn('total_cpn_count');
        });
    }
};
