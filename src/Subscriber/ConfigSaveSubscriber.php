<?php

namespace Ambimax\GlobalsysConnect\Subscriber;

use Ambimax\GlobalsysConnect\Administration\Log;
use Ambimax\GlobalsysConnect\ScheduledTask\Export\ExportOrders;
use Ambimax\GlobalsysConnect\ScheduledTask\Import\ImportOrders;
use Ambimax\GlobalsysConnect\ScheduledTask\Import\ImportProducts;
use Ambimax\GlobalsysConnect\ScheduledTask\Import\ImportStock;
use DateTimeImmutable;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;

class ConfigSaveSubscriber implements EventSubscriberInterface
{
    private $logger;

    private SystemConfigService $systemConfigService;

    private $scheduledTaskRepository;

    public function __construct(
        SystemConfigService $systemConfigService,
        EntityRepository    $scheduledTaskRepository,
        Log     $logger
    )
    {
        $this->systemConfigService = $systemConfigService;
        $this->scheduledTaskRepository = $scheduledTaskRepository;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SystemConfigChangedEvent::class => 'onConfigChanged',
        ];
    }

    public function onConfigChanged(SystemConfigChangedEvent $event): void
    {
        $taskName = null;

        switch ($event->getKey()) {
            case 'AmbimaxGlobalsysConnect.config.importOrdersInterval':
                $taskName = ImportOrders::getTaskName();
                break;
            case 'AmbimaxGlobalsysConnect.config.exportOrdersInterval':
                $taskName = ExportOrders::getTaskName();
                break;
            case 'AmbimaxGlobalsysConnect.config.importProductsInterval':
                $taskName = ImportProducts::getTaskName();
                break;
            case 'AmbimaxGlobalsysConnect.config.importStockInterval':
                $taskName = ImportStock::getTaskName();
                break;
        }

        if ($taskName) {
            $this->updateTask($taskName, $event->getValue());
        }
    }

    private function updateTask($taskName, $interval): void
    {
        $context = new Context(new SystemSource());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $taskName));

        $taskId = $this->scheduledTaskRepository->searchIds($criteria, $context)->firstId();

        $now = new DateTimeImmutable();
        $this->scheduledTaskRepository->update([
            [
                'id' => $taskId,
                'runInterval' => intval($interval),
                'status' => ScheduledTaskDefinition::STATUS_SCHEDULED,
                'lastExecutionTime' => $now,
                'nextExecutionTime' => $now->modify(sprintf('+%d seconds', $interval)),
            ],
        ], $context);
    }
}