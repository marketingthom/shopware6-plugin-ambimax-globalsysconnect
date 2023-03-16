<?php declare(strict_types=1);


namespace Ambimax\GlobalsysConnect\Import\Product\Processor;


use Shopware\Core\System\SystemConfig\SystemConfigService;

class DeliveryTime extends AbstractProcessor implements ProcessorInterface
{
    private SystemConfigService $systemConfigService;

    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    public function provide(?array $productData = null): ?string
    {
        return $this->systemConfigService->get('AmbimaxGlobalsysConnect.config.deliveryTime');
    }
}
