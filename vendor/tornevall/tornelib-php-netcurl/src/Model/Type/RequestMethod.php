<?php
/**
 * Copyright © Tomas Tornevall / Tornevall Networks. All rights reserved.
 * See LICENSE.md for license details.
 */

namespace TorneLIB\Model\Type;

/**
 * Class RequestMethod
 * @package TorneLIB\Model\Type
 * @since 6.1.0
 */
abstract class RequestMethod
{
    /**
     * HTTP GET Method.
     * @var int
     */
    const GET = 0;
    /**
     * HTTP Post Method.
     * @var int
     */
    const POST = 1;
    /**
     * HTTP_PUT Method.
     * @var int
     */
    const PUT = 2;
    /**
     * HTTP DELETE Method.
     * @var int
     */
    const DELETE = 3;
    /**
     * HTTP HEAD Method.
     * @var int
     */
    const HEAD = 4;
    /**
     * HTTP Head Method/Request.
     * @var int
     */
    const REQUEST = 5;
    /**
     * HTTP Request Method.
     * @var int
     */
    const PATCH = 6;

    // Redundant Constants Below.

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
