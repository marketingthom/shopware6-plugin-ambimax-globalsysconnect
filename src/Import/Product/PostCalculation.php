<?php

declare(strict_types=1);

namespace Ambimax\GlobalsysConnect\Import\Product;

use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class PostCalculation
{
    private SystemConfigService $systemConfigService;
    private EntityRepositoryInterface $propertyGroupOptionRepository;

    /**
     * @var array|bool|float|int|string|null
     */
    protected $pluginConfig;

    public function __construct(
        SystemConfigService $systemConfigService,
        EntityRepositoryInterface $propertyGroupOptionRepository
    ) {
        $this->systemConfigService = $systemConfigService;
        $this->propertyGroupOptionRepository = $propertyGroupOptionRepository;

        $this->pluginConfig = $this->systemConfigService->get('AmbimaxGlobalsysConnect.config');
    }

    public function calculate(array $productEntityData, array $productErpData): array
    {
        if ($this->needsToAdjustGenderProperty($productErpData)) {
            $productEntityData = $this->adjustGenderProperty($productEntityData, $productErpData);
        }

        return $productEntityData;
    }

    /**
     * https://ambimax.atlassian.net/browse/BERG-281.
     * Gender "Unisex" will be abandoned. Assign "Mädchen" and "Jungs" or "Damen" and "Herren" instead.
     *
     * @param array<string,mixed> $productErpData
     */
    protected function needsToAdjustGenderProperty(array $productErpData): bool
    {
        foreach ($productErpData['attributes'] as $attribute) {
            $normedName = str_replace([' ', '-', '_'], '', $attribute['name']);
            $value = trim($attribute['value'], '-');

            if ('FormBez' == $normedName && 'Unisex' == $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $productEntityData
     *
     * @return array<string,mixed>
     */
    protected function adjustGenderProperty(array $productEntityData, array $productErpData): array
    {
        $properties = $productEntityData['properties'];
        $genderGroupId = $this->pluginConfig['FormBez'];
        $unisexOptionId = $this->queryOptionId($genderGroupId, 'Unisex');

        // if it is in "Kinderschuhe"
        if (in_array('28', $productErpData['categories'])) {
            $properties[] = $this->getProperty($genderGroupId, 'Jungs');
            $properties[] = $this->getProperty($genderGroupId, 'Mädchen');
        }

        // if it is in "Damenschuhe" or "Herrenschuhe"
        if (in_array('3', $productErpData['categories']) || in_array('17', $productErpData['categories'])) {
            $properties[] = $this->getProperty($genderGroupId, 'Herren');
            $properties[] = $this->getProperty($genderGroupId, 'Damen');
        }

        // drop option 'Unisex'
        $properties = array_filter($properties, function ($property) use ($unisexOptionId) {
            return isset($property['id']) ? ($property['id'] != $unisexOptionId) : ('Unisex' != $property['name']);
        });

        $productEntityData['properties'] = $properties;
        return $productEntityData;
    }

    /**
     * @return string[]
     */
    protected function getProperty(string $propertyGroupId, string $value): array
    {
        $optionId = $this->queryOptionId($propertyGroupId, $value);
        if ($optionId) {
            // <=> option exists
            return ['id' => $optionId];
        } else {
            // <=> option will be created
            return ['name' => $value, 'groupId' => $propertyGroupId];
        }
    }

    protected function queryOptionId(string $propertyGroupId, string $value): ?string
    {
        return $this->propertyGroupOptionRepository
            ->searchIds(
                (new Criteria())
                    ->addFilter(new EqualsFilter('groupId', $propertyGroupId))
                    ->addFilter(new EqualsFilter('name', $value)),
                new Context(new SystemSource())
            )
            ->firstId();
    }
}
