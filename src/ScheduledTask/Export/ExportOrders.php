<?php declare(strict_types=1);

namespace Ambimax\GlobalsysConnect\ScheduledTask\Export;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class ExportOrders extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'ambimax_globalsysconnect.export_orders';
    }

    // Reloading the interval:
    // $mysql: delete from scheduled_task where name='ambimax_globalsysconnect.export_orders';
    // $sw6: bin/console scheduled-task:register
    public static function getDefaultInterval(): int
    {
        return 600; // 10 minutes
    }
}
