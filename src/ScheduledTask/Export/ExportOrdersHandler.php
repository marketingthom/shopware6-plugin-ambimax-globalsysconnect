<?php declare(strict_types=1);

namespace Ambimax\GlobalsysConnect\ScheduledTask\Export;

use Ambimax\GlobalsysConnect\Export\Order\OrderCollection;
use DateTimeImmutable;
use Exception;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ExportOrdersHandler extends ScheduledTaskHandler
{
    private SystemConfigService $systemConfigService;
    private OrderCollection $orderCollection;

    public function __construct(
        EntityRepositoryInterface $scheduledTaskRepository,
        SystemConfigService       $systemConfigService,
        OrderCollection           $orderCollection
    )
    {
        parent::__construct($scheduledTaskRepository);
        $this->systemConfigService = $systemConfigService;
        $this->orderCollection = $orderCollection;
    }

    public static function getHandledMessages(): iterable
    {
        return [ExportOrders::class];
    }

    /**
     * @throws Exception
     */
    public function run(): void
    {
        if (!$this->systemConfigService->get('AmbimaxGlobalsysConnect.config.exportOrdersEnabled')) {
            return;
        }

        $this->orderCollection->export();
    }

    /**
     * @param ScheduledTask $task
     * @param ScheduledTaskEntity $taskEntity
     *
     * Overrides the 'rescheduleTask' method in 'ScheduledTaskHandler' to modify the 'nextExecutionTime' entry.
     */
    protected function rescheduleTask(ScheduledTask $task, ScheduledTaskEntity $taskEntity): void
    {
        $now = new DateTimeImmutable();
        $this->scheduledTaskRepository->update([
            [
                'id'                => $task->getTaskId(),
                'runInterval'       => $this->getRunInterval(),
                'status'            => ScheduledTaskDefinition::STATUS_SCHEDULED,
                'lastExecutionTime' => $now,
                'nextExecutionTime' => $now->modify(sprintf('+%d seconds', $this->getRunInterval())),
            ],
        ], new Context(new SystemSource()));
    }

    protected function getRunInterval(): ?int
    {
        return $this->systemConfigService->get('AmbimaxGlobalsysConnect.config.exportOrdersInterval');
    }

}
