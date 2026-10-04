<?php

namespace App\Http\Controllers;

use App\Enums\BoxStatus;
use App\Enums\QrLabelStatus;
use App\Http\Requests\InboundScanRequest;
use App\Models\ActivityLog;
use App\Models\Box;
use App\Models\InboundBatch;
use App\Models\Product;
use App\Models\QrLabel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InboundScanController extends Controller
{
    /**
     * Scan one box in: only an available sticker may be used, and only once.
     * The label row is locked so two scanners can never use the same sticker.
     */
    public function store(InboundScanRequest $request, InboundBatch $batch): RedirectResponse
    {
        if ($batch->isFinished()) {
            throw ValidationException::withMessages(['code' => 'Batch ini sudah selesai.']);
        }

        $validated = $request->validated();
        $expiredDate = $validated['expired_date'] ?? Product::findOrFail($validated['product_id'])->expiryFrom($validated['production_date'] ?? null);

        if ($expiredDate === null) {
            throw ValidationException::withMessages(['expired_date' => 'Isi tanggal expired, atau tanggal produksi untuk produk yang punya masa simpan.']);
        }

        DB::transaction(function () use ($validated, $expiredDate, $batch, $request): void {
            $label = QrLabel::where('code', $validated['code'])->lockForUpdate()->first();

            $error = match ($label?->status) {
                null => "Kode {$validated['code']} tidak dikenal.",
                QrLabelStatus::Used => "Stiker {$validated['code']} sudah dipakai.",
                QrLabelStatus::Void => "Stiker {$validated['code']} sudah di-void.",
                QrLabelStatus::Available => null,
            };

            if ($error) {
                throw ValidationException::withMessages(['code' => $error]);
            }

            $box = Box::create([
                'qr_label_id' => $label->id,
                'qr_code' => $label->code,
                'inbound_batch_id' => $batch->id,
                'product_id' => $validated['product_id'],
                'location_id' => $validated['location_id'],
                'production_date' => $validated['production_date'] ?? null,
                'expired_date' => $expiredDate,
                'status' => BoxStatus::InWarehouse,
                'scanned_in_by' => $request->user()->id,
                'scanned_in_at' => now(),
            ]);

            $label->update(['status' => QrLabelStatus::Used, 'used_at' => now()]);

            ActivityLog::record('box.scanned_in', $box, newValues: ActivityLog::valuesOf($box, ['qr_code', 'inbound_batch_id', 'product_id', 'location_id', 'production_date', 'expired_date']));
        });

        return back();
    }
}
