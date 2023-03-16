<?php
declare(strict_types=1);


namespace Ambimax\GlobalsysConnect\Import\Product\Processor\VariantData;


use Ambimax\GlobalsysConnect\Import\Product\Processor\DefaultPrice;
use Shopware\Core\Defaults;

class Price extends DefaultPrice
{
    public function provide(?array $variantData = null, string $taxId = null): array
    {
        $taxRate = !$taxId ? (float)$variantData['products_spec_mwst'] : $this->getTaxRate($taxId);
        $price = (float)$variantData['variant_vk'];
        $priceUvp = (float)$variantData['variant_uvp'];

        $tax = $this->calculateTaxFromGross($price, $taxRate);
        $taxUvp = $this->calculateTaxFromGross($priceUvp, $taxRate);

        return
            [
                'currencyId' => Defaults::CURRENCY,
                'gross'      => $price,
                'net'        => $price - $tax,
                'linked'     => false,
                'listPrice'  => [
                    'gross'      => $priceUvp,
                    'net'        => $priceUvp - $taxUvp,
                    'currencyId' => Defaults::CURRENCY,
                    'linked'     => false,
                ],
                'extensions' => [],
            ];
    }
}
