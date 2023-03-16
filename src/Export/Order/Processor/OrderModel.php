<?php declare(strict_types=1);


namespace Ambimax\GlobalsysConnect\Export\Order\Processor;

use Ambimax\GlobalsysConnect\Export\Order\Processor\OrderModel\PaymentType;
use Ambimax\GlobalsysConnect\Export\Order\Processor\OrderModel\ShippingType;
use Ambimax\GlobalsysConnect\Export\Order\Processor\OrderModel\Voucher;
use Globalsys\EDCSDK\Model\PostOrderModel;
use Shopware\Core\Checkout\Order\OrderEntity;

class OrderModel implements ProcessorInterface
{
    private PaymentType $paymentType;
    private ShippingType $shippingType;
    private Voucher $voucher;

    public function __construct(
        PaymentType  $paymentType,
        ShippingType $shippingType,
        Voucher      $voucher
    )
    {
        $this->paymentType = $paymentType;
        $this->shippingType = $shippingType;
        $this->voucher = $voucher;
    }

    public function provide(OrderEntity $order): PostOrderModel
    {
        return $this->provideOrderModel($order);
    }

    protected function provideOrderModel(OrderEntity $order): PostOrderModel
    {
        $orderModel = new PostOrderModel();

        $orderModel->setShoporderOrderNr($order->getOrderNumber());
        $orderModel->setShoporderOrderDate($this->getOrderDate($order));
        $orderModel->setShoporderPaymentType($this->paymentType->provide($order));
        $orderModel->setShoporderTotalOrdersum($order->getAmountTotal());
        $orderModel->setShoporderDelCost($order->getShippingTotal());
        $orderModel->setShoporderPaid($this->getPaidDate($order));
        $orderModel->setShoporderShippingType($this->shippingType->provide($order));
        $orderModel->setShoporderVoucher($this->voucher->provide($order));

        return $orderModel;
    }

    protected function getPaidDate(OrderEntity $order): string
    {
        $paidDate = '';

        if ($order->getTransactions()->first()->getStateMachineState()->getTechnicalName() == 'paid') {
            $paidDate = '@' . $order->getTransactions()->first()->getUpdatedAt()->getTimestamp();
        }

        return $paidDate;
    }

    protected function getOrderDate(OrderEntity $order): string
    {
        return '@' . $order->getOrderDateTime()->getTimestamp();
    }
}
