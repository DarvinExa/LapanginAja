<?php

namespace App\Http\Requests;

use App\Models\Court;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class GetAvailabilityRequest extends FormRequest
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
            'date' => ['required', 'date', 'date_format:Y-m-d'],
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
                if ($validator->errors()->has('date') || $validator->errors()->has('court_id')) {
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

                $timezone = $tenant->timezone ?? 'Asia/Makassar';
                $requestedDateStr = $this->input('date');

                // Determine local limits
                $localToday = Carbon::now($timezone)->startOfDay();
                $localMaxAdvance = Carbon::now($timezone)->addDays($tenant->max_advance_days ?? 30)->endOfDay();

                $requestedDate = Carbon::parse($requestedDateStr, $timezone)->startOfDay();

                if ($requestedDate->lt($localToday)) {
                    $validator->errors()->add('date', 'Tanggal availability tidak boleh sebelum hari ini.');
                }

                if ($requestedDate->gt($localMaxAdvance)) {
                    $validator->errors()->add('date', 'Tanggal availability melebihi batas pemesanan maksimum venue ('.$tenant->max_advance_days.' hari ke depan).');
                }
            },
        ];
    }
}
