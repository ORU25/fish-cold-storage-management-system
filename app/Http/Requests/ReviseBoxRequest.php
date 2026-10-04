<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin corrects a box's product or dates (PRD 5.9). A reason is always required; no approval.
 */
class ReviseBoxRequest extends FormRequest
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
            'product_id' => ['required', Rule::exists('products', 'id')->where('is_active', true)],
            'production_date' => ['nullable', 'date', 'before_or_equal:today'],
            'expired_date' => ['nullable', 'date', 'after_or_equal:production_date'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_id.exists' => 'Produk tidak ditemukan atau sudah nonaktif.',
            'production_date.before_or_equal' => 'Tanggal produksi tidak boleh di masa depan.',
            'expired_date.after_or_equal' => 'Tanggal expired tidak boleh sebelum tanggal produksi.',
            'reason.required' => 'Alasan revisi wajib diisi.',
        ];
    }
}
