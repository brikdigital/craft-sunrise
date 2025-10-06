<?php

namespace brikdigital\sunrise;

use brikdigital\sunrise\exceptions\SunriseException;
use Craft;
use brikdigital\sunrise\models\Settings;
use brikdigital\sunrise\services\ProductGroup;
use brikdigital\sunrise\services\Sunrise as SunriseAPI;
use craft\base\Model;
use craft\base\Plugin;
use craft\log\MonologTarget;
use Monolog\Formatter\LineFormatter;
use Psr\Log\LogLevel;
use yii\log\Logger;

/**
 * craft-sunrise plugin
 *
 * @method static Sunrise getInstance()
 * @method Settings getSettings()
 * @author brikdigital
 * @copyright brikdigital
 * @license MIT
 * @property-read SunriseAPI $api
 * @property-read ProductGroup $productGroup
 */
class Sunrise extends Plugin
{
    public string $schemaVersion = '4.0.0';
    public bool $hasCpSettings = true;

    public static function config(): array
    {
        return [
            'components' => [
                'api' => SunriseAPI::class,
                'productGroup' => ProductGroup::class
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
        // Register event handlers here ...
        // (see https://craftcms.com/docs/4.x/extend/events.html to get started)
    }

    protected function afterInstall(): void
    {
        $this->productGroup->ensureSectionAndField();
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

    public static function error(string $message, array $context = []): void
    {
        $message = self::log($message, Logger::LEVEL_ERROR, $context);
        throw new SunriseException($message);
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
