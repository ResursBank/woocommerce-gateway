<?php
/**
 * Copyright © Tomas Tornevall / Tornevall Networks. All rights reserved.
 * See LICENSE.md for license details.
 */

namespace TorneLIB\Utils;

use TorneLIB\Exception\ExceptionHandler;

/**
 * Class WordPress WordPress Helping class.
 *
 * @package TorneLIB\Utils
 * @version 6.1.0
 * @since 6.1.11
 */
class WordPress
{
    /**
     * @var string
     * @since 6.1.11
     */
    private $wpPrefix;

    /**
     * @var string
     * @since 6.1.17
     */
    private $pluginBaseFile;

    /**
     * @var Generic
     * @since 6.1.17
     */
    private $generic;

    /**
     * @throws ExceptionHandler
     * @since 6.1.11
     */
    public function __construct()
    {
        $this->validate();
        $this->generic = new Generic();
    }

    /**
     * @throws ExceptionHandler
     * @since 6.1.11
     */
    public function validate()
    {
        $this->validateAbsPath();
        $functionCheck = [
            'add_action',
            'add_filter',
            'apply_filters',
            'get_current_user_id',
            'get_user_meta',
        ];
        foreach ($functionCheck as $functionName) {
            if (!function_exists($functionName)) {
                throw new ExceptionHandler('Can not find methods that WordPress is depending on.');
            }
        }

        return $this;
    }

    /**
     * @throws ExceptionHandler
     * @since 6.1.11
     */
    public function validateAbsPath()
    {
        if (!defined('ABSPATH')) {
            throw new ExceptionHandler(
                'No WordPress installation found.',
                404
            );
        }
    }

    /**
     * @param $prefix
     * @return $this
     * @since 6.1.11
     */
    public function setPrefix($prefix)
    {
        $this->wpPrefix = $prefix;
        return $this;
    }

    /**
     * @param string $key
     * @return bool|string
     * @since 6.1.11
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
     * Anti collider.
     *
     * @param null $extra
     * @return string
     * @since 6.1.11
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
     * @param $value
     * @return bool|null If value returned is null, then this is a signal to the receiving part that it is not a bool.
     * @since 6.1.11
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

    /**
     * Base file for which the WP-plugin-info-headers can be found.
     *
     * @param $pluginBaseFile
     * @since 6.1.17
     */
    public function setPluginBaseFile($pluginBaseFile)
    {
        $this->pluginBaseFile = $pluginBaseFile;
    }

    /**
     * @return string
     * @since 6.1.17
     */
    public function getPluginTitle()
    {
        return $this->getPluginDataContent('Plugin Name');
    }

    /**
     * Get data from plugin setup (top of init.php).
     *
     * @param $key
     * @return string
     * @throws ExceptionHandler
     * @version 6.1.17
     */
    public function getPluginDataContent($key)
    {
        // get_file_data resides in wp-includes/functions.php
        if (file_exists($this->pluginBaseFile)) {
            $pluginContent = get_file_data($this->pluginBaseFile, [$key => $key]);
            $return = $pluginContent[$key];
        } else {
            throw new ExceptionHandler(
                'Plugin base file does not exist or is not set.',
                404
            );
        }

        return $return;
    }

    /**
     * @return string
     * @throws ExceptionHandler
     * @since 6.1.17
     */
    public function getCurrentVersion()
    {
        return $this->getPluginDataContent('version');
    }

    /**
     * @param $key
     * @return int|mixed|null
     * @since 6.1.16
     */
    public function getUserInfo($key)
    {
        $return = null;

        if (function_exists('get_current_user_id') && function_exists('get_user_meta')) {
            $currentUserId = get_current_user_id();
            if ($key === 'userid' || empty($key)) {
                $return = $currentUserId;
            } else {
                $metaData = empty($key) ? get_user_meta($currentUserId) : get_user_meta($currentUserId, $key);
                if ($currentUserId && is_array($metaData) && !count($metaData)) {
                    $return = get_userdata($currentUserId)->get($key);
                } else {
                    $return = isset($metaData[$key]) ? $metaData[$key] : $metaData;
                    if (is_array($return) && count($return) === 1) {
                        $return = array_pop($return);
                    }
                }
            }
        }

        return $return;
    }
}
