<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class RenameRfqTableToRfqs extends Migration
{
    public function up(): void
    {
        Schema::rename('rfq', 'rfqs');
    }
    public function down(): void
    {
        Schema::rename('rfqs', 'rfq');
    }
}
