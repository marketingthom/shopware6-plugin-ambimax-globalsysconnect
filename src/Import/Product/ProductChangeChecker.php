<?php
declare(strict_types=1);


namespace Ambimax\GlobalsysConnect\Import\Product;

use Ambimax\GlobalsysConnect\Administration\Log;
use Ambimax\GlobalsysConnect\Api\Product\ProductCollectionGet;
use Ambimax\GlobalsysConnect\Import\Product\Processor\Categories;
use Ambimax\GlobalsysConnect\Import\Product\Processor\CustomFields;
use Ambimax\GlobalsysConnect\Import\Product\Processor\DefaultPrice;
use Ambimax\GlobalsysConnect\Import\Product\Processor\DeliveryTime;
use Ambimax\GlobalsysConnect\Import\Product\Processor\Manufacturer;
use Ambimax\GlobalsysConnect\Import\Product\Processor\Properties;
use Ambimax\GlobalsysConnect\Import\Product\Processor\VariantData;
use Exception;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ProductChangeChecker
{
    /*
     * Required services
     */
    protected ProductCollectionGet $productCollectionGet;
    protected EntityRepositoryInterface $productRepository;
    protected SystemConfigService $systemConfigService;

    /*
     * Optional processors that provide data
     */
    private Categories $categories;
    private CustomFields $customFields;
    private DefaultPrice $defaultPrice;
    private DeliveryTime $deliveryTime;
    private Manufacturer $manufacturer;
    private Properties $properties;
    private VariantData $variantData;

    private Log $log;

    public function __construct(
        ProductCollectionGet      $productCollectionGet,
        EntityRepositoryInterface $productRepository,
        SystemConfigService       $systemConfigService,
        Categories                $categories,
        CustomFields              $customFields,
        DefaultPrice              $defaultPrice,
        DeliveryTime              $deliveryTime,
        Manufacturer              $manufacturer,
        Properties                $properties,
        VariantData               $variantData,
        Log                       $log
    )
    {
        $this->productCollectionGet = $productCollectionGet;
        $this->productRepository = $productRepository;
        $this->systemConfigService = $systemConfigService;
        $this->categories = $categories;
        $this->customFields = $customFields;
        $this->defaultPrice = $defaultPrice;
        $this->deliveryTime = $deliveryTime;
        $this->manufacturer = $manufacturer;
        $this->properties = $properties;
        $this->variantData = $variantData;
        $this->log = $log;
    }

    /**
     * @throws Exception
     */
    public function detectChanges(array $productData): bool
    {
        $product = $this->productRepository->search(
            (new Criteria())
                ->addFilter(new EqualsFilter('productNumber', $productData['products_sku']))
                ->addAssociation("categories")
                ->addAssociation("children")
                ->addAssociation("configuratorSettings")
                ->addAssociation("properties"),
            new Context(new SystemSource())
        )->getEntities()->first();

        if (!$product) {
            return true;
        }

        /** @var ProductEntity $product */
        $serializedProduct = $product->jsonSerialize();

        if ($this->checkPrice($serializedProduct, $productData)) {
            return true;
        }
        if ($this->checkCustomFields($serializedProduct, $productData)) {
            return true;
        }
        if ($this->checkDeliveryTime($serializedProduct, $productData)) {
            return true;
        }
        if ($this->checkManufacturer($serializedProduct, $productData)) {
            return true;
        }
        if ($this->checkProperties($serializedProduct, $productData)) {
            return true;
        }
        if ($this->checkCategories($serializedProduct, $productData)) {
            return true;
        }
        if ($this->checkVariants($serializedProduct, $productData)) {
            return true;
        }

        return false;
    }

    private function checkPrice(array $existingProduct, array $newProduct): bool
    {
        $taxId = $this->systemConfigService->get('AmbimaxGlobalsysConnect.config.productsTax');

        $oldPrice = $existingProduct["price"]->first()->jsonSerialize();
        $newPrice = $this->defaultPrice->provide($newProduct, $taxId);

        if ($oldPrice['net'] != $newPrice['net'] ||
            $oldPrice['gross'] != $newPrice['gross']) {
            $this->log->debugLog(
                "detected changes in gross or net price. sku:{$newProduct['products_sku']}",
                ProductCollection::PLUGIN_SECTION
            );
            return true;
        }

        return false;
    }

    private function checkCustomFields(array $existingProduct, array $newProduct): bool
    {
        if ($existingProduct["customFields"] != $this->customFields->provide($newProduct)) {
            $this->log->debugLog(
                "detected changes in customFields. sku:{$newProduct['products_sku']}",
                ProductCollection::PLUGIN_SECTION
            );
            return true;
        }

        return false;
    }

    private function checkDeliveryTime(array $existingProduct, array $newProduct): bool
    {
        if ($existingProduct['deliveryTimeId'] != $this->deliveryTime->provide($newProduct)) {
            $this->log->debugLog(
                "detected changes in deliveryTimeId. sku:{$newProduct['products_sku']}",
                ProductCollection::PLUGIN_SECTION
            );
            return true;
        }

        return false;
    }

    private function checkManufacturer(array $existingProduct, array $newProduct): bool
    {
        if ($existingProduct['manufacturerId'] != $this->manufacturer->provide($newProduct)) {
            $this->log->debugLog(
                "detected changes in manufacturerId. sku:{$newProduct['products_sku']}",
                ProductCollection::PLUGIN_SECTION
            );
            return true;
        }

        return false;
    }

    private function checkProperties(array $existingProduct, array $newProduct): bool
    {
        // check for Properties change
        if (count($this->properties->provide($newProduct)) != count($existingProduct["properties"])) {
            $this->log->debugLog(
                "detected changes in Properties. sku:{$newProduct['products_sku']}",
                ProductCollection::PLUGIN_SECTION
            );
            return true;
        }

        foreach ($this->properties->provide($newProduct) as $newProperty) {
            $found = false;
            foreach ($existingProduct["properties"] as $existingPropertyId => $entity) {
                if (isset($newProperty["id"]) && $existingPropertyId == $newProperty["id"]) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $this->log->debugLog(
                    "detected changes in properties. sku:{$newProduct['products_sku']}",
                    ProductCollection::PLUGIN_SECTION
                );
                return true;
            }
        }

        return false;
    }

    private function checkCategories(array $existingProduct, array $newProduct): bool
    {
        // check for Category changes
        if (count($this->categories->provide($newProduct)) != count($existingProduct["categories"])) {
            $this->log->debugLog(
                "detected changes in categories. sku:{$newProduct['products_sku']}",
                ProductCollection::PLUGIN_SECTION
            );
            return true;
        }

        foreach ($this->categories->provide($newProduct) as $newCategory) {
            $found = false;
            foreach ($existingProduct["categories"] as $existingCategoryId => $entity) {
                if ($existingCategoryId == $newCategory["id"]) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $this->log->debugLog(
                    "detected changes in categories. sku:{$newProduct['products_sku']}",
                    ProductCollection::PLUGIN_SECTION
                );
                return true;
            }
        }

        return false;
    }

    /**
     * @throws Exception
     */
    protected function checkVariants(array $existingProduct, array $productData): bool
    {
        // only for variants
        if (empty($productData['variants'])) {
            return false;
        }

        /** @var array $variantData
         * [
         *  0 := 'configuratorGroupConfig', stays the same
         *  1 := 'children', may vary
         *  2 := 'configuratorSettings', may vary
         *  3 := 'enableParentProduct', may vary
         * ]
         */
        $variantData = $this->variantData->provide($productData);
        $debugMessage = "Detected changes in variant data. sku: {$productData['products_sku']}, change: %s";
        /** check whether the active status of the parent product would change */
        /** the difference from amount of variants is not checked here, because old variants are not deleted atm */
        if ($existingProduct['active'] != $variantData[3]) {
            $this->log->debugLog(sprintf($debugMessage, "parent's active"), ProductCollection::PLUGIN_SECTION);
            return true;
        }

        /** check whether a new option would be added */
        $oldOptionIds = $existingProduct['configuratorSettings']->getOptionIds();
        /** @var array $newOption ['optionId' => UUID] */
        foreach ($variantData[2] as $newOption) {
            if (!in_array($newOption['optionId'], $oldOptionIds)) {
                $this->log->debugLog(sprintf($debugMessage, 'optionIds'), ProductCollection::PLUGIN_SECTION);
                return true;
            }
        }

        /** @var \Shopware\Core\Content\Product\ProductCollection $children */
        $children = $existingProduct['children'];

        foreach ($variantData[1] as $newVariant) {
            /** not existing variant */
            if (!in_array($newVariant['id'], $children->getIds())) {
                $this->log->debugLog(sprintf($debugMessage, 'new variant'), ProductCollection::PLUGIN_SECTION);
                return true;
            }

            $oldVariant = $children->get($newVariant['id']);

            /** active status or stock would change */
            if ($oldVariant->getActive() != $newVariant['active'] ||
                $oldVariant->getStock() != $newVariant['stock']) {
                $this->log->debugLog(sprintf($debugMessage, 'active or stock'), ProductCollection::PLUGIN_SECTION);
                return true;
            }

            /** determine the price object of the variant */
            $oldPrice = $oldVariant->getPrice();
            if (!$oldPrice) {
                $oldPrice = $existingProduct["price"];
            }

            /** check whether anything from price would change */
            $oldVariantPrice = $oldPrice->first();
            $newVariantPrice = $newVariant['price'][0];
            if ($oldVariantPrice->getNet() != $newVariantPrice['net'] ||
                $oldVariantPrice->getGross() != $newVariantPrice['gross']) {
                $this->log->debugLog(sprintf($debugMessage, 'price'), ProductCollection::PLUGIN_SECTION);
                return true;
            }
            if ($oldVariantPrice->getListPrice() &&
                ($oldVariantPrice->getListPrice()->getNet() != $newVariantPrice['listPrice']['net'] ||
                    $oldVariantPrice->getListPrice()->getGross() != $newVariantPrice['listPrice']['gross'])) {
                $this->log->debugLog(sprintf($debugMessage, 'listPrice'), ProductCollection::PLUGIN_SECTION);
                return true;
            }
        }

        return false;
    }
}
