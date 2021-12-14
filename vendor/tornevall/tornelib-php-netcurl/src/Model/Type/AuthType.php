<?php
/**
 * Copyright © Tomas Tornevall / Tornevall Networks. All rights reserved.
 * See LICENSE.md for license details.
 */

namespace TorneLIB\Model\Type;

/**
 * Class AuthType Authentication methods (inherited from CURLAUTH constants).
 *
 * @package TorneLIB\Model\Type
 * @since 6.1.0
 */
class AuthType
{
    /**
     * @var int
     */
    const HTTPAUTH = 107;

    /**
     * @var int
     */
    const BASIC = 1;

    /**
     * @var int
     */
    const DIGEST = 2;

    /**
     * @var int
     */
    const GSSNEGOTIATE = 4;

    /**
     * @var int
     */
    const NTLM = 8;

    /**
     * @var int
     */
    const ANY = -1;

    /**
     * @var int
     */
    const ANYSAFE = -2;
}
