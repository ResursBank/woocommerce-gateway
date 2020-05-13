<?php

namespace TorneLIB\Utils;

use Exception;
use TorneLIB\Exception\Constants;
use TorneLIB\Exception\ExceptionHandler;

/**
 * Class Memory
 * @package TorneLIB\Utils
 * @version 6.1.2
 */
class Memory
{
    private $INI;

    /**
     * Memory constructor.
     * @since 6.1.0
     */
    public function __construct()
    {
        $this->INI = new Ini();
    }

    private $haltOnLowMemory = false;

    /**
     * Set new memory limit for PHP.
     *
     * @param string $newLimitValue
     * @return bool
     * @since 6.1.0
     */
    public function setMemoryLimit($newLimitValue = '512M')
    {
        $return = false;

        $oldMemoryValue = $this->getBytes(ini_get('memory_limit'));
        if ($this->INI->getIniSettable('memory_limit')) {
            $blindIniSet = ini_set('memory_limit', $newLimitValue) !== false ? true : false;
            $newMemoryValue = $this->getBytes(ini_get('memory_limit'));
            $return = $blindIniSet && $oldMemoryValue !== $newMemoryValue ? true : false;
        }

        return $return;
    }

    /**
     * Enforce automatic adjustment if memory limit is set too low (or your defined value).
     *
     * @param string $minLimit
     * @param string $maxLimit
     * @return bool
     * @throws Exception
     * @since 6.1.0
     */
    public function getMemoryLimitAdjusted($minLimit = '256M', $maxLimit = '-1')
    {
        $return = false;
        $currentLimit = $this->getBytes(ini_get('memory_limit'));
        $myLimit = $this->getBytes($minLimit);
        if ($currentLimit <= $myLimit) {
            $return = $this->setMemoryLimit($maxLimit);

            if (!$return && $this->getHaltOnLowMemory()) {
                throw new ExceptionHandler(
                    'Your server is running on too low memory, and I am not allowed to adjust this.',
                    Constants::LIB_UTILS_MEMORY_FAILSET
                );
            }
        }

        return $return;
    }

    /**
     * WP Style byte conversion for memory limits. To avoid circular dependencies of IO, we've put a local copy
     * here, so we don't need to require the IO library.
     *
     * @param $value
     * @return mixed
     */
    public function getBytes($value)
    {
        $value = strtolower(trim($value));
        $bytes = (int)$value;

        if (false !== strpos($value, 't')) {
            $bytes *= 1024 * 1024 * 1024 * 1024;
        } elseif (false !== strpos($value, 'g')) {
            $bytes *= 1024 * 1024 * 1024;
        } elseif (false !== strpos($value, 'm')) {
            $bytes *= 1024 * 1024;
        } elseif (false !== strpos($value, 'k')) {
            $bytes *= 1024;
        } elseif (false !== strpos($value, 'b')) {
            $bytes *= 1;
        }

        // Deal with large (float) values which run into the maximum integer size.
        return min($bytes, PHP_INT_MAX);
    }

    /**
     * @return bool
     * @since 6.1.0
     */
    public function getHaltOnLowMemory()
    {
        return $this->haltOnLowMemory;
    }

    /**
     * @param bool $haltOnLowMemory
     * @since 6.1.0
     */
    public function setHaltOnLowMemory($haltOnLowMemory)
    {
        $this->haltOnLowMemory = $haltOnLowMemory;
    }
}
