<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isManager();
    }

    public function rules(): array
    {
        return [
            'travel_request_id' => 'required|exists:travel_requests,id',
            'type' => 'required|in:flight,hotel',
            'provider' => 'required|string|max:255',
            'provider_data' => 'nullable|array',
        ];
    }
}
