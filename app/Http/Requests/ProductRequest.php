<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    /**
     * Role is checked by the route middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Uppercase and trim so "mb a" and "MB A" count as the same product; empty grade/size become ''.
     */
    protected function prepareForValidation(): void
    {
        $normalize = fn (string $key): string => Str::upper(trim((string) $this->input($key)));

        $this->merge([
            'fish_name' => $normalize('fish_name'),
            'grade' => $normalize('grade'),
            'size' => $normalize('size'),
            'code' => $normalize('code') ?: Product::makeCode($normalize('fish_name'), $normalize('grade'), $normalize('size')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('products')->ignore($product)],
            'fish_name' => [
                'required', 'string', 'max:50',
                Rule::unique('products')->where('grade', $this->input('grade'))->where('size', $this->input('size'))->ignore($product),
            ],
            'grade' => ['present', 'string', 'max:20'],
            'size' => ['present', 'string', 'max:20'],
            'kg_per_carton' => ['required', 'numeric', 'min:0.01', 'max:9999'],
            'shelf_life_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fish_name.unique' => 'Kombinasi jenis, grade, dan size ini sudah ada.',
            'code.unique' => 'Kode produk sudah dipakai.',
        ];
    }
}
