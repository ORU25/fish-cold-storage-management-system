<?php

namespace App\Http\Controllers;

use App\Enums\AdjustmentStatus;
use App\Enums\BoxStatus;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Adjustment;
use App\Models\Box;
use App\Models\OutboundScan;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff get quick links only; Admin also sees the stock recap and boxes near expiry;
 * the Owner gets the full dashboard (PRD 5.11): pending adjustments, FEFO violations, Admin corrections and daily in/out.
 */
class DashboardController extends Controller
{
    /**
     * Admin actions the Owner keeps an eye on (rancangan 6).
     */
    private const ADMIN_ACTIONS = ['box.updated', 'box.location_changed', 'box.move_cancelled', 'box.inbound_cancelled', 'box.outbound_cancelled', 'order.cancelled', 'qr.voided'];

    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        if ($user->role === Role::Staff) {
            return Inertia::render('dashboard', ['stock' => null, 'owner' => null]);
        }

        $days = (int) ($request->validate(['days' => ['nullable', 'integer', 'min:1', 'max:365']])['days'] ?? 30);

        return Inertia::render('dashboard', [
            'stock' => [
                'perProduct' => Box::stockPerProduct(),
                'days' => $days,
                'nearExpiry' => Box::with(['product:id,display_name', 'location:id,name'])
                    ->whereIn('status', Box::IN_STOCK)
                    ->where('expired_date', '<=', today()->addDays($days))
                    ->orderBy('expired_date')
                    ->orderBy('qr_code')
                    ->limit(50)
                    ->get(['id', 'qr_code', 'product_id', 'location_id', 'expired_date', 'status']),
            ],
            'owner' => $user->role === Role::Owner ? $this->ownerPanels() : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function ownerPanels(): array
    {
        return [
            'pendingAdjustments' => Adjustment::with(['box:id,qr_code,product_id,location_id', 'box.product:id,display_name', 'box.location:id,name', 'requestedBy:id,name'])
                ->where('status', AdjustmentStatus::Pending)
                ->oldest()
                ->get(),
            'fefoViolations' => OutboundScan::with(['box:id,qr_code,product_id,expired_date', 'box.product:id,display_name', 'item.order:id,order_number', 'scannedBy:id,name'])
                ->where('fefo_violation', true)
                ->whereNull('cancelled_at')
                ->latest()
                ->limit(10)
                ->get(),
            'adminActions' => ActivityLog::with(['user:id,name', 'subject'])
                ->whereIn('action', self::ADMIN_ACTIONS)
                ->latest('id')
                ->limit(10)
                ->get()
                ->map(fn (ActivityLog $log): array => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'user' => $log->user?->name,
                    'box_id' => $log->box_id,
                    // A box whose inbound scan was cancelled is soft deleted, so its code comes from the logged old values.
                    'subject' => $log->subject?->qr_code ?? $log->subject?->order_number ?? $log->subject?->code ?? $log->old_values['qr_code'] ?? null,
                    'reason' => $log->reason,
                    'created_at' => $log->created_at,
                ]),
            'daily' => $this->dailyInOut(14),
        ];
    }

    /**
     * Boxes scanned in and out per day over the last $days days, oldest first, days without movement included.
     *
     * @return Collection<int, array{date: string, in: int, out: int}>
     */
    private function dailyInOut(int $days): Collection
    {
        $from = today()->subDays($days - 1);
        $in = Box::where('scanned_in_at', '>=', $from)->selectRaw('date(scanned_in_at) as day, count(*) as total')->groupBy('day')->pluck('total', 'day');
        $out = Box::where('status', BoxStatus::Outbound)->where('scanned_out_at', '>=', $from)->selectRaw('date(scanned_out_at) as day, count(*) as total')->groupBy('day')->pluck('total', 'day');

        return collect(range(0, $days - 1))->map(function (int $offset) use ($from, $in, $out): array {
            $day = Carbon::parse($from)->addDays($offset)->toDateString();

            return ['date' => $day, 'in' => (int) ($in[$day] ?? 0), 'out' => (int) ($out[$day] ?? 0)];
        });
    }
}
