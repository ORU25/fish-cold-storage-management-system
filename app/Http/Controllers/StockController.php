<?php

namespace App\Http\Controllers;

use App\Enums\BoxStatus;
use App\Models\Box;
use App\Models\Location;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'product_id' => ['nullable', 'uuid'],
            'location_id' => ['nullable', 'uuid'],
            'status' => ['nullable', Rule::enum(BoxStatus::class)],
            'code' => ['nullable', 'string', 'max:50'],
        ]);

        $products = Product::orderBy('display_name')->get(['id', 'display_name', 'kg_per_carton'])->keyBy('id');
        $locations = Location::orderBy('name')->get(['id', 'name'])->keyBy('id');

        $perProduct = Box::whereIn('status', Box::IN_STOCK)
            ->selectRaw('product_id, count(*) as total, sum(case when status = ? then 1 else 0 end) as pending', [BoxStatus::PendingAdjustment->value])
            ->groupBy('product_id')
            ->get()
            ->map(fn (Box $row): array => [
                'product' => $products[$row->product_id]->display_name,
                'mc' => (int) $row->total,
                'pending' => (int) $row->pending,
                'kg' => (float) $products[$row->product_id]->kg_per_carton * (int) $row->total,
            ])
            ->sortBy('product')
            ->values();

        $perLocation = Box::whereIn('status', Box::IN_STOCK)
            ->selectRaw('location_id, product_id, count(*) as total')
            ->groupBy('location_id', 'product_id')
            ->get()
            ->map(fn (Box $row): array => [
                'location' => $locations[$row->location_id]->name ?? '-',
                'product' => $products[$row->product_id]->display_name,
                'mc' => (int) $row->total,
            ])
            ->sortBy(['location', 'product'])
            ->values();

        $boxes = Box::with(['product:id,display_name', 'location:id,name'])
            ->when($filters['product_id'] ?? null, fn ($query, $id) => $query->where('product_id', $id))
            ->when($filters['location_id'] ?? null, fn ($query, $id) => $query->where('location_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status), fn ($query) => $query->whereIn('status', Box::IN_STOCK))
            ->when($filters['code'] ?? null, fn ($query, $code) => $query->where('qr_code', 'like', '%'.strtoupper($code).'%'))
            ->orderBy('expired_date')
            ->orderBy('qr_code')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('stock/index', [
            'perProduct' => $perProduct,
            'perLocation' => $perLocation,
            'boxes' => $boxes,
            'filters' => $filters,
            'products' => $products->values(),
            'locations' => $locations->values(),
            'statuses' => array_column(BoxStatus::cases(), 'value'),
        ]);
    }
}
