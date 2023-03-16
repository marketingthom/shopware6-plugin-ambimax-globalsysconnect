<?php declare(strict_types=1);


namespace Ambimax\GlobalsysConnect\Export\Order\Processor\OrderModel;


use Ambimax\GlobalsysConnect\Export\Order\Processor\ProcessorInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

class Voucher implements ProcessorInterface
{
    public function provide(OrderEntity $order): string
    {
        $voucherAmount = 0;

        $skonto = $this->getSkonto($order->getLineItems());
        if ($skonto) {
            $voucherAmount += abs($skonto);
        }

        return $this->eval($voucherAmount);
    }

    /**
     * Evaluates the argument to a string in needed format.
     * @param float $voucherAmount
     * @return string
     */
    protected function eval(float $voucherAmount): string
    {
        if ($voucherAmount) {
            return (string)abs(round($voucherAmount, 2));
        }

        return '';
    }

    protected function getSkonto(?OrderLineItemCollection $lineItems): float
    {
        foreach ($lineItems as $lineItem) {
            if ($this->isSkontoItem($lineItem)) {
                return $lineItem->getTotalPrice();
            }
        }
        return 0;
    }

    protected function isSkontoItem(OrderLineItemEntity $lineItem): bool
    {
        if (!$lineItem->getGood()) {
            $label = strtolower($lineItem->getLabel());
            return strpos($label, 'skonto') !== false;
        }
        return false;
    }
}
