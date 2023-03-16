<?php

declare(strict_types=1);

namespace Ambimax\GlobalsysConnect\Import\Product\Processor;

use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class Properties extends AbstractProcessor implements ProcessorInterface
{
    /**
     * These keys have to be existing 'attributes' in the fetched products.
     */
    protected const PROPERTY_MAP_ATTRIBUTES = [
        'AbsatzBez',
        'ErlebnisBez',
        'FarbBez',
        'FormBez',
        'FutterBez',
        'MaterialBez',
        'SohleBez',
        'WeiteBez',
    ];

    /**
     * These keys have to be camel cased from existing keys in the fetched products
     * E. g. 'productsWwsCode' is from 'products_wws_code'.
     */
    protected const PROPERTY_MAP_BASE = [
        'productsName',
        'productsWwsCode',
    ];

    private SystemConfigService $systemConfigService;
    private EntityRepositoryInterface $propertyGroupOptionRepository;
    private EntityRepositoryInterface $productPropertyRepository;

    private array $pluginConfig;

    public function __construct(
        SystemConfigService $systemConfigService,
        EntityRepositoryInterface $propertyGroupOptionRepository,
        EntityRepositoryInterface $productPropertyRepository
    ) {
        $this->systemConfigService = $systemConfigService;
        $this->propertyGroupOptionRepository = $propertyGroupOptionRepository;
        $this->productPropertyRepository = $productPropertyRepository;
        $this->pluginConfig = $this->systemConfigService->get('AmbimaxGlobalsysConnect.config');
    }

    public function clear(?string $productId)
    {
        if (null == $productId) {
            return;
        }

        $allProductOptionIds = $this->productPropertyRepository
            ->searchIds(
                (new Criteria())
                    ->addFilter(new EqualsFilter('productId', $productId)),
                new Context(new SystemSource())
            )->getIds();

        if (empty($allProductOptionIds)) {
            return;
        }

        $mappedIdsToDelete = [];
        foreach ($allProductOptionIds as $entry) {
            $mappedIdsToDelete[] = [
                'productId' => $productId,
                'optionId' => $entry['property_group_option_id'],
            ];
        }

        $this->productPropertyRepository->delete(
            $mappedIdsToDelete,
            new Context(new SystemSource())
        );
    }

    public function provide(?array $productData = null): array
    {
        $propertiesFromBase = $this->providePropertiesByBase($productData);
        $propertiesFromAttributes = $this->providePropertiesByAttributes($productData['attributes']);

        return array_merge($propertiesFromBase, $propertiesFromAttributes);
    }

    protected function providePropertiesByBase(array $product): array
    {
        $properties = [];

        foreach (self::PROPERTY_MAP_BASE as $property) {
            $propertySnakeCase = $this->camelToSnakeCase($property);

            if (in_array($propertySnakeCase, array_keys($product)) &&
                in_array($property, array_keys($this->pluginConfig)) &&
                $this->pluginConfig[$property]) {
                // <=> $this->pluginConfig[$property] is mappable and $product[$propertySnakeCase] is the desired data

                $value = $product[$propertySnakeCase];
                $properties[] = $this->getProperty($this->pluginConfig[$property], $value);
            }
        }

        return $properties;
    }

    protected function providePropertiesByAttributes(array $attributes): array
    {
        $properties = [];

        foreach ($attributes as $attribute) {
            $normedName = str_replace([' ', '-', '_'], '', $attribute['name']);

            if (in_array($normedName, self::PROPERTY_MAP_ATTRIBUTES) &&
                in_array($normedName, array_keys($this->pluginConfig)) &&
                $this->pluginConfig[$normedName]) {
                // <=> $this->pluginConfig[$normedName] exists, is a 'propertyGroupId' and is mappable

                $value = trim($attribute['value'], '-');
                $properties[] = $this->getProperty($this->pluginConfig[$normedName], $value);
            }
        }

        return $properties;
    }

    protected function camelToSnakeCase(string $str): string
    {
        preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $str, $matches);
        $ret = $matches[0];
        foreach ($ret as &$match) {
            $match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
        }

        return implode('_', $ret);
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
