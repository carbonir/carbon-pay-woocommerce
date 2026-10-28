<?php

namespace Carbon\Pay\Payment;

use Carbon\Pay\Support\Money;
use WC_Order;
use WC_Order_Item_Fee;
use WC_Order_Item_Product;

/**
 * Builds the body of POST /v1/cpg/payments (CreateOnlineValidator) from a WooCommerce order.
 */
final class PaymentRequestBuilder
{
    public function build(WC_Order $order, string $callbackUrl, string $mobile, string $nationalCode): array
    {
        $currency = (string) $order->get_currency();

        $payload = [
            'callback_url' => $callbackUrl,
            'invoice_reference' => (string) $order->get_order_number(),
            'invoice_date' => (string) $order->get_date_created(),
            'invoice_amount' => Money::toRial($currency, $order->get_total()),
            'payer_first_name' => trim((string) $order->get_billing_first_name()),
            'payer_last_name' => trim((string) $order->get_billing_last_name()),
            'payer_national_code' => $nationalCode,
            'payer_mobile' => $mobile,
            'description' => 'پرداخت سفارش #' . $order->get_order_number(),
            'items' => [],
        ];

        foreach ($order->get_items() as $item) {
            if ($item instanceof WC_Order_Item_Product) {
                $payload['items'][] = $this->productItem($item, $currency);
            }
        }

        foreach ($order->get_items('fee') as $fee) {
            if ($fee instanceof WC_Order_Item_Fee && (float) $fee->get_total() > 0) {
                $payload['items'][] = [
                    'reference' => 'FEE-' . $fee->get_id(),
                    'name' => (string) $fee->get_name(),
                    'quantity' => 1,
                    'unit_price' => Money::toRial($currency, $fee->get_total()),
                    'unit_discount' => 0,
                    'unit_tax_amount' => Money::toRial($currency, $fee->get_total_tax()),
                    'is_product' => false,
                ];
            }
        }

        if ((float) $order->get_shipping_total() > 0) {
            $payload['items'][] = [
                'reference' => 'SEND-COST',
                'name' => 'هزینه ارسال',
                'quantity' => 1,
                'unit_price' => Money::toRial($currency, $order->get_shipping_total()),
                'unit_discount' => 0,
                'unit_tax_amount' => Money::toRial($currency, $order->get_shipping_tax()),
                'is_product' => false,
            ];
        }

        return $payload;
    }

    private function productItem(WC_Order_Item_Product $item, string $currency): array
    {
        $quantity = max(1, (int) $item->get_quantity());
        $subtotal = (float) $item->get_subtotal();
        $total = (float) $item->get_total();
        $discount = max(0.0, $subtotal - $total);

        return [
            'reference' => (string) $item->get_product_id(),
            'name' => (string) $item->get_name(),
            'quantity' => $quantity,
            'unit_price' => Money::toRial($currency, max(0.0, $subtotal) / $quantity),
            'unit_discount' => Money::toRial($currency, $discount / $quantity),
            'unit_tax_amount' => Money::toRial($currency, max(0.0, (float) $item->get_total_tax()) / $quantity),
            'is_product' => true,
        ];
    }
}
