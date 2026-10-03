<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row = one fish/grade/size combination (MB A 3-5 and MB A 6-10 are two products).
     * Grade and size are '' instead of NULL so the unique constraint still applies.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('fish_name');
            $table->string('grade')->default('');
            $table->string('size')->default('');
            $table->string('display_name');
            $table->decimal('kg_per_carton', 8, 2)->default(10);
            $table->unsignedSmallInteger('shelf_life_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['fish_name', 'grade', 'size']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
