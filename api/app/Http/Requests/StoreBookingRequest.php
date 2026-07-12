<?php

namespace App\Http\Requests;

use App\Models\Court;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class StoreBookingRequest extends FormRequest
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
            'court_id' => ['required', 'exists:courts,id'],
            'start_time' => ['required', 'date', 'date_format:Y-m-d H:i:s'],
            'customer_name' => ['required', 'string', 'min:2', 'max:100'],
            'customer_phone' => ['required', 'string', 'regex:/^(\+62|0)8[0-9]{7,13}$/'],
            'customer_email' => ['required', 'email', 'max:100'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('start_time') || $validator->errors()->has('court_id')) {
                    return;
                }

                /** @var Tenant $tenant */
                $tenant = app(Tenant::class);
                $court = Court::find($this->input('court_id'));

                // Verify that court belongs to the active resolved tenant
                if ($court && $court->tenant_id !== $tenant->id) {
                    $validator->errors()->add('court_id', 'Lapangan tidak sesuai dengan venue.');

                    return;
                }

                // Verify court is active
                if ($court && ! $court->is_active) {
                    $validator->errors()->add('court_id', 'Lapangan sedang tidak aktif.');

                    return;
                }

                $timezone = $tenant->timezone ?? 'Asia/Makassar';
                $requestedDateTimeStr = $this->input('start_time');

                // Determine local limits
                $localToday = Carbon::now($timezone)->startOfDay();
                $localMaxAdvance = Carbon::now($timezone)->addDays($tenant->max_advance_days ?? 30)->endOfDay();

                $requestedDateTime = Carbon::parse($requestedDateTimeStr, $timezone);

                if ($requestedDateTime->copy()->startOfDay()->lt($localToday)) {
                    $validator->errors()->add('start_time', 'Tanggal booking tidak boleh sebelum hari ini.');
                }

                if ($requestedDateTime->copy()->startOfDay()->gt($localMaxAdvance)) {
                    $validator->errors()->add('start_time', 'Tanggal booking melebihi batas pemesanan maksimum venue ('.$tenant->max_advance_days.' hari ke depan).');
                }
            },
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
            'customer_phone.regex' => 'Format nomor HP tidak valid. Gunakan format Indonesia (08xxxx atau +628xxxx).',
        ];
    }
}
