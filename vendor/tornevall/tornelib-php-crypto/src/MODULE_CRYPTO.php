<?php

namespace TorneLIB;

use TorneLIB\Data\Crypto;

/**
 * Class MODULE_CRYPTO
 *
 * Compatibility class.
 *
 * @package TorneLIB
 * @version 6.1.0
 * @since 6.0.0
 * @deprecated Use v6.1 classes instead!!
 */
class MODULE_CRYPTO
{
    private $CRYPTO;

    public function __construct()
    {
        $this->CRYPTO = new Crypto();
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     * @since 6.1.0
     */
    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->CRYPTO, $name], $arguments);
    }
}
