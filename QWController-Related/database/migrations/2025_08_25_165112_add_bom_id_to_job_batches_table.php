<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('job_batches', function (Blueprint $table) {
            $table->unsignedBigInteger('bom_id')->nullable()->index()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('job_batches', function (Blueprint $table) {
            $table->dropColumn('bom_id');
        });
    }
};
