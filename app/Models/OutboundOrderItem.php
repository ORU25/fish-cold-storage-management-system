<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OutboundOrderItem extends Model
{
    use HasUuids;

    protected $fillable = ['outbound_order_id', 'product_id', 'quantity_requested', 'quantity_scanned'];

    protected function casts(): array
    {
        return [
            'quantity_requested' => 'integer',
            'quantity_scanned' => 'integer',
        ];
    }

    public function remaining(): int
    {
        return $this->quantity_requested - $this->quantity_scanned;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(OutboundOrder::class, 'outbound_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(OutboundScan::class);
    }
}
