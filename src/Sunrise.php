<?php

namespace brikdigital\sunrise;

use Craft;
use brikdigital\sunrise\models\Settings;
use brikdigital\sunrise\services\Sunrise as SunriseAPI;
use craft\base\Model;
use craft\base\Plugin;

/**
 * craft-sunrise plugin
 *
 * @method static Sunrise getInstance()
 * @method Settings getSettings()
 * @author brikdigital
 * @copyright brikdigital
 * @license MIT
 * @property-read SunriseAPI $api
 */
class Sunrise extends Plugin
{
    public string $schemaVersion = '4.0.0';
    public bool $hasCpSettings = true;

    public static function config(): array
    {
        return [
            'components' => [
                'api' => SunriseAPI::class
            ],
        ];
    }

    public function init(): void
    {
        parent::init();
        $this->attachEventHandlers();

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
}
