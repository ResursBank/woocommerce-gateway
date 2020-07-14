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
    /** @var int COMPLEX_UPPER Add uppercase characters to complexity. */
    const COMPLEX_UPPER = 1;
    /** @var int COMPLEX_LOWER Add lowercase characters to complexity. */
    const COMPLEX_LOWER = 2;
    /** @var int COMPLEX_NUMERICS Add numeric values to complexity. */
    const COMPLEX_NUMERICS = 4;
    /** @var int COMPLEX_SPECIAL Add special characters to complexity (!, #, @, etc). */
    const COMPLEX_SPECIAL = 8;
    /** @var int COMPLEX_BINARY Add binary values to complexity (the rest of the charset). */
    const COMPLEX_BINARY = 16;

    /** @var int CRYPTO_UNAVAILABLE Set if neither SSL nor MCRYPT are found in system. */
    const CRYPTO_UNAVAILABLE = 0;
    /** @var int CRYPTO_SSL SSL has higher priority than mcrypt. When found, this is set primarily. */
    const CRYPTO_SSL = 1;
    /** @var int CRYPTO_MCRYPT When SSL is not available, but mcrypt is, this is chosen. */
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
