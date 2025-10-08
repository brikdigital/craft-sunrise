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

        $service = Sunrise::getInstance()->productGroup;
        $section = $service->getSection();
        foreach ($productGroups as $productGroup) {
            $productGroupId = $productGroup['typeId'];

            $parent = $service->getProductGroupByForeignId($productGroupId);
            if (!$parent) {
                $parent = new Entry([
                    'sectionId' => $section->id,
                    'typeId' => $section->getEntryTypes()[0]?->id,
                    'sunriseForeignId' => $productGroupId
                ]);
            }

            $parent->title = $productGroup['descriType'] ?? null;
            Craft::$app->getElements()->saveElement($parent);

            foreach ($productGroup['categoryIds'] as $category) {
                $categoryId = $category['categoryId'];

                $child = $service->getProductGroupByForeignId($productGroupId, $categoryId);
                if (!$child) {
                    $child = new Entry([
                        'sectionId' => $section->id,
                        'typeId' => $section->getEntryTypes()[0]?->id,
                        'sunriseForeignId' => $categoryId
                    ]);
                }

                $child->title = $category['descriCategory'] ?? null;
                Craft::$app->getElements()->saveElement($child);
                Craft::$app->getStructures()->append($section->structureId, $child, $parent);
            }
        }

        Sunrise::info('FINISHED PRODUCT GROUP SYNC');
    }

    protected function defaultDescription(): ?string
    {
        return 'Syncing product groups';
    }
}