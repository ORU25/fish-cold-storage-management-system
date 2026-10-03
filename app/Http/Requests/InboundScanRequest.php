<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * One inbound scan: the QR code plus the batch header values in effect at that moment (PRD 5.4).
 */
class InboundScanRequest extends FormRequest
{
    /**
     * Role is checked by the route middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => Str::upper(trim((string) $this->input('code')))]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50'],
            'product_id' => ['required', Rule::exists('products', 'id')->where('is_active', true)],
            'location_id' => ['required', Rule::exists('locations', 'id')->where('is_active', true)],
            'production_date' => ['nullable', 'date', 'before_or_equal:today'],
            'expired_date' => ['nullable', 'date', 'after_or_equal:production_date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Kode QR kosong.',
            'product_id.required' => 'Pilih produk dulu.',
            'product_id.exists' => 'Produk tidak ditemukan atau sudah nonaktif.',
            'location_id.required' => 'Pilih lokasi dulu.',
            'location_id.exists' => 'Lokasi tidak ditemukan atau sudah nonaktif.',
            'production_date.before_or_equal' => 'Tanggal produksi tidak boleh di masa depan.',
            'expired_date.after_or_equal' => 'Tanggal expired tidak boleh sebelum tanggal produksi.',
        ];
    }
}
