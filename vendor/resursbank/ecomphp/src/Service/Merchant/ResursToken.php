<?php

namespace Resursbank\Ecommerce\Service\Merchant;

class ResursToken
{
    private $accessToken;
    private $tokenType;
    private $tokenExpire;

    public function __construct($accessToken, $tokenType, $tokenExpire)
    {
        $this->accessToken = $accessToken;
        $this->tokenType = $tokenType;
        $this->tokenExpire = $tokenExpire;
    }

    /**
     * @return string
     */
    public function getAccessToken()
    {
        return (string)$this->accessToken;
    }

    /**
     * @return string
     */
    public function getTokenType()
    {
        return (string)$this->tokenType;
    }

    /**
     * @return int
     */
    public function getExpire()
    {
        return (int)$this->tokenExpire;
    }
}
