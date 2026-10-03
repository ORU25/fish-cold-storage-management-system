<?php

namespace App\Http\Controllers;

use App\Enums\BoxStatus;
use App\Enums\OrderStatus;
use App\Models\ActivityLog;
use App\Models\Box;
use App\Models\OutboundOrder;
use App\Models\OutboundScan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Scan one box out against an open order (PRD 5.6, rancangan 4.4).
 * Order, box and item rows are locked so two staff can never send out the same box, or overfill an item.
 * A fully scanned order stays open until an Admin checks it physically and completes it.
 */
class OutboundScanController extends Controller
{
    public function store(Request $request, OutboundOrder $order): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'fefo_reason' => ['nullable', 'string', 'max:500'],
        ]);
        $code = Str::upper(trim($validated['code']));

        DB::transaction(function () use ($order, $code, $validated, $request): void {
            $order = OutboundOrder::lockForUpdate()->findOrFail($order->id);

            if ($order->status !== OrderStatus::Open) {
                $this->reject('Order ini tidak lagi terbuka.');
            }

            $box = Box::with('product:id,display_name')->where('qr_code', $code)->lockForUpdate()->first()
                ?? $this->reject("Kode {$code} tidak dikenal atau belum discan masuk.");

            match ($box->status) {
                BoxStatus::InWarehouse => null,
                BoxStatus::Outbound => $this->reject("Dus {$code} sudah keluar."),
                BoxStatus::PendingAdjustment => $this->reject("Dus {$code} sedang diajukan hilang/rusak, tidak bisa dikeluarkan."),
                BoxStatus::Lost, BoxStatus::Damaged => $this->reject("Dus {$code} tercatat hilang/rusak."),
            };

            $item = $order->items()->where('product_id', $box->product_id)->lockForUpdate()->first()
                ?? $this->reject("Produk {$box->product->display_name} tidak ada di order ini.");

            if ($item->remaining() <= 0) {
                $this->reject("Item {$box->product->display_name} sudah terpenuhi.");
            }

            $fefoViolation = $this->checkFefo($box, $validated['fefo_reason'] ?? null);

            OutboundScan::create([
                'outbound_order_item_id' => $item->id,
                'box_id' => $box->id,
                'scanned_by' => $request->user()->id,
                'fefo_violation' => $fefoViolation,
                'fefo_reason' => $fefoViolation ? $validated['fefo_reason'] : null,
            ]);

            $box->update([
                'status' => BoxStatus::Outbound,
                'outbound_order_id' => $order->id,
                'scanned_out_by' => $request->user()->id,
                'scanned_out_at' => now(),
            ]);
            $item->increment('quantity_scanned');

            ActivityLog::record('box.scanned_out', $box, ['status' => BoxStatus::InWarehouse->value], [
                'status' => BoxStatus::Outbound->value,
                'order_number' => $order->order_number,
                'fefo_violation' => $fefoViolation,
            ]);

            if ($fefoViolation) {
                ActivityLog::record('box.fefo_override', $box, newValues: ['order_number' => $order->order_number, 'expired_date' => $box->expired_date->toDateString()], reason: $validated['fefo_reason']);
            }
        });

        return back();
    }

    /**
     * FEFO: if a matching box that expires earlier is still in the warehouse, the scan needs a reason.
     * Without one the scan is refused with a "fefo" warning so the screen can ask for it.
     *
     * @return bool whether this scan is a FEFO violation
     */
    private function checkFefo(Box $box, ?string $reason): bool
    {
        $earlier = Box::where('product_id', $box->product_id)
            ->where('status', BoxStatus::InWarehouse)
            ->where('expired_date', '<', $box->expired_date->toDateString())
            ->selectRaw('count(*) as total, min(expired_date) as earliest')
            ->first();

        if ((int) $earlier->total === 0) {
            return false;
        }

        if (blank($reason)) {
            $earliest = Carbon::parse($earlier->earliest)->format('d/m/Y');

            throw ValidationException::withMessages([
                'fefo' => "Masih ada {$earlier->total} dus {$box->product->display_name} dengan expired lebih awal ({$earliest}). Isi alasan untuk tetap mengeluarkan dus ini.",
            ]);
        }

        return true;
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['code' => $message]);
    }
}
