<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bom_data', function (Blueprint $table) {
            $table->integer('inventory_stock')->nullable()->after('supplier_id');
        });
    }

    public function down(): void
    {
        Schema::table('bom_data', function (Blueprint $table) {
            $table->dropColumn('inventory_stock');
        });
    }
};
