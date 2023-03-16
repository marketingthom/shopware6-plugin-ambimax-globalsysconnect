<?php declare(strict_types=1);


namespace Ambimax\GlobalsysConnect\Api\Order;


use Ambimax\GlobalsysConnect\Api\Client;
use Ambimax\GlobalsysConnect\Api\Order\OrderPost\Validation;
use Exception;
use Globalsys\EDCSDK\Request\GetOrders;
use Globalsys\EDCSDK\Response\Response;

class OrderGetter
{
    protected GetOrders $request;
    protected Client $client;
    protected Validation $validation;

    protected bool $sandboxMode = false;


    public function __construct(
        Client     $client,
        Validation $validation
    )
    {
        $this->client = $client;
        $this->validation = $validation;
        $this->request = new GetOrders();
    }

    /**
     * @throws Exception
     */
    public function get(): void
    {
        $this->client->authenticate($this->sandboxMode);
        $this->client->setRequest($this->request);
        $this->client->send();
    }

    public function getResponse(): Response
    {
        return $this->client->getResponse();
    }

    public function setChangedFrom(string $changedFrom): void
    {
        $this->request->setChangedFrom($changedFrom);
    }

    public function setSandboxMode(bool $sandboxMode)
    {
        $this->sandboxMode = $sandboxMode;
    }

    public function getSandboxMode(): bool
    {
        return $this->sandboxMode;
    }
}
