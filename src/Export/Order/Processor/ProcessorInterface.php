<?php declare(strict_types=1);

namespace Ambimax\GlobalsysConnect\Export\Order\Processor;

use Shopware\Core\Checkout\Order\OrderEntity;

interface ProcessorInterface
{
    public function provide(OrderEntity $order);
}
