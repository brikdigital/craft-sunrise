<?php

namespace brikdigital\sunrise\services;

use Craft;
use craft\elements\Entry;
use craft\models\Section;
use yii\base\Component;

class ProductGroupService extends Component
{
    private const SECTION_HANDLE = 'sunriseProductGroups';

    public function getSection(): ?Section
    {
        return Craft::$app->getSections()->getSectionByHandle(self::SECTION_HANDLE);
    }

    public function getProductGroupByForeignId(string $parentForeignId, ?string $childForeignId = null)
    {
        $parent = Entry::find()
            ->section(self::SECTION_HANDLE)
            ->sunriseForeignId($parentForeignId)
            ->level(1)
            ->one();
        if ($childForeignId) {
            return Entry::find()
                ->section(self::SECTION_HANDLE)
                ->sunriseForeignId($childForeignId)
                ->descendantOf($parent)
                ->level(2)
                ->one();
        }
        return $parent;
    }
}
