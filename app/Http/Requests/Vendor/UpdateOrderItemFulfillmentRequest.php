<?php

namespace App\Http\Requests\Vendor;

use App\Models\OrderItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Vendor fulfillment status update.
 *
 * Authorizes via OrderItemPolicy ownership. Does not accept ownership fields.
 */
class UpdateOrderItemFulfillmentRequest extends FormRequest
{
    /**
     * Only the owning vendor (or admin via policy) may update fulfillment.
     */
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

        return $this->user()?->can('updateFulfillment', $item) ?? false;
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
        ];
    }
}
