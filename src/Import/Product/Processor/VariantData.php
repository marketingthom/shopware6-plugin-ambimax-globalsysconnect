<?php

declare(strict_types=1);

namespace Ambimax\GlobalsysConnect\Import\Product\Processor;

use Exception;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class VariantData extends AbstractProcessor implements ProcessorInterface
{
    private EntityRepositoryInterface $productRepository;
    private EntityRepositoryInterface $propertyGroupOptionRepository;
    private SystemConfigService $systemConfigService;
    private ProcessorInterface $clearanceSale;
    private ProcessorInterface $price;

    private ?ProductEntity $existingParentProduct = null;
    private ?ProductEntity $existingVariantProduct = null;

    public function __construct(
        EntityRepositoryInterface $productRepository,
        EntityRepositoryInterface $propertyGroupOptionRepository,
        SystemConfigService $systemConfigService,
        ProcessorInterface $clearanceSale,
        ProcessorInterface $price
    ) {
        $this->productRepository = $productRepository;
        $this->propertyGroupOptionRepository = $propertyGroupOptionRepository;
        $this->systemConfigService = $systemConfigService;
        $this->clearanceSale = $clearanceSale;
        $this->price = $price;
    }

    /**
     * @return array|array[]
     *
     * @throws Exception
     */
    public function provide(?array $productData = null): array
    {
        if (empty($productData['variants'])) {
            return [[], [], [], true];
        }

        $propertyGroupId = $this->systemConfigService->get('AmbimaxGlobalsysConnect.config.optionAxisId');

        if (!$propertyGroupId) {
            throw new Exception('Missing optionAxisId in config');
        }

        $children = [];
        $configuratorSettings = [];
        $enableParentProduct = false;

        $this->loadExistingParentProduct($productData);

        foreach ($productData['variants'] as $variant) {
            if ($variant['variant_catalogue'] != $this->getStandardCatalog()) {
                continue;
            }

            $this->loadExistingVariantProduct($variant);

            $child = $this->getBaseData($variant);
            $enableParentProduct |= $child['active'];
            $child['isCloseout'] = $this->clearanceSale->provide();
            $child['price'] = [
                $this->price->provide(array_merge(
                        $variant,
                        [
                            'products_spec_mwst' => $productData['products_spec_mwst'],
                        ]
                    )
                ),
            ];

            $shoeSizeEurope = (string) max(
                (float) (str_replace(',', '.', $variant['variant_size']) ?? 0),
                (float) (str_replace(',', '.', $variant['variant_size_converted']) ?? 0)
            );

            $optionId = $this->queryOptionId($propertyGroupId, $shoeSizeEurope);
            $configuratorSettingExists = false;

            if (!$optionId) {
                $optionId = Uuid::randomHex();
                $child['options'] = [
                    ['id' => $optionId, 'name' => $shoeSizeEurope, 'groupId' => $propertyGroupId],
                ];
            } else {
                if ($this->getExistingConfiguratorSettingByOptionId($optionId)) {
                    $configuratorSettingExists = true;
                }
                $child['options'] = [
                    ['id' => $optionId],
                ];
            }

            if (!$configuratorSettingExists) {
                $configuratorSettings[] = ['optionId' => $optionId];
            }
            $children[] = $child;
            unset($this->existingVariantProduct);
        }

        if (empty($children)) {
            return [[], [], [], true];
        }

        $configuratorGroupConfig = [
            // "Shoe size" axis
            $this->getOptionAxisArray($propertyGroupId),
        ];

        return [
            $configuratorGroupConfig,
            $children,
            $configuratorSettings,
            (bool) $enableParentProduct,
        ];
    }

    protected function loadExistingParentProduct(array $parentProduct): void
    {
        $criteria = (new Criteria())
            ->addAssociation('configuratorSettings')
            ->addFilter(new EqualsFilter('productNumber', $parentProduct['products_sku']));

        $this->existingParentProduct = $this->productRepository->search(
            $criteria,
            new Context(new SystemSource())
        )->first();
    }

    protected function loadExistingVariantProduct(array $variantProduct): void
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('productNumber', $variantProduct['variant_sku']));

        $this->existingVariantProduct = $this->productRepository->search(
            $criteria,
            new Context(new SystemSource())
        )->first();
    }

    protected function getBaseData(array $variantData): array
    {
        $stock = (int) $variantData['variant_quantity'];

        return [
            'id' => $this->existingVariantProduct ? $this->existingVariantProduct->getId() : Uuid::randomHex(),
            'productNumber' => $variantData['variant_sku'],
            'ean' => $variantData['variant_ean'],
            'stock' => $stock,
            'active' => (bool) $stock,
            'releaseDate' => $variantData['variant_new'],
        ];
    }

    protected function getExistingConfiguratorSettingByOptionId(string $optionId): ?string
    {
        if ($this->existingParentProduct && $this->existingParentProduct->getConfiguratorSettings()) {
            $productConfiguratorSetting = $this->existingParentProduct->getConfiguratorSettings()->getByOptionId($optionId);
            if ($productConfiguratorSetting) {
                return $productConfiguratorSetting->getId();
            }
        }

        return null;
    }

    protected function queryOptionId($propertyGroupId, $value): ?string
    {
        return $this->propertyGroupOptionRepository->searchIds(
            (new Criteria())
                ->addFilter(new EqualsFilter('groupId', $propertyGroupId))
                ->addFilter(new EqualsFilter('name', $value)),
            new Context(new SystemSource()))->firstId();
    }

    protected function getOptionAxisArray($propertyGroupId): array
    {
        return [
            'id' => $propertyGroupId,
            'representation' => 'box',
            'expressionForListings' => false,
        ];
    }

    protected function getStandardCatalog(): string
    {
        return 'Standard';
    }
}
