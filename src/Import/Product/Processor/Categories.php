<?php

declare(strict_types=1);

namespace Ambimax\GlobalsysConnect\Import\Product\Processor;

use Ambimax\GlobalsysConnect\AmbimaxGlobalsysConnect;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class Categories extends AbstractProcessor implements ProcessorInterface
{
    private EntityRepositoryInterface $categoryRepository;
    private EntityRepositoryInterface $productCategoryRepository;

    public function __construct(EntityRepositoryInterface $categoryRepository, EntityRepositoryInterface $productCategoryRepository)
    {
        $this->categoryRepository = $categoryRepository;
        $this->productCategoryRepository = $productCategoryRepository;
    }

    public function clear(?string $productId)
    {
        if (!$productId) {
            return;
        }

        $associationsToDelete = $this->getAssociationsToDelete($productId);

        if (empty($associationsToDelete)) {
            return;
        }

        $this->productCategoryRepository->delete($associationsToDelete, (new Context(new SystemSource())));
    }

    public function provide(?array $productData = null): array
    {
        if (empty($productData['categories'])) {
            return [];
        }
        $categories = [];

        foreach ($productData['categories'] as $erpCategoryId) {
            $categoryIds = $this->queryCategoryIds($erpCategoryId);
            foreach ($categoryIds as $categoryId) {
                $categories[] = ['id' => $categoryId];
            }
        }

        return $categories;
    }

    /**
     * @param $erpCategoryId
     *
     * @return string[]|null
     */
    public function queryCategoryIds($erpCategoryId): ?array
    {
        return $this->categoryRepository->searchIds(
            (new Criteria())->addFilter(
                new EqualsFilter(
                    'customFields.'.AmbimaxGlobalsysConnect::CUSTOM_FIELD_CATEGORY_ID,
                    $erpCategoryId
                )
            ),
            (new Context(new SystemSource()))
        )->getIds();
    }

    protected function getAssociationsToDelete(string $productId): array
    {
        $associatedCategoryIds = $this->categoryRepository->searchIds(
            (new Criteria())
                ->addAssociation('products')
                ->addFilter(
                    new EqualsFilter(
                        'products.id',
                        $productId
                    )
                ),
            (new Context(new SystemSource()))
        )->getIds();

        $associationsToDelete = [];

        foreach ($associatedCategoryIds as $categoryId) {
            $associationsToDelete[] = [
                'productId' => $productId,
                'categoryId' => $categoryId,
            ];
        }

        return $associationsToDelete;
    }
}
