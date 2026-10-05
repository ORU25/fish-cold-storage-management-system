<?php

namespace App\Models;

use App\Enums\BoxStatus;
use Database\Factories\BoxFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class Box extends Model
{
    /** @use HasFactory<BoxFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * Real stock = boxes physically in the warehouse, including those waiting for an adjustment decision (PRD 5.8).
     */
    public const IN_STOCK = [BoxStatus::InWarehouse, BoxStatus::PendingAdjustment];

    protected $fillable = [
        'qr_label_id',
        'qr_code',
        'inbound_batch_id',
        'product_id',
        'location_id',
        'production_date',
        'expired_date',
        'status',
        'scanned_in_by',
        'scanned_in_at',
        'outbound_order_id',
        'scanned_out_by',
        'scanned_out_at',
    ];

    protected function casts(): array
    {
        return [
            'production_date' => 'date:Y-m-d',
            'expired_date' => 'date:Y-m-d',
            'status' => BoxStatus::class,
            'scanned_in_at' => 'datetime',
            'scanned_out_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function inboundBatch(): BelongsTo
    {
        return $this->belongsTo(InboundBatch::class);
    }

    public function qrLabel(): BelongsTo
    {
        return $this->belongsTo(QrLabel::class);
    }

    public function outboundOrder(): BelongsTo
    {
        return $this->belongsTo(OutboundOrder::class);
    }

    public function scannedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_in_by');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(Adjustment::class);
    }

    /**
     * Real stock per product in MC and KG, with the boxes pending an adjustment and what is available for new orders.
     *
     * @return Collection<int, array{product: string, mc: int, pending: int, available: int, kg: float}>
     */
    public static function stockPerProduct(): Collection
    {
        $available = OutboundOrder::availableStock();
        $products = Product::get(['id', 'display_name', 'kg_per_carton'])->keyBy('id');

        return self::whereIn('status', self::IN_STOCK)
            ->selectRaw('product_id, count(*) as total, sum(case when status = ? then 1 else 0 end) as pending', [BoxStatus::PendingAdjustment->value])
            ->groupBy('product_id')
            ->get()
            ->map(fn (Box $row): array => [
                'product' => $products[$row->product_id]->display_name,
                'mc' => (int) $row->total,
                'pending' => (int) $row->pending,
                'available' => $available[$row->product_id] ?? 0,
                'kg' => (float) $products[$row->product_id]->kg_per_carton * (int) $row->total,
            ])
            ->sortBy('product')
            ->values()
            ->toBase();
    }

    /**
     * Only boxes still in the warehouse can be revised or moved; boxes that left, or were lost or damaged, are history.
     */
    public function isInStock(): bool
    {
        return in_array($this->status, self::IN_STOCK, true);
    }

    /**
     * Move to another location and log it as $action (rancangan 4.7). Call inside a transaction with the box locked.
     * Errors are reported under $errorKey so each screen can show them next to its own field.
     */
    public function moveTo(Location $location, string $errorKey = 'location_id', string $action = 'box.location_changed'): void
    {
        if (! $this->isInStock()) {
            throw ValidationException::withMessages([$errorKey => "Dus {$this->qr_code} tidak lagi di gudang, lokasinya tidak bisa diubah."]);
        }

        if ($this->location_id === $location->id) {
            throw ValidationException::withMessages([$errorKey => "Dus {$this->qr_code} sudah di {$location->name}."]);
        }

        $this->update(['location_id' => $location->id]);
        ActivityLog::recordChanges($action, $this);
    }
}
