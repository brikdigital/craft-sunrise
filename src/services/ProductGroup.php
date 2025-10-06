<?php

namespace brikdigital\sunrise\services;

use Craft;
use craft\fieldlayoutelements\CustomField;
use craft\fields\PlainText;
use craft\models\FieldGroup;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use yii\base\Component;

/**
 * Product Group service
 */
class ProductGroup extends Component
{
    private const SECTION_HANDLE = 'sunriseProductGroups';

    public function ensureSectionAndField(): void
    {
        $section = $this->createSection('Product groups');
        $field = $this->createField('sunriseForeignId', 'Sunrise Foreign ID', 'Sunrise');
        $this->attachFieldToSection($section, $field);
    }

    private function createSection(string $name): Section
    {
        $sectionsService = Craft::$app->sections;
        $section = $this->getSection();

        if (!$section) {
            $section = new Section([
                'handle' => self::SECTION_HANDLE,
                'type' => Section::TYPE_STRUCTURE,
                'name' => $name,
            ]);

            $siteSettings = array_map(fn($site) => new Section_SiteSettings(['siteId' => $site->id]), Craft::$app->sites->getAllSites());
            $section->setSiteSettings($siteSettings);

            if (!$sectionsService->saveSection($section)) {
                \brikdigital\sunrise\Sunrise::error('Could not save section', ['errors' => $section->getErrors()]);
            }
        }

        return $section;
    }

    public function getSection(): ?Section
    {
        return Craft::$app->getSections()->getSectionByHandle(self::SECTION_HANDLE);
    }

    private function createField(string $handle, string $name, string $groupName): CustomField
    {
        $fieldsService = Craft::$app->fields;

        $group = collect($fieldsService->getAllGroups())
            ->first(fn($g) => $g->name === $groupName) ?? new FieldGroup(['name' => $groupName]);

        if (!$group->id) {
            $fieldsService->saveGroup($group);
        }

        $field = $fieldsService->getFieldByHandle($handle)
            ?? new PlainText([
                'name' => $name,
                'handle' => $handle,
                'required' => true,
                'groupId' => $group->id,
            ]);

        if (!$field->id && !$fieldsService->saveField($field)) {
            \brikdigital\sunrise\Sunrise::error('Could not save field', ['errors' => $field->getErrors()]);
        }

        return new CustomField($field);
    }

    private function attachFieldToSection(Section $section, CustomField $field): void
    {
        $entryType = $section->getEntryTypes()[0];
        $tab = $entryType->getFieldLayout()->getTabs()[0];

        if (!collect($tab->getElements())->contains(fn($el) => $el instanceof CustomField && $el->getField()->handle === $field->getField()->handle)) {
            $tab->setElements(array_merge($tab->getElements(), [$field]));

            $entryType->setFieldLayout($entryType->getFieldLayout());
            if (!Craft::$app->sections->saveEntryType($entryType)) {
                \brikdigital\sunrise\Sunrise::error('Could not save entry type', ['errors' => $entryType->getErrors()]);
            }
        }
    }
}
