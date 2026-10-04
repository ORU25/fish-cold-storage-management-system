<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A wrong outbound scan is cancelled, not deleted, so the scan stays in the order history (PRD 5.9).
     */
    public function up(): void
    {
        Schema::table('outbound_scans', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('fefo_reason');
            $table->foreignUuid('cancelled_by')->nullable()->after('cancelled_at')->constrained('users');
            $table->text('cancel_reason')->nullable()->after('cancelled_by');
        });
    }

    public function down(): void
    {
        Schema::table('outbound_scans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancelled_at', 'cancel_reason']);
        });
    }
};
