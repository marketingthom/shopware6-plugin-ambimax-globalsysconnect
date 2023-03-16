<?php declare(strict_types=1);


namespace Ambimax\GlobalsysConnect\Export\Order\Processor\OrderModel;


use Ambimax\GlobalsysConnect\Export\Order\Processor\ProcessorInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class PaymentType implements ProcessorInterface
{
    private EntityRepositoryInterface $paymentMethodRepository;

    public function __construct(
        EntityRepositoryInterface $paymentMethodRepository
    )
    {
        $this->paymentMethodRepository = $paymentMethodRepository;
    }

    public function provide(OrderEntity $order): string
    {
        $paymentMethodIds = $order->getTransactions()->getPaymentMethodIds();
        return $this->queryPaymentMethodName($paymentMethodIds[array_key_first($paymentMethodIds)]);
    }

    protected function queryPaymentMethodName(string $paymentMethodId): ?string
    {
        /** @var PaymentMethodEntity $paymentMethodEntity */
        $paymentMethodEntity = $this->paymentMethodRepository->search(
            new Criteria([$paymentMethodId]),
            new Context(new SystemSource())
        )->first();

        return $paymentMethodEntity->getName();
    }

}
