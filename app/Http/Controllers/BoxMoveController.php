<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Box;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bulk location move (rancangan 4.7): pick the destination once, then every scanned box is moved there straight away.
 * A wrong scan is undone from the list of today's moves, which puts the box back where it was.
 */
class BoxMoveController extends Controller
{
    private const ACTIONS = ['box.location_changed', 'box.move_cancelled'];

    public function index(Request $request): Response
    {
        $logs = ActivityLog::where('user_id', $request->user()->id)
            ->whereIn('action', self::ACTIONS)
            ->where('created_at', '>=', today())
            ->latest('id')
            ->limit(50)
            ->get();
        $boxes = Box::with('product:id,display_name')->whereIn('id', $logs->pluck('box_id'))->get()->keyBy('id');
        $latestLogIds = ActivityLog::whereIn('box_id', $boxes->keys())->whereIn('action', self::ACTIONS)
            ->groupBy('box_id')->selectRaw('max(id) as id')->pluck('id')->all();
        $names = Location::whereIn('id', $logs->flatMap(fn (ActivityLog $log): array => [$log->old_values['location_id'] ?? null, $log->new_values['location_id'] ?? null]))
            ->pluck('name', 'id');

        return Inertia::render('boxes/move', [
            'locations' => Location::active()->orderBy('name')->get(['id', 'name']),
            'recentMoves' => $logs->filter(fn (ActivityLog $log): bool => $boxes->has($log->box_id))->values()->map(function (ActivityLog $log) use ($boxes, $latestLogIds, $names): array {
                $box = $boxes[$log->box_id];

                return [
                    'id' => $log->id,
                    'is_cancellation' => $log->action === 'box.move_cancelled',
                    'code' => $box->qr_code,
                    'product' => $box->product->display_name,
                    'from' => $names[$log->old_values['location_id'] ?? null] ?? '-',
                    'to' => $names[$log->new_values['location_id'] ?? null] ?? '-',
                    'created_at' => $log->created_at,
                    'can_cancel' => $log->action === 'box.location_changed' && $this->isUndoable($log, $box, in_array($log->id, $latestLogIds)),
                ];
            }),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'location_id' => ['required', Rule::exists('locations', 'id')->where('is_active', true)],
        ]);
        $code = Str::upper(trim($validated['code']));

        DB::transaction(function () use ($code, $validated): void {
            $box = Box::where('qr_code', $code)->lockForUpdate()->first()
                ?? throw ValidationException::withMessages(['code' => "Kode {$code} tidak dikenal atau belum discan masuk."]);

            $box->moveTo(Location::findOrFail($validated['location_id']), 'code');
        });

        return back();
    }

    /**
     * Undo a wrong scan: the box goes back to where it was, logged as box.move_cancelled.
     * Only the box's latest move can be undone, and only while it is still at that move's destination.
     */
    public function cancel(ActivityLog $log): RedirectResponse
    {
        abort_unless($log->action === 'box.location_changed', 404);

        DB::transaction(function () use ($log): void {
            $box = Box::lockForUpdate()->findOrFail($log->box_id);
            $isLatest = ! ActivityLog::where('box_id', $box->id)->whereIn('action', self::ACTIONS)->where('id', '>', $log->id)->exists();

            if (! $this->isUndoable($log, $box, $isLatest)) {
                throw ValidationException::withMessages(['move' => "Pindahan dus {$box->qr_code} tidak bisa dibatalkan, dus sudah dipindah lagi atau tidak lagi di gudang."]);
            }

            $box->moveTo(Location::findOrFail($log->old_values['location_id']), 'move', 'box.move_cancelled');
        });

        return back();
    }

    private function isUndoable(ActivityLog $log, Box $box, bool $isLatest): bool
    {
        return $isLatest && $box->isInStock() && $box->location_id === ($log->new_values['location_id'] ?? null);
    }
}
