<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviseBoxRequest;
use App\Models\ActivityLog;
use App\Models\Box;
use App\Models\Location;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One box: its data, its full history from the activity log, and the Admin corrections (PRD 5.9, rancangan 4.7).
 */
class BoxController extends Controller
{
    public function show(Box $box): Response
    {
        $box->load([
            'product:id,display_name',
            'location:id,name',
            'inboundBatch:id,supplier_name,delivery_note_number,started_at',
            'outboundOrder:id,order_number',
            'scannedInBy:id,name',
        ]);
        $history = ActivityLog::where('box_id', $box->id)->with('user:id,name')->latest('id')->get();

        return Inertia::render('boxes/show', [
            'box' => $box,
            'history' => $history,
            'names' => $this->namesIn($history->flatMap(fn (ActivityLog $log): array => [...array_values($log->old_values ?? []), ...array_values($log->new_values ?? [])])),
            'products' => Product::active()->orderBy('display_name')->get(['id', 'display_name', 'shelf_life_days']),
            'locations' => Location::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Revise product and dates. An empty expiry is worked out from the production date and the product's shelf life,
     * the same way as on inbound.
     */
    public function update(ReviseBoxRequest $request, Box $box): RedirectResponse
    {
        $validated = $request->validated();
        $expiredDate = $validated['expired_date'] ?? Product::findOrFail($validated['product_id'])->expiryFrom($validated['production_date'] ?? null);

        if ($expiredDate === null) {
            throw ValidationException::withMessages(['expired_date' => 'Isi tanggal expired, atau tanggal produksi untuk produk yang punya masa simpan.']);
        }

        DB::transaction(function () use ($box, $validated, $expiredDate): void {
            $box = Box::lockForUpdate()->findOrFail($box->id);

            if (! $box->isInStock()) {
                throw ValidationException::withMessages(['reason' => "Dus {$box->qr_code} tidak lagi di gudang, datanya tidak bisa direvisi."]);
            }

            $box->update([
                'product_id' => $validated['product_id'],
                'production_date' => $validated['production_date'] ?? null,
                'expired_date' => $expiredDate,
            ]);

            ActivityLog::recordChanges('box.updated', $box, $validated['reason'])
                ?? throw ValidationException::withMessages(['reason' => 'Tidak ada data yang berubah.']);
        });

        return back();
    }

    public function move(Request $request, Box $box): RedirectResponse
    {
        $validated = $request->validate(['location_id' => ['required', Rule::exists('locations', 'id')->where('is_active', true)]]);

        DB::transaction(fn () => Box::lockForUpdate()->findOrFail($box->id)->moveTo(Location::findOrFail($validated['location_id'])));

        return back();
    }

    /**
     * Product and location names for the ids that appear in the history, so the page shows "Blok A" instead of a UUID.
     *
     * @param  Collection<int, mixed>  $values
     * @return array<string, string>
     */
    private function namesIn(Collection $values): array
    {
        $ids = $values->filter(fn (mixed $value): bool => is_string($value) && Str::isUuid($value))->unique()->values()->all();

        return [
            ...Product::whereIn('id', $ids)->pluck('display_name', 'id')->all(),
            ...Location::whereIn('id', $ids)->pluck('name', 'id')->all(),
        ];
    }
}
