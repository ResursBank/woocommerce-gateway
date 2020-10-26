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
abstract class requestMethod
{
    const METHOD_GET = 0;
    const METHOD_POST = 1;
    const METHOD_PUT = 2;
    const METHOD_DELETE = 3;
    const METHOD_HEAD = 4;
    const METHOD_REQUEST = 5;
}
