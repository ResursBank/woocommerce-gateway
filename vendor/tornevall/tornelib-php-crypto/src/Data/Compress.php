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
use TorneLIB\IO\Data\Strings;
use TorneLIB\Utils\Security;

/**
 * Class Compress Compression methods.
 *
 * @package TorneLIB\Data
 * @version 6.1.0
 * @since 6.1.0 Class is new, compression methods did however exist in v6.0 internally.
 */
class Compress
{
    /**
     * @var int
     */
    private $compressionLevel = 5;

    /**
     * @param int $compressionLevel
     */
    public function setCompressionLevel($compressionLevel = 9)
    {
        $this->compressionLevel = $compressionLevel;
    }

    /**
     * @return int
     */
    public function getCompressionLevel()
    {
        return $this->compressionLevel;
    }

    /**
     * @param $data
     * @return string
     */
    public function getGzEncode($data)
    {
        return (new Strings())->base64urlEncode(
            gzencode(
                $data,
                $this->getCompressionLevel()
            )
        );
    }

    /**
     * @param $data
     * @return string
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    public function getGzDecode($data)
    {
        return $this->getDecodedGz(
            (new Strings())->base64urlDecode($data)
        );
    }

    /**
     * @param $data
     * @return mixed
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    private function getDecodedGz($data)
    {
        if (Security::getCurrentFunctionState('gzencode')) {
            return gzdecode($data);
        }
        // If gzdecode is unexistent, try to fall back on gzinflate.
        if (!Security::getCurrentFunctionState('gzinflag')) {
            throw new ExceptionHandler(
                'Function gzinflate and gzdecode is missing',
                Constants::LIB_METHOD_OR_LIBRARY_UNAVAILABLE
            );
        }
        $flags = ord(substr($data, 3, 1));
        $headerlen = 10;
        if ($flags & 4) {
            $extralen = unpack('v', substr($data, 10, 2));
            $extralen = $extralen[1];
            $headerlen += 2 + $extralen;
        }
        // Filename
        if ($flags & 8) {
            $headerlen = strpos($data, chr(0), $headerlen) + 1;
        }
        // Comment
        if ($flags & 16) {
            $headerlen = strpos($data, chr(0), $headerlen) + 1;
        }
        // CRC at end of file
        if ($flags & 2) {
            $headerlen += 2;
        }
        $unpacked = gzinflate(substr($data, $headerlen));
        if ($unpacked === false) {
            $unpacked = $data;
        }

        return $unpacked;
    }

    /**
     * @param $data
     * @return string
     * @throws ExceptionHandler
     */
    public function getBzEncode($data)
    {
        $return = null;

        if (Security::getCurrentFunctionState('bzcompress')) {
            $return = (new Strings())->base64urlEncode(
                bzcompress(
                    $data
                )
            );
        }

        return $return;
    }

    /**
     * @param $data
     * @return mixed|null
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    public function getBzDecode($data)
    {
        $return = null;
        if (Security::getCurrentFunctionState('bzdecompress')) {
            $return = bzdecompress(
                (new Strings())->base64urlDecode($data)
            );
        }
        return $return;
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed|null
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    public function __call($name, $arguments)
    {
        $return = null;

        if (preg_match('/_/', $name)) {
            $exReq = explode("_", $name);
            if (isset($exReq[1]) && !empty($exReq[1])) {
                $newName = sprintf('get%s', $exReq[1]);
                if (method_exists($this, $newName)) {
                    $return = call_user_func_array(
                        [
                            $this,
                            $newName,
                        ],
                        $arguments
                    );
                }
            }
        }

        if (is_null($return)) {
            throw new ExceptionHandler(
                sprintf(
                    'Function "%s" in %s does not exist!',
                    $name,
                    __CLASS__
                )
            );
        }

        return $return;
    }
}
