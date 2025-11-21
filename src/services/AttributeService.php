<?php

namespace brikdigital\sunrise\services;

use Craft;
use craft\elements\Entry;
use craft\models\Section;
use yii\base\Component;

class AttributeService extends Component
{
    public const SECTION_HANDLE = 'sunriseAttributes';

    public function getSection(): ?Section
    {
        return Craft::$app->getSections()->getSectionByHandle(self::SECTION_HANDLE);
    }

    public function getAttributeByForeignId(string $foreignId)
    {
        return Entry::find()
            ->section(self::SECTION_HANDLE)
            ->sunriseForeignId($foreignId)
            ->level(1)
            ->status(null)
            ->one();
    }

    public function getOptionByForeignId(string $attributeId, string $foreignId)
    {
        return Entry::find()
            ->section(self::SECTION_HANDLE)
            ->sunriseForeignId($foreignId)
            ->descendantOf($attributeId)
            ->level(2)
            ->status(null)
            ->one();
    }
}
