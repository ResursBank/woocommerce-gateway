<?php

/**
 * Copyright 2018 Tomas Tornevall & Tornevall Networks
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
 * Tornevall Networks netCurl library - Yet another http- and network communicator library
 * Each class in this library has its own version numbering to keep track of where the changes are. However, there is a major version too.
 * @package TorneLIB
 * @version 6.1.0
 * @since 6.0
 * @deprecated You should learn how to run this by yourself.
 */

namespace TorneLIB\Module;

/**
 * Class Bit Bitmasking handler if you're unable to handle & and | by yourself.
 *
 * @package TorneLIB
 */
class Bit
{
    /** @var array Standard bitmask setup */
    private $BIT_SETUP;
    private $maxBits = 8;

    /**
     * Bit constructor.
     * @param array $bitStructure
     */
    function __construct($bitStructure = [])
    {
        $this->BIT_SETUP = [
            'OFF' => 0,
            'BIT_1' => 1,
            'BIT_2' => 2,
            'BIT_4' => 4,
            'BIT_8' => 8,
            'BIT_16' => 16,
            'BIT_32' => 32,
            'BIT_64' => 64,
            'BIT_128' => 128,
        ];
        if (count($bitStructure)) {
            $this->BIT_SETUP = $this->validateBitStructure($bitStructure);
        }
    }

    /**
     * Set max bits to handle. 8 bits = 1 byte. So in short, 1 byte can store up to 8 values.
     *
     * @param int $maxBits
     * @return Bit
     * @since 6.0
     */
    public function setMaxBits($maxBits = 8)
    {
        $this->maxBits = $maxBits;
        $this->validateBitStructure($maxBits);
        return $this;
    }

    /**
     * Return number of bits.
     *
     * @return int
     * @since 6.0
     */
    public function getMaxBits()
    {
        return $this->maxBits;
    }

    /**
     * @param int $maxBits
     * @return array
     * @since 6.0
     */
    private function getRequiredBits($maxBits = 8)
    {
        $requireArray = [];
        if ($this->maxBits != $maxBits) {
            $maxBits = $this->maxBits;
        }
        for ($curBit = 0; $curBit <= $maxBits; $curBit++) {
            $requireArray[] = (int)pow(2, $curBit);
        }

        return $requireArray;
    }

    /**
     * @param array $bitStructure
     * @return array
     * @since 6.0
     */
    private function validateBitStructure($bitStructure = [])
    {
        if (is_numeric($bitStructure)) {
            $newBitStructure = [
                'OFF' => 0,
            ];
            for ($bitIndex = 0; $bitIndex <= $bitStructure; $bitIndex++) {
                $powIndex = pow(2, $bitIndex);
                $newBitStructure["BIT_" . $powIndex] = $powIndex;
            }
            $bitStructure = $newBitStructure;
            $this->BIT_SETUP = $bitStructure;
        }
        $require = $this->getRequiredBits(count($bitStructure));
        $validated = [];
        $newValidatedBitStructure = [];
        $valueKeys = [];
        foreach ($bitStructure as $key => $value) {
            if (in_array($value, $require)) {
                $newValidatedBitStructure[$key] = $value;
                $valueKeys[$value] = $key;
                $validated[] = $value;
            }
        }
        foreach ($require as $bitIndex) {
            if (!in_array($bitIndex, $validated)) {
                if ($bitIndex == "0") {
                    $newValidatedBitStructure["OFF"] = $bitIndex;
                } else {
                    $bitIdentificationName = "BIT_" . $bitIndex;
                    $newValidatedBitStructure[$bitIdentificationName] = $bitIndex;
                }
            } else {
                if (isset($valueKeys[$bitIndex]) && !empty($valueKeys[$bitIndex])) {
                    $bitIdentificationName = $valueKeys[$bitIndex];
                    $newValidatedBitStructure[$bitIdentificationName] = $bitIndex;
                }
            }
        }
        asort($newValidatedBitStructure);
        $this->BIT_SETUP = $newValidatedBitStructure;

        return $newValidatedBitStructure;
    }

    /**
     * @param array $bitStructure
     * @since 6.0
     */
    public function setBitStructure($bitStructure = [])
    {
        $this->validateBitStructure($bitStructure);
    }

    /**
     * @return array
     * @since 6.0
     */
    public function getBitStructure()
    {
        return $this->BIT_SETUP;
    }

    /**
     * Finds out if a bitmasked value is located in a bitarray
     *
     * @param int $requestedExistingBit
     * @param int $requestedBitSum
     * @return bool
     * @since 6.0
     */
    public function isBit($requestedExistingBit = 0, $requestedBitSum = 0)
    {
        $return = false;
        if (is_array($requestedExistingBit)) {
            foreach ($requestedExistingBit as $bitKey) {
                if (!$this->isBit($bitKey, $requestedBitSum)) {
                    return false;
                }
            }

            return true;
        }

        // Solution that works with unlimited bits
        for ($bitCount = 0; $bitCount < count($this->getBitStructure()); $bitCount++) {
            if ($requestedBitSum & pow(2, $bitCount)) {
                if ($requestedExistingBit == pow(2, $bitCount)) {
                    $return = true;
                }
            }
        }

        // Solution that works with bits up to 8
        /*
        $sum = 0;
        preg_match_all("/\d/", sprintf("%08d", decbin( $requestedBitSum)), $bitArray);
        for ($bitCount = count($bitArray[0]); $bitCount >= 0; $bitCount--) {
            if (isset($bitArray[0][$bitCount])) {
                if ( $requestedBitSum & pow(2, $bitCount)) {
                    if ( $requestedExistingBit == pow(2, $bitCount)) {
                        $return = true;
                    }
                }
            }
        }
        */

        return $return;
    }

    /**
     * Get active bits in an array
     *
     * @param int $bitValue
     * @return array
     * @since 6.0
     */
    public function getBitArray($bitValue = 0)
    {
        $returnBitList = [];
        foreach ($this->BIT_SETUP as $key => $value) {
            if ($this->isBit($value, $bitValue)) {
                $returnBitList[] = $key;
            }
        }

        return $returnBitList;
    }
}
