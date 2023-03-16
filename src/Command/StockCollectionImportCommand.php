<?php declare(strict_types=1);


namespace Ambimax\GlobalsysConnect\Command;


use Ambimax\GlobalsysConnect\Import\Stock\StockCollection;
use Exception;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class StockCollectionImportCommand extends Command
{
    static public $defaultName = 'ambimax:import:stock';

    private StockCollection $stockCollectionImport;

    public function __construct(StockCollection $productCollectionImport)
    {
        parent::__construct(self::$defaultName);
        $this->stockCollectionImport = $productCollectionImport;
    }


    protected function configure(): void
    {
        $this->setDescription("Import all stocks from Globalsys that have changed in the last n minutes.");
        $this->addOption(
            'pastMinutes',
            'p',
            InputOption::VALUE_OPTIONAL,
            "Import stocks that have been updated in this past minutes. 0 for all.",
        );
        $this->addOption(
            'bunchSize',
            'b',
            InputOption::VALUE_OPTIONAL,
            "Set the bunch size for updating products in DB.",
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);
        $io->title('Ambimax Globalsys - Import Stocks');

        $pastMinutes = $input->getOption('pastMinutes');
        $bunchSize = $input->getOption('bunchSize');

        if (!empty($bunchSize)) {
            $this->stockCollectionImport->setBunchSize((int)$bunchSize);
        }

        if ($pastMinutes == null) {
            $this->stockCollectionImport->setVerbose(true);
            $this->stockCollectionImport->import();
            return 0;
        }

        $from = '';
        if ($pastMinutes != '0') {
            $from = sprintf("-%sminutes", $pastMinutes);
        }

        $this->stockCollectionImport->setQueryParameters(['from' => $from]);

        $this->stockCollectionImport->setVerbose(true);
        $this->stockCollectionImport->import();
        return 0;
    }
}

