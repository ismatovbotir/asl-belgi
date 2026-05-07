<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('km_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('km_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('km_order_item_id')->nullable()->constrained('km_order_items')->nullOnDelete();
            $table->foreignId('aggregation_id')->nullable()->constrained('km_aggregations')->nullOnDelete();

            // Raw code exactly as received (file or API)
            $table->string('code', 500);
            // Normalized: GS characters (\x1D) stripped — used for API calls
            $table->string('cis', 191)->unique();

            // Parsed GS1 Application Identifiers
            $table->char('gtin', 14)->nullable();
            $table->string('serial_number', 50)->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('batch', 50)->nullable();

            // Lifecycle: available → printed → applied → aggregated → in_circulation → spoiled/withdrawn
            $table->string('status')->default('available');

            $table->boolean('label_printed')->default(false);
            $table->timestamp('printed_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('km_order_id');
            $table->index('gtin');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('km_codes');
    }
};
