<?php
/**
 * Copyright © Tomas Tornevall / Tornevall Networks. All rights reserved.
 * See LICENSE.md for license details.
 */

namespace TorneLIB\Model\Type;

/**
 * Class authType Authentication methods (inherited from CURLAUTH constants).
 *
 * @package TorneLIB\Model\Type
 */
class AuthType
{
    const HTTPAUTH = 107;
    const BASIC = 1;
    const DIGEST = 2;
    const GSSNEGOTIATE = 4;
    const NTLM = 8;
    const ANY = -1;
    const ANYSAFE = -2;
}
