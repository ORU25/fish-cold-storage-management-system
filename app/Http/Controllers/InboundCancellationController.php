<?php

namespace App\Http\Controllers;

use App\Enums\BoxStatus;
use App\Enums\QrLabelStatus;
use App\Models\ActivityLog;
use App\Models\Box;
use App\Models\InboundBatch;
use App\Models\QrLabel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin undoes a wrong inbound scan while its batch is still open (PRD 5.9): the box is soft deleted and its sticker
 * becomes available again, so it can be scanned into the right batch. Once the batch is finished the box is part of
 * stock history; corrections then go through data revision or adjustment (stage 4).
 * Staff can not do this, so whoever handles the goods can not erase the trace.
 */
class InboundCancellationController extends Controller
{
    public function store(Request $request, string $boxId): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        DB::transaction(function () use ($boxId, $validated): void {
            $box = Box::lockForUpdate()->findOrFail($boxId);

            // Locked so a concurrent "Selesai Batch" waits for this cancellation (or the other way round).
            if (InboundBatch::lockForUpdate()->findOrFail($box->inbound_batch_id)->isFinished()) {
                throw ValidationException::withMessages(['reason' => 'Batch sudah ditutup, scan masuknya tidak bisa dibatalkan. Gunakan revisi data atau adjustment.']);
            }

            if ($box->status !== BoxStatus::InWarehouse) {
                throw ValidationException::withMessages(['reason' => "Dus {$box->qr_code} tidak lagi di gudang, scan masuknya tidak bisa dibatalkan."]);
            }

            $oldValues = ActivityLog::valuesOf($box, ['qr_code', 'inbound_batch_id', 'product_id', 'location_id', 'production_date', 'expired_date', 'scanned_in_by', 'scanned_in_at']);

            $box->delete();
            QrLabel::lockForUpdate()->findOrFail($box->qr_label_id)->update(['status' => QrLabelStatus::Available, 'used_at' => null]);

            ActivityLog::record('box.inbound_cancelled', $box, oldValues: $oldValues, reason: $validated['reason']);
        });

        return back();
    }
}
