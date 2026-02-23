<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }


    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'courier_code' => ['required', 'string'],
            'courier_service_code' => ['required', 'string'],
            'shipping_price' => ['required', 'integer', 'min:0'],
            'voucher_code' => ['nullable', 'string'],
            'points_used' => ['nullable', 'integer', 'min:0']
        ];
    }

}
