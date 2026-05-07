<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_groups', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary(); // official ASL BELGISI group IDs
            $table->string('code')->unique();              // e.g. 'appliances'
            $table->string('name_ru');                     // Russian display name
        });

        DB::table('product_groups')->insert([
            ['id' =>  3, 'code' => 'tobacco',       'name_ru' => 'Табачная продукция'],
            ['id' =>  7, 'code' => 'pharma',         'name_ru' => 'Лекарственные средства'],
            ['id' => 10, 'code' => 'medicals',       'name_ru' => 'Изделия медицинского назначения'],
            ['id' => 11, 'code' => 'alcohol',        'name_ru' => 'Алкогольная продукция'],
            ['id' => 13, 'code' => 'water',          'name_ru' => 'Вода и прохладительные напитки'],
            ['id' => 15, 'code' => 'beer',           'name_ru' => 'Пиво и пивные напитки'],
            ['id' => 17, 'code' => 'bio',            'name_ru' => 'Биологически активные добавки'],
            ['id' => 18, 'code' => 'appliances',     'name_ru' => 'Бытовая техника'],
            ['id' => 19, 'code' => 'antiseptic',     'name_ru' => 'Спиртосодержащая непищевая продукция'],
            ['id' => 33, 'code' => 'vegetableoil',   'name_ru' => 'Пищевая масложировая продукция'],
            ['id' => 53, 'code' => 'fertilizers',    'name_ru' => 'Минеральные удобрения и средства защиты растений'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('product_groups');
    }
};
