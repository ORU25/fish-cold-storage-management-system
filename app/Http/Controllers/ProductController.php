<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\ActivityLog;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Products are never deleted (PRD 5.2), only deactivated.
 */
class ProductController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('products/index', [
            'products' => Product::orderBy('display_name')->get(),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = Product::create($request->validated())->refresh();

        ActivityLog::record('product.created', $product, newValues: $product->only($product->getFillable()));

        return back();
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        ActivityLog::recordChanges('product.updated', $product);

        return back();
    }
}
