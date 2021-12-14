<?php
/**
 * Copyright © Tomas Tornevall / Tornevall Networks. All rights reserved.
 * See LICENSE.md for license details.
 */

namespace TorneLIB\Model\Type;

/**
 * Class DataType
 *
 * @package TorneLIB\Model\Type
 * @since 6.1.0
 */
class DataType
{
    /**
     * Normal DataType means that we usually use the standard GET/POST variables like ?var=val&var1=val1
     * @var int
     */
    const NORMAL = 0;

    /**
     * Using JSON-formatted data.
     * @var int
     */
    const JSON = 1;

    /**
     * Using SOAP-structured values.
     * @var int
     */
    const SOAP = 2;

    /**
     * Using XML-values.
     * DataType
     * @var int
     */
    const XML = 3;

    /**
     * Use SOAP/XML.
     * @var int
     */
    const SOAP_XML = 4;

    /**
     * Use XML that is defined in RSS feeds.
     * @var int
     */
    const RSS_XML = 5;
}
