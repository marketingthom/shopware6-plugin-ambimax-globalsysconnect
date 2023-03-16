<?php declare(strict_types=1);


namespace Ambimax\GlobalsysConnect\Import\Product\Processor\VariantData;


use Ambimax\GlobalsysConnect\Import\Product\Processor\AbstractProcessor;
use Ambimax\GlobalsysConnect\Import\Product\Processor\ProcessorInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ClearanceSale extends AbstractProcessor implements ProcessorInterface
{
    private SystemConfigService $systemConfigService;

    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    public function provide(?array $productData = null): bool
    {
        return $this->systemConfigService->get('AmbimaxGlobalsysConnect.config.importProductsSetClearanceSale');
    }
}
