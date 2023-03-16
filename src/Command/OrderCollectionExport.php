<?php declare(strict_types=1);


namespace Ambimax\GlobalsysConnect\Command;


use Ambimax\GlobalsysConnect\Export\Order\OrderCollection;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class OrderCollectionExport extends Command
{
    static public $defaultName = 'ambimax:export:order';

    private OrderCollection $orderCollectionExport;

    public function __construct(OrderCollection $orderCollectionExport)
    {
        parent::__construct(self::$defaultName);
        $this->orderCollectionExport = $orderCollectionExport;
    }


    protected function configure(): void
    {
        $this->setDescription("Export all not sent and valid orders to Globalsys. Configuration required in administration");
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->orderCollectionExport->export();
        return 0;
    }
}

