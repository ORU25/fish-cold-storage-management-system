<?php

use App\Enums\AdjustmentStatus;
use App\Enums\BoxStatus;
use App\Models\Adjustment;
use App\Models\Box;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Reads a downloaded .xlsx back into plain rows; date cells come back as DateTimeImmutable.
 *
 * @return list<list<mixed>>
 */
function xlsxRows(string $content): array
{
    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($path, $content);
    $reader = new Reader;
    $reader->open($path);
    $rows = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }

    $reader->close();
    unlink($path);

    return $rows;
}

test('mutation: opening + in - out - adjustment = closing, counting only events inside the period', function () {
    $product = Product::factory()->create(['fish_name' => 'MB', 'grade' => 'A', 'size' => '3-5']);
    $box = fn (string $in, array $attributes = []) => Box::factory()->create(['product_id' => $product->id, 'scanned_in_at' => $in, ...$attributes]);

    $box('2026-02-10 08:00');
    $box('2026-02-15 08:00', ['status' => BoxStatus::Outbound, 'scanned_out_at' => '2026-03-05 10:00']);
    $box('2026-03-10 08:00');
    $lost = $box('2026-02-20 08:00', ['status' => BoxStatus::Lost]);
    Adjustment::factory()->create(['box_id' => $lost->id, 'status' => AdjustmentStatus::Approved, 'decided_at' => '2026-03-20 09:00']);
    $box('2026-03-12 08:00')->delete();
    $box('2026-04-02 08:00');
    $box('2026-03-11 08:00', ['status' => BoxStatus::Outbound, 'scanned_out_at' => '2026-04-05 10:00']);

    $this->actingAs(User::factory()->admin()->create())->get(route('reports.index', ['tab' => 'mutation', 'from' => '2026-03-01', 'to' => '2026-03-31']))
        ->assertInertia(fn ($page) => $page
            ->component('reports/index')
            ->where('rows', [['product' => 'MB A 3-5', 'opening' => 3, 'in' => 2, 'out' => 1, 'adjustment' => 1, 'closing' => 3]]));
});

test('stock per location lists every box in stock, grouped by location and product', function () {
    $product = Product::factory()->create(['fish_name' => 'Tongkol', 'grade' => '', 'size' => '']);
    $blockA = Location::factory()->create(['name' => 'Blok A']);
    $inStock = Box::factory()->create(['product_id' => $product->id, 'location_id' => $blockA->id]);
    $pending = Box::factory()->create(['product_id' => $product->id, 'location_id' => $blockA->id, 'status' => BoxStatus::PendingAdjustment]);
    Box::factory()->create(['product_id' => $product->id, 'location_id' => $blockA->id, 'status' => BoxStatus::Outbound]);

    $this->actingAs(User::factory()->owner()->create())->get(route('reports.index'))
        ->assertInertia(fn ($page) => $page
            ->where('tab', 'location')
            ->has('rows', 1)
            ->where('rows.0.location', 'Blok A')
            ->where('rows.0.products.0.product', 'Tongkol')
            ->has('rows.0.products.0.boxes', 2));

    $rows = xlsxRows($this->actingAs(User::factory()->admin()->create())->get(route('reports.export', ['tab' => 'location']))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->streamedContent());
    $byCode = collect($rows)->keyBy(2);

    expect($rows[0])->toBe(['Lokasi', 'Produk', 'Kode dus', 'Expired', 'Status', 'Hitung fisik'])
        ->and(array_slice($byCode[$inStock->qr_code], 0, 3))->toBe(['Blok A', 'Tongkol', $inStock->qr_code])
        ->and($byCode[$pending->qr_code][3]->format('Y-m-d'))->toBe($pending->expired_date->toDateString())
        ->and($byCode[$pending->qr_code][4])->toBe('Menunggu approval');
});

test('adjustment report and export cover requests made in the period', function () {
    $inside = Adjustment::factory()->create(['created_at' => '2026-03-10 09:00', 'reason' => 'Dus sobek']);
    Adjustment::factory()->create(['created_at' => '2026-02-10 09:00']);
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->get(route('reports.index', ['tab' => 'adjustment', 'from' => '2026-03-01', 'to' => '2026-03-31']))
        ->assertInertia(fn ($page) => $page->has('rows', 1)->where('rows.0.id', $inside->id));

    $rows = xlsxRows($this->actingAs($owner)->get(route('reports.export', ['tab' => 'adjustment', 'from' => '2026-03-01', 'to' => '2026-03-31']))->streamedContent());

    expect($rows)->toHaveCount(2)
        ->and($rows[1][0]->format('Y-m-d H:i'))->toBe('2026-03-10 09:00')
        ->and($rows[1][1])->toBe($inside->box->qr_code)
        ->and($rows[1][4])->toBe('Dus sobek');
});

test('staff can not open or export reports, and an invalid period is rejected', function () {
    $this->actingAs(User::factory()->create())->get(route('reports.index'))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('reports.export'))->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())->get(route('reports.index', ['tab' => 'mutation', 'from' => '2026-03-31', 'to' => '2026-03-01']))
        ->assertSessionHasErrors('to');
});
