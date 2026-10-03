<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutboundScan extends Model
{
    use HasUuids;

    protected $fillable = ['outbound_order_item_id', 'box_id', 'scanned_by', 'fefo_violation', 'fefo_reason'];

    protected function casts(): array
    {
        return [
            'fefo_violation' => 'boolean',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(OutboundOrderItem::class, 'outbound_order_item_id');
    }

    public function box(): BelongsTo
    {
        return $this->belongsTo(Box::class);
    }

    public function scannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}
