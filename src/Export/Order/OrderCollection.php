<?php declare(strict_types=1);

namespace Ambimax\GlobalsysConnect\Export\Order;

use Ambimax\GlobalsysConnect\Administration\Log;
use Ambimax\GlobalsysConnect\AmbimaxGlobalsysConnect;
use Ambimax\GlobalsysConnect\Api\Order\OrderPost;
use Ambimax\GlobalsysConnect\Export\Order\Processor\OrderArticleModel;
use Ambimax\GlobalsysConnect\Export\Order\Processor\OrderCustomerModel;
use Ambimax\GlobalsysConnect\Export\Order\Processor\OrderModel;
use Exception;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class OrderCollection
{
    public const PLUGIN_SECTION = 'OrderExport';

    protected OrderPost $orderPost;
    protected EntityRepositoryInterface $orderRepository;
    protected SystemConfigService $systemConfigService;
    protected Log $log;

    private OrderArticleModel $orderArticleModel;
    private OrderCustomerModel $orderCustomerModel;
    private OrderModel $orderModel;
    private string $currentOrderId;

    public function __construct(
        OrderPost                 $orderPost,
        EntityRepositoryInterface $orderRepository,
        SystemConfigService       $systemConfigService,
        OrderArticleModel         $orderArticleModel,
        OrderCustomerModel        $orderCustomerModel,
        OrderModel                $orderModel,
        Log                       $log
    )
    {
        $this->orderPost = $orderPost;
        $this->orderRepository = $orderRepository;
        $this->systemConfigService = $systemConfigService;
        $this->orderArticleModel = $orderArticleModel;
        $this->orderCustomerModel = $orderCustomerModel;
        $this->orderModel = $orderModel;
        $this->log = $log;
    }

    /**
     * @throws Exception
     */
    public function export(): void
    {
        $exportOrdersEnabled = (bool)$this->systemConfigService->get('AmbimaxGlobalsysConnect.config.exportOrdersEnabled');
        $sandboxEnabled = (bool)$this->systemConfigService->get('AmbimaxGlobalsysConnect.config.sandboxEnabled');
        $this->log->debugLog(($exportOrdersEnabled == 1) ? 'Export enabled' : 'Export disabled', self::PLUGIN_SECTION);

        if (!$exportOrdersEnabled) {
            return;
        }

        $this->orderPost->setSandboxMode($sandboxEnabled);

        $orderCollection = $this->getNotSentOrderCollection($sandboxEnabled);

        $this->log->debugLog('Currently ' . count($orderCollection) . ' orders not send', self::PLUGIN_SECTION);

        $singleExportEnabled = $this->systemConfigService->get('AmbimaxGlobalsysConnect.config.exportOrdersSingle');
        $this->log->debugLog($singleExportEnabled ? 'Single export enabled' : 'Single export disabled',
            self::PLUGIN_SECTION);


        /** @var OrderEntity $order */
        foreach ($orderCollection as $order) {
            $this->setCurrentOrderId($order->getOrderNumber());
            if (!$this->decideWhetherToExport($order)) {
                $this->log->debugLog('Order not exported. Order number: ' . $this->getCurrentOrderId(),
                    self::PLUGIN_SECTION,
                    $order);
                continue;
            }

            $success = $this->exportOrder($order);
            if ($success) {
                $this->log->debugLog('Order exported. Order number: ' . $this->getCurrentOrderId(), self::PLUGIN_SECTION);
                if ($singleExportEnabled) {
                    $this->log->debugLog('Stop exporting because single export is enabled', self::PLUGIN_SECTION);
                    break;
                }
            }
        }
    }

    protected function getNotSentOrderCollection($sandboxMode = false): EntitySearchResult
    {
        $this->log->debugLog('Start getNotSentOrderCollection', self::PLUGIN_SECTION);

        $criteria = (new Criteria())
            ->addFilter(
                new OrFilter([
                    new EqualsFilter('customFields.' . AmbimaxGlobalsysConnect::CUSTOM_FIELD_ORDER_SENT, false),
                    new EqualsFilter('customFields.' . AmbimaxGlobalsysConnect::CUSTOM_FIELD_ORDER_SENT, null)
                ]),
            )
            ->addAssociation('addresses.country')
            ->addAssociation('addresses.salutation')
            ->addAssociation('deliveries')
            ->addAssociation('lineItems')
            ->addAssociation('transactions');

        if ($sandboxMode) {
            $emails = $this->systemConfigService->get('AmbimaxGlobalsysConnect.config.sandboxEmails');
            $emails = str_replace(' ', '', $emails);
            $emails = explode(',', $emails);

            $criteria
                ->addAssociation('orderCustomer')
                ->addFilter(
                    new EqualsAnyFilter('orderCustomer.email', $emails)
                );
        }

        return $this->orderRepository->search(
            $criteria,
            new Context(new SystemSource())
        );
    }

    /**
     * @param OrderEntity $order
     * @return bool true on success, false on failure
     * @throws Exception
     */
    protected function exportOrder(OrderEntity $order): bool
    {
        $orderNumber = $order->getOrderNumber();
        $this->log->debugLog('Start exporting order. Order number: ' . $orderNumber, self::PLUGIN_SECTION);
        $orderArticleModels = $this->orderArticleModel->provide($order);
        $orderCustomer = $this->orderCustomerModel->provide($order);
        $orderModel = $this->orderModel->provide($order);

        $this->orderPost->setPostOrderArticleModels($orderArticleModels);
        $this->orderPost->setPostOrderCustomerModel($orderCustomer);
        $this->orderPost->setPostOrderModel($orderModel);
        if (!$this->orderPost->validate()) {
            $this->log->debugLog('Order exporting failed. Order number ' . $orderNumber, self::PLUGIN_SECTION,
                [
                    'Order post data' => $this->orderPost->getData()
                ]);
            return false;
        }
        $this->orderPost->post();
        $response = $this->orderPost->getResponse();
        if ($response->getHttpCode() != 200) {
            $this->log->debugLog('Order exporting failed. Order number ' . $orderNumber, self::PLUGIN_SECTION,
                [
                    'http_code' => $response->getHttpCode(),
                    'message'   => $response->getContent(),
                ]);
            return false;
        }
        $this->orderRepository->update(
            [
                ['id' => $order->getId(), 'customFields' => [AmbimaxGlobalsysConnect::CUSTOM_FIELD_ORDER_SENT => true]]
            ],
            new Context(new SystemSource()));
        return true;
    }

    protected function decideWhetherToExport(OrderEntity $order): bool
    {
        $transactions = $order->getTransactions();

        if (!$transactions->count()) {
            $this->log->debugLog('Order has no transactions', self::PLUGIN_SECTION);
            return false;
        }

        if ($this->transactionsHavePaymentMethod($transactions)) {
            return true;
        }

        if ($this->transactionsHavePaymentStatus($transactions)) {
            return true;
        }

        $this->log->debugLog('Order did not have necessary payment status or method :' . $this->getCurrentOrderId(),
            self::PLUGIN_SECTION,
            $order);
        return false;
    }

    protected function transactionsHavePaymentMethod(OrderTransactionCollection $transactions): bool
    {
        $exportedPaymentMethods = $this->systemConfigService->get('AmbimaxGlobalsysConnect.config.exportOrdersPaymentMethods');

        foreach ($transactions->getPaymentMethodIds() as $paymentMethodId) {
            if (in_array($paymentMethodId, $exportedPaymentMethods)) {
                return true;
            }
        }

        $this->log->debugLog('Order did not have necessary payment method',
            self::PLUGIN_SECTION,
            [
                'Allowed payment method IDs' => $exportedPaymentMethods,
                'Order payment method'       => $transactions->getPaymentMethodIds()
            ]);
        return false;
    }

    protected function transactionsHavePaymentStatus(OrderTransactionCollection $transactions): bool
    {
        $invalidTransactionStates = [];
        foreach ($transactions as $transaction) {
            $transactionState = $transaction->getStateMachineState()->getTechnicalName();
            if ($transactionState == 'paid') {
                return true;
            }
            $invalidTransactionStates[] = $transactionState;
        }

        $this->log->debugLog(
            'Order payment state', self::PLUGIN_SECTION,
            ['Invalid payment states' => $invalidTransactionStates]
        );
        return false;
    }

    private function setCurrentOrderId(string $orderId): void
    {
        $this->currentOrderId = $orderId;
    }

    private function getCurrentOrderId(): string
    {
        return $this->currentOrderId;
    }
}

