<?php

namespace App\Http\Controllers;

use App\Enums\AdjustmentStatus;
use App\Enums\AdjustmentType;
use App\Enums\BoxStatus;
use App\Models\ActivityLog;
use App\Models\Adjustment;
use App\Models\Box;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lost or damaged boxes (PRD 5.8, rancangan 4.6): Admin requests, the box waits as pending_adjustment,
 * and only the Owner approves (box becomes lost/damaged) or rejects (box back in the warehouse).
 * Logs use the box as subject so the request and the decision show up in the box history.
 */
class AdjustmentController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['status' => ['nullable', Rule::enum(AdjustmentStatus::class)]]);
        $filters['status'] ??= AdjustmentStatus::Pending->value;

        return Inertia::render('adjustments/index', [
            'adjustments' => Adjustment::with(['box:id,qr_code,product_id,location_id', 'box.product:id,display_name', 'box.location:id,name', 'requestedBy:id,name', 'decidedBy:id,name'])
                ->where('status', $filters['status'])
                ->latest()
                ->paginate(30)
                ->withQueryString(),
            'filters' => $filters,
            'statuses' => array_column(AdjustmentStatus::cases(), 'value'),
        ]);
    }

    public function store(Request $request, Box $box): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::enum(AdjustmentType::class)],
            'reason' => ['required', 'string', 'max:500'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ]);
        $photoPath = $request->file('photo')?->store('adjustments');

        try {
            DB::transaction(function () use ($request, $box, $validated, $photoPath): void {
                $box = Box::lockForUpdate()->findOrFail($box->id);

                // A pending request already moved the box out of in_warehouse, so this also keeps it to one pending per box.
                if ($box->status !== BoxStatus::InWarehouse) {
                    throw ValidationException::withMessages(['reason' => "Dus {$box->qr_code} tidak berstatus di gudang, tidak bisa diajukan."]);
                }

                Adjustment::create([
                    'box_id' => $box->id,
                    'type' => $validated['type'],
                    'reason' => $validated['reason'],
                    'photo_path' => $photoPath,
                    'status' => AdjustmentStatus::Pending,
                    'requested_by' => $request->user()->id,
                ]);
                $box->update(['status' => BoxStatus::PendingAdjustment]);

                ActivityLog::record('adjustment.requested', $box, ['status' => BoxStatus::InWarehouse->value], ['status' => BoxStatus::PendingAdjustment->value, 'type' => $validated['type']], $validated['reason']);
            });
        } catch (ValidationException $exception) {
            if ($photoPath !== null) {
                Storage::delete($photoPath);
            }

            throw $exception;
        }

        return back()->with('success', 'Pengajuan adjustment dikirim.');
    }

    public function approve(Request $request, Adjustment $adjustment): RedirectResponse
    {
        return $this->decide($request, $adjustment, AdjustmentStatus::Approved);
    }

    public function reject(Request $request, Adjustment $adjustment): RedirectResponse
    {
        return $this->decide($request, $adjustment, AdjustmentStatus::Rejected);
    }

    public function photo(Adjustment $adjustment): StreamedResponse
    {
        abort_if($adjustment->photo_path === null, 404);

        return Storage::response($adjustment->photo_path);
    }

    private function decide(Request $request, Adjustment $adjustment, AdjustmentStatus $decision): RedirectResponse
    {
        $validated = $request->validate(['decision_note' => ['nullable', 'string', 'max:500']]);

        DB::transaction(function () use ($request, $adjustment, $decision, $validated): void {
            $box = Box::lockForUpdate()->findOrFail($adjustment->box_id);
            $adjustment = Adjustment::lockForUpdate()->findOrFail($adjustment->id);

            if ($adjustment->status !== AdjustmentStatus::Pending) {
                throw ValidationException::withMessages(['decision_note' => "Pengajuan dus {$box->qr_code} sudah diputuskan."]);
            }

            $boxStatus = $decision === AdjustmentStatus::Approved ? $adjustment->type->boxStatus() : BoxStatus::InWarehouse;

            $adjustment->update([
                'status' => $decision,
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
                'decision_note' => $validated['decision_note'] ?? null,
            ]);
            $box->update(['status' => $boxStatus]);

            ActivityLog::record("adjustment.{$decision->value}", $box, ['status' => BoxStatus::PendingAdjustment->value], ['status' => $boxStatus->value, 'type' => $adjustment->type->value], $validated['decision_note'] ?? null);
        });

        return back()->with('success', $decision === AdjustmentStatus::Approved ? 'Adjustment disetujui.' : 'Adjustment ditolak.');
    }
}
