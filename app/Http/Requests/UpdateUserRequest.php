<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    /**
     * Role is checked by the route middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The Owner cannot demote or deactivate their own account, so the system is never left without an Owner.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isSelf = $this->route('user')->is($this->user());

        return [
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::enum(Role::class), Rule::when($isSelf, Rule::in([Role::Owner->value]))],
            'is_active' => ['required', 'boolean', Rule::when($isSelf, 'accepted')],
            'password' => ['nullable', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.in' => 'Anda tidak bisa mengubah role akun sendiri.',
            'is_active.accepted' => 'Anda tidak bisa menonaktifkan akun sendiri.',
        ];
    }
}
