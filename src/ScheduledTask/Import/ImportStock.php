<?php declare(strict_types=1);

namespace Ambimax\GlobalsysConnect\ScheduledTask\Import;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class ImportStock extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'ambimax_globalsysconnect.import_stock';
    }

    // Reloading the interval:
    // $mysql: delete from scheduled_task where name='ambimax_globalsysconnect.import_stock';
    // $sw6: bin/console scheduled-task:register
    public static function getDefaultInterval(): int
    {
        return 300; // 5 minutes
    }
}
