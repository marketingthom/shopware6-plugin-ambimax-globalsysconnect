<?php declare(strict_types=1);


namespace Ambimax\GlobalsysConnect\Import\Order\Processor;


use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;

class DeliveryHandler
{
    private Context $context;
    private StateMachineRegistry $statemachineregistry;

    public function __construct(StateMachineRegistry $stateMachineRegistry)
    {
        $this->context = new Context(new SystemSource());
        $this->statemachineregistry = $stateMachineRegistry;
    }

    public function ship(string $id): void
    {
        $this->statemachineregistry->transition(new Transition(
            OrderDeliveryDefinition::ENTITY_NAME,
            $id,
            'ship',
            'stateId'
        ), $this->context);
    }

}
