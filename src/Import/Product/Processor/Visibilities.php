<?php declare(strict_types=1);


namespace Ambimax\GlobalsysConnect\Import\Product\Processor;


use Ambimax\GlobalsysConnect\AmbimaxGlobalsysConnect;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class Visibilities extends AbstractProcessor implements ProcessorInterface
{

    private EntityRepositoryInterface $productRepository;
    private EntityRepositoryInterface $salesChannelRepository;
    private EntityRepositoryInterface $categoryRepository;

    public function __construct(
        EntityRepositoryInterface $productRepository,
        EntityRepositoryInterface $salesChannelRepository,
        EntityRepositoryInterface $categoryRepository
    )
    {
        $this->productRepository = $productRepository;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->categoryRepository = $categoryRepository;
    }

    public function provide(?array $productData = null): array
    {
        if (empty($productData['categories'])) {
            return [];
        }

        /** @var ProductCollection $productCollection */
        $productCollection = $this->productRepository->search(
            (new Criteria())
                ->addFilter(new EqualsFilter('productNumber', $productData['products_sku']))
                ->addAssociation('visibilities'),
            new Context(new SystemSource())
        );

        $visibilities = [];

        if ($productCollection->count() && $productCollection->first()->getVisibilities()->count()) {
            /** @var ProductVisibilityEntity $visibility */
            foreach ($productCollection->first()->getVisibilities() as $visibility) {
                $visibilities[] = [
                    'id'             => $visibility->getId(),
                    'salesChannelId' => $visibility->getSalesChannelId(),
                    'visibility'     => $visibility->getVisibility()
                ];
            }
        }

        $targetedSalesChannelIds = $this->calculateSalesChannelIds($productData['categories']);

        foreach ($targetedSalesChannelIds as $salesChannelId) {
            if ($this->isAlreadyAssignedToVisibility($salesChannelId, $visibilities)) {
                continue;
            }
            $visibilities[] = [
                'salesChannelId' => $salesChannelId,
                'visibility'     => 30
            ];
        }

        return $visibilities;
    }

    protected function getAllSalesChannels(): array
    {
        return $this->salesChannelRepository->search(
            new Criteria(),
            new Context(new SystemSource())
        )->getElements();
    }

    /**
     * @param string[] $erpCategoryIds
     * @return array
     */
    public function calculateSalesChannelIds(array $erpCategoryIds): array
    {
        $associatedCategoryIds = $this->collectPossibleNavigationCategoryIds($erpCategoryIds);
        $salesChannelElements = $this->getAllSalesChannels();
        $salesChannelIds = [];

        /** @var SalesChannelEntity $salesChannel */
        foreach ($salesChannelElements as $salesChannel) {
            if (in_array($salesChannel->getNavigationCategoryId(), $associatedCategoryIds)) {
                $salesChannelIds[] = $salesChannel->getId();
            }
        }

        return $salesChannelIds;
    }

    protected function isAlreadyAssignedToVisibility(string $salesChannelId, array $visibilities): bool
    {
        foreach ($visibilities as $visibility) {
            if ($visibility['salesChannelId'] == $salesChannelId) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array $erpCategoryIds
     * @return array
     */
    protected function collectPossibleNavigationCategoryIds(array $erpCategoryIds): array
    {
        // search for all needed categories
        $categories = $this->categoryRepository->search(
            (new Criteria())
                ->addFilter(
                    new EqualsAnyFilter(
                        'customFields.' . AmbimaxGlobalsysConnect::CUSTOM_FIELD_CATEGORY_ID,
                        $erpCategoryIds
                    )
                ),
            new Context(new SystemSource())
        );

        $associatedCategoryIds = [];
        // collect all category ids in a category path
        /** @var CategoryEntity $category */
        foreach ($categories->getElements() as $category) {
            $pathArray = explode('|', trim($category->getPath(), '|'));
            foreach ($pathArray as $categoryId) {
                if (!in_array($categoryId, $associatedCategoryIds)) {
                    $associatedCategoryIds[] = $categoryId;
                }
            }
        }
        return $associatedCategoryIds;
    }
}
