<?php declare(strict_types=1);


namespace Ambimax\GlobalsysConnect\Import\Product\Processor;


use Ambimax\GlobalsysConnect\AmbimaxGlobalsysConnect;

class CustomFields extends AbstractProcessor implements ProcessorInterface
{
    const ATTRIBUTE_NAME_SEASON = 'SaisonBez';

    /**
     * @param array|null $productData
     * @return string[]
     */
    public function provide(?array $productData = null): array
    {
        return [
            AmbimaxGlobalsysConnect::CUSTOM_FIELD_PRODUCT_ID     => (string)$productData['products_id'],
            AmbimaxGlobalsysConnect::CUSTOM_FIELD_PRODUCT_SEASON => $this->getAttributeValue($productData, self::ATTRIBUTE_NAME_SEASON),
        ];
    }

    protected function getAttributeValue(array $productData, string $attributeName): string
    {
        foreach ($productData['attributes'] as $attribute) {
            if ($attribute['name'] == $attributeName) {
                return $attribute['value'];
            }
        }

        return '';
    }
}
