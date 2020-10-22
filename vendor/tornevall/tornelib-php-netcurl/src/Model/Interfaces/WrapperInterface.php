<?php
/**
 * Copyright © Tomas Tornevall / Tornevall Networks. All rights reserved.
 * See LICENSE for license details.
 */

namespace TorneLIB\Model\Interfaces;

use TorneLIB\Model\Type\authType;
use TorneLIB\Model\Type\dataType;
use TorneLIB\Model\Type\requestMethod;
use TorneLIB\Module\Config\WrapperConfig;
use TorneLIB\Utils\Generic;

// Avoid conflicts and use what we have.
if (!defined('NETCURL_VERSION')) {
    // Normally, you should not need to use this definition.
    // Use getVersion in each class instead, we're doing the same request from them.
    define('NETCURL_VERSION', (new Generic())->getVersionByAny(__DIR__, 3, WrapperConfig::class));
}

/**
 * Interface Wrapper Interface with basic setup that should be present in all modules included in this package.
 * @package TorneLIB\Module\Network\Model
 * @since 6.1.0
 */
interface WrapperInterface
{
    /**
     * Wrapper constructor.
     * @since 6.1.0
     */
    public function __construct();

    /**
     * Get current configuration from WrapperConfig, so it can be updated with custom settings.
     *
     * @return WrapperConfig
     * @since 6.1.0
     */
    public function getConfig();

    /**
     * Save/overwrite new WrapperConfig with new settings and options.
     *
     * @param WrapperConfig $config
     * @return mixed
     * @since 6.1.0
     */
    public function setConfig($config);

    /**
     * Authentication setup for all modules. Default setup is to use Basic Auth.
     *
     * @param $username
     * @param $password
     * @param authType $authType
     * @return array
     * @since 6.1.0
     */
    public function setAuthentication($username, $password, $authType);

    /**
     * Get current authentication data.
     *
     * @return array
     * @since 6.1.0
     */
    public function getAuthentication();

    /**
     * Get http request body, raw.
     *
     * @return mixed
     * @since 6.1.0
     */
    public function getBody();

    /**
     * Get http request parsed. Normally a body converted from xml, json, etc to a workable object.
     *
     * @return mixed
     * @since 6.1.0
     */
    public function getParsed();

    /**
     * Get http request head code. Example 200 success, 401 Unauthorized, etc.
     *
     * @return mixed
     * @since 6.1.0
     */
    public function getCode();

    /**
     * Get current version of netcurl, either from docblocks or internal settings.
     *
     * @return string
     */
    public function getVersion();

    /**
     * Default request method. Replaces doGet, doPost, doPut, doDelete, etc.
     *
     * @param $url
     * @param array $data
     * @param $method
     * @param int $dataType
     * @return mixed
     */
    public function request($url, $data = [], $method = requestMethod::METHOD_GET, $dataType = dataType::NORMAL);
}
