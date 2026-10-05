<?php

namespace App\Http\Controllers;

use App\Enums\BoxStatus;
use App\Enums\OrderStatus;
use App\Models\ActivityLog;
use App\Models\Box;
use App\Models\OutboundOrder;
use App\Models\OutboundOrderItem;
use App\Models\OutboundScan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin undoes a wrong outbound scan while its order is still open (PRD 5.9): the box goes back to the warehouse,
 * the item needs one more box again, and the scan row stays as history with the reason.
 * Staff can not do this, so whoever handles the goods can not erase the trace.
 */
class OutboundScanCancellationController extends Controller
{
    public function store(Request $request, OutboundScan $scan): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        DB::transaction(function () use ($request, $scan, $validated): void {
            // Same lock order as scanning (order, box, item), so a concurrent scan, "Selesaikan order" or order
            // cancellation waits for this instead of deadlocking.
            $order = OutboundOrder::lockForUpdate()->findOrFail($scan->item->outbound_order_id);
            $box = Box::lockForUpdate()->findOrFail($scan->box_id);
            $item = OutboundOrderItem::lockForUpdate()->findOrFail($scan->outbound_order_item_id);
            $scan = OutboundScan::lockForUpdate()->findOrFail($scan->id);

            if ($scan->cancelled_at !== null) {
                throw ValidationException::withMessages(['reason' => "Scan dus {$box->qr_code} sudah dibatalkan."]);
            }

            if ($order->status !== OrderStatus::Open || $box->status !== BoxStatus::Outbound || $box->outbound_order_id !== $order->id) {
                throw ValidationException::withMessages(['reason' => 'Order sudah tidak open, scan keluarnya tidak bisa dibatalkan.']);
            }

            $box->update(['status' => BoxStatus::InWarehouse, 'outbound_order_id' => null, 'scanned_out_by' => null, 'scanned_out_at' => null]);
            $item->decrement('quantity_scanned');
            $scan->update(['cancelled_at' => now(), 'cancelled_by' => $request->user()->id, 'cancel_reason' => $validated['reason']]);

            ActivityLog::record('box.outbound_cancelled', $box, ['status' => BoxStatus::Outbound->value, 'order_number' => $order->order_number], ['status' => BoxStatus::InWarehouse->value], $validated['reason']);
        });

        return back()->with('success', 'Scan keluar dibatalkan.');
    }
}
