<?php declare(strict_types=1);


namespace Ambimax\GlobalsysConnect\Import\Order;

use Ambimax\GlobalsysConnect\Administration\Log;
use Ambimax\GlobalsysConnect\Api\Order\OrderGetter;
use Ambimax\GlobalsysConnect\Import\Order\Processor\OrderHandler;
use Exception;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class OrderCollection
{
    public const PLUGIN_SECTION = 'OrderImport';

    protected OrderGetter $orderGetter;
    protected SystemConfigService $systemConfigService;
    protected OrderHandler $orderHandler;
    protected Log $log;

    public function __construct(
        OrderGetter         $orderGet,
        SystemConfigService $systemConfigService,
        OrderHandler        $orderHandler,
        Log                 $log
    )
    {
        $this->orderGetter = $orderGet;
        $this->systemConfigService = $systemConfigService;
        $this->orderHandler = $orderHandler;
        $this->log = $log;
    }

    /**
     * @throws Exception
     */
    public function import(string $changedFrom = "-24hours"): void
    {
        $importOrdersEnabled = $this->systemConfigService->getBool('AmbimaxGlobalsysConnect.config.importOrdersEnabled');
        $sandboxEnabled = $this->systemConfigService->getBool('AmbimaxGlobalsysConnect.config.sandboxEnabled');
        $this->log->debugLog(($importOrdersEnabled == 1) ? 'Import enabled' : 'Import disabled', self::PLUGIN_SECTION);
        if (!$importOrdersEnabled) {
            return;
        }

        $this->orderGetter->setChangedFrom($changedFrom);
        $this->orderGetter->setSandboxMode($sandboxEnabled);
        $this->orderGetter->get();

        $response = $this->orderGetter->getResponse();

        if ($response->getHttpCode() !== 200) {
            $this->log->debugLog(
                'Wrong HTTP code',
                self::PLUGIN_SECTION,
                [
                    'http_code' => $response->getHttpCode(),
                    'content'   => $response->getContent()
                ]
            );
            return;
        }

        $responseContent = $response->getContent();
        $this->log->debugLog('Find orders to update', self::PLUGIN_SECTION,
            ['Qty orders' => count($responseContent['order'])]);

        foreach ($responseContent['order'] as $order) {
            $this->orderHandler->updateOrderDeliveryTrackingCodes($order);
            $this->orderHandler->updateOrderPaymentStatus($order);
            $this->orderHandler->updateOrderShipmentStatus($order);
        }
    }
}
