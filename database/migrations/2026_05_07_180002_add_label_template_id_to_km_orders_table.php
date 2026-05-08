<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('km_orders', function (Blueprint $table) {
            $table->foreignId('label_template_id')
                  ->nullable()->after('pdf_path')
                  ->constrained('label_templates')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('km_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('label_template_id');
        });
    }
};
