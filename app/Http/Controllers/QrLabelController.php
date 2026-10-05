<?php

namespace App\Http\Controllers;

use App\Enums\QrLabelStatus;
use App\Models\ActivityLog;
use App\Models\QrLabel;
use App\Models\QrPrintBatch;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class QrLabelController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('qr-labels/index', [
            'batches' => QrPrintBatch::with('generatedBy:id,name')
                ->withCount($this->statusCounts())
                ->withMin('labels', 'code')
                ->withMax('labels', 'code')
                ->latest()
                ->paginate(20),
        ]);
    }

    /**
     * Every sticker of one batch with its status, searchable by code. A used sticker shows the box it is stuck on.
     */
    public function show(Request $request, QrPrintBatch $batch): Response
    {
        $filters = $request->validate([
            'code' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', Rule::enum(QrLabelStatus::class)],
        ]);

        $batch->load('generatedBy:id,name')->loadCount($this->statusCounts())->loadMin('labels', 'code')->loadMax('labels', 'code');

        return Inertia::render('qr-labels/show', [
            'batch' => $batch,
            'labels' => $batch->labels()
                ->with(['box:id,qr_label_id,product_id,location_id,status,expired_date', 'box.product:id,display_name', 'box.location:id,name'])
                ->when($filters['code'] ?? null, fn ($query, $code) => $query->where('code', 'like', '%'.Str::upper(trim($code)).'%'))
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->orderBy('code')
                ->paginate(50, ['id', 'code', 'status', 'used_at'])
                ->withQueryString(),
            'filters' => $filters,
            'statuses' => array_column(QrLabelStatus::cases(), 'value'),
        ]);
    }

    /**
     * Opens the print sheet right away; Inertia::location forces a full page load because it is not an Inertia page.
     */
    public function store(Request $request): SymfonyResponse
    {
        $validated = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:100']]);

        $batch = QrPrintBatch::generate($validated['quantity'], $request->user());

        ActivityLog::record('qr.generated', $batch, newValues: ['quantity' => $batch->quantity]);

        return Inertia::location(route('qr-labels.print', $batch));
    }

    /**
     * Printable label sheet: one 50 x 30 mm thermal label per page. Can be reprinted any time.
     */
    public function print(QrPrintBatch $batch): View
    {
        return $this->printSheet($batch, $batch->labels()->orderBy('code')->get());
    }

    /**
     * Reprint one sticker that got damaged or was missed. A void sticker can never be printed.
     */
    public function printLabel(QrLabel $label): View
    {
        abort_if($label->status === QrLabelStatus::Void, 404);

        return $this->printSheet($label->printBatch, collect([$label]));
    }

    /**
     * @param  Collection<int, QrLabel>  $labels
     */
    private function printSheet(QrPrintBatch $batch, Collection $labels): View
    {
        $qrCode = new QRCode(new QROptions(['quietzoneSize' => 1]));

        return view('qr-labels.print', [
            'batch' => $batch,
            'labels' => $labels->map(fn (QrLabel $label): array => [
                'code' => $label->code,
                'status' => $label->status,
                // render() appends to the previous data, so clear it or each QR would also hold the codes before it.
                'image' => $qrCode->clearSegments()->render($label->code),
            ]),
        ]);
    }

    /**
     * Mark damaged, unused stickers as void so they can never be scanned in. Several at once with one reason, all or nothing,
     * with one log row per sticker so each sticker keeps its own history.
     */
    public function void(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:50'],
            'ids.*' => ['required', 'uuid', 'distinct'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($validated): void {
            $labels = QrLabel::whereIn('id', $validated['ids'])->lockForUpdate()->get();

            if ($labels->count() !== count($validated['ids'])) {
                throw ValidationException::withMessages(['ids' => 'Sebagian stiker tidak ditemukan. Muat ulang halaman lalu coba lagi.']);
            }

            $notAvailable = $labels->reject(fn (QrLabel $label): bool => $label->status === QrLabelStatus::Available);
            if ($notAvailable->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'ids' => 'Hanya stiker available yang bisa di-void: '.$notAvailable->map(fn (QrLabel $label): string => "{$label->code} ({$label->status->value})")->join(', ').'.',
                ]);
            }

            foreach ($labels as $label) {
                $label->update(['status' => QrLabelStatus::Void]);
                ActivityLog::recordChanges('qr.voided', $label, $validated['reason']);
            }
        });

        return back()->with('success', count($validated['ids']).' stiker di-void.');
    }

    /**
     * withCount() keys for available_count, used_count and void_count.
     *
     * @return array<string, \Closure>
     */
    private function statusCounts(): array
    {
        return collect(QrLabelStatus::cases())
            ->mapWithKeys(fn (QrLabelStatus $status): array => ['labels as '.$status->value.'_count' => fn ($query) => $query->where('status', $status)])
            ->all();
    }
}
