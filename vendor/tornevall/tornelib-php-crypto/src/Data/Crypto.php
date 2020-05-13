<?php
/**
 * Copyright 2020 Tomas Tornevall & Tornevall Networks
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @package TorneLIB
 * @version 6.1.0
 * @since 6.0
 */

namespace TorneLIB\Data;

use TorneLIB\Exception\Constants;
use TorneLIB\Exception\ExceptionHandler;

/**
 * Class Crypto Default encryption failover module.
 *
 * @package TorneLIB\Data
 * @version 6.1.0
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
