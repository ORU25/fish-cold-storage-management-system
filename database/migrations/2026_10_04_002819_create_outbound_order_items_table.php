<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One line per product in an order.
     */
    public function up(): void
    {
        Schema::create('outbound_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('outbound_order_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained();
            $table->unsignedInteger('quantity_requested');
            $table->unsignedInteger('quantity_scanned')->default(0);
            $table->timestamps();

            $table->unique(['outbound_order_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outbound_order_items');
    }
};
