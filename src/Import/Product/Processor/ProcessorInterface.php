<?php declare(strict_types=1);

namespace Ambimax\GlobalsysConnect\Import\Product\Processor;

interface ProcessorInterface
{
    public function clear(?string $productId);

    public function provide(?array $productData = null);

    public function validate($providedData): bool;
}
