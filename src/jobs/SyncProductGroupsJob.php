<?php

namespace brikdigital\sunrise\jobs;

use brikdigital\sunrise\Sunrise;
use Craft;
use craft\elements\Entry;
use craft\queue\BaseJob;

class SyncProductGroupsJob extends BaseJob
{
    public function execute($queue): void
    {
        Sunrise::info('STARTING PRODUCT GROUP SYNC');

        $response = Sunrise::getInstance()->api->get('productgroups');
        $productGroups = $response[0]['typeIds'] ?? [];
        if (empty($productGroups)) {
            Sunrise::error('No product groups found', ['response' => $response]);
        }

        $section = Sunrise::getInstance()->productGroup->getSection();
        foreach ($productGroups as $productGroup) {
            $productGroupId = $productGroup['typeId'];

            $parent = Entry::find()
                ->sectionId($section->id)
                ->sunriseForeignId($productGroupId)
                ->level(1)
                ->one();
            if (!$parent) {
                $title = $productGroup['descriType'] ?? null;
                $parent = new Entry([
                    'sectionId' => $section->id,
                    'typeId' => $section->getEntryTypes()[0]?->id,
                    'title' => $title,
                    'sunriseForeignId' => $productGroupId
                ]);

                if (Craft::$app->getElements()->saveElement($parent)) {
                    Sunrise::info("Created product group $title");
                }
            }

            foreach ($productGroup['categoryIds'] as $category) {
                $categoryId = $category['categoryId'];

                $child = Entry::find()
                    ->sectionId($section->id)
                    ->sunriseForeignId($categoryId)
                    ->level(2)
                    ->one();
                if (!$child) {
                    $title = $category['descriCategory'] ?? null;
                    $child = new Entry([
                        'sectionId' => $section->id,
                        'typeId' => $section->getEntryTypes()[0]?->id,
                        'title' => $title,
                        'sunriseForeignId' => $categoryId
                    ]);

                    if (Craft::$app->getElements()->saveElement($child)) {
                        Sunrise::info("Created product subgroup $title");
                    }

                    Craft::$app->getStructures()->append($section->structureId, $child, $parent);
                }
            }
        }

        Sunrise::info('FINISHED PRODUCT GROUP SYNC');
    }

    protected function defaultDescription(): ?string
    {
        return 'Syncing product groups';
    }
}