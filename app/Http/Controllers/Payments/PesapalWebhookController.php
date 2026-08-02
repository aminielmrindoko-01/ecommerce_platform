<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Services\PesapalPaymentProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PesaPal IPN endpoint (sandbox). CSRF-exempt. Never trusts payload status alone.
 */
class PesapalWebhookController extends Controller
{
    public function __invoke(Request $request, PesapalPaymentProcessor $processor): JsonResponse
    {
        $payload = [
            'OrderTrackingId' => $request->input('OrderTrackingId', $request->query('OrderTrackingId')),
            'OrderMerchantReference' => $request->input('OrderMerchantReference', $request->query('OrderMerchantReference')),
            'OrderNotificationType' => $request->input('OrderNotificationType', $request->query('OrderNotificationType')),
        ];

        logger()->info('pesapal.callback.received', [
            'type' => $payload['OrderNotificationType'],
            'merchant_reference' => $payload['OrderMerchantReference'],
            'provider_tracking' => $payload['OrderTrackingId'],
        ]);

        $result = $processor->processNotification($payload);

        return response()->json([
            'orderNotificationType' => $payload['OrderNotificationType'] ?: 'IPNCHANGE',
            'orderTrackingId' => $payload['OrderTrackingId'],
            'orderMerchantReference' => $payload['OrderMerchantReference'],
            'status' => $result['ok'] ? 200 : 500,
        ]);
    }
}
