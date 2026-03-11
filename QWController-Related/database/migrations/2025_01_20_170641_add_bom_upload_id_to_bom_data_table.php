<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bom_data', function (Blueprint $table) {
            $table->foreignId('bom_upload_id')->nullable()->constrained('bom_uploads')->onDelete('cascade');
        });
    }
    public function down(): void
    {
        Schema::table('bom_data', function (Blueprint $table) {
            $table->dropForeign(['bom_upload_id']);
            $table->dropColumn('bom_upload_id');
        });
    }
};
