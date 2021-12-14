<?php
/**
 * Copyright © Tomas Tornevall / Tornevall Networks. All rights reserved.
 * See LICENSE.md for license details.
 */

namespace TorneLIB\Model\Type;

/**
 * Class AuthSource
 *
 * @package TorneLIB\Model\Type
 * @since 6.1.0
 */
class AuthSource
{
    /**
     * Authentication source is unchanged and will internally handle the authentication.
     * @var int
     */
    const NORMAL = 0;
    /**
     * Authentication source set as SOAP. Authentication will follow SOAP standards.
     * @var int
     */
    const SOAP = 1;
    /**
     * Authentication source set as internal PHP Streams. Authentication will follow the streams standard.
     * @var int
     */
    const STREAM = 2;
}
