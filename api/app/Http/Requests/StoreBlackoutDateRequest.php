<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBlackoutDateRequest extends FormRequest
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
        $courtId = $this->route('court_id');

        return [
            'date' => [
                'required',
                'date',
                'date_format:Y-m-d',
                'after_or_equal:today',
                Rule::unique('blackout_dates', 'date')->where(function ($query) use ($courtId) {
                    return $query->where('court_id', $courtId);
                }),
            ],
            'reason' => ['nullable', 'string', 'max:255'],
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
            'date.unique' => 'Tanggal blackout ini sudah didaftarkan untuk lapangan ini.',
            'date.after_or_equal' => 'Tanggal blackout harus hari ini atau hari berikutnya.',
        ];
    }
}
