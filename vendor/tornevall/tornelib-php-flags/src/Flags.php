<?php

declare(strict_types=1);

/**
 * Copyright © Tomas Tornevall / Tornevall Networks. All rights reserved.
 * See LICENSE for license details.
 */

namespace TorneLIB;

use TorneLIB\Exception\Constants;
use TorneLIB\Exception\ExceptionHandler;

/**
 * Class Flags Statically callable.
 * @package TorneLIB
 * @version 6.1.5
 */
class Flags
{
    /**
     * Internally stored flags.
     * @var array
     * @since 6.1.0
     */
    private $internalFlags = [];

    /**
     * List of locked flags, which can only be set while they are not set.
     * @var array
     * @since 6.1.4
     */
    private $lockedFlags = [];

    /**
     * For all static calls.
     * @var array
     * @since 6.1.0
     */
    protected static $staticFlagSet = [];

    /**
     * Set internal flag parameter.
     *
     * @param string $flagKey
     * @param null $flagValue
     * @param bool $lock
     * @return bool
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    public function setFlag(string $flagKey = '', $flagValue = null, bool $lock = false): bool
    {
        $return = true;
        if (!empty($flagKey)) {
            if (is_null($flagValue)) {
                $flagValue = true;
            }
            if ($lock) {
                $this->lockedFlags[$flagKey] = (bool)$lock;
            }
            if (isset($this->lockedFlags[$flagKey]) &&
                $this->lockedFlags[$flagKey] === true &&
                isset($this->internalFlags[$flagKey])
            ) {
                $return = false;
            } else {
                $this->internalFlags[$flagKey] = $flagValue;
            }

            return $return;
        }

        // LIB_UNHANDLED
        throw new ExceptionHandler(
            sprintf(
                'Exception in "%s": Flags can not be empty.',
                __FUNCTION__
            ),
            Constants::LIB_FLAG_EXCEPTION
        );
    }

    /**
     * Get internal flag.
     *
     * @param string $flagKey
     * @return mixed|null
     * @since 6.1.0
     */
    public function getFlag(string $flagKey)
    {
        if (isset($this->internalFlags[$flagKey])) {
            return $this->internalFlags[$flagKey];
        }

        return null;
    }

    /**
     * Check if flag is set and true.
     *
     * @param string $flagKey
     * @return bool
     * @since 6.1.0
     */
    public function isFlag(string $flagKey): bool
    {
        if ($this->hasFlag($flagKey)) {
            return ($this->getFlag($flagKey) === 1 || $this->getFlag($flagKey) === true ? true : false);
        }

        return false;
    }

    /**
     * Check if there is an internal flag set with current key.
     *
     * @param string $flagKey
     * @return bool
     * @since 6.1.0
     */
    public function hasFlag(string $flagKey): bool
    {
        if (!is_null($this->getFlag($flagKey))) {
            return true;
        }

        return false;
    }

    /**
     * @param array $arrayData
     * @return bool
     * @since 6.1.1
     */
    public function isAssoc(array $arrayData): bool
    {
        if ([] === $arrayData) {
            return false;
        }

        return array_keys($arrayData) !== range(0, count($arrayData) - 1);
    }

    /**
     * Set multiple flags. Chained from 6.1.5.
     *
     * @param array $flags
     * @param array $lock
     * @return $this
     * @throws ExceptionHandler
     * @since 6.1.1
     */
    public function setFlags(array $flags = [], array $lock = []): Flags
    {
        if ($this->isAssoc($flags)) {
            foreach ($flags as $flagKey => $flagData) {
                $lockFlag = isset($lock[$flagKey]) ? $lock[$flagKey] : false;
                $this->setFlag($flagKey, $flagData, $lockFlag);
            }
        } else {
            foreach ($flags as $flagKey) {
                $lockFlag = isset($lock[$flagKey]) ? $lock[$flagKey] : false;
                $this->setFlag($flagKey, true, $lockFlag);
            }
        }

        return $this;
    }

    /**
     * Return all flags
     * @return array
     * @since 6.1.1
     */
    public function getFlags(): array
    {
        return $this->internalFlags;
    }

    /**
     * Remove flag from internals. Returns boolean if successful.
     *
     * @param string $flagKey
     * @return bool
     * @since 6.1.0
     */
    public function unsetFlag(string $flagKey): bool
    {
        if ($this->hasFlag($flagKey)) {
            unset($this->internalFlags[$flagKey]);

            return true;
        }

        return false;
    }

    /**
     * @param string $flagKey
     * @return bool
     * @since 6.1.0
     */
    public function removeFlag(string $flagKey): bool
    {
        return $this->unsetFlag($flagKey);
    }

    /**
     * @param string $flagKey
     * @return bool
     * @since 6.1.0
     */
    public function deleteFlag(string $flagKey): bool
    {
        return $this->unsetFlag($flagKey);
    }

    /**
     * @return $this
     * @since 6.1.0
     */
    public function clearAllFlags(): Flags
    {
        $this->internalFlags = [];

        return $this;
    }

    /**
     * Returns this class.
     *
     * @return $this
     * @since 6.1.5
     */
    public function getInstance(): Flags
    {
        return $this;
    }

    /**
     * Get them all.
     * @return array
     * @since 6.1.1
     */
    public function getAllFlags()
    {
        return $this->internalFlags;
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     * @since 6.1.2
     */
    public static function __callStatic($name, $arguments)
    {
        if (!is_object(self::$staticFlagSet)) {
            self::$staticFlagSet = new Flags();
        }

        return call_user_func_array(
            [
                self::$staticFlagSet,
                preg_replace(
                    '/^_/',
                    '',
                    $name
                ),
            ],
            $arguments
        );
    }
}
