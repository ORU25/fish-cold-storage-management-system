<?php

namespace App\Models;

use App\Enums\QrLabelStatus;
use Database\Factories\QrPrintBatchFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class QrPrintBatch extends Model
{
    /** @use HasFactory<QrPrintBatchFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['quantity', 'generated_by'];

    /**
     * Create $quantity available labels coded DUS-YYMMDD-NNNN, numbered per day.
     * ponytail: two admins generating at the same second can collide on the unique code; the second one fails and can retry. Add a lock if that ever happens.
     */
    public static function generate(int $quantity, User $generatedBy): self
    {
        return DB::transaction(function () use ($quantity, $generatedBy): self {
            $prefix = 'DUS-'.now()->format('ymd').'-';
            $lastCode = QrLabel::where('code', 'like', $prefix.'%')->orderByDesc('code')->lockForUpdate()->value('code');
            $lastNumber = $lastCode ? (int) substr($lastCode, strlen($prefix)) : 0;

            $batch = self::create(['quantity' => $quantity, 'generated_by' => $generatedBy->id]);

            $batch->labels()->createMany(array_map(
                fn (int $number): array => ['code' => $prefix.str_pad((string) $number, 4, '0', STR_PAD_LEFT), 'status' => QrLabelStatus::Available],
                range($lastNumber + 1, $lastNumber + $quantity),
            ));

            return $batch;
        });
    }

    public function labels(): HasMany
    {
        return $this->hasMany(QrLabel::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
