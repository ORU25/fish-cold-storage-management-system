<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Orders have one way to stop: cancelled, from draft or open (PRD 5.5). Cancelling returns scanned boxes to the warehouse,
     * so existing closed orders become cancelled and their boxes go back too.
     * ponytail: the returned boxes get no activity log row here; it only touches pre-release test data.
     */
    public function up(): void
    {
        $closedOrderIds = DB::table('outbound_orders')->where('status', 'closed')->pluck('id');

        DB::table('boxes')->whereIn('outbound_order_id', $closedOrderIds)->where('status', 'outbound')->update([
            'status' => 'in_warehouse',
            'outbound_order_id' => null,
            'scanned_out_by' => null,
            'scanned_out_at' => null,
        ]);
        DB::table('outbound_orders')->whereIn('id', $closedOrderIds)->update(['status' => 'cancelled']);

        Schema::table('outbound_orders', function (Blueprint $table) {
            $table->renameColumn('close_reason', 'cancel_reason');
        });
        Schema::table('outbound_orders', function (Blueprint $table) {
            $table->enum('status', ['draft', 'open', 'completed', 'cancelled'])->default('draft')->change();
        });
    }

    public function down(): void
    {
        Schema::table('outbound_orders', function (Blueprint $table) {
            $table->enum('status', ['draft', 'open', 'completed', 'closed', 'cancelled'])->default('draft')->change();
        });
        Schema::table('outbound_orders', function (Blueprint $table) {
            $table->renameColumn('cancel_reason', 'close_reason');
        });
    }
};
