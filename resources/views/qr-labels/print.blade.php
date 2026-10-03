<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak Stiker {{ $labels->first()['code'] ?? '' }}</title>
    <style>
        /* Thermal label 50 x 30 mm, QR 25 x 25 mm with the code underneath (PRD 9). */
        @page { size: 50mm 30mm; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: ui-monospace, monospace; background: #f4f4f5; }
        .toolbar { position: sticky; top: 0; padding: 12px 16px; background: #fff; border-bottom: 1px solid #e4e4e7; font-family: system-ui, sans-serif; font-size: 14px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
        .toolbar button { padding: 8px 16px; font-size: 14px; cursor: pointer; }
        .sheet { display: flex; flex-wrap: wrap; gap: 8px; padding: 16px; }
        .label { width: 50mm; height: 30mm; background: #fff; display: flex; flex-direction: column; align-items: center; justify-content: center; overflow: hidden; break-after: page; }
        .label img { width: 25mm; height: 25mm; }
        .label span { font-size: 8pt; font-weight: bold; line-height: 1; margin-top: 0.5mm; }
        .label.void { opacity: 0.35; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { display: block; padding: 0; }
            .label.void { display: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Cetak</button>
        <a href="{{ route('qr-labels.index') }}">Kembali</a>
        <span>{{ $labels->count() }} stiker &middot; dibuat {{ $batch->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }} &middot; stiker void tidak ikut dicetak</span>
    </div>
    <div class="sheet">
        @foreach ($labels as $label)
            <div class="label {{ $label['status'] === \App\Enums\QrLabelStatus::Void ? 'void' : '' }}">
                <img src="{{ $label['image'] }}" alt="{{ $label['code'] }}">
                <span>{{ $label['code'] }}</span>
            </div>
        @endforeach
    </div>
</body>
</html>
