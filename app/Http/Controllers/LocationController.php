<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
        $location->update($this->validated($request, $location));

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
