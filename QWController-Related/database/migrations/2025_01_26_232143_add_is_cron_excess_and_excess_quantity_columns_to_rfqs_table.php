<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfqs', function (Blueprint $table) {
            $table->boolean('is_cron')->nullable()->default(false);
            $table->decimal('excess_qty', 10, 2)->nullable();
            $table->decimal('excess_cost', 15, 2)->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('rfqs', function (Blueprint $table) {
            $table->dropColumn('is_cron');
            $table->dropColumn('excess_qty');
            $table->dropColumn('excess_cost');
        });
    }
};
