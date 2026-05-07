<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('km_orders', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('external_order_id')->unique()->nullable();
            $table->string('product_group')->nullable();
            $table->string('release_method_type')->nullable();
            // PENDING | READY | DOWNLOADED | DONE | CLOSED
            $table->string('status')->default('PENDING');
            $table->integer('total_codes_requested')->default(0);
            $table->integer('total_codes_downloaded')->default(0);
            $table->string('pdf_path')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
            $table->timestamp('closed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('km_orders');
    }
};
