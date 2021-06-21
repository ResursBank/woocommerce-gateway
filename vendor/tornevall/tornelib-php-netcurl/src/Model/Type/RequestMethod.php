<?php
/**
 * Copyright © Tomas Tornevall / Tornevall Networks. All rights reserved.
 * See LICENSE.md for license details.
 */

namespace TorneLIB\Model\Type;

/**
 * Class requestMethod
 *
 * @package TorneLIB\Model\Type
 */
abstract class RequestMethod
{
    const GET = 0;
    const POST = 1;
    const PUT = 2;
    const DELETE = 3;
    const HEAD = 4;
    const REQUEST = 5;
    const PATCH = 6;

    // Findable redundant constants below.

    /**
     * @var int
     * @deprecated Redundant.
     */
    const METHOD_GET = 0;
    /**
     * @var int
     * @deprecated Redundant.
     */
    const METHOD_POST = 1;
    /**
     * @var int
     * @deprecated Redundant.
     */
    const METHOD_PUT = 2;
    /**
     * @var int
     * @deprecated Redundant.
     */
    const METHOD_DELETE = 3;
    /**
     * @var int
     * @deprecated Redundant.
     */
    const METHOD_HEAD = 4;
    /**
     * @var int
     * @deprecated Redundant.
     */
    const METHOD_REQUEST = 5;
    /**
     * @var int
     * @deprecated Redundant.
     */
    const METHOD_PATCH = 6;
}
