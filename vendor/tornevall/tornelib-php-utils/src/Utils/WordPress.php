<?php

namespace TorneLIB\Utils;

use TorneLIB\Exception\ExceptionHandler;

/**
 * Class WordPress WordPress Helping class.
 * @package TorneLIB\Utils
 * @version 6.1.0
 * @since 6.1.11
 */
class WordPress
{
    private $wpPrefix;

    public function __construct()
    {
        $this->validate();
    }

    /**
     * @throws ExceptionHandler
     */
    public function validateAbsPath()
    {
        if (!defined('ABSPATH')) {
            throw new ExceptionHandler('WordPress can not be found');
        }
    }

    /**
     * @throws ExceptionHandler
     */
    public function validate()
    {
        $this->validateAbsPath();
        $functionCheck = [
            'add_action',
            'add_filter',
            'apply_filters',
        ];
        foreach ($functionCheck as $functionName) {
            if (!function_exists($functionName)) {
                throw new ExceptionHandler('Can not find methods that WordPress is depending on.');
            }
        }

        return $this;
    }

    /**
     * Anti collider.
     *
     * @param null $extra
     * @return string
     */
    public function getPrefix($extra = null)
    {
        if (empty($extra) && !empty($this->wpPrefix)) {
            // Extra empty, prefix not empty.
            $return = $this->wpPrefix;
        } elseif (!empty($extra) && !empty($this->wpPrefix)) {
            // Extra not empty, prefix not empty.
            $return = sprintf('%s_%s', $this->wpPrefix, $extra);
        } else {
            // Extra not empty.
            $return = $extra;
        }

        return $return;
    }

    /**
     * @param $prefix
     * @return $this
     */
    public function setPrefix($prefix)
    {
        $this->wpPrefix = $prefix;
        return $this;
    }

    /**
     * @param string $key
     * @return bool|string
     * @since 0.0.1.0
     */
    public function getOption($key)
    {
        $optionKeyPrefix = sprintf('%s_%s', $this->getPrefix('admin'), $key);
        $getOptionReturn = get_option($optionKeyPrefix);

        if (!empty($getOptionReturn)) {
            $return = $getOptionReturn;
        }

        // What the old plugin never did to save space.
        if (($testBoolean = $this->getTruth($return)) !== null) {
            $return = (bool)$testBoolean;
        } else {
            $return = (string)$return;
        }

        return $return;
    }

    /**
     * @param $value
     * @return bool|null If value returned is null, then this is a signal to the receiving part that it is not a bool.
     * @since 0.0.1.0
     */
    private function getTruth(
        $value
    ) {
        if (in_array($value, ['true', 'yes'])) {
            $return = true;
        } elseif (in_array($value, ['false', 'no'])) {
            $return = false;
        } else {
            $return = null;
        }

        return $return;
    }
}
