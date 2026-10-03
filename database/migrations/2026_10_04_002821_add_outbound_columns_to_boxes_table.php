<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('boxes', function (Blueprint $table) {
            $table->foreignUuid('outbound_order_id')->nullable()->after('scanned_in_at')->constrained();
            $table->foreignUuid('scanned_out_by')->nullable()->after('outbound_order_id')->constrained('users');
            $table->timestamp('scanned_out_at')->nullable()->after('scanned_out_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('boxes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('outbound_order_id');
            $table->dropConstrainedForeignId('scanned_out_by');
            $table->dropColumn('scanned_out_at');
        });
    }
};
