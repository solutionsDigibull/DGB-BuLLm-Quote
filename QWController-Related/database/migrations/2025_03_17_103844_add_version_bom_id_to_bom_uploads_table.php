<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bom_uploads', function (Blueprint $table) {
            $table->unsignedBigInteger('version_bom_id')->nullable()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('bom_uploads', function (Blueprint $table) {
            $table->dropColumn('version_bom_id');
        });
    }
};