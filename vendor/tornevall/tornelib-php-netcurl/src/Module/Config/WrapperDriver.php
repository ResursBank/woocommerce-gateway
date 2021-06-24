<?php
/**
 * Copyright © Tomas Tornevall / Tornevall Networks. All rights reserved.
 * See LICENSE.md for license details.
 */

namespace TorneLIB\Module\Config;

use Exception;
use TorneLIB\Exception\Constants;
use TorneLIB\Exception\ExceptionHandler;
use TorneLIB\Model\Interfaces\WrapperInterface;
use TorneLIB\Module\Network\Wrappers\CurlWrapper;
use TorneLIB\Module\Network\Wrappers\RssWrapper;
use TorneLIB\Module\Network\Wrappers\SimpleStreamWrapper;
use TorneLIB\Module\Network\Wrappers\SoapClientWrapper;

/**
 * Class WrapperDriver
 * @package TorneLIB\Module\Config
 * @since 6.1.0
 */
class WrapperDriver
{
    /**
     * Internal wrappers loaded.
     * @var array $wrappers
     * @since 6.1.0
     */
    private static $wrappers = [];

    /**
     * What we support internally.
     *
     * @var array $internalWrapperList
     * @since 6.1.0
     */
    private static $internalWrapperList = [
        CurlWrapper::class,
        SoapClientWrapper::class,
        SimpleStreamWrapper::class,
        RssWrapper::class,
    ];

    /**
     * List of self developed wrappers to use if nothing else works.
     * @var array $externalWrapperList
     * @since 6.1.0
     */
    private static $externalWrapperList = [];

    /**
     * @var $instanceClass
     * @since 6.1.0
     */
    private static $instanceClass;

    /**
     * If true, make NetWrapper try to use those wrappers first.
     * @var bool $useRegisteredWrappersFirst
     * @since 6.1.0
     */
    private static $useRegisteredWrappersFirst = false;

    /**
     * Returns proper wrapper for internal wrapper requests, depending on external available wrappers.
     *
     * @param $wrapperName
     * @param bool $testOnly
     * @return mixed
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    public static function getWrapperAllowed($wrapperName, $testOnly = false)
    {
        // If there are no available external wrappers, let getWrapper do its actions and throw exceptions if
        // the internal wrapper fails to load.
        if (!count(self::$externalWrapperList)) {
            $return = self::getWrapper($wrapperName, $testOnly);
        } else {
            // If there are available external wrappers, just try to load external wrapper and proceed
            // without noise on failures, as we'd like to try to use the externals first. Always.
            $return = self::getWrapper($wrapperName, true);
        }

        return $return;
    }

    /**
     * Find out if internal wrapper is available and return it.
     *
     * @param $wrapperNameClass
     * @param bool $testOnly Test wrapper only. Meaning: Do not throw exceptions during control.
     * @return mixed
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    private static function getWrapper($wrapperNameClass, $testOnly = false)
    {
        $return = null;

        //$wrapperNameClass = preg_replace('/\//', "\", $wrapperNameClass);

        $allWrappers = self::getWrappers();
        foreach ($allWrappers as $wrapperClass) {
            $currentWrapperClass = get_class($wrapperClass);
            if ($currentWrapperClass === $wrapperNameClass ||
                $currentWrapperClass === sprintf('TorneLIB\Module\Network\Wrappers\%s', $wrapperNameClass)
            ) {
                self::$instanceClass = $wrapperNameClass;
                $return = $wrapperClass;
                break;
            }
        }

        if (!$testOnly && !is_object($return)) {
            throw new ExceptionHandler(
                sprintf(
                    'Could not find a proper NetWrapper (%s) to communicate with!',
                    $wrapperNameClass
                ),
                Constants::LIB_NETCURL_NETWRAPPER_NO_DRIVER_FOUND
            );
        }

        return $return;
    }

    /**
     * Get list of available wrappers, both internal and external.
     *
     * @return array
     * @since 6.1.0
     */
    public static function getWrappers()
    {
        // return self::$wrappers;
        return array_merge(self::$wrappers, self::$externalWrapperList);
    }

    /**
     * Initialize available wrappers.
     * @return mixed
     * @since 6.1.0
     */
    public static function initializeWrappers()
    {
        foreach (self::$internalWrapperList as $wrapperClass) {
            if (!empty($wrapperClass) &&
                !isset(self::$wrappers[$wrapperClass])
            ) {
                try {
                    self::$wrappers[$wrapperClass] = new $wrapperClass();
                } catch (Exception $wrapperLoadException) {
                }
            }
        }

        return self::$wrappers;
    }

    /**
     * Returns the instance classname if set and ready.
     *
     * @return string
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    public static function getInstanceClass()
    {
        if (empty(self::$instanceClass)) {
            throw new ExceptionHandler(
                sprintf(
                    '%s instantiation failure: No wrapper available.',
                    __CLASS__
                ),
                Constants::LIB_NETCURL_NETWRAPPER_NO_DRIVER_FOUND
            );
        }

        return (string)self::$instanceClass;
    }

    /**
     * Register a new wrapperClass for netcurl (NetWrapper).
     *
     * @param object $wrapperClass
     * @param bool $tryFirst
     * @return string
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    public static function register($wrapperClass, $tryFirst = false)
    {
        if (!is_object($wrapperClass)) {
            throw new ExceptionHandler(
                sprintf(
                    'Unable to register wrong class type in %s.',
                    __CLASS__
                ),
                Constants::LIB_CLASS_UNAVAILABLE
            );
        }

        self::$useRegisteredWrappersFirst = $tryFirst;
        self::registerClassInterface($wrapperClass);

        return self::class;
    }

    /**
     * Register external wrapper class as useble if it implements the wrapper interface.
     *
     * @param $wrapperClass
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    private static function registerClassInterface($wrapperClass)
    {
        $badClass = false;

        $wrapperClassName = get_class($wrapperClass);
        if (!isset(self::$externalWrapperList[$wrapperClassName])) {
            if (self::registerCheckImplements($wrapperClass)) {
                self::$externalWrapperList[$wrapperClassName] = $wrapperClass;
            } else {
                $badClass = true;
            }
        }

        self::registerCheckBadClass($badClass, $wrapperClass);
    }

    /**
     * Checks if registering class implements WrapperInterface.
     *
     * @param $wrapperClass
     * @return bool
     * @since 6.1.0
     */
    private static function registerCheckImplements($wrapperClass)
    {
        $implements = class_implements($wrapperClass);

        return in_array(WrapperInterface::class, $implements, false);
    }

    /**
     * Check if class is not properly registered and throw exception.
     *
     * @param $badClass
     * @param $wrapperClass
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    private static function registerCheckBadClass($badClass, $wrapperClass)
    {
        if ($badClass) {
            throw new ExceptionHandler(
                sprintf(
                    'Unable to register class %s in %s with wrong interface.',
                    get_class($wrapperClass),
                    __CLASS__
                ),
                Constants::LIB_CLASS_UNAVAILABLE
            );
        }
    }

    /**
     * If this is true NetWrapper will try the registered drivers before the internal.
     *
     * @return bool
     * @since 6.1.0
     */
    public static function getRegisteredWrappersFirst()
    {
        return self::$useRegisteredWrappersFirst;
    }

    /**
     * Get list of externally registered wrappers.
     *
     * @return array
     * @since 6.1.0
     */
    public static function getExternalWrappers()
    {
        return self::$externalWrapperList;
    }
}
