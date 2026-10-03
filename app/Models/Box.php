<?php

namespace App\Models;

use App\Enums\BoxStatus;
use Database\Factories\BoxFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

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
}
