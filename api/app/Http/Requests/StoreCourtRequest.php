<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCourtRequest extends FormRequest
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
            'sport_type' => ['required', 'string', 'max:50'],
            'price_per_hour' => ['required', 'numeric', 'min:0'],
            'slot_duration_minutes' => ['required', 'integer', 'in:30,60,90,120'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
