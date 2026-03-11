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
        Schema::create('bom_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('folder_name')->nullable();
            $table->string('file_name')->nullable();
            $table->string('version')->nullable();
            $table->enum('status', ['completed', 'completed_with_errors'])->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('bom_upload_id')->nullable();
            $table->json('errors')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('bom_upload_id')->references('id')->on('bom_uploads')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bom_audit_logs');
    }
};
