<?php

namespace App\Http\Controllers;

use App\Enums\BoxStatus;
use App\Enums\OrderStatus;
use App\Models\Box;
use App\Models\OutboundOrder;
use App\Models\OutboundOrderItem;
use App\Models\OutboundScan;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff outbound screen (PRD 5.6): only open orders are listed.
 */
class OutboundController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('outbound/index', [
            'orders' => OutboundOrder::where('status', OrderStatus::Open)
                ->with('items.product:id,display_name')
                ->withSum('items', 'quantity_requested')
                ->withSum('items', 'quantity_scanned')
                ->orderBy('order_date')
                ->orderBy('order_number')
                ->get(),
        ]);
    }

    public function show(OutboundOrder $order): Response
    {
        $order->load('items.product:id,display_name');

        return Inertia::render('outbound/show', [
            'order' => $order,
            'pickList' => $order->status === OrderStatus::Open ? $this->pickList($order) : [],
            'recentScans' => OutboundScan::whereIn('outbound_order_item_id', $order->items->pluck('id'))
                ->with(['box:id,qr_code,product_id,expired_date', 'box.product:id,display_name'])
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }

    /**
     * FEFO pick list per unfinished item: the boxes to fetch, earliest expiry first, grouped by expiry date and location.
     * When the nearest date runs out the list simply continues with the next date.
     * ponytail: two open orders for the same product get the same recommendations; the scan-time FEFO check keeps it honest.
     *
     * @return list<array{product: string, remaining: int, groups: list<array{expired_date: string, location: string, codes: list<string>}>}>
     */
    private function pickList(OutboundOrder $order): array
    {
        return $order->items
            ->filter(fn (OutboundOrderItem $item): bool => $item->remaining() > 0)
            ->map(function (OutboundOrderItem $item): array {
                $boxes = Box::with('location:id,name')
                    ->where('product_id', $item->product_id)
                    ->where('status', BoxStatus::InWarehouse)
                    ->orderBy('expired_date')
                    ->orderBy('location_id')
                    ->orderBy('qr_code')
                    ->limit($item->remaining())
                    ->get(['id', 'qr_code', 'location_id', 'expired_date']);

                return [
                    'product' => $item->product->display_name,
                    'remaining' => $item->remaining(),
                    'groups' => $boxes
                        ->groupBy(fn (Box $box): string => $box->expired_date->toDateString().'|'.$box->location_id)
                        ->map(fn ($group): array => [
                            'expired_date' => $group->first()->expired_date->toDateString(),
                            'location' => $group->first()->location->name ?? '-',
                            'codes' => $group->pluck('qr_code')->all(),
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }
}
