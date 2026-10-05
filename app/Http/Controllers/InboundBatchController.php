<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Box;
use App\Models\InboundBatch;
use App\Models\Location;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InboundBatchController extends Controller
{
    public function index(): Response
    {
        $batches = fn (bool $finished) => InboundBatch::with('createdBy:id,name')
            ->withCount('boxes')
            ->when($finished, fn ($query) => $query->whereNotNull('finished_at'), fn ($query) => $query->whereNull('finished_at'))
            ->latest('started_at');

        return Inertia::render('inbound/index', [
            'openBatches' => $batches(false)->get(),
            'finishedBatches' => $batches(true)->limit(20)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_name' => ['required', 'string', 'max:255'],
            'delivery_note_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $batch = InboundBatch::create([...$validated, 'created_by' => $request->user()->id, 'started_at' => now()]);

        ActivityLog::record('inbound.started', $batch, newValues: $validated);

        return to_route('inbound.show', $batch)->with('success', 'Batch masuk dibuat.');
    }

    public function show(InboundBatch $batch): Response
    {
        return Inertia::render('inbound/show', [
            'batch' => $batch->load('createdBy:id,name'),
            'boxCount' => $batch->boxes()->count(),
            'recentBoxes' => $batch->boxes()
                ->with(['product:id,display_name', 'location:id,name'])
                ->latest('scanned_in_at')
                ->limit(10)
                ->get(['id', 'qr_code', 'product_id', 'location_id', 'production_date', 'expired_date', 'status', 'scanned_in_at']),
            'summary' => $this->summary($batch),
            'products' => Product::active()->orderBy('display_name')->get(['id', 'display_name', 'shelf_life_days']),
            'locations' => Location::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function finish(InboundBatch $batch): RedirectResponse
    {
        if (! $batch->boxes()->exists()) {
            throw ValidationException::withMessages(['batch' => 'Belum ada dus yang discan. Batalkan batch ini jika tidak jadi dipakai.']);
        }

        if (! $batch->isFinished()) {
            $batch->update(['finished_at' => now()]);

            ActivityLog::record('inbound.finished', $batch, newValues: ['box_count' => $batch->boxes()->count()]);
        }

        return to_route('inbound.show', $batch)->with('success', 'Batch selesai.');
    }

    /**
     * Cancel a batch that never got a box: it holds no stock data, so it is deleted and only the log keeps its trace.
     * Batches with boxes (even cancelled ones, which are soft deleted) can not be removed.
     */
    public function destroy(InboundBatch $batch): RedirectResponse
    {
        if ($batch->boxes()->withTrashed()->exists()) {
            throw ValidationException::withMessages(['batch' => 'Batch yang sudah berisi dus tidak bisa dibatalkan.']);
        }

        ActivityLog::record('inbound.cancelled', $batch, oldValues: $batch->only('supplier_name', 'delivery_note_number', 'notes', 'created_by', 'started_at'));

        $batch->delete();

        return to_route('inbound.index')->with('success', 'Batch dibatalkan.');
    }

    /**
     * Box count per product and date, shown when the batch is finished (PRD 5.4).
     *
     * @return list<array{product: string, production_date: ?string, expired_date: string, total: int}>
     */
    private function summary(InboundBatch $batch): array
    {
        return $batch->boxes()
            ->with('product:id,display_name')
            ->selectRaw('product_id, production_date, expired_date, count(*) as total')
            ->groupBy('product_id', 'production_date', 'expired_date')
            ->get()
            ->map(fn (Box $row): array => [
                'product' => $row->product->display_name,
                'production_date' => $row->production_date?->toDateString(),
                'expired_date' => $row->expired_date->toDateString(),
                'total' => (int) $row->total,
            ])
            ->sortBy(['product', 'expired_date'])
            ->values()
            ->all();
    }
}
