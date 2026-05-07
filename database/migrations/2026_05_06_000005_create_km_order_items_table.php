<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('km_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('km_order_id')->constrained()->cascadeOnDelete();
            $table->string('buffer_id')->nullable();
            $table->string('gtin')->nullable();
            $table->integer('quantity')->default(0);
            // PENDING | READY | DOWNLOADED | DEPLETED | CLOSED
            $table->string('status')->default('PENDING');
            $table->integer('codes_downloaded')->default(0);
            $table->json('raw_data')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('km_order_items');
    }
};
