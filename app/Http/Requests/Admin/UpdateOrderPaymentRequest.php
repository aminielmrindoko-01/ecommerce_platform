<?php

namespace App\Http\Requests\Admin;

use App\Models\PaymentTransaction;
use App\Services\PaymentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Admin payment foundation operations (manual/stub only).
 */
class UpdateOrderPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->isAdmin() && $this->user()->can('manage', PaymentTransaction::class));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payment_status' => [
                'required',
                'string',
                Rule::in(['processing', 'paid', 'failed', 'cancelled']),
            ],
            'reason' => ['nullable', 'string', 'max:500'],
            'provider_reference' => ['nullable', 'string', 'max:128'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $to = strtolower((string) $this->input('payment_status'));
            if (app(PaymentService::class)->reasonRequired($to) && blank($this->input('reason'))) {
                $validator->errors()->add('reason', 'A reason is required for this payment change.');
            }
        });
    }
}
