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

        return $this;
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