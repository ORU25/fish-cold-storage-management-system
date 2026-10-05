<?php

namespace App\Http\Controllers;

use App\Enums\AdjustmentStatus;
use App\Enums\BoxStatus;
use App\Models\Adjustment;
use App\Models\Box;
use App\Models\Product;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reports for the manual stock check and the Owner (PRD 5.7, 5.11, rancangan 4.5): stock per location with box codes,
 * stock movement per period, and adjustments. Each one can be downloaded as an Excel file (.xlsx).
 */
class ReportController extends Controller
{
    private const TABS = ['location', 'mutation', 'adjustment'];

    private const BOX_STATUS_LABELS = ['in_warehouse' => 'Di gudang', 'pending_adjustment' => 'Menunggu approval'];

    private const ADJUSTMENT_LABELS = ['lost' => 'Hilang', 'damaged' => 'Rusak', 'pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'];

    public function index(Request $request): Response
    {
        [$tab, $from, $to] = $this->filters($request);

        return Inertia::render('reports/index', [
            'tab' => $tab,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'rows' => match ($tab) {
                'location' => $this->stockPerLocation()->groupBy('location')->map(fn (Collection $boxes, string $location): array => [
                    'location' => $location,
                    'products' => $boxes->groupBy('product')->map(fn (Collection $productBoxes, string $product): array => [
                        'product' => $product,
                        'boxes' => $productBoxes->values(),
                    ])->values(),
                ])->values(),
                'mutation' => $this->mutation($from, $to),
                'adjustment' => $this->adjustments($from, $to),
            },
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$tab, $from, $to] = $this->filters($request);
        $period = $tab === 'location' ? today()->toDateString() : $from->toDateString().'-sd-'.$to->toDateString();

        return $this->xlsx("laporan-{$tab}-{$period}.xlsx", ...match ($tab) {
            'location' => [
                ['Lokasi', 'Produk', 'Kode dus', 'Expired', 'Status', 'Hitung fisik'],
                $this->stockPerLocation()->map(fn (array $box): array => [$box['location'], $box['product'], $box['qr_code'], Carbon::parse($box['expired_date']), self::BOX_STATUS_LABELS[$box['status']], null]),
            ],
            'mutation' => [
                ['Produk', 'Stok awal', 'Masuk', 'Keluar', 'Adjustment', 'Stok akhir'],
                $this->mutation($from, $to)->map(fn (array $row): array => array_values($row)),
            ],
            'adjustment' => [
                ['Diajukan', 'Kode dus', 'Produk', 'Jenis', 'Alasan', 'Diajukan oleh', 'Status', 'Diputuskan oleh', 'Diputuskan', 'Catatan'],
                $this->adjustments($from, $to)->map(fn (Adjustment $adjustment): array => [
                    $adjustment->created_at,
                    $adjustment->box->qr_code,
                    $adjustment->box->product->display_name,
                    self::ADJUSTMENT_LABELS[$adjustment->type->value],
                    $adjustment->reason,
                    $adjustment->requestedBy->name,
                    self::ADJUSTMENT_LABELS[$adjustment->status->value],
                    $adjustment->decidedBy?->name,
                    $adjustment->decided_at,
                    $adjustment->decision_note,
                ]),
            ],
        });
    }

    /**
     * @return array{0: string, 1: Carbon, 2: Carbon}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'tab' => ['nullable', 'in:'.implode(',', self::TABS)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        return [
            $validated['tab'] ?? 'location',
            Carbon::parse($validated['from'] ?? today()->startOfMonth()),
            Carbon::parse($validated['to'] ?? today()),
        ];
    }

    /**
     * Every box in real stock, ordered by location, product and expiry: the manual count sheet.
     *
     * @return Collection<int, array{id: string, location: string, product: string, qr_code: string, expired_date: string, status: string}>
     */
    private function stockPerLocation(): Collection
    {
        return Box::with(['product:id,display_name', 'location:id,name'])
            ->whereIn('status', Box::IN_STOCK)
            ->get(['id', 'qr_code', 'product_id', 'location_id', 'expired_date', 'status'])
            ->map(fn (Box $box): array => [
                'id' => $box->id,
                'location' => $box->location->name ?? '-',
                'product' => $box->product->display_name,
                'qr_code' => $box->qr_code,
                'expired_date' => $box->expired_date->toDateString(),
                'status' => $box->status->value,
            ])
            ->sortBy([['location', 'asc'], ['product', 'asc'], ['expired_date', 'asc'], ['qr_code', 'asc']])
            ->values()
            ->toBase();
    }

    /**
     * Opening stock + in - out - adjustments = closing stock, per product. Each figure comes from three running totals
     * (boxes scanned in, scanned out, written off by an approved adjustment) taken at the start and the end of the period.
     * ponytail: counted under the box's current product, so a product revision also rewrites history; store the product per event if that matters.
     *
     * @return Collection<int, array{product: string, opening: int, in: int, out: int, adjustment: int, closing: int}>
     */
    private function mutation(Carbon $from, Carbon $to): Collection
    {
        $start = $this->runningTotals($from->copy()->startOfDay());
        $end = $this->runningTotals($to->copy()->addDay()->startOfDay());
        $products = Product::orderBy('display_name')->pluck('display_name', 'id');

        return $products->map(function (string $name, string $id) use ($start, $end): array {
            $at = fn (array $totals, string $key): int => (int) ($totals[$key][$id] ?? 0);
            $opening = $at($start, 'in') - $at($start, 'out') - $at($start, 'adjustment');

            return [
                'product' => $name,
                'opening' => $opening,
                'in' => $at($end, 'in') - $at($start, 'in'),
                'out' => $at($end, 'out') - $at($start, 'out'),
                'adjustment' => $at($end, 'adjustment') - $at($start, 'adjustment'),
                'closing' => $at($end, 'in') - $at($end, 'out') - $at($end, 'adjustment'),
            ];
        })->filter(fn (array $row): bool => array_sum(array_slice($row, 1)) !== 0)->values();
    }

    /**
     * Boxes per product scanned in, scanned out (and not cancelled), and written off, before $at.
     *
     * @return array{in: Collection<string, int>, out: Collection<string, int>, adjustment: Collection<string, int>}
     */
    private function runningTotals(Carbon $at): array
    {
        $count = fn (Builder $query): Collection => $query->selectRaw('product_id, count(*) as total')->groupBy('product_id')->pluck('total', 'product_id');

        return [
            'in' => $count(Box::where('scanned_in_at', '<', $at)),
            'out' => $count(Box::where('status', BoxStatus::Outbound)->where('scanned_out_at', '<', $at)),
            'adjustment' => $count(Box::whereHas('adjustments', fn (Builder $query) => $query->where('status', AdjustmentStatus::Approved)->where('decided_at', '<', $at))),
        ];
    }

    /**
     * @return Collection<int, Adjustment>
     */
    private function adjustments(Carbon $from, Carbon $to): Collection
    {
        return Adjustment::with(['box:id,qr_code,product_id', 'box.product:id,display_name', 'requestedBy:id,name', 'decidedBy:id,name'])
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->latest()
            ->get();
    }

    /**
     * One-sheet Excel file with a bold, frozen header row. OpenSpout writes row by row, so memory stays flat however many rows there are.
     * Dates become real Excel dates; a value at exactly midnight is shown without the time.
     * ponytail: date vs date-time is guessed from the time part; pass the style per column if a real 00:00 timestamp ever matters.
     *
     * @param  list<string>  $header
     * @param  iterable<int, array<int, string|int|DateTimeInterface|null>>  $rows
     */
    private function xlsx(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows): void {
            $date = (new Style)->setFormat('dd/mm/yyyy');
            $dateTime = (new Style)->setFormat('dd/mm/yyyy hh:mm');

            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->getCurrentSheet()->setSheetView((new SheetView)->setFreezeRow(2));
            $writer->addRow(Row::fromValues($header, (new Style)->setFontBold()));

            foreach ($rows as $row) {
                $writer->addRow(new Row(array_map(fn (mixed $value): Cell => $value instanceof DateTimeInterface
                    ? Cell::fromValue($value, $value->format('H:i:s') === '00:00:00' ? $date : $dateTime)
                    : Cell::fromValue($value), $row)));
            }

            $writer->close();
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
