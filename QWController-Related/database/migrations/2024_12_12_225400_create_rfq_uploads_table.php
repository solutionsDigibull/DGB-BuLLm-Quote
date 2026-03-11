<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
		Schema::create('rfq_uploads', function (Blueprint $table) {
			$table->id();
			$table->unsignedBigInteger('project_id')->nullable();
			$table->unsignedBigInteger('supplier_id')->nullable();
			$table->unsignedBigInteger('bom_id')->nullable();
			$table->string('file_name')->nullable();
			$table->text('file_path')->nullable();
			$table->timestamp('upload_date')->nullable();
			$table->enum('validation_status', ['pending', 'validated', 'error'])->nullable();
			$table->text('validation_errors')->nullable();
			$table->enum('status', ['pending', 'submitted', 'revised', 'approved', 'rejected'])->nullable();
			$table->text('remarks')->nullable();
			$table->boolean('ai_scan_enabled')->default(false);
			$table->timestamps();

			$table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
			$table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');
			$table->foreign('bom_id')->references('id')->on('bom_uploads')->onDelete('cascade');
		});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
		DB::statement('ALTER TABLE rfq_uploads DROP CONSTRAINT IF EXISTS rfq_uploads_supplier_id_foreign');
        Schema::dropIfExists('rfq_uploads');
    }
};
