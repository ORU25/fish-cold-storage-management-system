<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Arr;
use LogicException;

/**
 * Append-only audit trail (PRD 5.10). Keeps a bigint id (rancangan 5.8); every other table uses UUIDs. Rows can never be updated or deleted through the app.
 */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Log aktivitas tidak bisa diubah.'));
        static::deleting(fn () => throw new LogicException('Log aktivitas tidak bisa dihapus.'));
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public static function record(
        string $action,
        ?Model $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
        ?User $user = null,
    ): self {
        return self::create([
            'user_id' => $user?->id ?? auth()->id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'reason' => $reason,
            'ip_address' => request()?->ip(),
            'user_agent' => substr((string) request()?->userAgent(), 0, 255) ?: null,
        ]);
    }

    /**
     * Log the attributes changed by the last save() of $subject. Call right after saving.
     */
    public static function recordChanges(string $action, Model $subject, ?string $reason = null): ?self
    {
        $changes = Arr::except($subject->getChanges(), ['updated_at', 'remember_token']);

        if ($changes === []) {
            return null;
        }

        $keys = array_keys($changes);
        $previous = (clone $subject)->setRawAttributes([...$subject->getAttributes(), ...$subject->getPrevious()]);
        $mask = fn (array $values): array => array_key_exists('password', $values) ? ['password' => '[diubah]'] + $values : $values;

        return self::record(
            $action,
            $subject,
            $mask($previous->only($keys)),
            $mask($subject->only($keys)),
            $reason,
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
