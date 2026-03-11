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
            $table->json('volume')->nullable()->after('json_data'); 
            $table->string('proto')->nullable()->after('volume');
            $table->string('eau')->nullable()->after('proto');
            $table->string('centum_id')->nullable()->after('eau');
            $table->string('assigned_to')->nullable()->after('centum_id');
            $table->string('quote_id')->nullable()->after('assigned_to');
            $table->boolean('new_bid')->default(false)->after('quote_id'); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bom_details', function (Blueprint $table) {
            $table->dropColumn(['volume', 'proto', 'eau', 'centum_id', 'assigned_to', 'quote_id', 'new_bid']);
        });
    }
};
