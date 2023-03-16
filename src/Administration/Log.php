<?php declare(strict_types=1);

namespace Ambimax\GlobalsysConnect\Administration;

use Ambimax\GlobalsysConnect\Export\Order\OrderCollection as OrderExportCollection;
use Ambimax\GlobalsysConnect\Import\Order\OrderCollection as OrderImportCollection;
use Ambimax\GlobalsysConnect\Import\Product\ProductCollection;
use Ambimax\GlobalsysConnect\Import\Stock\StockCollection;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class Log
{
    private Logger $logger;

    /**
     * Gets loaded in __construct
     * @var array Plugin config
     */
    protected array $_pluginConfig = [];


    public function __construct(AbstractProcessingHandler $doctrineSQLHandler, SystemConfigService $systemConfigService)
    {
        $this->logger = new Logger('ambimax_globalsysconnect');
        $this->logger->setHandlers([$doctrineSQLHandler]);

        $this->_pluginConfig = $systemConfigService->getDomain('AmbimaxGlobalsysConnect');
    }

    public function log(string $message, int $level, $context): void
    {
        $this->logger->addRecord(
            $level,
            $message,
            $context
        );
    }

    public function debugLog(string $message, string $pluginSection, $extraInformation = null): void
    {
        if (!$this->getConfig('debugLogEnabled')) {
            return;
        }

        switch ($pluginSection) {
            case OrderExportCollection::PLUGIN_SECTION:
                if (!$this->getConfig('exportOrdersDebugLogEnabled')) {
                    return;
                }
                break;
            case OrderImportCollection::PLUGIN_SECTION:
                if (!$this->getConfig('importOrdersDebugLogEnabled')) {
                    return;
                }
                break;
            case ProductCollection::PLUGIN_SECTION:
                if (!$this->getConfig('importProductsDebugLogEnabled')) {
                    return;
                }
                break;
            case StockCollection::PLUGIN_SECTION:
                if (!$this->getConfig('importStockDebugLogEnabled')) {
                    return;
                }
                break;
        }

        $messageTitle = $pluginSection . ': ' . $message;
        $this->log(
            "$messageTitle",
            Logger::DEBUG,
            [
                'message'           => $message,
                'extra information' => $extraInformation,
            ],
        );
    }

    /**
     * @return mixed|null
     */
    protected function getConfig(string $configKey)
    {
        return $this->_pluginConfig['AmbimaxGlobalsysConnect.config.' . $configKey] ?? null;
    }
}
