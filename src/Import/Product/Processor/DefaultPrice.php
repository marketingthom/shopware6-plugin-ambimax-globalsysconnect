<?php declare(strict_types=1);


namespace Ambimax\GlobalsysConnect\Import\Product\Processor;


use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Tax\TaxEntity;

class DefaultPrice extends AbstractProcessor implements ProcessorInterface
{
    private EntityRepositoryInterface $taxRepository;

    public function __construct(EntityRepositoryInterface $taxRepository)
    {
        $this->taxRepository = $taxRepository;
    }

    /**
     * @param array|null $productData
     * @param string|null $taxId
     * @return array
     */
    public function provide(?array $productData = null, string $taxId = null): array
    {
        //Collect tax rate and price
        $taxRate = !$taxId ? (float)$productData['products_spec_mwst'] : $this->getTaxRate($taxId);
        $price = (float)$productData['products_vk'];

        $tax = $this->calculateTaxFromGross($price, $taxRate);

        return
            [
                'currencyId' => Defaults::CURRENCY,
                'gross'      => $price,
                'net'        => $price - $tax,
                'linked'     => false,
                'listPrice'  => null,
                'extensions' => [],
            ];
    }

    /**
     * @param float $grossPrice
     * @param float $taxRate Tax rate in percentage. e. g. 16 or 5.
     * @return float
     */
    protected function calculateTaxFromGross(float $grossPrice, float $taxRate): float
    {
        $taxCalculator = new TaxCalculator();
        $calculatedTax = $taxCalculator->calculateGrossTaxes($grossPrice, new TaxRuleCollection([new TaxRule($taxRate)]));
        $calculatedTax->round(new CashRounding(), new CashRoundingConfig(2, 0.01, true));

        return $calculatedTax->getAmount();
    }

    protected function getTaxRate(string $taxId): float
    {
        $taxCollection = $this->taxRepository->search(new Criteria([$taxId]), new Context(new SystemSource()));
        /** @var TaxEntity $taxEntity */
        $taxEntity = $taxCollection->getElements()[$taxId];

        return $taxEntity->getTaxRate();
    }
}
