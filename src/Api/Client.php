<?php declare(strict_types=1);


namespace Ambimax\GlobalsysConnect\Api;


use Exception;
use Globalsys\EDCSDK\Request\AbstractRequest;
use Globalsys\EDCSDK\Request\Auth;
use Globalsys\EDCSDK\Response\Response;
use Globalsys\EDCSDK\Utils\RequestHandler;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class Client
{
    private string $apiUrl;
    private string $apiUser;
    private string $apiSecret;
    private string $sandboxApiUser;
    private string $sandboxApiSecret;
    /**
     * This property will not be set through SystemConfigService directly
     * to decide during runtime which request should be sent to the sandbox channel.
     */
    private bool $sandboxMode = false;

    protected AbstractRequest $request;
    protected RequestHandler $requestHandler;
    protected Response $response;
    protected string $accessToken;

    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->apiUrl = (string)$systemConfigService->get('AmbimaxGlobalsysConnect.config.apiUrl');
        $this->apiUser = (string)$systemConfigService->get('AmbimaxGlobalsysConnect.config.apiUser');
        $this->apiSecret = (string)$systemConfigService->get('AmbimaxGlobalsysConnect.config.apiSecret');
        $this->sandboxApiUser = (string)$systemConfigService->get('AmbimaxGlobalsysConnect.config.sandboxApiUser');
        $this->sandboxApiSecret = (string)$systemConfigService->get('AmbimaxGlobalsysConnect.config.sandboxApiSecret');
    }

    /**
     * @throws Exception
     */
    public function authenticate(bool $sandboxMode = false): void
    {
        if ($this->sandboxMode != $sandboxMode) {
            $this->sandboxMode = $sandboxMode;
            $this->deauthenticate();
        }

        if (isset($this->accessToken)) {
            return;
        }

        if ($this->sandboxMode) {
            $auth = new Auth($this->sandboxApiUser, $this->sandboxApiSecret);
        } else {
            $auth = new Auth($this->apiUser, $this->apiSecret);
        }

        $authResponse = $this->getRequestHandler()->execute($auth);
        $authContent = $authResponse->getContent();

        if ($authResponse->getHttpCode() != 200) {
            throw new Exception($authContent['message']);
        }

        $this->accessToken = $authContent['token'];
    }

    /**
     * @throws Exception
     */
    public function send(): Response
    {
        $this->response = $this->getRequestHandler()->execute($this->request, $this->accessToken);

        if ($this->response->getHttpCode() == 401) {
            $this->deauthenticate();
            $this->authenticate();
            $this->response = $this->getRequestHandler()->execute($this->request, $this->accessToken);
        }

        return $this->response;
    }

    protected function getRequestHandler(): RequestHandler
    {
        if (!isset($this->requestHandler)) {
            $this->requestHandler = new RequestHandler($this->apiUrl);
        }
        return $this->requestHandler;
    }

    public function deauthenticate(): void
    {
        unset($this->accessToken);
    }

    public function setRequest(AbstractRequest $request): void
    {
        $this->request = $request;
    }

    public function getRequest(): AbstractRequest
    {
        return $this->request;
    }

    public function getResponse(): Response
    {
        if (!isset($this->response)) {
            $this->response = new Response();
        }
        return $this->response;
    }

}
