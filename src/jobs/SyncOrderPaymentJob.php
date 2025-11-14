<?php

namespace brikdigital\sunrise\jobs;

use brikdigital\sunrise\Sunrise;
use craft\commerce\elements\Order;
use craft\queue\BaseJob;

class SyncOrderPaymentJob extends BaseJob
{
    public int $orderId;

    protected function defaultDescription(): ?string
    {
        return 'Synchronise order payment';
    }

    public function execute($queue): void
    {
        $plugin = Sunrise::getInstance();
        $api = $plugin->api;
        $order = Order::findOne($this->orderId);

        if (empty($order->sunriseForeignId)) {
            $plugin::error('Order is missing Sunrise ID', ['order' => $order->toArray()]);
        }

        $customer = $order->getCustomer();
        if (empty($customer->sunriseForeignId)) {
            $plugin::error('Error getting customer foreign id', ['customer' => $customer->toArray()]);
        }

        $api->post('payment', [
            'order_id' => $order->sunriseForeignId,
            'customer_id' => $customer->sunriseForeignId,
            'amount' => $order->getTotal(),
            'currency_id' => $order->getPaymentCurrency(),
            'payment_term_id' => 'MOL',
        ]);
    }
}