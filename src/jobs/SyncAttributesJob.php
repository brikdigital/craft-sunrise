<?php

namespace brikdigital\sunrise\jobs;

use brikdigital\sunrise\services\AttributeService;
use brikdigital\sunrise\services\SunriseService;
use brikdigital\sunrise\Sunrise;
use Craft;
use craft\elements\Entry;
use craft\queue\BaseJob;

class SyncAttributesJob extends BaseJob
{
    public SunriseService $api;
    public AttributeService $service;

    public function __construct($config = [])
    {
        $plugin = Sunrise::getInstance();
        $this->api = $plugin->api;
        $this->service = $plugin->attributeService;

        parent::__construct($config);
    }

    function execute($queue): void
    {
        Sunrise::info('STARTING ATTRIBUTE SYNC');

        $section = $this->service->getSection();

        $attributes = $this->service->getAttributes();
        $count = count($attributes);
        foreach ($attributes as $i => $attribute) {
            $this->setProgress(
                $queue,
                $i / $count,
                Craft::t('app', '{step, number} of {total, number}', [
                    'step' => $i + 1,
                    'total' => $count,
                ])
            );

            $sunriseOptions = $this->api->getAll('attributemasteroption/search', [
                'attribute_extension' => $attribute->sunriseForeignId
            ]);
            $sunriseOptions = array_filter($sunriseOptions, fn($option) => $option['lang_id'] === 'EN');

            foreach ($sunriseOptions as $sunriseOption) {
                $optionId = $sunriseOption['option_extension'] ?? null;

                $option = $this->service->getOptionByForeignId($attribute->id, $optionId);
                if (!$option) {
                    $option = new Entry([
                        'sectionId' => $section->id,
                        'typeId' => $section->getEntryTypes()[0]?->id,
                        'sunriseForeignId' => $optionId
                    ]);
                }

                $option->title = $sunriseOption['option_name'] ?? null;

                if ($option->getDirtyAttributes() || $option->getDirtyFields()) {
                    Craft::$app->getElements()->saveElement($option);
                    Craft::$app->getStructures()->append($section->structureId, $option, $attribute);
                }
            }
        }

        Sunrise::info('FINISHED ATTRIBUTE SYNC');
    }

    protected function defaultDescription(): ?string
    {
        return 'Synchronizing attributes';
    }
}
