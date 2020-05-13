<?php
/**
 * Copyright © Tomas Tornevall / Tornevall Networks. All rights reserved.
 * See LICENSE for license details.
 */

namespace TorneLIB\Helpers;

class Browsers
{
    /**
     * @var array $userAgents Template for future browsers. This till change over time.
     */
    protected $userAgents = [
        'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 1.0.3705; .NET CLR 1.1.4322; Media Center PC 4.0;)',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.116 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/70.0.3538.77 Safari/537.36'
    ];

    /**
     * Currently returning the name of set NETCURL6.0 useragent. This will change over time.
     *
     * @return string
     * @todo Randomize.
     */
    public function getBrowser()
    {
        // PHP Limitation: Only variables should be passed by reference on [...] - code problem below, so it's splitted.
        // (string)array_pop(array_reverse($this->userAgents)).
        $reverseAgents = array_reverse($this->userAgents);
        return (string)array_pop($reverseAgents);
    }
}