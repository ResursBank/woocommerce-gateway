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

namespace TorneLIB\IO;

use TorneLIB\Exception\Constants;
use TorneLIB\Exception\ExceptionHandler;
use TorneLIB\IO\Data\Arrays;
use TorneLIB\IO\Data\Content;
use TorneLIB\IO\Data\Strings;

/**
 * Class Bridge IO-bridge.
 *
 * @package TorneLIB\IO
 * @deprecated If you ever thinking of using this, please don't. Use direct calls instead.
 */
class Bridge
{
    /**
     * @var Strings
     */
    private $strings;

    /**
     * @var Content
     */
    private $content;

    /**
     * @var Arrays
     */
    private $arrays;

    public function __construct()
    {
        $this->strings = new Strings();
        $this->content = new Content();
        $this->arrays = new Arrays();
    }

    public function __call($name, $arguments)
    {
        $return = null;
        $requestClass = null;

        if (method_exists($this->content, $name)) {
            $requestClass = $this->content;
        }
        if (method_exists($this->strings, $name)) {
            $requestClass = $this->content;
        }
        if (method_exists($this->arrays, $name)) {
            $requestClass = $this->content;
        }

        if (!is_null($requestClass)) {
            $return = call_user_func_array(
                [
                    $requestClass,
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