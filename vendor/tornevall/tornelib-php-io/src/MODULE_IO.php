<?php

namespace TorneLIB;

use TorneLIB\Data\IO;
use TorneLIB\IO\Bridge;

/**
 * Class MODULE_IO
 * @package TorneLIB
 * @version 6.1.0
 * @since 6.0
 * @deprecated Use v6.1 classes instead!!
 */
class MODULE_IO
{
    private $realIo;

    public function __construct()
    {
        $this->realIo = new Bridge();
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     * @since 6.1.0
     */
    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->realIo, $name], $arguments);
    }
}
