<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */
declare(strict_types=1);

namespace Resursbank\Ecommerce\Service\Merchant;

class ResursToken
{
    /**
     * @var string
     */
    private $accessToken;
    /**
     * @var string
     */
    private $tokenType;
    /**
     * Number of seconds for the token validity.
     * @var int
     */
    private $tokenExpire;
    /**
     * Timestamp for when the token was registered.
     * @var int
     */
    private $tokenRegisterTime;
    /**
     * Ending timestamp for the token validity.
     * @var int
     */
    private $validEndTime;

    /**
     * Build up the requested token.
     *
     * @param string $accessToken
     * @param string $tokenType
     * @param int $tokenExpire
     * @param int $tokenRegisterTime
     */
    public function __construct(string $accessToken, string $tokenType, int $tokenExpire, int $tokenRegisterTime)
    {
        $this->accessToken = $accessToken;
        $this->tokenType = $tokenType;
        $this->tokenExpire = $tokenExpire;
        $this->tokenRegisterTime = $tokenRegisterTime;
        // Future timestamp for how long the token can be used.
        $this->validEndTime = $tokenRegisterTime + $this->tokenExpire;
    }

    /**
     * Return the timestamp for when the token was registered.
     * @return int
     */
    public function getTokenRegisterTime(): int
    {
        return $this->tokenRegisterTime;
    }

    /**
     * @return int
     */
    public function getValidEndTime(): int
    {
        return $this->validEndTime;
    }

    /**
     * @return string
     */
    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    /**
     * @return string
     */
    public function getTokenType(): string
    {
        return $this->tokenType;
    }

    /**
     * @return int
     */
    public function getExpire(): int
    {
        return $this->tokenExpire;
    }
}
