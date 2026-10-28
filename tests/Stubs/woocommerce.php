<?php
/**
 * Minimal in-memory stand-ins for the WooCommerce/WordPress classes the services type-hint.
 * They implement only what the plugin calls.
 */

// phpcs:disable

class WP_Error
{
    private $code;
    private $message;
    private $data;

    public function __construct($code = '', $message = '', $data = '')
    {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }

    public function get_error_code()
    {
        return $this->code;
    }

    public function get_error_message()
    {
        return $this->message;
    }

    public function get_error_data()
    {
        return $this->data;
    }
}

class WC_Order_Item
{
    public $id;
    public $name;

    public function get_id()
    {
        return $this->id;
    }

    public function get_name()
    {
        return $this->name;
    }
}

class WC_Order_Item_Product extends WC_Order_Item
{
    public $product_id = 0;
    public $quantity = 1;
    public $subtotal = 0.0;
    public $total = 0.0;
    public $total_tax = 0.0;
    public $taxes = ['total' => []];
    public $order;

    public function __construct(array $props = [])
    {
        foreach ($props as $key => $value) {
            $this->$key = $value;
        }
    }

    public function get_product_id()
    {
        return $this->product_id;
    }

    public function get_quantity()
    {
        return $this->quantity;
    }

    public function get_subtotal()
    {
        return $this->subtotal;
    }

    public function get_total()
    {
        return $this->total;
    }

    public function get_total_tax()
    {
        return $this->total_tax;
    }

    public function get_taxes()
    {
        return $this->taxes;
    }

    public function get_order()
    {
        return $this->order;
    }
}

class WC_Order_Item_Fee extends WC_Order_Item
{
    public $total = 0.0;
    public $total_tax = 0.0;

    public function __construct(array $props = [])
    {
        foreach ($props as $key => $value) {
            $this->$key = $value;
        }
    }

    public function get_total()
    {
        return $this->total;
    }

    public function get_total_tax()
    {
        return $this->total_tax;
    }
}

class WC_Order
{
    public $id = 1287;
    public $number = '1287';
    public $order_key = 'wc_order_abc123';
    public $currency = 'IRT';
    public $total = 21396000.0;
    public $status = 'pending';
    public $payment_method = 'WC_Carbon_Pay';
    public $payment_method_title = '';
    public $transaction_id = '';
    public $customer_id = 0;
    public $date_created = '2026-10-22T14:18:00+03:30';
    public $date_paid = null;
    public $billing_first_name = 'علی';
    public $billing_last_name = 'رضایی';
    public $shipping_total = 0.0;
    public $shipping_tax = 0.0;
    public $meta = [];
    public $items = [];
    public $fees = [];
    public $notes = [];
    public $status_notes = [];
    public $saves = 0;

    public function __construct(array $props = [])
    {
        foreach ($props as $key => $value) {
            $this->$key = $value;
        }
    }

    public function get_id() { return $this->id; }
    public function get_order_number() { return $this->number; }
    public function get_order_key() { return $this->order_key; }
    public function get_currency() { return $this->currency; }
    public function get_total() { return $this->total; }
    public function get_date_created() { return $this->date_created; }
    public function get_date_paid() { return $this->date_paid; }
    public function get_billing_first_name() { return $this->billing_first_name; }
    public function get_billing_last_name() { return $this->billing_last_name; }
    public function get_shipping_total() { return $this->shipping_total; }
    public function get_shipping_tax() { return $this->shipping_tax; }
    public function get_payment_method() { return $this->payment_method; }
    public function set_payment_method($method) { $this->payment_method = $method; }
    public function set_payment_method_title($title) { $this->payment_method_title = $title; }
    public function get_customer_id() { return $this->customer_id; }
    public function get_transaction_id() { return $this->transaction_id; }

    public function get_items($type = 'line_item')
    {
        return $type === 'fee' ? $this->fees : $this->items;
    }

    public function get_item($id)
    {
        foreach ($this->items as $item) {
            if ($item->get_id() === $id) {
                return $item;
            }
        }

        return false;
    }

    public function get_meta($key)
    {
        return $this->meta[$key] ?? '';
    }

    public function update_meta_data($key, $value)
    {
        $this->meta[$key] = $value;
    }

    public function delete_meta_data($key)
    {
        unset($this->meta[$key]);
    }

    public function add_order_note($note)
    {
        $this->notes[] = $note;

        return count($this->notes);
    }

    public function save()
    {
        $this->saves++;

        return $this->id;
    }

    public function is_paid()
    {
        return in_array($this->status, ['processing', 'completed'], true);
    }

    public function needs_payment()
    {
        return in_array($this->status, ['pending', 'failed'], true) && $this->total > 0;
    }

    public function has_status($status)
    {
        return in_array($this->status, (array) $status, true);
    }

    public function update_status($status, $note = '')
    {
        $this->status = $status;
        $this->status_notes[] = $note;
        $this->saves++;

        return true;
    }

    public function payment_complete($transaction_id = '')
    {
        $this->transaction_id = $transaction_id;
        $this->status = 'processing';
        $this->date_paid = '2026-10-22T14:22:00+03:30';
        $this->saves++;

        return true;
    }

    public function get_checkout_order_received_url()
    {
        return 'https://shop.test/checkout/order-received/' . $this->id . '/?key=' . $this->order_key;
    }

    public function get_view_order_url()
    {
        return 'https://shop.test/my-account/view-order/' . $this->id . '/';
    }

    public function get_checkout_payment_url($on_checkout = false)
    {
        return 'https://shop.test/checkout/order-pay/' . $this->id . '/?' . ($on_checkout ? '' : 'pay_for_order=true&') . 'key=' . $this->order_key;
    }

}
