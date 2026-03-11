<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bom_details', function (Blueprint $table) {
            $table->renameColumn('volume', 'volume_quantity');
            $table->integer('volume_count')->nullable()->after('volume_quantity');
            $table->string('settings', 10)->nullable()->after('volume_count'); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bom_details', function (Blueprint $table) {
            $table->renameColumn('volume_quantity', 'volume');
            $table->dropColumn(['volume_count', 'settings']);
        });
    }
};
