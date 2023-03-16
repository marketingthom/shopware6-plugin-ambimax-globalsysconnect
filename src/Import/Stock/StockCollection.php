<?php declare(strict_types=1);

namespace Ambimax\GlobalsysConnect\Import\Stock;

use Ambimax\GlobalsysConnect\Administration\Log;
use Ambimax\GlobalsysConnect\Api\Stock\StockCollectionGet;
use Exception;
use Globalsys\EDCSDK\Response\Response;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Traversable;

class StockCollection
{
    /*
     * Required services
     */
    protected StockCollectionGet $stockCollectionGet;
    protected EntityRepositoryInterface $productRepository;
    protected SystemConfigService $systemConfigService;
    protected Log $log;

    public const PLUGIN_SECTION = 'StockImport';
    protected const DEFAULT_IMPORT_PAST_MINUTES = 100;
    protected const DEFAULT_BUNCH_SIZE = 300;

    protected array $_queryParameters = [];

    protected bool $_verbose = false;

    protected Response $_stockCollectionResponse;
    protected int $_collectedAmount = 0;
    protected ?int $_bunchSize = null;

    public function __construct(
        StockCollectionGet        $stockCollectionGet,
        EntityRepositoryInterface $productRepository,
        SystemConfigService       $systemConfigService,
        Log                       $log
    )
    {
        $this->stockCollectionGet = $stockCollectionGet;
        $this->productRepository = $productRepository;
        $this->systemConfigService = $systemConfigService;
        $this->log = $log;
    }

    /**
     * @throws Exception
     */
    public function import(): void
    {
        $importStockEnabled = $this->systemConfigService->get('AmbimaxGlobalsysConnect.config.importStockEnabled');
        $this->log->debugLog(($importStockEnabled == 1) ? 'Import enabled' : 'Import disabled', self::PLUGIN_SECTION);

        if (!$importStockEnabled) {
            return;
        }

        $queryParameters = $this->getQueryParameters();
        $this->setQueryParameters($queryParameters);
        $this->log->debugLog('Query parameters', self::PLUGIN_SECTION, $queryParameters);

        $startTime = microtime(true);

        $this->fetch();
        foreach ($this->collectProductBunch() as $bunch) {
            $this->log->debugLog('Start in Bundles', self::PLUGIN_SECTION);
            $this->verboseBunchSize(count($bunch));
            $this->saveProductBunch($bunch);
        }

        $endTime = microtime(true);

        $this->logSuccess(round($endTime - $startTime, 2));
    }

    protected function collectProductBunch(): Traversable
    {
        if ($this->_stockCollectionResponse->getHttpCode() == 200) {
            $stockCollection = $this->_stockCollectionResponse->getContent();

            $productCollection = [];
            foreach ($stockCollection['stocks'] as $stock) {
                $product = $this->provideProductData($stock);
                if ($product === null) {
                    continue;
                }
                $productCollection[] = $product;
                if (count($productCollection) >= $this->getBunchSize()) {
                    $this->_collectedAmount += $this->getBunchSize();
                    yield $productCollection;
                    $productCollection = [];
                }
            }

            $this->_collectedAmount += count($productCollection);
            yield $productCollection;
        }
    }

    protected function saveProductBunch(array $productBunch): ?EntityWrittenContainerEvent
    {
        if ($quantityProducts = count($productBunch)) {
            $this->log->debugLog(
                'Starting stock update',
                self::PLUGIN_SECTION,
                [
                    'Number of products:' => $quantityProducts,
                    'Productbunch:'       => $productBunch
                ]);
            return $this->productRepository->upsert($productBunch, new Context(new SystemSource()));
        }

        $this->log->debugLog('No products in bunch', self::PLUGIN_SECTION);
        return null;
    }

    protected function provideProductData(array $stockData): ?array
    {
        if (!(bool)$stockData['active_in_shop']) {
            $this->log->debugLog('Product should be disabled', self::PLUGIN_SECTION, $stockData['products_sku']);
            return null;
        }

        // Select the whole product to determine if it is a parent product or not - for 'active'
        /** @var ProductEntity $product */
        $product = $this->productRepository->search(
            (new Criteria())
                ->addFilter(new EqualsFilter('productNumber', $stockData['products_sku']))
                ->addFilter(
                    new NotFilter(
                        MultiFilter::CONNECTION_AND,
                        [new EqualsFilter('stock', $stockData['products_quantity'])])
                ),
            new Context(new SystemSource())
        )->first();

        if ($product === null) {
            $this->log->debugLog(
                'Product not in Database or stock has no changes',
                self::PLUGIN_SECTION,
                [
                    'SKU: ' => $stockData['products_sku']
                ]
            );
            return null;
        }

        $productId = $product->getId();
        $stock = ($stockData['products_quantity'] <= 0) ? 0 : $stockData['products_quantity'];

        return [
            'id'     => $productId,
            'stock'  => (int)$stock,
            'active' => (bool)($product->getChildCount() ?: $stock),
        ];
    }

    /**
     * @return void
     * @throws Exception
     */
    protected function fetch(): void
    {
        $this->log->debugLog('Start fetching products', self::PLUGIN_SECTION);
        $this->_stockCollectionResponse = $this->stockCollectionGet->fetch();

        // print info when using command
        $this->verbose($this->_stockCollectionResponse->getContent()['information']);
    }

    public function getQueryParameters(): array
    {
        $this->_queryParameters['from'] ??= sprintf("-%dminutes", $this->getPastMinutes());
        return $this->_queryParameters;
    }

    public function setQueryParameters(array $queryParameters): void
    {
        $this->_queryParameters = $queryParameters;
        $this->stockCollectionGet->setQueryParameters($queryParameters);
    }

    public function getBunchSize(): int
    {
        return $this->_bunchSize ?? self::DEFAULT_BUNCH_SIZE;
    }

    public function setBunchSize(?int $bunchSize)
    {
        $this->_bunchSize = $bunchSize;
    }

    protected function getPastMinutes(): ?int
    {
        return $this->systemConfigService->get('AmbimaxGlobalsysConnect.config.importStockPastMinutes') ?? self::DEFAULT_IMPORT_PAST_MINUTES;
    }

    public function verbose(?array $information)
    {
        if ($this->_verbose && $information) {
            echo "----" . PHP_EOL;
            echo "Available by query: " . $information['count'] . PHP_EOL;
            echo "Query: " .
                "from=" . ($this->_queryParameters['from'] ?: 'null');
            echo PHP_EOL . "----" . PHP_EOL;
        }
    }

    protected function verboseBunchSize(int $bunchSize)
    {
        if ($this->_verbose) {
            echo sprintf("Prepared %d products to update %s", $bunchSize, PHP_EOL);
            echo "----" . PHP_EOL;
        }
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
     * @param float $time
     */
    protected function logSuccess(float $time): void
    {
        $message = sprintf(
            "Import took %s seconds for updating stock of %d products",
            $time,
            $this->_collectedAmount
        );

        if ($this->_verbose) {
            print_r($message . PHP_EOL);
        }

        $this->log->debugLog(
            "Stock import successful",
            self::PLUGIN_SECTION,
            [
                'message' => $message,
            ],
        );
    }
}
