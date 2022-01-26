<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */
declare(strict_types=1);

namespace Resursbank\Ecommerce\Service\Merchant;

use Exception;
use TorneLIB\Exception\ExceptionHandler;
use TorneLIB\Model\Type\RequestMethod;
use TorneLIB\Module\Network\NetWrapper;

class MerchantApiConnector
{
    /**
     * @var array
     */
    protected $urls = [
        'mock' => 'https://apigw.integration.resurs.com',
        'prod' => 'https://apigw.resurs.com',
    ];
    /**
     * @var string[]
     */
    protected $merchantApiServiceUrls = [
        'mock' => '/api/mock_merchant_api_service',
        'prod' => '??',
    ];
    /**
     * @var ResursToken
     */
    protected $token;
    /**
     * @var string[]
     */
    private $tokenRequest = [
        'mock' => '/api/oauth2/token',
        'prod' => '/api/oauth2/token',
    ];
    private $environmentIsTest = true;
    /**
     * @var string
     */
    private $clientId = '';
    /**
     * @var string
     */
    private $clientSecret = '';
    /**
     * @var string
     */
    private $clientScope = '';

    /**
     * @var string
     */
    private $grantType = '';

    /**
     * @var string
     */
    private $bearerToken = '';

    /**
     * @var bool
     */
    private $jwtReady = false;

    /**
     * @var NetWrapper $connection
     */
    private $connection;

    /**
     * @var bool
     */
    private $isRenewedToken = false;

    /**
     * @return $this
     */
    public function setProduction(): MerchantApiConnector
    {
        $this->environmentIsTest = false;

        return $this;
    }

    /**
     * @param string $merchantToken
     *
     * @return $this
     */
    public function setBearer(string $merchantToken = ''): MerchantApiConnector
    {
        if (empty($merchantToken) && !empty($this->token->getAccessToken())) {
            $this->bearerToken = $this->token->getAccessToken();
        } else {
            $this->bearerToken = $merchantToken;
        }

        $this->getConnection()->setHeader('Authorization', sprintf('Bearer %s', $this->bearerToken));

        return $this;
    }

    /**
     * @param $resource
     *
     * @return string
     */
    private function getRequestUrl($resource): string
    {
        return sprintf(
            '%s%s/%s',
            $this->getApiUrl(),
            $this->getMerchantApiUrlService(),
            $resource
        );
    }

    /**
     * Checks whether the Resurs Token is still valid or expired.
     *
     * @return bool
     */
    public function hasExpiredToken(): bool
    {
        $return = true;

        if (!empty($this->token) &&
            time() < $this->token->getValidEndTime()
        ) {
            $return = false;
        }

        return $return;
    }

    /**
     * Compile a request to the API, so all requests look the same.
     *
     * @param $resource
     * @param $data
     * @param int $requestMethod
     *
     * @return mixed
     * @throws ExceptionHandler
     * @throws Exception
     */
    public function getMerchantRequest($resource, $data = null, int $requestMethod = RequestMethod::GET)
    {
        // Parsed responses may contain:
        // content->[],
        // page->number, size, totalElements, totalPages
        // Note: This may be necessary when looking for content with large sized arrays.

        if ($this->hasExpiredToken() && $this->isJwtReady() && !$this->isRenewedToken) {
            $this->getRenewedToken();
        }

        /*
         * Exceptions that occurs at this point should very much be handled by merchants (at least for now).
         * We've removed an automation that was placed within a try-catch block here before that was checking
         * for 401-auth errors and, when necessary automatically renewed the token. A helper like this may
         * likely be a security issue and should be handled by the remote, instead of this module.
         */
        return $this->getMerchantConnection()->request(
            $this->getRequestUrl($resource),
            (array)$data,
            $requestMethod
        )->getParsed();
    }

    /**
     * @return ResursToken
     * @throws Exception
     */
    private function getRenewedToken()
    {
        $this->isRenewedToken = true;

        return $this->getToken(true);
    }

    /**
     * Make sure the Jwt request for tokens are properly set up before allowing usage.
     *
     * @return bool
     * @noinspection PhpUnused
     */
    public function isJwtReady(): bool
    {
        return $this->jwtReady;
    }

    /**
     * Set the client id for the merchant requests.
     *
     * @param $clientId
     *
     * @return $this
     */
    public function setClientId($clientId): MerchantApiConnector
    {
        $this->clientId = $clientId;

        return $this->setPreparedJwtInit();
    }

    /**
     * If all data for the Jwt is filled, then make requests allowable.
     *
     * @return $this
     */
    private function setPreparedJwtInit(): MerchantApiConnector
    {
        if (!empty($this->clientId) &&
            !empty($this->clientSecret) &&
            !empty($this->clientScope) &&
            !empty($this->grantType)
        ) {
            $this->jwtReady = true;
        }

        return $this;
    }

    /**
     * Set a secret for the API.
     *
     * @param $clientSecret
     *
     * @return $this
     */
    public function setClientSecret($clientSecret): MerchantApiConnector
    {
        $this->clientSecret = $clientSecret;

        return $this->setPreparedJwtInit();
    }

    /**
     * Set the request scope for the API.
     *
     * @param $clientScope
     *
     * @return $this
     */
    public function setScope($clientScope): MerchantApiConnector
    {
        $this->clientScope = $clientScope;

        return $this->setPreparedJwtInit();
    }

    /**
     * Set the grant type for the API.
     *
     * @param $grantType
     *
     * @return $this
     */
    public function setGrantType($grantType): MerchantApiConnector
    {
        $this->grantType = $grantType;

        return $this->setPreparedJwtInit();
    }

    /**
     * This method works almost like getConnection(), with the difference that each request will also include
     * the bearer token, which is normally not happening in the real connection (which you can see that we
     * call from here). We can set the bearer statically from the setHeader method, but for this particular
     * moment we won't do this.
     *
     * @return NetWrapper
     * @throws Exception
     */
    public function getMerchantConnection(): NetWrapper
    {
        if (!empty($this->token) && !empty($this->token->getAccessToken()) && !$this->hasConnectionBearer()) {
            $this->getConnection()->setHeader(
                'Authorization',
                sprintf('Bearer %s', $this->getToken()->getAccessToken())
            );
        } elseif ($this->isRenewedToken) {
            $this->getConnection()->setHeader(
                'Authorization',
                sprintf('Bearer %s', $this->getToken()->getAccessToken())
            );
        }

        return $this->connection;
    }

    /**
     * Check if this set up API has a bearer token ready.
     *
     * @return bool
     */
    private function hasConnectionBearer(): bool
    {
        $storedHeader = $this->getConnection()->getConfig()->getHeader();

        return isset($storedHeader['Authorization']);
    }

    /**
     * Create and/or return communications wrapper.
     *
     * @return NetWrapper
     */
    public function getConnection(): NetWrapper
    {
        if (empty($this->connection)) {
            $this->connection = new NetWrapper();
        }

        return $this->connection;
    }

    /**
     * Set connection from external part. Makes it possible to initialize an instance of NetWrapper/CurlWrapper
     * from another service.
     *
     * @param $connection
     * @return MerchantApiConnector
     */
    public function setConnection($connection): MerchantApiConnector
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * @param $accessToken
     * @param $tokenType
     * @param $tokenExpire
     * @param $tokenRegisterTime
     *
     * @return $this
     */
    public function setStoredToken($accessToken, $tokenType, $tokenExpire, $tokenRegisterTime): MerchantApiConnector
    {
        $this->token = new ResursToken(
            $accessToken,
            $tokenType,
            $tokenExpire,
            $tokenRegisterTime
        );

        return $this;
    }

    /**
     * Get a ResursToken.
     *
     * @throws Exception
     */
    public function getToken($renew = false): ResursToken
    {
        if (empty($this->token) || $renew) {
            $tokenParsedResponse = $this->getConnection()->request(
                sprintf('%s%s', $this->getApiUrl(), $this->getTokenRequestUrl()),
                $this->getJwtData(),
                RequestMethod::POST
            )->getParsed();

            $this->token = new ResursToken(
                $tokenParsedResponse->access_token,
                $tokenParsedResponse->token_type,
                $tokenParsedResponse->expires_in,
                time()
            );
        }
        return $this->token;
    }

    /***
     * @return string
     */
    protected function getApiUrl(): string
    {
        return (string)($this->isTest() ? $this->urls['mock'] : $this->urls['prod']);
    }

    /**
     * @return bool
     */
    public function isTest(): bool
    {
        return $this->environmentIsTest;
    }

    /**
     * @return string
     */
    private function getTokenRequestUrl(): string
    {
        return $this->isTest() ? $this->tokenRequest['mock'] : $this->tokenRequest['prod'];
    }

    /**
     * @return array
     * @throws Exception
     */
    private function getJwtData(): array
    {
        if (!$this->jwtReady) {
            throw new Exception('JWT credentials not ready.', 500);
        }

        return [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope' => $this->clientScope,
            'grant_type' => $this->grantType,
        ];
    }

    /**
     * @return string
     */
    protected function getMerchantApiUrlService(): string
    {
        return $this->isTest() ? $this->merchantApiServiceUrls['mock'] : $this->merchantApiServiceUrls['prod'];
    }
}
