<?php

namespace App\Models;

use App\Enums\AdjustmentStatus;
use App\Enums\AdjustmentType;
use Database\Factories\AdjustmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Admin asks to write a box off as lost or damaged; only the Owner decides (PRD 5.8, rancangan 4.6).
 */
class Adjustment extends Model
{
    /** @use HasFactory<AdjustmentFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['box_id', 'type', 'reason', 'photo_path', 'status', 'requested_by', 'decided_by', 'decided_at', 'decision_note'];

    protected function casts(): array
    {
        return [
            'type' => AdjustmentType::class,
            'status' => AdjustmentStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public function box(): BelongsTo
    {
        return $this->belongsTo(Box::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
