<?php

namespace brikdigital\sunrise\services;

use Craft;
use craft\models\Section;
use yii\base\Component;

class ProductGroupService extends Component
{
    private const SECTION_HANDLE = 'sunriseProductGroups';

    public function getSection(): ?Section
    {
        return Craft::$app->getSections()->getSectionByHandle(self::SECTION_HANDLE);
    }
}
