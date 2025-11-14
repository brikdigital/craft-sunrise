<?php

namespace brikdigital\sunrise;

use brikdigital\sunrise\jobs\SyncOrderJob;
use brikdigital\sunrise\jobs\SyncOrderPaymentJob;
use Craft;
use craft\commerce\elements\Order;
use craft\controllers\UsersController;
use craft\helpers\Queue;
use Monolog\Formatter\LineFormatter;
use Psr\Log\LogLevel;
use brikdigital\sunrise\exceptions\SunriseException;
use brikdigital\sunrise\models\Settings;
use brikdigital\sunrise\services\AttributeService;
use brikdigital\sunrise\services\CustomerService;
use brikdigital\sunrise\services\ProductGroupService;
use brikdigital\sunrise\services\ProductService;
use brikdigital\sunrise\services\SunriseService;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\UserEvent;
use craft\log\MonologTarget;
use craft\services\Users;
use yii\base\Event;
use yii\log\Logger;

/**
 * craft-sunrise plugin
 *
 * @method static Sunrise getInstance()
 * @method Settings getSettings()
 * @author brikdigital
 * @copyright brikdigital
 * @license MIT
 * @property-read SunriseService $api
 * @property-read ProductGroupService $productGroup
 * @property-read ProductService $product
 * @property-read AttributeService $attribute
 * @property-read CustomerService $customer
 */
class Sunrise extends Plugin
{
    public string $schemaVersion = '4.0.0';
    public bool $hasCpSettings = true;

    public static function config(): array
    {
        return [
            'components' => [
                'api' => SunriseService::class,
                'productGroup' => ProductGroupService::class,
                'product' => ProductService::class,
                'attribute' => AttributeService::class,
                'customer' => CustomerService::class
            ],
        ];
    }

    public function init(): void
    {
        parent::init();
        $this->attachEventHandlers();
        $this->registerLogTarget();

        // Any code that creates an element query or loads Twig should be deferred until
        // after Craft is fully initialized, to avoid conflicts with other plugins/modules
        Craft::$app->onInit(function() {
            // ...
        });
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate(
            "$this->handle/_settings", [
            'settings' => $this->getSettings(),
        ]);
    }

    private function attachEventHandlers(): void
    {
        // Customers
        Event::on(
            Users::class,
            Users::EVENT_AFTER_ACTIVATE_USER,
            function (UserEvent $event) {
                $user = $event->user;
                if ($user->isInGroup($this->customer::USER_GROUP_HANDLE)) {
                    $this->customer->createCustomer($user);
                }
            }
        );

        Event::on(
            UsersController::class,
            UsersController::EVENT_AFTER_ASSIGN_GROUPS_AND_PERMISSIONS,
            function (UserEvent $event) {
                $user = $event->user;
                if ($user->isInGroup($this->customer::USER_GROUP_HANDLE)) {
                    $this->customer->createCustomer($user);
                }
            }
        );

        /**
         * Orders
         */
        Event::on(
            Order::class,
            Order::EVENT_AFTER_COMPLETE_ORDER,
            function (Event $e) {
                $order = $e->sender;
                Queue::push(new SyncOrderJob([
                    'orderId' => $order->id
                ]));
            }
        );

        Event::on(
            Order::class,
            Order::EVENT_AFTER_ORDER_PAID,
            function (Event $e) {
                $order = $e->sender;
                Queue::push(new SyncOrderPaymentJob([
                    'orderId' => $order->id
                ]));
            }
        );
    }

    /**
     * Logging
     */
    private function registerLogTarget(): void
    {
        Craft::getLogger()->dispatcher->targets[] = new MonologTarget([
            'name' => $this->handle,
            'categories' => [$this->handle],
            'level' => LogLevel::INFO,
            'maxFiles' => 14,
            'logContext' => false,
            'formatter' => new LineFormatter(
                format: "%datetime% [%channel%.%level_name%] %message%\n",
                dateFormat: 'Y-m-d H:i:s',
            ),
        ]);
    }

    public static function error(string $message, array $context = [], int $code = 0): void
    {
        $message = self::log($message, Logger::LEVEL_ERROR, $context);
        throw new SunriseException($message, $code);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log($message, Logger::LEVEL_INFO, $context);
    }

    private static function log(string $message, int $type, array $context): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = $trace[2] ?? null;
        $method = $caller
            ? ($caller['class'] ?? '') . ($caller['type'] ?? '') . ($caller['function'] ?? '')
            : 'unknown';

        $message = "$method: $message";
        Craft::getLogger()->log($message . ' ' .json_encode($context), $type, 'sunrise');
        return $message;
    }
}
