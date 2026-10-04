<?php

namespace App\Http\Controllers;

use App\Models\Box;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bulk location move (rancangan 4.7): pick the destination once, then every scanned box is moved there straight away.
 */
class BoxMoveController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('boxes/move', [
            'locations' => Location::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'location_id' => ['required', Rule::exists('locations', 'id')->where('is_active', true)],
        ]);
        $code = Str::upper(trim($validated['code']));

        DB::transaction(function () use ($code, $validated): void {
            $box = Box::where('qr_code', $code)->lockForUpdate()->first()
                ?? throw ValidationException::withMessages(['code' => "Kode {$code} tidak dikenal atau belum discan masuk."]);

            $box->moveTo(Location::findOrFail($validated['location_id']), 'code');
        });

        return back();
    }
}
