<?php declare(strict_types=1);


namespace Ambimax\GlobalsysConnect\Export\Order\Processor;


use Globalsys\EDCSDK\Model\PostOrderArticleModel;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class OrderArticleModel implements ProcessorInterface
{
    private EntityRepositoryInterface $productRepository;

    public function __construct(EntityRepositoryInterface $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function provide(OrderEntity $order): array
    {
        if (!$order->getLineItems()->count()) {
            return [];
        }

        $orderArticleModels = [];
        foreach ($order->getLineItems() as $lineItem) {
            // [BERG-148](https://ambimax.atlassian.net/browse/BERG-148) ignore skonto
            if ($this->isSkontoItem($lineItem)) {
                continue;
            }
            $orderArticleModels[] = $this->provideOrderArticleModel($lineItem);
        }

        return $orderArticleModels;
    }

    protected function provideOrderArticleModel(OrderLineItemEntity $lineItem): PostOrderArticleModel
    {
        $orderArticle = new PostOrderArticleModel();
        $orderArticle->setOrderarticlesSku($this->getProductNumber($lineItem));
        $orderArticle->setOrderarticlesName($lineItem->getLabel());
        $orderArticle->setOrderarticlesTotalprice($lineItem->getTotalPrice());
        $orderArticle->setOrderarticlesAmount($lineItem->getQuantity());
        $calculatedTaxes = $lineItem->getPrice()->getCalculatedTaxes();
        if($calculatedTaxes->count()) {
            $orderArticle->setOrderarticlesVat($calculatedTaxes->first()->getTaxRate());
        } else {
            $orderArticle->setOrderarticlesVat(0);
        }
        return $orderArticle;
    }

    protected function getProductNumber(OrderLineItemEntity $lineItem): ?string
    {
        // some $lineItem are not products - e. g. a discount
        if (!$lineItem->getProductId()) {
            return null;
        }

        return $lineItem->getPayload()['productNumber'] ?? $this->productRepository->search(
                new Criteria([$lineItem->getProductId()]),
                new Context(new SystemSource())
            )->first()->getProductNumber();
    }

    protected function isSkontoItem(OrderLineItemEntity $lineItem): bool
    {
        if (!$lineItem->getGood()) {
            $label = strtolower($lineItem->getLabel());
            return strpos($label, 'skonto') !== false;
        }

        return false;
    }
}
