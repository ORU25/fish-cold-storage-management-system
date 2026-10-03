<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Box;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Locations are never deleted (PRD 5.2), only deactivated.
 */
class LocationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('locations/index', [
            'locations' => Location::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $location = Location::create($this->validated($request))->refresh();

        ActivityLog::record('location.created', $location, newValues: $location->only($location->getFillable()));

        return back();
    }

    public function update(Request $request, Location $location): RedirectResponse
    {
        $validated = $this->validated($request, $location);

        if ($location->is_active && ! ($validated['is_active'] ?? true)) {
            $boxesInStock = $location->boxes()->whereIn('status', Box::IN_STOCK)->count();

            if ($boxesInStock > 0) {
                throw ValidationException::withMessages([
                    'is_active' => "Masih ada {$boxesInStock} dus di {$location->name}. Pindahkan dulu sebelum lokasi dinonaktifkan.",
                ]);
            }
        }

        $location->update($validated);

        ActivityLog::recordChanges('location.updated', $location);

        return back();
    }

    /**
     * @return array{name: string, description: ?string, is_active?: bool}
     */
    private function validated(Request $request, ?Location $location = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('locations')->ignore($location)],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
