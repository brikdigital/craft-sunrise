<?php

namespace brikdigital\sunrise\jobs;

use brikdigital\sunrise\Sunrise;
use Craft;
use craft\commerce\elements\Order;
use craft\queue\BaseJob;

/**
 * Sync Order Job queue job
 */
class SyncOrderJob extends BaseJob
{
    public int $orderId;

    protected function defaultDescription(): ?string
    {
        return 'Synchronise order';
    }

    function execute($queue): void
    {
        $plugin = Sunrise::getInstance();
        $api = $plugin->api;
        $order = Order::findOne($this->orderId);

        $customer = $order->getCustomer();
        if (empty($customer->sunriseForeignId)) {
            $customer = $plugin->customer->createCustomer($order);
        }

        $details = [];
        foreach ($order->getLineItems() as $lineItem) {
            $taxRate = $lineItem->getTaxCategory()->getTaxRates()[0]->rate ?? null;
            $variant = $lineItem->getPurchasable();

            $itemPrice = $lineItem->getSalePrice();
            $discount = $lineItem->getDiscount();
            $subtotal = $lineItem->getSubtotal();

            if ($discount) {
                $itemDiscount = abs($discount / $lineItem->qty);
                $itemPrice = $lineItem->getSalePrice() - $itemDiscount;
                $subtotal = $itemPrice * $lineItem->qty;
            }


            $details[] = [
                'product_id' => $variant->getProduct()->sunriseForeignId,
                'product_extension' => $lineItem->getSku(),
                'qty' => $lineItem->qty,

                'product_title' => $lineItem->getSku() . ' ' . $lineItem->getDescription(),

                'selling_price' => $lineItem->getSalePrice(),
                'price_prod' => $itemPrice,
                'tax_rate' => $taxRate * 100,
                'price_incl_vat' => round($itemPrice * ($taxRate + 1), 2),

                'total_price_excl_vat' => $subtotal,
                'total_amount_tax' => round($subtotal * $taxRate, 2),
                'total_price_incl_vat' => round($subtotal * ($taxRate + 1), 2),
            ];
        }

        $billing = $order->getBillingAddress();
        $shipping = $order->getShippingAddress();

        $response = $api->post('order', [
            'customer_id_api' => $customer->id,
            'order_id_api' => $order->id,

            'customer_id' => $customer->sunriseForeignId,
            'currency_id' => $order->getPaymentCurrency(),
            'shipping_method_id' => $order->shippingMethodHandle != 'shipping'
                ? 34
                : ($order->getTotalShippingCost() == 0 ? 24 : 27),

            'discount_amount' => $order->getTotalDiscount(),
            'total_ord_excl_vat' => ($order->getTotalPrice() - $order->getTotalTax()),
            'total_ord_vat' => $order->getTotalTax(),
            'total_order_ship' => $order->getTotalShippingCost(),
            'total_ord' => $order->getTotalPrice(),

            'delivery_company' => $shipping->getOrganization() ?? null,
            'delivery_name' => $shipping->fullName ?? implode(' ', array_filter([$shipping->firstName, $shipping->lastName])) ?: $shipping->getOrganization() ?? null,
            'delivery_address' => $shipping->getAddressLine1() ?? null,
            'delivery_zip' => $shipping->getPostalCode() ?? null,
            'delivery_city' => $shipping->getLocality() ?? null,
            'delivery_country_id' => $shipping->getCountryCode() ?? null,

            'billing_comp' => $billing->getOrganization() ?? null,
            'billing_name' => $billing->fullName ?? implode(' ', array_filter([$billing->firstName, $billing->lastName])) ?: $billing->getOrganization() ?? null,
            'billing_address' => $billing->getAddressLine1() ?? null,
            'billing_zip' => $billing->getPostalCode() ?? null,
            'billing_city' => $billing->getLocality() ?? null,
            'billing_country_code' => $billing->getCountryCode() ?? null,

            'details' => $details,
        ]);

        if (empty($response['order_id'])) {
            $plugin::error('Error creating order', ['response' => json_encode($response)]);
        }

        $order->sunriseForeignId = $response['order_id'];
        Craft::$app->getElements()->saveElement($order);
    }
}
