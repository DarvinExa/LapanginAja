<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'regex:/^(\+62|0)8[0-9]{7,13}$/'], // Match Indonesian 08xxxx / +628xxxx with 9-15 digits
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers()],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Format nomor HP tidak valid. Gunakan format Indonesia (08xxxx atau +628xxxx).',
            'password' => 'Password minimal 8 karakter dan merupakan kombinasi huruf dan angka.',
        ];
    }
}
