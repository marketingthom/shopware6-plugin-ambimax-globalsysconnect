<?php declare(strict_types=1);


namespace Ambimax\GlobalsysConnect\Api\Order\OrderPost;


class Validation
{
    protected const REQUIRED_FIELD_CONFIG = [
        'notNull'  => [
            'shoporder_order_nr',
            'shoporder_order_date',
            'shoporder_payment_type',
            'shoporder_total_ordersum',
            'customer' => [
                'customer_email'
            ]
        ],
        'notEmpty' => [
            'orderarticles',
            'customer' => [
                'payment_address',
                'shipping_address'
            ]
        ],

    ];

    public function validate(array $data): bool
    {
        foreach (self::REQUIRED_FIELD_CONFIG as $method => $fields) {
            if (!$this->methodValidateData($method, $data, $fields)) {
                return false;
            }
        }

        return true;
    }

    protected function methodValidateData(string $method, array $data, $field): bool
    {
        if (is_array($field)) {
            foreach ($field as $fieldName => $subField) {
                if (is_array($subField)) {
                    if (!$this->methodValidateData($method, $data[$fieldName], $subField)) {
                        return false;
                    }
                } else {
                    if (!$this->$method($subField, $data)) {
                        return false;
                    }
                }
            }

            return true;
        }

        return $this->$method($field, $data);
    }

    protected function notNull(string $field, array $data): bool
    {
        return $data[$field] != null;
    }

    protected function notEmpty(string $field, array $data): bool
    {
        return !empty($data[$field]);
    }

}
