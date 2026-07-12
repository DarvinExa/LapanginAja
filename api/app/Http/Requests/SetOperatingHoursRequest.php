<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetOperatingHoursRequest extends FormRequest
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
            'hours' => ['required', 'array', 'min:1'],
            'hours.*.day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'hours.*.is_closed' => ['required', 'boolean'],
            'hours.*.open_time' => ['required_if:hours.*.is_closed,false', 'nullable', 'date_format:H:i'],
            'hours.*.close_time' => [
                'required_if:hours.*.is_closed,false',
                'nullable',
                'date_format:H:i',
                'after:hours.*.open_time',
            ],
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
            'hours.*.close_time.after' => 'Jam tutup harus setelah jam buka.',
        ];
    }
}
