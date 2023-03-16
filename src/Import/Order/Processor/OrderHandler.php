<?php declare(strict_types=1);


namespace Ambimax\GlobalsysConnect\Import\Order\Processor;


use Ambimax\GlobalsysConnect\Administration\Log;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class OrderHandler
{
    public const PLUGIN_SECTION = 'OrderImport';

    private Context $context;
    private EntityRepositoryInterface $orderRepository;
    private TransactionHandler $transactionHelper;
    private DeliveryHandler $deliveryHandler;

    protected Log $log;

    public function __construct(
        EntityRepositoryInterface $orderRepository,
        TransactionHandler        $transactionHandler,
        DeliveryHandler           $deliveryHandler,
        Log                       $log
    )
    {
        $this->context = new Context(new SystemSource());
        $this->orderRepository = $orderRepository;
        $this->transactionHelper = $transactionHandler;
        $this->deliveryHandler = $deliveryHandler;
        $this->log = $log;
    }

    public function updateOrderPaymentStatus(array $orderData): void
    {
        $order = $this->loadOrderByOrderNumber($orderData['shoporder_order_nr']);
        if (!$order) {
            return;
        }

        if (!$this->orderIsPaid($orderData)) {
            return;
        }

        if (!$this->stateIsOpen($this->getCurrentTransactionState($order))) {
            return;
        }

        $this->log->debugLog('Order', self::PLUGIN_SECTION, $order);

        $transactionId = $this->getTransactionId($order);
        $this->log->debugLog('Update Payment status', self::PLUGIN_SECTION, [
            'transactionId' => $transactionId,
            'order'         => $orderData
        ]);
        $this->transactionHelper->setStatusToPaid($transactionId);
    }

    public function updateOrderShipmentStatus(array $orderData): void
    {
        $order = $this->loadOrderByOrderNumber($orderData['shoporder_order_nr']);
        if (!$order) {
            return;
        }

        if (!$this->orderIsPaid($orderData)) {
            return;
        }

        if (!$this->orderIsSent($orderData)) {
            return;
        }

        if (!$this->stateIsOpen($this->getCurrentDeliveryState($order))) {
            return;
        }

        $deliveryId = $this->getOrderDeliveryEntity($order)->getId();
        $this->log->debugLog('Update shipment status', self::PLUGIN_SECTION, [
            'deliveryId' => $deliveryId,
            'order'      => $orderData
        ]);
        $this->deliveryHandler->ship($deliveryId);
    }

    public function updateOrderDeliveryTrackingCodes(array $orderData)
    {
        if (!$orderData['shoporder_trackcode']) {
            return;
        }

        $order = $this->loadOrderByOrderNumber($orderData['shoporder_order_nr']);
        if (!$order) {
            return;
        }

        $orderDelivery = $this->getOrderDeliveryEntity($order);
        if (!$orderDelivery) {
            return;
        }

        if (in_array($orderData['shoporder_trackcode'], $orderDelivery->getTrackingCodes())) {
            return;
        }

        $trackingCodes = array_merge($orderDelivery->getTrackingCodes(), [$orderData['shoporder_trackcode']]);

        $this->log->debugLog('Add tracking code to order with order number: ' . $orderData['shoporder_order_nr'], self::PLUGIN_SECTION);

        $this->orderRepository->update(
            [
                [
                    'id'         => $order->getId(),
                    'deliveries' => [
                        [
                            'id'            => $orderDelivery->getId(),
                            'trackingCodes' => $trackingCodes
                        ]
                    ]
                ]
            ],
            $this->context
        );
    }

    public function loadOrderByOrderNumber(string $orderNumber): ?OrderEntity
    {
        $criteria = new Criteria();

        $criteria
            ->addFilter(new EqualsFilter("orderNumber", $orderNumber))
            ->addAssociation("deliveries")
            ->addAssociation("transactions");

        return $this->orderRepository
            ->search($criteria, $this->context)
            ->first();
    }

    private function getCurrentTransactionState(OrderEntity $orderEntity): string
    {
        return $orderEntity
            ->getTransactions()
            ->first()
            ->getStateMachineState()
            ->getTechnicalName();
    }

    private function getTransactionId(OrderEntity $orderEntity): string
    {
        return $orderEntity
            ->getTransactions()
            ->first()
            ->getId();
    }

    private function getCurrentDeliveryState(OrderEntity $orderEntity): string
    {
        $orderDelivery = $this->getOrderDeliveryEntity($orderEntity);

        if (!$orderDelivery) {
            return "";
        }

        return $orderDelivery
            ->getStateMachineState()
            ->getTechnicalName();
    }

    private function getOrderDeliveryEntity(OrderEntity $orderEntity): ?OrderDeliveryEntity
    {
        return $orderEntity
            ->getDeliveries()
            ->first();
    }

    private function stateIsOpen(string $state): bool
    {
        return $state == "open";
    }

    private function orderIsPaid(array $orderData): bool
    {
        return $orderData['shoporder_paid'] != null && $orderData['shoporder_paid'] != "0000-00-00 00:00:00";
    }

    private function orderIsSent(array $orderData): bool
    {
        return $orderData['shoporder_send_date'] != null && $orderData['shoporder_send_date'] != "0000-00-00 00:00:00";
    }
}
