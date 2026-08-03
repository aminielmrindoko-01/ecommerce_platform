<?php

namespace App\Http\Requests\Admin;

use App\Models\OrderItem;
use App\Services\OrderItemFulfillmentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Admin fulfillment override with reason rules for exception paths.
 */
class UpdateOrderItemFulfillmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $item = $this->route('orderItem');

        if (! $item instanceof OrderItem) {
            return false;
        }

        $order = $this->route('order');
        if ($order && (int) $item->order_id !== (int) $order->id) {
            return false;
        }

        return (bool) ($this->user()?->hasPermission('orders.update') && $this->user()->can('updateFulfillment', $item));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fulfillment_status' => [
                'required',
                'string',
                Rule::in(OrderItem::FULFILLMENT_STATUSES),
            ],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $item = $this->route('orderItem');
            if (! $item instanceof OrderItem) {
                return;
            }

            $to = strtolower((string) $this->input('fulfillment_status'));
            $from = $item->fulfillment_status ?: 'pending';
            $service = app(OrderItemFulfillmentService::class);

            if ($service->reasonRequired('admin', $from, $to) && blank($this->input('reason'))) {
                $validator->errors()->add('reason', 'A reason is required for this admin override.');
            }
        });
    }
}
