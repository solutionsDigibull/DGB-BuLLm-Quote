<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bom_data', function (Blueprint $table) {
            $table->text('Description')->nullable()->change();
            $table->text('Reference Designators')->nullable()->change();
            $table->text('CE Remarks')->nullable()->change();
            $table->text('Drawing Reference')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('bom_data', function (Blueprint $table) {
            $table->string('Description', 255)->change();
            $table->string('Reference Designators', 255)->nullable()->change();
            $table->string('CE Remarks', 255)->nullable()->change();
            $table->string('Drawing Reference', 255)->nullable()->change();
        });
    }
};