<?php declare(strict_types=1);


namespace Ambimax\GlobalsysConnect\Import\Order\Processor;


use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;

class TransactionHandler
{
    private Context $context;
    private StateMachineRegistry $statemachineregistry;

    public function __construct(StateMachineRegistry $stateMachineRegistry)
    {
        $this->context = new Context(new SystemSource());
        $this->statemachineregistry = $stateMachineRegistry;
    }

    public function setStatusToPaid(string $transactionId): void
    {
        $this->statemachineregistry->transition(new Transition(
            OrderTransactionDefinition::ENTITY_NAME,
            $transactionId,
            'paid',
            'stateId'
        ), $this->context);
    }

    public function setStatusToRefunded(string $transactionId): void
    {
        $this->statemachineregistry->transition(new Transition(
            OrderTransactionDefinition::ENTITY_NAME,
            $transactionId,
            'refund',
            'stateId'
        ), $this->context);
    }

    public function setStatusToCancelled(string $transactionId): void
    {
        $this->statemachineregistry->transition(new Transition(
            OrderTransactionDefinition::ENTITY_NAME,
            $transactionId,
            'cancel',
            'stateId'
        ), $this->context);
    }
}
