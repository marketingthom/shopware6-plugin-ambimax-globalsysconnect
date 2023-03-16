<?php declare(strict_types=1);

namespace Ambimax\GlobalsysConnect;

use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class AmbimaxGlobalsysConnect extends Plugin
{
    public const CUSTOM_FIELD_SET_NAME_PREFIX = 'ambimax_globalsysconnect_';

    public const CUSTOM_FIELD_SET_NAMES = [
        'category' => self::CUSTOM_FIELD_SET_NAME_PREFIX . 'category',
        'order'    => self::CUSTOM_FIELD_SET_NAME_PREFIX . 'order',
        'product'  => self::CUSTOM_FIELD_SET_NAME_PREFIX . 'product'
    ];

    public const CUSTOM_FIELD_CATEGORY_ID = self::CUSTOM_FIELD_SET_NAME_PREFIX . 'category_id';
    public const CUSTOM_FIELD_ORDER_SENT = self::CUSTOM_FIELD_SET_NAME_PREFIX . 'order_sent';
    public const CUSTOM_FIELD_PRODUCT_ID = self::CUSTOM_FIELD_SET_NAME_PREFIX . 'product_id';
    public const CUSTOM_FIELD_PRODUCT_SEASON = self::CUSTOM_FIELD_SET_NAME_PREFIX . 'product_season';

    /**
     * Use this to test the installation script:
     *
     * bin/console plugin:uninstall AmbimaxGlobalsysConnect && bin/console plugin:install -r -c AmbimaxGlobalsysConnect
     *
     * @param InstallContext $installContext
     */
    public function install(InstallContext $installContext): void
    {
        $this->createCustomFieldSets();
        $this->updateAllOrdersSetSent();
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        if ($uninstallContext->keepUserData()) {
            parent::uninstall($uninstallContext);
        }
    }

    /**
     * @return Context
     */
    protected function getDefaultContext(): Context
    {
        return new Context(new SystemSource());
    }

    protected function createCustomFieldSets(): void
    {
        $customFieldSets = $this->collectMissingCustomFieldSets();

        if (empty($customFieldSets)) {
            return;
        }

        /** @var EntityRepositoryInterface $customFieldSetRepository */
        $customFieldSetRepository = $this->container->get('custom_field_set.repository');
        $customFieldSetRepository->create(
            $customFieldSets,
            $this->getDefaultContext()
        );
    }

    protected function getCustomFieldSetCategory(): array
    {
        return [
            'name'         => self::CUSTOM_FIELD_SET_NAMES['category'],
            'config'       => [
                'description' => "Configure categories with custom fields for communication with the Globalsys API",
                'label'       => [
                    'en-GB' => "Ambimax Globalsys Connect Category",
                    'de-DE' => "Ambimax Globalsys Connect Kategorie"
                ],
                'translated'  => true,
            ],
            'relations'    => [
                [
                    'entityName' => 'category'
                ]
            ],
            'customFields' => [
                [
                    'name'   => self::CUSTOM_FIELD_CATEGORY_ID,
                    'type'   => CustomFieldTypes::TEXT,
                    'config' => [
                        'label'      => [
                            'en-GB' => "Category ID",
                            'de-DE' => "Kategorie ID"
                        ],
                        'helpText'   => [
                            'de-DE' => "ID zum Identifizieren der Kategorie bei Globalsys",
                            'en-GB' => "ID used in the ERP of Globalsys to identify a category",
                        ],
                        'translated' => true
                    ]
                ]
            ]
        ];
    }

    protected function getCustomFieldSetOrder(): array
    {
        return [
            'name'         => self::CUSTOM_FIELD_SET_NAMES['order'],
            'config'       => [
                'description' => "Configure orders with custom fields for communication with the Globalsys API",
                'label'       => [
                    'en-GB' => "Ambimax Globalsys Connect Order",
                    'de-DE' => "Ambimax Globalsys Connect Bestellung"
                ],
                'translated'  => true,
            ],
            'relations'    => [
                [
                    'entityName' => 'order'
                ]
            ],
            'customFields' => [
                [
                    'name'   => self::CUSTOM_FIELD_ORDER_SENT,
                    'type'   => CustomFieldTypes::BOOL,
                    'config' => [
                        'label'      => [
                            'en-GB' => "Has been sent",
                            'de-DE' => "Wurde versendet"
                        ],
                        'helpText'   => [
                            'en-GB' => "Flag that this order has been sent to the Globalsys API",
                            'de-DE' => "Indikator, dass diese Bestellung an das Globalsys API versendet wurde"
                        ],
                        'translated' => true
                    ]
                ]
            ]
        ];
    }

    protected function getCustomFieldSetProduct(): array
    {
        return [
            'name'         => self::CUSTOM_FIELD_SET_NAMES['product'],
            'config'       => [
                'description' => "Configure products with custom fields for communication with the Globalsys API",
                'label'       => [
                    'en-GB' => "Ambimax Globalsys Connect Product",
                    'de-DE' => "Ambimax Globalsys Connect Produkt"
                ],
                'translated'  => true,
            ],
            'relations'    => [
                [
                    'entityName' => 'product'
                ]
            ],
            'customFields' => [
                [
                    'name'   => self::CUSTOM_FIELD_PRODUCT_ID,
                    'type'   => CustomFieldTypes::TEXT,
                    'config' => [
                        'label'      => [
                            'en-GB' => "Product ID",
                            'de-DE' => "Produkt ID"
                        ],
                        'helpText'   => [
                            'en-GB' => "ID used in the ERP of Globalsys to identify a product",
                            'de-DE' => "ID zum Identifizieren des Produktes bei Globalsys"
                        ],
                        'translated' => true
                    ]
                ]
            ]
        ];
    }

    protected function updateAllOrdersSetSent(): void
    {
        /** @var EntityRepositoryInterface $orderRepository */
        $orderRepository = $this->container->get('order.repository');

        // fetch all orders
        $allOrderIds = $orderRepository->searchIds(
            (new Criteria([]))
                ->addFilter(new EqualsFilter('customFields.ambimax_globalsysconnect_order_sent', null)),
            $this->getDefaultContext()
        );

        $total = $allOrderIds->getTotal();
        if (!$total || $total < 50) {
            return;
        }

        $ids = $allOrderIds->getIds();

        // extend array with CUSTOM_FIELD_ORDER_SENT set to true
        $ids = array_map(
            function ($id, $true) {
                return [
                    'id'           => $id,
                    'customFields' => [self::CUSTOM_FIELD_ORDER_SENT => true]
                ];
            },
            $ids,
            [true]
        );


        $chunkSize = 512;

        while ($total > 0) {
            $spliced = array_splice($ids, 0, $chunkSize);
            $orderRepository->update(
                $spliced,
                $this->getDefaultContext()
            );
            $total = count($ids);
        }
    }

    protected function getExistingCustomFieldSetIds(): array
    {
        $customFieldSetIds = [];

        /** @var EntityRepositoryInterface $customFieldSetRepository */
        $customFieldSetRepository = $this->container->get('custom_field_set.repository');

        foreach (self::CUSTOM_FIELD_SET_NAMES as $key => $customFieldSetName) {
            $customFieldSetIds[$key] = null;

            $customFieldSetId = $customFieldSetRepository->searchIds(
                (new Criteria())->addFilter(new EqualsFilter('name', $customFieldSetName)),
                $this->getDefaultContext()
            )->firstId();

            if ($customFieldSetId) {
                $customFieldSetIds[$key] = $customFieldSetId;
            }
        }

        return $customFieldSetIds;
    }

    protected function collectMissingCustomFieldSets(): array
    {
        $customFieldSetIds = $this->getExistingCustomFieldSetIds();

        $missingCustomFieldSets = [];

        if (!$customFieldSetIds['category']) {
            $missingCustomFieldSets[] = $this->getCustomFieldSetCategory();
        }

        if (!$customFieldSetIds['order']) {
            $missingCustomFieldSets[] = $this->getCustomFieldSetOrder();
        }

        if (!$customFieldSetIds['product']) {
            $missingCustomFieldSets[] = $this->getCustomFieldSetProduct();
        }

        return $missingCustomFieldSets;
    }
}
