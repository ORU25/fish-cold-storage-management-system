<?php

namespace App\Models;

use Database\Factories\InboundBatchFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InboundBatch extends Model
{
    /** @use HasFactory<InboundBatchFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['supplier_name', 'delivery_note_number', 'notes', 'created_by', 'started_at', 'finished_at'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function isFinished(): bool
    {
        return $this->finished_at !== null;
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
