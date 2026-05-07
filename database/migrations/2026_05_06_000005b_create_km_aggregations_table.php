<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('km_aggregations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('km_order_id')->nullable()->constrained()->nullOnDelete();
            // SSCC or internal box/pallet code
            $table->string('code', 100)->unique();
            // UNIT_BOX | MASTER_BOX | PALLET
            $table->string('type', 20);
            // Self-referential: box inside a pallet
            $table->foreignId('parent_id')->nullable()->constrained('km_aggregations')->nullOnDelete();
            // OPEN | CLOSED | SHIPPED
            $table->string('status', 20)->default('OPEN');
            $table->unsignedInteger('codes_count')->default(0);
            $table->timestamps();
            $table->timestamp('closed_at')->nullable();

            $table->index('km_order_id');
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('km_aggregations');
    }
};
