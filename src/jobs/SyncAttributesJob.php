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
        $this->service = $plugin->attribute;

        parent::__construct($config);
    }

    function execute($queue): void
    {
        Sunrise::info('STARTING ATTRIBUTE SYNC');

        $section = $this->service->getSection();

        $sunriseAttributes = $this->api->getAll('attributemaster/search');
        $sunriseAttributes = array_values(array_filter($sunriseAttributes, fn($attribute) => $attribute['lang_id'] === 'EN'));

        $count = count($sunriseAttributes);
        foreach ($sunriseAttributes as $i => $sunriseAttribute) {
            $this->setProgress(
                $queue,
                $i / $count,
                Craft::t('app', '{step, number} of {total, number}', [
                    'step' => $i + 1,
                    'total' => $count,
                ])
            );

            /**
             * UPSERT ATTRIBUTE
             */
            $attributeId = $sunriseAttribute['attribute_extension'] ?? null;

            $attribute = $this->service->getAttributeByForeignId($attributeId);
            if (!$attribute) {
                $attribute = new Entry([
                    'sectionId' => $section->id,
                    'typeId' => $section->getEntryTypes()[0]?->id,
                    'sunriseForeignId' => $attributeId
                ]);
            }

            $attribute->title = $sunriseAttribute['attribute_name'] ?? null;

            if ($attribute->getDirtyAttributes() || $attribute->getDirtyFields()) {
                Craft::$app->getElements()->saveElement($attribute);

                Sunrise::info('Updated attribute', ['id' => $attribute->id, 'title' => $attribute->title]);
            }

            /**
             * UPSERT OPTIONS
             */
            $sunriseOptions = $this->api->getAll('attributemasteroption/search', [
                'attribute_extension' => $attributeId
            ]);
            $sunriseOptions = array_filter($sunriseOptions, fn($option) => $option['lang_id'] === 'EN');

            foreach ($sunriseOptions as $sunriseOption) {
                $optionId = $sunriseOption['option_id'] ?? null;

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

                    Sunrise::info('Updated option', ['id' => $option->id, 'title' => $option->title]);
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
