<?php declare(strict_types=1);


namespace Ambimax\GlobalsysConnect\Api\Stock;


use Ambimax\GlobalsysConnect\Api\Client;
use Exception;
use Globalsys\EDCSDK\Request\GetStocks;
use Globalsys\EDCSDK\Response\Response;

class StockCollectionGet
{
    protected Client $client;
    protected GetStocks $request;

    public function __construct(Client $client)
    {
        $this->client = $client;

        $this->request = new GetStocks();
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
            if ($parameter == 'from') {
                $this->setFrom((string)$value);
            }
        }
    }

    public function setFrom(string $dateFrom): void
    {
        $this->request->setFrom($dateFrom);
    }
}
