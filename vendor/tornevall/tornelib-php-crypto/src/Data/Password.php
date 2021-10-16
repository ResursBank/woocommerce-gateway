<?php

namespace TorneLIB\Data;

use Exception;
use function random_int;

/**
 * Class Password Password generating library.
 *
 * Note: One big difference to the v6.0 release of the "keymaker" is that this tool generates
 * password strings that is based on bitmasks.
 *
 * @package TorneLIB\Data
 */
class Password
{
    const COMPLEX_UPPER = 1;
    const COMPLEX_LOWER = 2;
    const COMPLEX_NUMERICS = 4;
    const COMPLEX_SPECIAL = 8;
    const COMPLEX_BINARY = 16;

    private $passwordRetries = 0;

    /**
     * Complexity array table.
     * @var array $characterArray
     * @since 6.1.0
     */
    private $characterArray = [
        Crypto::COMPLEX_UPPER => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
        Crypto::COMPLEX_LOWER => 'abcdefghijklmnopqrstuvwxyz',
        Crypto::COMPLEX_NUMERICS => '0123456789',
        Crypto::COMPLEX_SPECIAL => '!@#$%*?',
        Crypto::COMPLEX_BINARY => '',
    ];

    /**
     * @var array
     */
    private $characterCache = [];

    /**
     * @var array
     * @since 6.1.0
     */
    private $ambigousCharacters = [
        '+',
        '/',
        '=',
        'I',
        'G',
        '0',
        'O',
        'D',
        'Q',
        'R',
    ];

    /**
     * @var string
     */
    private $lastRandomizedCharacter = '';

    /**
     * @var array
     */
    private $usedRandomizedCharacter = [];

    /**
     * @return array
     */
    public function getCharacterArray()
    {
        return $this->characterArray;
    }

    /**
     * Refactored generator to create a random password or string.
     *
     * @param int $complexity
     * @param int $totalLength Length of the string
     * @param bool $ambigous Exclude what we see as ambigous characters (this has no effect in complexity > 4)
     * @param bool $antiDouble Never use same character twice.
     * @return string
     * @throws Exception
     * @since 6.0.4
     */
    public function mkpass(
        $complexity = self::COMPLEX_UPPER + self::COMPLEX_LOWER + self::COMPLEX_NUMERICS,
        $totalLength = 16,
        $ambigous = true,
        $antiDouble = true
    ) {
        $pwString = '';
        $this->usedRandomizedCharacter = [];

        if (is_null($complexity)) {
            // Defaultify if someone sends null in here.
            $complexity = self::COMPLEX_UPPER + self::COMPLEX_LOWER + self::COMPLEX_NUMERICS;
        }
        if (!intval($totalLength)) {
            $totalLength = 16;
        }

        for ($charIndex = 0; $charIndex < $totalLength; $charIndex++) {
            $this->passwordRetries = 0;
            $pwString .= $this->getCharacterFromComplexity($complexity, $ambigous, $antiDouble);
        }

        return $pwString;
    }

    /**
     * Returns a random character based on complexity selection.
     *
     * @param int $complexity
     * @param bool $ambigous
     * @param bool $antiDouble
     * @return mixed|string
     * @throws Exception
     * @since 6.0.4
     */
    private function getCharacterFromComplexity(
        $complexity,
        $ambigous = false,
        $antiDouble = false
    ) {
        if ($complexity & self::COMPLEX_BINARY && $this->characterArray[$complexity]) {
            $this->getBinaryTable();
        }

        return $this->getRandomCharacterFromArray($complexity, $ambigous, $antiDouble);
    }

    /**
     * @since 6.1.0
     */
    private function getBinaryTable()
    {
        for ($i = 0; $i <= 255; $i++) {
            $this->characterArray[self::COMPLEX_BINARY] .= chr($i);
        }
    }

    /**
     * Returns a random character from a selected character list.
     *
     * @param int $complexity
     * @param bool $ambigous
     * @param bool $antiDouble
     * @return mixed
     * @throws Exception
     * @since 6.0.4
     */
    private function getRandomCharacterFromArray(
        $complexity,
        $ambigous = false,
        $antiDouble = false
    ) {
        $characterArray = $this->getCharactersFromList(
            $complexity,
            $this->getCharacterList($complexity)
        );

        // Seems to fail sometimes, in PHP 7.4 for unknown reasons.
        if (function_exists('random_int')) {
            $rInt = random_int(0, count($characterArray) - 1);
        } else {
            $rInt = rand(0, count($characterArray) - 1);
        }

        $return = $characterArray[$rInt];

        if (
            // complexity is not special and numerics and return is the same as last
            (
                (
                    $complexity !== self::COMPLEX_SPECIAL &&
                    $complexity !== self::COMPLEX_NUMERICS
                ) &&
                $return === $this->lastRandomizedCharacter
            ) ||
            // or ambiguous request and ambiguous content
            (
                $ambigous &&
                in_array($return, $this->ambigousCharacters)
            ) ||
            // or avoid dupes and return value has been used where not special or numerics
            (
                $antiDouble &&
                isset($this->usedRandomizedCharacter[$return]) &&
                (
                    $complexity !== self::COMPLEX_SPECIAL &&
                    $complexity !== self::COMPLEX_NUMERICS
                )
            )
        ) {
            $this->passwordRetries++;
            if ($this->passwordRetries <= count($characterArray)) {
                return $this->getRandomCharacterFromArray($complexity, $ambigous, $antiDouble);
            } else {
                return $this->getRandomCharacterFromArray($complexity, $ambigous, false);
            }
        }

        if (!isset($this->usedRandomizedCharacter[$return])) {
            $this->usedRandomizedCharacter[$return] = $return;
        }
        $this->lastRandomizedCharacter = $return;

        return $return;
    }

    /**
     * Returns a selected character list array string as a new array.
     *
     * @param int $complexity
     * @param $characterString
     * @return array
     * @since 6.0.4
     * @noinspection PhpUnusedParameterInspection
     */
    private function getCharactersFromList($complexity, $characterString)
    {
        return preg_split("//", $characterString, -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * @param int $complexity
     * @return mixed
     * @throws Exception
     * @since 6.1.0
     */
    public function getCharacterList($complexity)
    {
        $allowedList = [];

        foreach ($this->characterArray as $arrayBit => $arrayContent) {
            if ($complexity & $arrayBit) {
                $allowedList[] = $arrayContent;
            }
        }

        if (!count($allowedList)) {
            $return = $this->characterArray[self::COMPLEX_UPPER];
        } else {
            if (function_exists('random_int')) {
                $rInt = random_int(0, count($allowedList) - 1);
            } else {
                $rInt = rand(0, count($allowedList) - 1);
            }
            $return = $allowedList[$rInt];
        }

        return $return;
    }
}
