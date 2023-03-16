<?php declare(strict_types=1);

namespace Ambimax\GlobalsysConnect\ScheduledTask\Import;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class ImportProducts extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'ambimax_globalsysconnect.import_products';
    }

    // Reloading the interval:
    // $mysql: delete from scheduled_task where name='ambimax_globalsysconnect.import_products';
    // $sw6: bin/console scheduled-task:register
    public static function getDefaultInterval(): int
    {
        return 1800; // 30 minutes
    }
}
