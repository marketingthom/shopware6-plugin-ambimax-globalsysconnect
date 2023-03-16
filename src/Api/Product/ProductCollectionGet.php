<?php declare(strict_types=1);


namespace Ambimax\GlobalsysConnect\Api\Product;


use Ambimax\GlobalsysConnect\Api\Client;
use Exception;
use Globalsys\EDCSDK\Request\GetProducts;
use Globalsys\EDCSDK\Response\Response;

class ProductCollectionGet
{
    protected Client $client;
    protected GetProducts $request;

    public function __construct(Client $client)
    {
        $this->client = $client;

        $this->request = new GetProducts();
    }

    /**
     * @param array|null $queryParameters
     * @return Response
     * @throws Exception
     */
    public function fetch(array $queryParameters = null): Response
    {
        if ($queryParameters !== null) {
            $this->setQueryParameters($queryParameters);
        }

        $this->client->authenticate();
        $this->client->setRequest($this->request);
        return $this->client->send();
    }

    public function setQueryParameters(array $queryParameter): void
    {
        foreach ($queryParameter as $parameter => $value) {
            if ($parameter == 'catalog') {
                $this->setCatalogBy((string)$value);
                continue;
            }
            if ($parameter == 'categories') {
                $this->setCategoryBy((string)$value);
                continue;
            }
            if ($parameter == 'categoryOption') {
                $this->setCategoryOption((string)$value);
                continue;
            }
            if ($parameter == 'currentPage') {
                $this->setCurrentPage($value);
                continue;
            }
            if ($parameter == 'pageSize') {
                $this->setLimit($value);
                continue;
            }
            if ($parameter == 'searchString') {
                $this->setSearchString((string)$value);
                continue;
            }
            if ($parameter == 'updatedAfter') {
                $this->setDateFrom((string)$value);
            }
        }
    }

    public function setCatalogBy(string $catalog): void
    {
        $this->request->setCatalogueBy($catalog);
    }

    public function setCategoryBy(string $categories): void
    {
        $this->request->setCategoryBy($categories);
    }

    public function setCategoryOption(string $categoryOption): void
    {
        $this->request->setCategoryOption($categoryOption);
    }

    public function setCurrentPage(int $page): void
    {
        $this->request->setCurrentPage($page);
    }

    public function setDateFrom(string $dateFrom): void
    {
        $this->request->setDateFrom($dateFrom);
    }

    public function setLimit(int $limit): void
    {
        $this->request->setLimit($limit);
    }

    public function setSearchString(string $search): void
    {
        $this->request->setSearchString($search);
    }

}
