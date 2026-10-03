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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class QrLabelController extends Controller
{
    public function index(): Response
    {
        $statusCount = fn (QrLabelStatus $status): array => ['labels as '.$status->value.'_count' => fn ($query) => $query->where('status', $status)];

        return Inertia::render('qr-labels/index', [
            'batches' => QrPrintBatch::with('generatedBy:id,name')
                ->withCount([...$statusCount(QrLabelStatus::Available), ...$statusCount(QrLabelStatus::Used), ...$statusCount(QrLabelStatus::Void)])
                ->withMin('labels', 'code')
                ->withMax('labels', 'code')
                ->latest()
                ->paginate(20),
        ]);
    }

    /**
     * Opens the print sheet right away; Inertia::location forces a full page load because it is not an Inertia page.
     */
    public function store(Request $request): SymfonyResponse
    {
        $validated = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:1000']]);

        $batch = QrPrintBatch::generate($validated['quantity'], $request->user());

        ActivityLog::record('qr.generated', $batch, newValues: ['quantity' => $batch->quantity]);

        return Inertia::location(route('qr-labels.print', $batch));
    }

    /**
     * Printable label sheet: one 50 x 30 mm thermal label per page. Can be reprinted any time.
     */
    public function print(QrPrintBatch $batch): View
    {
        $qrCode = new QRCode(new QROptions(['quietzoneSize' => 1]));

        return view('qr-labels.print', [
            'batch' => $batch,
            'labels' => $batch->labels()->orderBy('code')->get()->map(fn (QrLabel $label): array => [
                'code' => $label->code,
                'status' => $label->status,
                'image' => $qrCode->render($label->code),
            ]),
        ]);
    }

    /**
     * Mark a damaged, unused sticker as void so it can never be scanned in.
     */
    public function void(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($validated): void {
            $label = QrLabel::where('code', Str::upper(trim($validated['code'])))->lockForUpdate()->first();

            if (! $label || $label->status !== QrLabelStatus::Available) {
                throw ValidationException::withMessages([
                    'code' => $label ? 'Hanya stiker berstatus available yang bisa di-void.' : 'Kode stiker tidak ditemukan.',
                ]);
            }

            $label->update(['status' => QrLabelStatus::Void]);

            ActivityLog::recordChanges('qr.voided', $label, $validated['reason']);
        });

        return back();
    }
}
