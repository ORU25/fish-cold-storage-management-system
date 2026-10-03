<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OutboundOrderRequest extends FormRequest
{
    /**
     * Role is checked by the route middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'destination' => ['required', 'string', 'max:255'],
            'order_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'distinct', Rule::exists('products', 'id')->where('is_active', true)],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'open' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Order harus berisi minimal satu item.',
            'items.*.product_id.required' => 'Pilih produk.',
            'items.*.product_id.distinct' => 'Produk ini sudah ada di baris lain.',
            'items.*.product_id.exists' => 'Produk tidak ditemukan atau sudah nonaktif.',
            'items.*.quantity.min' => 'Jumlah minimal 1 dus.',
        ];
    }
}
