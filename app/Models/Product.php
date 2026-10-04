<?php

namespace App\Models;

use App\Models\Concerns\HasActiveFlag;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A fish product = one fish/grade/size combination, e.g. "MB A 3-5".
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasActiveFlag, HasFactory, HasUuids;

    protected $fillable = ['code', 'fish_name', 'grade', 'size', 'kg_per_carton', 'shelf_life_days', 'is_active'];

    protected function casts(): array
    {
        return [
            'kg_per_carton' => 'decimal:2',
            'shelf_life_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            $product->grade ??= '';
            $product->size ??= '';
            $product->display_name = self::makeDisplayName($product->fish_name, $product->grade, $product->size);
            $product->code = $product->code ?: self::makeCode($product->fish_name, $product->grade, $product->size);
        });
    }

    /**
     * "MB", "A", "3-5" => "MB A 3-5". Empty grade/size are skipped.
     */
    public static function makeDisplayName(string $fishName, string $grade, string $size): string
    {
        return implode(' ', array_filter([$fishName, $grade, $size], fn (string $part): bool => $part !== ''));
    }

    /**
     * "MB", "A", "3-5" => "MB-A-3-5".
     */
    public static function makeCode(string $fishName, string $grade, string $size): string
    {
        return Str::upper(Str::slug(self::makeDisplayName($fishName, $grade, $size)));
    }

    /**
     * Expiry date from a production date and this product's shelf life, or null when either is missing.
     */
    public function expiryFrom(?string $productionDate): ?string
    {
        if ($productionDate === null || $this->shelf_life_days === null) {
            return null;
        }

        return Carbon::parse($productionDate)->addDays($this->shelf_life_days)->toDateString();
    }
}
