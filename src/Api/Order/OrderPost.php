<?php declare(strict_types=1);


namespace Ambimax\GlobalsysConnect\Api\Order;


use Ambimax\GlobalsysConnect\Api\Client;
use Ambimax\GlobalsysConnect\Api\Order\OrderPost\Validation;
use Exception;
use Globalsys\EDCSDK\Model\PostOrderCustomerModel;
use Globalsys\EDCSDK\Model\PostOrderModel;
use Globalsys\EDCSDK\Request\PostOrder;
use Globalsys\EDCSDK\Response\Response;

class OrderPost
{
    protected Client $client;
    protected PostOrder $request;
    protected Validation $validation;

    protected bool $sandboxMode = false;


    public function __construct(Client $client, Validation $validation)
    {
        $this->client = $client;
        $this->validation = $validation;
        $this->request = new PostOrder();
    }

    /**
     * @throws Exception
     */
    public function post(): void
    {
        $this->client->authenticate($this->sandboxMode);
        $this->client->setRequest($this->request);
        $this->client->send();
    }

    public function getResponse(): Response
    {
        return $this->client->getResponse();
    }

    public function setPostOrderModel(PostOrderModel $postOrderModel): void
    {
        $this->request->setPostOrderModel($postOrderModel);
    }

    public function setPostOrderArticleModels(array $postOrderArticleModels): void
    {
        $this->request->setPostOrderArticleModels($postOrderArticleModels);
    }

    public function setPostOrderCustomerModel(PostOrderCustomerModel $postOrderCustomerModel): void
    {
        $this->request->setPostOrderCustomerModel($postOrderCustomerModel);
    }

    public function getData(): array
    {
        return $this->request->getData();
    }

    public function setSandboxMode(bool $sandboxMode)
    {
        $this->sandboxMode = $sandboxMode;
    }

    public function getSandboxMode(): bool
    {
        return $this->sandboxMode;
    }

    public function validate(): bool
    {
        return $this->validation->validate($this->getData());
    }
}
