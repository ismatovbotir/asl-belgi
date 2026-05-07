<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('name_ru')->nullable()->after('name');
            $table->string('name_uz')->nullable()->after('name_ru');
            $table->string('inn')->nullable()->after('name_uz');
            $table->string('category_code')->nullable()->after('category');
            $table->string('product_group_code')->nullable()->after('product_group');
            $table->string('tnved_code')->nullable()->after('product_group_code');
            $table->string('package_type')->nullable()->after('tnved_code');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'name_ru', 'name_uz', 'inn',
                'category_code', 'product_group_code', 'tnved_code', 'package_type',
            ]);
        });
    }
};
