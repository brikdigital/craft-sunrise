<?php

namespace brikdigital\sunrise\services;

use brikdigital\sunrise\exceptions\SunriseException;
use brikdigital\sunrise\Sunrise;
use Craft;
use craft\commerce\elements\Order;
use craft\elements\User;
use yii\base\Component;

class CustomerService extends Component
{
    public function createCustomer(Order $order): User
    {
        $customer = $order->getCustomer();

        if (!empty($customer->sunriseForeignId)) {
            return $customer;
        }

        $plugin = Sunrise::getInstance();
        $api = $plugin->api;

        $sunriseCustomers = $api->getAll('customer/search', [
            'customer_email' => $customer->email,
        ]);
        $sunriseCustomer = current($sunriseCustomers);

        if (!$sunriseCustomer) {
            $billing = $order->getBillingAddress();

            $nameParts = explode(' ', $billing->fullName ?? implode(' ', array_filter([$billing->firstName, $billing->lastName])) ?: $billing->getOrganization() ?? null);
            $body = array_filter([
                'customer_id_api' => $customer->id,
                'customer_email' => $customer->email,
                'customer_company_name' => $billing->getOrganization() ?: null,
                'customer_last_name' => array_pop($nameParts) ?: null,
                'customer_first_name' => implode(' ', $nameParts) ?: null,
                'customer_address' => $billing->getAddressLine1() ?: null,
                'customer_zip' => $billing->getPostalCode() ?: null,
                'customer_city' => $billing->getLocality() ?: null,
                'country_id' => $billing->getCountryCode() ?: null,
                'state_code' => $billing->getAdministrativeArea() ?: null,
            ]);

            $response = $api->post('customer', $body);
            if (empty($response['customer_id'])) {
                $plugin::error('Error creating customer', ['response' => json_encode($response)]);
            }

            $sunriseCustomer = $response;
        }

        $customer->sunriseForeignId = $sunriseCustomer['customer_id'];

        if ($customer->getDirtyAttributes() || $customer->getDirtyFields()) {
            Craft::$app->getElements()->saveElement($customer);
        }

        return $customer;
    }
}
