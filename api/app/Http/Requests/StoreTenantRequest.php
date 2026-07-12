<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTenantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && in_array($this->user()->role, [UserRole::OWNER, UserRole::SUPER_ADMIN]);
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
            'slug' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[a-z0-9-]+$/',
                'unique:tenants,slug',
                Rule::notIn(['admin', 'api', 'auth', 'login', 'public', 'webhooks', 'payments', 'bookings', 'courts', 'users']),
            ],
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^(\+62|0)8[0-9]{7,13}$/'],
            'timezone' => ['nullable', 'string', 'in:Asia/Jakarta,Asia/Makassar,Asia/Jayapura'],
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
            'slug.regex' => 'Format slug tidak valid. Gunakan huruf kecil, angka, dan tanda hubung.',
            'slug.not_in' => 'Slug ini merupakan kata terlarang dan tidak boleh digunakan.',
            'phone.regex' => 'Format nomor HP tidak valid. Gunakan format Indonesia (08xxxx atau +628xxxx).',
        ];
    }
}
