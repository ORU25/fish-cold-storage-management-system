<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The log grows forever, so every query here must stay index-friendly:
 * date filters are plain ranges on created_at, and simplePaginate skips the COUNT(*) over the whole table.
 */
class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'user_id' => ['nullable', 'uuid'],
            'action' => ['nullable', 'string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $logs = ActivityLog::with('user:id,name,username')
            ->when($filters['user_id'] ?? null, fn ($query, $userId) => $query->where('user_id', $userId))
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->where('created_at', '>=', Carbon::parse($date)->startOfDay()))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->where('created_at', '<', Carbon::parse($date)->addDay()->startOfDay()))
            ->latest('id')
            ->simplePaginate(50)
            ->withQueryString();

        return Inertia::render('activity-logs/index', [
            'logs' => $logs,
            'filters' => $filters,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'actions' => ActivityLog::distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
