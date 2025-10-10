<?php

namespace brikdigital\sunrise\services;

use Craft;
use craft\elements\Entry;
use craft\models\Section;
use yii\base\Component;

class AttributeService extends Component
{
    public const SECTION_HANDLE = 'sunriseAttributes';

    public function getAttributes(): array
    {
        return Entry::find()
            ->section(self::SECTION_HANDLE)
            ->level(1)
            ->status(null)
            ->all();
    }

    public function getSection(): ?Section
    {
        return Craft::$app->getSections()->getSectionByHandle(self::SECTION_HANDLE);
    }

    public function getOptionByForeignId(string $attributeId, string $optionId)
    {
        return Entry::find()
            ->section(self::SECTION_HANDLE)
            ->sunriseForeignId($optionId)
            ->descendantOf($attributeId)
            ->level(2)
            ->status(null)
            ->one();
    }
}
