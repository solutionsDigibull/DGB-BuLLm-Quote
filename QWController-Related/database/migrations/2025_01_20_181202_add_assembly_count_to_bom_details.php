<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bom_details', function (Blueprint $table) {
            $table->integer('total_assembly_count')->default(0)->after('total_cpn_count');
        });
    }
    public function down(): void
    {
        Schema::table('bom_details', function (Blueprint $table) {
            $table->dropColumn('total_assembly_count');
        });
    }
};
