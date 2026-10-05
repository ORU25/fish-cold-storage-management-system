<?php

namespace App\Http\Controllers;

use App\Enums\BoxStatus;
use App\Enums\OrderStatus;
use App\Http\Requests\OutboundOrderRequest;
use App\Models\ActivityLog;
use App\Models\Box;
use App\Models\OutboundOrder;
use App\Models\OutboundScan;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin manages outbound orders (PRD 5.5): draft -> open -> completed (after a manual check once every item is scanned).
 * Draft and open orders can be cancelled; scanned boxes then go back to the warehouse.
 * Only open orders reserve stock and appear on the staff outbound screen.
 */
class OutboundOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['status' => ['nullable', Rule::enum(OrderStatus::class)]]);

        return Inertia::render('orders/index', [
            'orders' => OutboundOrder::with('createdBy:id,name')
                ->withSum('items', 'quantity_requested')
                ->withSum('items', 'quantity_scanned')
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->latest()
                ->paginate(30)
                ->withQueryString(),
            'filters' => $filters,
            'statuses' => array_column(OrderStatus::cases(), 'value'),
        ]);
    }

    public function create(): Response
    {
        return $this->form(null);
    }

    public function store(OutboundOrderRequest $request): RedirectResponse
    {
        $order = DB::transaction(function () use ($request): OutboundOrder {
            $order = OutboundOrder::create([
                ...$request->safe()->only('destination', 'order_date', 'notes'),
                'order_number' => OutboundOrder::nextOrderNumber(),
                'status' => OrderStatus::Draft,
                'created_by' => $request->user()->id,
            ]);

            $this->saveItems($order, $request->validated('items'));
            $this->ensureStockAvailable($order, $request->validated('items'));
            ActivityLog::record('order.created', $order, newValues: $this->snapshot($order));

            if ($request->boolean('open')) {
                $this->markOpen($order);
            }

            return $order;
        });

        return to_route('orders.show', $order)->with('success', 'Order disimpan.');
    }

    public function show(OutboundOrder $order): Response
    {
        $order->load(['items.product:id,display_name', 'createdBy:id,name']);

        return Inertia::render('orders/show', [
            'order' => $order,
            'available' => OutboundOrder::availableStock($order->items->pluck('product_id')->all(), except: $order),
            'scans' => OutboundScan::whereIn('outbound_order_item_id', $order->items->pluck('id'))
                ->with(['box:id,qr_code,product_id,location_id,expired_date', 'box.product:id,display_name', 'box.location:id,name', 'scannedBy:id,name', 'cancelledBy:id,name'])
                ->latest()
                ->get(),
        ]);
    }

    public function edit(OutboundOrder $order): Response|RedirectResponse
    {
        if ($order->status !== OrderStatus::Draft) {
            return to_route('orders.show', $order);
        }

        return $this->form($order->load('items'));
    }

    public function update(OutboundOrderRequest $request, OutboundOrder $order): RedirectResponse
    {
        DB::transaction(function () use ($request, $order): void {
            $order = OutboundOrder::lockForUpdate()->findOrFail($order->id);
            $this->ensureStatus($order, OrderStatus::Draft, 'Hanya order draft yang bisa diubah.');

            $old = $this->snapshot($order);
            $order->update($request->safe()->only('destination', 'order_date', 'notes'));
            $order->items()->delete();
            $this->saveItems($order, $request->validated('items'));
            $this->ensureStockAvailable($order, $request->validated('items'));
            ActivityLog::record('order.updated', $order, $old, $this->snapshot($order));

            if ($request->boolean('open')) {
                $this->markOpen($order);
            }
        });

        return to_route('orders.show', $order)->with('success', 'Order diperbarui.');
    }

    public function open(OutboundOrder $order): RedirectResponse
    {
        DB::transaction(function () use ($order): void {
            $order = OutboundOrder::lockForUpdate()->findOrFail($order->id);
            $this->ensureStatus($order, OrderStatus::Draft, 'Hanya order draft yang bisa dibuka.');
            $this->ensureStockAvailable($order);
            $this->markOpen($order);
        });

        return to_route('orders.show', $order)->with('success', 'Order dibuka.');
    }

    /**
     * Cancel a draft or open order. A partial order is cancelled and re-created with what the buyer can take,
     * so cancelling an open order returns its scanned boxes to the warehouse and needs a reason.
     */
    public function cancel(Request $request, OutboundOrder $order): RedirectResponse
    {
        $reason = $request->validate(['cancel_reason' => ['nullable', 'string', 'max:1000']])['cancel_reason'] ?? null;

        DB::transaction(function () use ($order, $reason): void {
            $order = OutboundOrder::lockForUpdate()->findOrFail($order->id);

            if (! in_array($order->status, [OrderStatus::Draft, OrderStatus::Open], true)) {
                throw ValidationException::withMessages(['order' => 'Hanya order draft atau open yang bisa dibatalkan.']);
            }

            if ($order->status === OrderStatus::Open && blank($reason)) {
                throw ValidationException::withMessages(['cancel_reason' => 'Alasan wajib diisi untuk membatalkan order yang sudah dibuka.']);
            }

            $order->boxes()->where('status', BoxStatus::Outbound)->lockForUpdate()->get()->each(function (Box $box) use ($order, $reason): void {
                $box->update(['status' => BoxStatus::InWarehouse, 'outbound_order_id' => null, 'scanned_out_by' => null, 'scanned_out_at' => null]);
                ActivityLog::record('box.outbound_cancelled', $box, ['status' => BoxStatus::Outbound->value, 'order_number' => $order->order_number], ['status' => BoxStatus::InWarehouse->value], $reason);
            });

            OutboundScan::whereIn('outbound_order_item_id', $order->items()->select('id'))->whereNull('cancelled_at')
                ->update(['cancelled_at' => now(), 'cancelled_by' => auth()->id(), 'cancel_reason' => $reason]);
            $order->items()->update(['quantity_scanned' => 0]);
            $order->update(['status' => OrderStatus::Cancelled, 'cancel_reason' => $reason]);
            ActivityLog::recordChanges('order.cancelled', $order, $reason);
        });

        return to_route('orders.show', $order)->with('success', 'Order dibatalkan.');
    }

    /**
     * After every item is scanned the order waits for a manual physical check; the Admin then completes it.
     */
    public function complete(OutboundOrder $order): RedirectResponse
    {
        DB::transaction(function () use ($order): void {
            $order = OutboundOrder::lockForUpdate()->findOrFail($order->id);
            $this->ensureStatus($order, OrderStatus::Open, 'Hanya order open yang bisa diselesaikan.');

            if (! $order->isFullyScanned()) {
                throw ValidationException::withMessages(['order' => 'Masih ada item yang belum terpenuhi. Jika pembeli hanya mengambil sebagian, batalkan order lalu buat order baru.']);
            }

            $order->update(['status' => OrderStatus::Completed]);
            ActivityLog::recordChanges('order.completed', $order);
        });

        return to_route('orders.show', $order)->with('success', 'Order selesai.');
    }

    private function form(?OutboundOrder $order): Response
    {
        return Inertia::render('orders/form', [
            'order' => $order,
            'products' => Product::active()->orderBy('display_name')->get(['id', 'display_name']),
            'available' => OutboundOrder::availableStock(except: $order),
        ]);
    }

    /**
     * @param  list<array{product_id: string, quantity: int}>  $items
     */
    private function saveItems(OutboundOrder $order, array $items): void
    {
        $order->items()->createMany(array_map(fn (array $item): array => [
            'product_id' => $item['product_id'],
            'quantity_requested' => $item['quantity'],
        ], $items));
    }

    /**
     * PRD 5.5: an item may not exceed available stock, checked when the order is saved and again when it is opened.
     * Product rows are locked as a mutex so two admins can not reserve the same last boxes at the same time.
     * Errors point at the form row when the request items are given, otherwise at the order as a whole.
     *
     * @param  list<array{product_id: string, quantity: int}>|null  $requestItems
     */
    private function ensureStockAvailable(OutboundOrder $order, ?array $requestItems = null): void
    {
        $items = $order->items()->with('product:id,display_name')->get();
        Product::whereIn('id', $items->pluck('product_id'))->lockForUpdate()->get();
        $available = OutboundOrder::availableStock($items->pluck('product_id')->all(), except: $order);
        $rowIndex = array_flip(array_column($requestItems ?? [], 'product_id'));

        $errors = [];
        foreach ($items as $item) {
            if ($item->quantity_requested > $available[$item->product_id]) {
                $key = isset($rowIndex[$item->product_id]) ? "items.{$rowIndex[$item->product_id]}.quantity" : 'order';
                $errors[$key][] = "{$item->product->display_name}: melebihi stok tersedia ({$available[$item->product_id]} dus).";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function markOpen(OutboundOrder $order): void
    {
        $order->update(['status' => OrderStatus::Open]);
        ActivityLog::recordChanges('order.opened', $order);
    }

    private function ensureStatus(OutboundOrder $order, OrderStatus $status, string $message): void
    {
        if ($order->status !== $status) {
            throw ValidationException::withMessages(['order' => $message]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(OutboundOrder $order): array
    {
        return [
            ...$order->only('destination', 'notes'),
            'order_date' => $order->order_date->toDateString(),
            'items' => $order->items()->get(['product_id', 'quantity_requested'])->map->only('product_id', 'quantity_requested')->all(),
        ];
    }
}
