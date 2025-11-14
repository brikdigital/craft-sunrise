<?php

namespace brikdigital\sunrise\services;

use brikdigital\sunrise\exceptions\SunriseException;
use brikdigital\sunrise\Sunrise;
use Craft;
use craft\elements\User;
use yii\base\Component;

class CustomerService extends Component
{
    public const USER_GROUP_HANDLE = 'customers';

    public function createCustomer(User $user): User
    {
         if (!empty($user->sunriseForeignId)) {
             return $user;
         }

         $plugin = Sunrise::getInstance();
         $api = $plugin->api;

         $customer = null;
         try {
             $customers = $api->getAll('customer/search', [
                 'customer_email' => $user->email,
             ]);
             $customer = $customers[0] ?? null;
         } catch (SunriseException $e) {
             // 404 = no customers found
             if ($e->getCode() !== 404) {
                 throw $e;
             }
         }

        if (!$customer) {
            $nameParts = explode(' ', $user->fullName);
            $body = array_filter([
                'customer_email' => $user->email,
                'customer_company_name' => $user->company ?: null,
                'customer_last_name' => array_pop($nameParts) ?: null,
                'customer_first_name' => implode(' ', $nameParts) ?: null,
                'customer_address' => $user->address ?: null,
                'customer_phone' => $user->phonenumber ?: null,
                'customer_zip' => $user->zipcode ?: null,
                'customer_city' => $user->city ?: null,
            ]);

            $response = $api->post('customer', $body);
            if (empty($response['customer_id'])) {
                $plugin::error('Error creating customer', ['response' => json_encode($response)]);
            }

            $customer = $response;
        }

        $user->sunriseForeignId = $customer['customer_id'];

        if ($user->getDirtyAttributes() || $user->getDirtyFields()) {
            Craft::$app->getElements()->saveElement($user);
        }

        return $user;
    }
}
