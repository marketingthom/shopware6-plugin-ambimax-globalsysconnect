<?php declare(strict_types=1);


namespace Ambimax\GlobalsysConnect\Export\Order\Processor\OrderModel;


use Ambimax\GlobalsysConnect\Export\Order\Processor\ProcessorInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ShippingType implements ProcessorInterface
{
    private SystemConfigService $systemConfigService;

    public function __construct(
        SystemConfigService $systemConfigService
    )
    {
        $this->systemConfigService = $systemConfigService;
    }

    public function provide(OrderEntity $order): string
    {
        $dhl = $this->systemConfigService->get('AmbimaxGlobalsysConnect.config.exportOrderShippingMappingDHL');
        $dpd = $this->systemConfigService->get('AmbimaxGlobalsysConnect.config.exportOrderShippingMappingDPD');
        $pickUp = $this->systemConfigService->get('AmbimaxGlobalsysConnect.config.exportOrderShippingMappingPickUp');

        foreach ($order->getDeliveries()->getElements() as $deliveryEntity) {
            $shippingMethodId = $deliveryEntity->getShippingMethodId();
            if ($shippingMethodId == $dhl) {
                return 'DHL';
            }
            if ($shippingMethodId == $dpd) {
                return 'DPD';
            }
            if ($shippingMethodId == $pickUp) {
                return 'Abholung';
            }
        }
        return '';
    }
}
