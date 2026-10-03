<?php

namespace App\Models;

use App\Enums\QrLabelStatus;
use Database\Factories\QrLabelFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrLabel extends Model
{
    /** @use HasFactory<QrLabelFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['code', 'qr_print_batch_id', 'status', 'used_at'];

    protected function casts(): array
    {
        return [
            'status' => QrLabelStatus::class,
            'used_at' => 'datetime',
        ];
    }

    public function printBatch(): BelongsTo
    {
        return $this->belongsTo(QrPrintBatch::class, 'qr_print_batch_id');
    }
}
