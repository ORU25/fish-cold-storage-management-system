<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Outbound columns (outbound_order_id, scanned_out_by, scanned_out_at) are added in stage 3.
     */
    public function up(): void
    {
        Schema::create('boxes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('qr_label_id')->unique()->constrained();
            $table->string('qr_code')->unique();
            $table->foreignUuid('inbound_batch_id')->constrained();
            $table->foreignUuid('product_id')->constrained();
            $table->foreignUuid('location_id')->nullable()->constrained();
            $table->date('production_date')->nullable();
            $table->date('expired_date')->index();
            $table->enum('status', ['in_warehouse', 'outbound', 'pending_adjustment', 'lost', 'damaged'])->default('in_warehouse')->index();
            $table->foreignUuid('scanned_in_by')->constrained('users');
            $table->timestamp('scanned_in_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'status', 'expired_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boxes');
    }
};
