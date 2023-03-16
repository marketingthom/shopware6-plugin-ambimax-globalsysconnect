<?php declare(strict_types=1);


namespace Ambimax\GlobalsysConnect\Import\Product\Processor;


use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;

class Manufacturer extends AbstractProcessor implements ProcessorInterface
{

    private EntityRepositoryInterface $productManufacturerRepository;

    public function __construct(EntityRepositoryInterface $productManufacturerRepository)
    {
        $this->productManufacturerRepository = $productManufacturerRepository;
    }

    public function provide(?array $productData = null): string
    {
        $manufacturer = $this->provideProductManufacturerEntity($productData['products_brand_name']);
        return $manufacturer == null ? '' : $manufacturer->getId();
    }

    public function validate($providedData): bool
    {
        return (bool)$providedData;
    }

    public function getGlobalsysManufacturerName(array $productData): string
    {
        return $productData['products_brand_name'];
    }

    protected function provideProductManufacturerEntity(string $brandName): ?ProductManufacturerEntity
    {
        return $this->productManufacturerRepository->search(
            (new Criteria())->addFilter(new EqualsAnyFilter('name', [$brandName, mb_strtolower($brandName, 'UTF-8')])),
            new Context(new SystemSource()))->first();
    }
}
