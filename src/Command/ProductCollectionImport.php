<?php declare(strict_types=1);


namespace Ambimax\GlobalsysConnect\Command;


use Ambimax\GlobalsysConnect\Import\Product\ProductCollection;
use Exception;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ProductCollectionImport extends Command
{
    static public $defaultName = 'ambimax:import:product';

    private ProductCollection $productCollectionImport;

    public function __construct(ProductCollection $productCollectionImport)
    {
        parent::__construct(self::$defaultName);
        $this->productCollectionImport = $productCollectionImport;
    }


    protected function configure(): void
    {
        $this->setDescription("Import all products from Globalsys. Configuration required in administration.");
        $this->addOption(
            'categories',
            'c',
            InputOption::VALUE_OPTIONAL,
            "Specify categories from which you want to import, separated by '~'");
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            "Force import of products. They will be imported, even nothing significant has changed.");
        $this->addOption(
            'max',
            'm',
            InputOption::VALUE_OPTIONAL,
            "Maximum amount of products you want to import");
        $this->addOption(
            'search',
            's',
            InputOption::VALUE_OPTIONAL,
            "Import products that are the result of a search with that string");
        $this->addOption(
            'updatedAfter',
            'u',
            InputOption::VALUE_OPTIONAL,
            "Import products that have been updated in this elapsed time",
            '-60minutes'
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
        $io->title('Ambimax Globalsys - Import Products');

        $categories = $input->getOption('categories');
        $dateFrom = $input->getOption('updatedAfter');
        $force = $input->getOption('force');
        $max = $input->getOption('max');
        $search = $input->getOption('search');

        $queryParameters = [
            'categories'   => $categories,
            'updatedAfter' => $dateFrom,
            'searchString' => $search,
        ];

        $this->productCollectionImport->setForce($force);
        if ($max !== null) {
            $this->productCollectionImport->setImportMax((int)$max);
        }
        $this->productCollectionImport->setQueryParameters($queryParameters);

        $this->productCollectionImport->setVerbose(true);
        $this->productCollectionImport->import();
        return 0;
    }
}

