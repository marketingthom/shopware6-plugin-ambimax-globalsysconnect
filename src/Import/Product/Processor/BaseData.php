<?php declare(strict_types=1);


namespace Ambimax\GlobalsysConnect\Import\Product\Processor;


class BaseData extends AbstractProcessor implements ProcessorInterface
{
    protected const KEY_MAP = [
        'name'               => ['type' => 'string', 'key' => 'products_name'],
        'description'        => ['type' => 'string', 'key' => 'products_description'],
        'productNumber'      => ['type' => 'string', 'key' => 'products_sku'],
        'ean'                => ['type' => 'string', 'key' => 'products_ean'],
        'manufacturerNumber' => ['type' => 'string', 'key' => 'products_sku_manufacturer'],
        'releaseDate'        => ['type' => 'string', 'key' => 'products_date_new'],
        'purchasePrice'      => ['type' => 'float', 'key' => 'products_ek'],
        'shippingFree'       => ['type' => 'bool', 'key' => 'products_free_shipping'],
        'weight'             => ['type' => 'float', 'key' => 'products_weight'],
        'width'              => ['type' => 'float', 'key' => 'products_width'],
        'height'             => ['type' => 'float', 'key' => 'products_height'],
        'length'             => ['type' => 'float', 'key' => 'products_length'],
        'stock'              => ['type' => 'int', 'key' => 'products_quantity'],
        'available_stock'    => ['type' => 'int', 'key' => 'products_quantity'],
    ];

    /**
     * @param array|null $productData
     * @return false[]
     */
    public function provide(?array $productData = null): array
    {
        $data = [];

        // Collect data that can be mapped
        foreach (self::KEY_MAP as $swKey => $entry) {
            $key = $entry['key'];

            switch ($entry['type']) {
                case 'int':
                    $data[$swKey] = (int)$productData[$key];
                    break;
                case 'float':
                    $data[$swKey] = (float)$productData[$key];
                    break;
                case 'bool':
                    $data[$swKey] = (bool)$productData[$key];
                    break;
                default:
                    $data[$swKey] = $productData[$key];
            }
        }

        $data['active'] = (bool)$data['stock'];
        return $data;
    }
}
