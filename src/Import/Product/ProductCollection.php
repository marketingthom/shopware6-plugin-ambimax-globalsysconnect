<?php

declare(strict_types=1);

namespace Ambimax\GlobalsysConnect\Import\Product;

use Ambimax\GlobalsysConnect\Administration\Log;
use Ambimax\GlobalsysConnect\Api\Product\ProductCollectionGet;
use Ambimax\GlobalsysConnect\Import\Product\Processor\BaseData;
use Ambimax\GlobalsysConnect\Import\Product\Processor\Categories;
use Ambimax\GlobalsysConnect\Import\Product\Processor\CustomFields;
use Ambimax\GlobalsysConnect\Import\Product\Processor\DefaultPrice;
use Ambimax\GlobalsysConnect\Import\Product\Processor\DeliveryTime;
use Ambimax\GlobalsysConnect\Import\Product\Processor\Manufacturer;
use Ambimax\GlobalsysConnect\Import\Product\Processor\Media;
use Ambimax\GlobalsysConnect\Import\Product\Processor\Properties;
use Ambimax\GlobalsysConnect\Import\Product\Processor\VariantData;
use Ambimax\GlobalsysConnect\Import\Product\Processor\Visibilities;
use Exception;
use Globalsys\EDCSDK\Response\Response;
use Monolog\Logger;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ProductCollection
{
    public const PLUGIN_SECTION = 'ProductImport';

    /*
     * Required services
     */
    protected ProductCollectionGet $productCollectionGet;
    protected EntityRepositoryInterface $productRepository;
    protected SystemConfigService $systemConfigService;

    /*
     * Optional processors that provide data
     */
    private BaseData $baseData;
    private Categories $categories;
    private CustomFields $customFields;
    private DefaultPrice $defaultPrice;
    private DeliveryTime $deliveryTime;
    private Manufacturer $manufacturer;
    private Media $media;
    private Properties $properties;
    private VariantData $variantData;
    private Visibilities $visibilities;

    private Context $defaultContext;
    private Log $log;
    private ProductChangeChecker $productChangeChecker;
    private PostCalculation $postCalculation;
    private bool $force = false;

    /*
     * Fields to specify the configuration of the query,
     * that defines the products to be imported.
     * Ensure setters in $this and $productCollectionGet.
     */
    protected array $_queryParameters = [];
    protected int $_currentPage = 1;
    protected string $_searchString = '';

    /*
     * Fields to limit the import
     */
    protected int $_importBunchSize = 100;
    protected int $_importMax;
    protected int $_importedCount = 0;

    /*
     * Fields for debugging
     */
    protected int $_skippedProductsByMissingInformation = 0;
    protected int $_skippedProductsByNoChanges = 0;
    protected bool $_verbose = false;

    /**
     * @var array The collection to be updated into Shopware
     */
    protected array $_collection = [];

    /**
     * Gets loaded in __construct.
     *
     * @var array Plugin config
     */
    protected array $_pluginConfig = [];

    public function __construct(
        ProductCollectionGet $productCollectionGet,
        EntityRepositoryInterface $productRepository,
        SystemConfigService $systemConfigService,
        BaseData $baseData,
        Categories $categories,
        CustomFields $customFields,
        DefaultPrice $defaultPrice,
        DeliveryTime $deliveryTime,
        Manufacturer $manufacturer,
        Media $media,
        Properties $properties,
        VariantData $variantData,
        Visibilities $visibilities,
        Log $log,
        ProductChangeChecker $productChangeChecker,
        PostCalculation $postCalculation
    ) {
        $this->productCollectionGet = $productCollectionGet;
        $this->productRepository = $productRepository;
        $this->systemConfigService = $systemConfigService;
        $this->baseData = $baseData;
        $this->categories = $categories;
        $this->customFields = $customFields;
        $this->defaultPrice = $defaultPrice;
        $this->deliveryTime = $deliveryTime;
        $this->manufacturer = $manufacturer;
        $this->media = $media;
        $this->properties = $properties;
        $this->variantData = $variantData;
        $this->visibilities = $visibilities;
        $this->log = $log;
        $this->productChangeChecker = $productChangeChecker;
        $this->postCalculation = $postCalculation;

        $this->defaultContext = new Context(new SystemSource());
        $this->loadConfig();
    }

    protected function loadConfig()
    {
        $this->_pluginConfig = $this->systemConfigService->getDomain('AmbimaxGlobalsysConnect');
    }

    /**
     * @return mixed|null
     */
    protected function getConfig(string $configKey)
    {
        return $this->_pluginConfig['AmbimaxGlobalsysConnect.config.'.$configKey] ?? null;
    }

    /**
     * @throws Exception
     */
    public function import(): void
    {
        if (!$this->getConfig('importProductsEnabled')) {
            $this->log->debugLog('Import disabled', self::PLUGIN_SECTION);

            return;
        }

        /** initialize $this->_queryParameters */
        $queryParameters = $this->getQueryParameters();
        $this->log->debugLog('QueryParameters', self::PLUGIN_SECTION, $queryParameters);
        $this->setQueryParameters($queryParameters);

        $this->log->debugLog('Start fetching Products', self::PLUGIN_SECTION);
        $response = $this->fetch();
        $body = $response->getContent();
        $httpCode = $response->getHttpCode();

        if (200 != $httpCode) {
            $this->log->debugLog('Bad status code', self::PLUGIN_SECTION,
                [
                    'HTTP Code:' => $httpCode,
                ]);

            return;
        }

        $this->log->debugLog('First fetch successful', self::PLUGIN_SECTION,
            [
                'Available products:' => $body['information']['count'],
                'HTTP Code:' => $httpCode,
            ]);

        $productPagesLeft = true;
        while ($productPagesLeft && 200 == $httpCode) {
            $this->verbose($body['information']);
            foreach ($body['product'] as $product) {
                if (!$this->validate($product)) {
                    continue;
                }
                if (!$this->force && !$this->productChangeChecker->detectChanges($product)) {
                    ++$this->_skippedProductsByNoChanges;
                    continue;
                }
                $this->addProductToCollection($product);

                if ($this->hasReachedImportMax()) {
                    $this->importCollection();
                    $this->logImportFinished($body['information']['count']);

                    return;
                }

                if ($this->hasReachedImportBunchSize()) {
                    $this->importCollection();
                    $this->resetCollection();
                }
            }

            if ($body['information']['currentpage'] < $body['information']['totalpages']) {
                $this->setCurrentPage($body['information']['currentpage'] + 1);
                $response = $this->fetch();
                $body = $response->getContent();
                $httpCode = $response->getHttpCode();
            } else {
                $productPagesLeft = false;
            }
        }

        $this->importCollection();

        $this->logImportFinished($body['information']['count']);
    }

    public function getQueryParameters(): array
    {
        $this->_queryParameters['catalog'] ??= $this->getStandardCatalog();
        $this->_queryParameters['currentPage'] ??= $this->getCurrentPage();
        $this->_queryParameters['pageSize'] ??= $this->getPageSize();
        $this->_queryParameters['searchString'] ??= $this->getSearchString();
        $this->_queryParameters['updatedAfter'] ??= $this->getImportProductsMaximumAgeConfig();

        return $this->_queryParameters;
    }

    public function setQueryParameters(array $queryParameters): void
    {
        $this->_queryParameters = $queryParameters;
        $this->productCollectionGet->setQueryParameters($queryParameters);
    }

    /**
     * @throws Exception
     */
    protected function fetch(): Response
    {
        return $this->productCollectionGet->fetch();
    }

    protected function validate(array $product): bool
    {
        if (!(int) $product['active_in_shop']
            || empty($product['pictures'])
            || empty($product['pictures']['picture'])
            || empty($product['attributes'])) {
            $this->log->debugLog('Missing product information', self::PLUGIN_SECTION,
                [
                    'Required information:' => 'active_in_shop, pictures, attributes',
                    'Product information:' => $product,
                ]
            );
            ++$this->_skippedProductsByMissingInformation;

            return false;
        }

        return true;
    }

    /**
     * @throws Exception
     */
    protected function addProductToCollection(array $product): bool
    {
        // First set the id
        $productId = $this->loadProductIdByProductNumber($product['products_sku']);
        $taxId = $this->getConfig('productsTax');

        if (!$productId) {
            // <=> product does not exist
            $productId = Uuid::randomHex();
            $this->log->debugLog('Product does not exist. ID generated', self::PLUGIN_SECTION,
                ['productId' => $productId]);
        } else {
            // <=> product does exist
            $this->log->debugLog('Product already exists.', self::PLUGIN_SECTION, ['productId' => $productId]);
            if ($this->getConfig('importProductsSkipExistingEnabled')) {
                $this->log->debugLog('Skip existing product is enabled. Product skipped', self::PLUGIN_SECTION);

                return false;
            }
        }

        $productEntityData = $this->baseData->provide($product);

        $productEntityData['id'] = $productId;

        $productEntityData['taxId'] = $taxId;

        $productEntityData['price'] = [
            $this->defaultPrice->provide($product, $taxId),
        ];

        $productEntityData['categories'] = $this->categories->provide($product);
        $productEntityData['isCloseout'] = false;
        $productEntityData['customFields'] = $this->customFields->provide($product);
        $productEntityData['deliveryTimeId'] = $this->deliveryTime->provide($product);
        $productEntityData['properties'] = $this->properties->provide($product);
        $productEntityData['visibilities'] = $this->visibilities->provide($product);

        $productEntityData['manufacturerId'] = $this->manufacturer->provide($product);

        if (!$this->manufacturer->validate($productEntityData['manufacturerId'])) {
            $this->log->log(
                'Manufacturer not found',
                Logger::WARNING,
                [
                    'message' => sprintf(
                        "Manufacturer not found! Manufacturer name: '%s', Product SKU: '%s'",
                        $this->manufacturer->getGlobalsysManufacturerName($product),
                        $productEntityData['productNumber']
                    ),
                ],
            );
            unset($productEntityData);

            return false;
        }

        $productEntityData['media'] = $this->media->provide($product);

        if (!empty($productEntityData['media'])) {
            // Use a generated productMediaId to have a thumbnail for the cover
            $productMediaId = Uuid::randomHex();
            $productEntityData['media'][0]['id'] = $productMediaId;
            $productEntityData['coverId'] = $productMediaId;
        }

        // area to clear old information of a product before saving the new one
        if (!empty($productEntityData['properties'])) {
            $this->categories->clear($productId);
            $this->properties->clear($productId);
        }

        // return if there are no variants for this product
        if (empty($product['variants'])) {
            $this->_collection[] = $this->postCalculation->calculate($productEntityData, $product);
            unset($productEntityData);

            return true;
        }

        list(
            $productEntityData['configuratorGroupConfig'],
            $productEntityData['children'],
            $productEntityData['configuratorSettings'],
            $productEntityData['active']
            ) = $this->variantData->provide($product);

        $this->_collection[] = $this->postCalculation->calculate($productEntityData, $product);
        unset($productEntityData);

        return true;
    }

    protected function importCollection(): void
    {
        $this->log->debugLog(
            'Product collection will be saved',
            self::PLUGIN_SECTION,
            ['count' => count($this->_collection)]
        );
        try {
            $this->productRepository->upsert($this->_collection, $this->defaultContext);
            $this->_importedCount += count($this->_collection);
        } catch (Exception $e) {
            $message = 'Error: Collection could not be saved';
            $this->log->log(
                sprintf('%s: %s', self::PLUGIN_SECTION, $message),
                Logger::ERROR,
                [
                    'message' => $e->getMessage(),
                ]
            );
            print_r(sprintf("\n%s.\n\nError message:\n%s\n", $message, $e->getMessage()));
        }
    }

    protected function resetCollection()
    {
        // reset collection for the next bunch
        $this->_collection = [];
    }

    /**
     * @param string $productNumber the 'productNumber' (SKU) used to looking up for the 'id'
     *
     * @return string|null the existing 'id' or '' if there is no 'id' to this 'productNumber'
     */
    protected function loadProductIdByProductNumber(string $productNumber): ?string
    {
        return $this->productRepository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('productNumber', $productNumber)),
            $this->defaultContext
        )->firstId();
    }

    protected function getImportProductsMaximumAgeConfig(): string
    {
        $productsAge = $this->getConfig('importProductsMaximumAge');

        return $productsAge ? "-{$productsAge}minutes" : '';
    }

    public function verbose(array $information)
    {
        if ($this->_verbose) {
            echo '----'.PHP_EOL;
            echo 'Maximum: '.($this->_importMax ?? 'all').PHP_EOL;
            echo 'Currently imported: '.$this->_importedCount.PHP_EOL;
            echo 'Available by query: '.$information['count'].PHP_EOL;
            echo 'Query: '.
                'importMax='.($this->_importMax ?? 'null').
                ' categories='.($this->_queryParameters['categories'] ?: 'null').
                ' dateFrom='.($this->_queryParameters['updatedAfter'] ?: 'null').
                ' searchString='.($this->_queryParameters['searchString'] ?: 'null').PHP_EOL;
            echo 'Page '.$information['currentpage'].' of '.$information['totalpages'].PHP_EOL;
        }
    }

    public function setForce(bool $force)
    {
        $this->force = $force;
    }

    public function setImportMax(int $max): void
    {
        $this->_importMax = $max;
    }

    protected function hasReachedImportBunchSize(): bool
    {
        return count($this->_collection) >= ($this->getConfig('importProductsBunchSize') ?? $this->_importBunchSize);
    }

    protected function hasReachedImportMax(): bool
    {
        return isset($this->_importMax) &&
            ($this->_importedCount + count($this->_collection) >= $this->_importMax);
    }

    protected function getStandardCatalog(): string
    {
        return 'Standard';
    }

    protected function getPageSize(): int
    {
        return 300;
    }

    protected function getCurrentPage(): int
    {
        return $this->_currentPage;
    }

    protected function setCurrentPage(int $page): void
    {
        $this->_currentPage = $page;
        $this->productCollectionGet->setCurrentPage($page);
    }

    protected function getSearchString(): string
    {
        return $this->_searchString;
    }

    protected function setSearchString(string $search): void
    {
        $this->_searchString = $search;
        $this->productCollectionGet->setSearchString($search);
    }

    public function setVerbose(bool $verbosity): void
    {
        $this->_verbose = $verbosity;
    }

    public function getVerbose(): bool
    {
        return $this->_verbose;
    }

    /**
     * @param $availableProductsCount
     */
    protected function logImportFinished($availableProductsCount): void
    {
        $this->log->debugLog('Import finished', self::PLUGIN_SECTION,
            [
                'Available products:' => $availableProductsCount,
                'Imported products:' => $this->_importedCount,
                'Skipped products (missing information):' => $this->_skippedProductsByMissingInformation,
                'Skipped products (no changes):' => $this->_skippedProductsByNoChanges,
            ]
        );
    }
}
