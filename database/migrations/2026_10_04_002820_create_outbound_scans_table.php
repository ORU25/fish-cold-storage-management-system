<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per box scanned out. Cancellation columns (cancelled_at/by, cancel_reason) come with stage 4.
     */
    public function up(): void
    {
        Schema::create('outbound_scans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('outbound_order_item_id')->constrained();
            $table->foreignUuid('box_id')->constrained();
            $table->foreignUuid('scanned_by')->constrained('users');
            $table->boolean('fefo_violation')->default(false)->index();
            $table->text('fefo_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outbound_scans');
    }
};
