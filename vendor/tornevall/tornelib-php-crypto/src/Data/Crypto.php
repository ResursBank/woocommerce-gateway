<?php

namespace TorneLIB\Data;

use TorneLIB\Exception\Constants;
use TorneLIB\Exception\ExceptionHandler;

/**
 * Class Crypto Default encryption failover module.
 *
 * @package TorneLIB\Data
 */
class Crypto
{
    const COMPLEX_UPPER = 1;
    const COMPLEX_LOWER = 2;
    const COMPLEX_NUMERICS = 4;
    const COMPLEX_SPECIAL = 8;
    const COMPLEX_BINARY = 16;

    const CRYPTO_UNAVAILABLE = 0;
    const CRYPTO_SSL = 1;
    const CRYPTO_MCRYPT = 2;

    /**
     * @var Password
     */
    private $password;

    /**
     * @var Aes
     */
    private $aes;

    /**
     * @var Compress
     */
    private $compress;

    /**
     * Crypto constructor.
     * @since 6.0
     */
    public function __construct()
    {
        $this->password = new Password();
        $this->aes = new Aes();
        $this->compress = new Compress();

        return $this;
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     * @throws ExceptionHandler
     * @snice 6.1.0
     */
    public function __call($name, $arguments)
    {
        $return = null;

        if (method_exists($this->aes, $name)) {
            $return = call_user_func_array(
                [
                    $this->aes,
                    $name,
                ],
                $arguments
            );
        } elseif (method_exists($this->aes, $name)) {
            $return = call_user_func_array(
                [
                    $this->compress,
                    $name,
                ],
                $arguments
            );
        } elseif (method_exists($this->password, $name)) {
            $return = call_user_func_array(
                [
                    $this->password,
                    $name,
                ],
                $arguments
            );
        }

        if (is_null($return)) {
            throw new ExceptionHandler(
                sprintf(
                    'There is no method named "%s" in class %s',
                    $name,
                    __CLASS__
                ),
                Constants::LIB_METHOD_OR_LIBRARY_UNAVAILABLE
            );
        }

        return $return;
    }
}
