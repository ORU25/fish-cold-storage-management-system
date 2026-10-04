<?php

namespace App\Models;

use App\Enums\BoxStatus;
use App\Enums\OrderStatus;
use Database\Factories\OutboundOrderFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OutboundOrder extends Model
{
    /** @use HasFactory<OutboundOrderFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['order_number', 'destination', 'order_date', 'notes', 'status', 'cancel_reason', 'created_by'];

    protected function casts(): array
    {
        return [
            'order_date' => 'date:Y-m-d',
            'status' => OrderStatus::class,
        ];
    }

    /**
     * OUT-YYMMDD-NNN, numbered per day. Call inside a transaction.
     * ponytail: same race as QR codes; two orders in the same instant collide on the unique number and one fails.
     */
    public static function nextOrderNumber(): string
    {
        $prefix = 'OUT-'.now()->format('ymd').'-';
        $lastNumber = self::where('order_number', 'like', $prefix.'%')->orderByDesc('order_number')->lockForUpdate()->value('order_number');

        return $prefix.str_pad((string) ((int) substr((string) $lastNumber, strlen($prefix)) + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Available stock per product (PRD 5.5): boxes in the warehouse minus what other open orders still need.
     * Boxes pending an adjustment are not in_warehouse, so they are never available.
     *
     * @param  list<string>|null  $productIds
     * @return array<string, int> product id => available boxes
     */
    public static function availableStock(?array $productIds = null, ?self $except = null): array
    {
        $inWarehouse = Box::where('status', BoxStatus::InWarehouse)
            ->when($productIds !== null, fn ($query) => $query->whereIn('product_id', $productIds))
            ->selectRaw('product_id, count(*) as total')
            ->groupBy('product_id')
            ->pluck('total', 'product_id');

        $reserved = OutboundOrderItem::whereHas('order', fn ($query) => $query
            ->where('status', OrderStatus::Open)
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey())))
            ->when($productIds !== null, fn ($query) => $query->whereIn('product_id', $productIds))
            ->selectRaw('product_id, sum(quantity_requested - quantity_scanned) as total')
            ->groupBy('product_id')
            ->pluck('total', 'product_id');

        return collect($productIds ?? $inWarehouse->keys()->merge($reserved->keys())->unique())
            ->mapWithKeys(fn (string $productId): array => [$productId => (int) ($inWarehouse[$productId] ?? 0) - (int) ($reserved[$productId] ?? 0)])
            ->all();
    }

    public function isFullyScanned(): bool
    {
        return $this->items()->whereColumn('quantity_scanned', '<', 'quantity_requested')->doesntExist();
    }

    public function items(): HasMany
    {
        return $this->hasMany(OutboundOrderItem::class);
    }

    public function boxes(): HasMany
    {
        return $this->hasMany(Box::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
