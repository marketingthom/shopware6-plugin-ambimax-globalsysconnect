<?php declare(strict_types=1);


namespace Ambimax\GlobalsysConnect\Command;


use Ambimax\GlobalsysConnect\Import\Order\OrderCollection;
use Exception;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class OrderStatusUpdateCommand extends Command
{
    static public $defaultName = 'ambimax:update:orders';

    private OrderCollection $orderCollectionImport;

    public function __construct(
        OrderCollection $orderCollectionImport
    )
    {
        parent::__construct(self::$defaultName);
        $this->orderCollectionImport = $orderCollectionImport;
    }

    protected function configure(): void
    {
        $this->setDescription("Update the orders of the last 24 hours.");
        $this->addOption(
            'changedFrom',
            '-f',
            InputOption::VALUE_OPTIONAL,
            "Specify the timeframe in which the orders are fetched. Default is '-24hours'"
        );
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);
        $io->title('Ambimax Globalsys - Update Order Status');

        $changedFrom = $input->getOption('changedFrom') != null ? $input->getOption('changedFrom') : "-24hours";

        $this->orderCollectionImport->import($changedFrom);

        return 0;
    }
}
