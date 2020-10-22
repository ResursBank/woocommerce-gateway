<?php
/**
 * Copyright © Tomas Tornevall / Tornevall Networks. All rights reserved.
 * See LICENSE for license details.
 */

// Inspections should be ignored here as this is a depending-environment-based class.
/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpSingleStatementWithBracesInspection */
/** @noinspection PhpFullyQualifiedNameUsageInspection */
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection NotOptimalIfConditionsInspection */

namespace TorneLIB\Module\Network\Wrappers;

use Exception;
use TorneLIB\Exception\ExceptionHandler;
use TorneLIB\Helpers\Version;
use TorneLIB\Model\Interfaces\WrapperInterface;
use TorneLIB\Model\Type\authType;
use TorneLIB\Model\Type\dataType;
use TorneLIB\Model\Type\requestMethod;
use TorneLIB\Module\Config\WrapperConfig;
use TorneLIB\Module\Network\NetWrapper;
use TorneLIB\Utils\Generic;

// If 8 or higher, don't bother.
if (PHP_VERSION_ID < 80000) {
    try {
        Version::getRequiredVersion();
    } catch (Exception $e) {
        die($e->getMessage());
    }
}

/**
 * Class RssWrapper
 * @package TorneLIB\Module\Network\Wrappers
 * @link https://docs.laminas.dev/laminas-feed/consuming-rss/
 */
class RssWrapper implements WrapperInterface
{
    /**
     * @var string
     * @since 6.1.0
     */
    private $requestResponseRaw;

    /**
     * @var
     * @since 6.1.0
     */
    private $requestResponse;

    /**
     * @var WrapperConfig
     */
    private $CONFIG;

    /**
     * RssWrapper constructor.
     * @since 6.1.0
     */
    public function __construct()
    {
        $this->CONFIG = new WrapperConfig();
        $this->CONFIG->setCurrentWrapper(__CLASS__);
    }

    /**
     * @return WrapperConfig
     * @since 6.1.0
     */
    public function getConfig()
    {
        return $this->CONFIG;
    }

    /**
     * @param WrapperConfig $config
     * @return RssWrapper
     * @since 6.1.0
     */
    public function setConfig($config)
    {
        $this->CONFIG = $this->getInheritedConfig($config);

        return $this;
    }

    /**
     * @param $config
     * @return mixed
     * @since 6.1.0
     */
    private function getInheritedConfig($config)
    {
        $config->setCurrentWrapper($this->CONFIG->getCurrentWrapper());

        return $config;
    }

    /**
     * @param $username
     * @param $password
     * @param int $authType
     * @return RssWrapper
     * @since 6.1.0
     */
    public function setAuthentication($username, $password, $authType = authType::BASIC)
    {
        $this->CONFIG->setAuthentication($username, $password, $authType);

        return $this;
    }

    /**
     * @return array
     * @since 6.1.0
     */
    public function getAuthentication()
    {
        return $this->CONFIG->getAuthentication();
    }

    /**
     * @inheritDoc
     */
    public function getBody()
    {
        return $this->requestResponseRaw;
    }

    /**
     * @inheritDoc
     */
    public function getParsed($string = '')
    {
        if (!empty($string) && empty($this->requestResponse) && class_exists('\Laminas\Feed\Reader\Reader')) {
            $this->requestResponse = \Laminas\Feed\Reader\Reader::importString($string);
        }
        return $this->requestResponse;
    }

    /**
     * Simplified response as Laminas handles most of the data for us.
     * @return int
     */
    public function getCode()
    {
        return isset($this->requestResponseRaw->entries) ? 200 : 404;
    }

    /**
     * @inheritDoc
     * @return string
     * @throws ExceptionHandler
     * @throws \ReflectionException
     */
    public function getVersion()
    {
        return isset($this->version) && !empty($this->version) ?
            $this->version : (new Generic())->getVersionByAny(__DIR__, 3, WrapperConfig::class);
    }

    /**
     * @inheritDoc
     * @throws ExceptionHandler
     */
    public function request(
        $url,
        $data = [],
        $method = requestMethod::METHOD_GET,
        $dataType = dataType::NORMAL
    ) {
        // Laminas requires two classes if url should be handled.
        if (class_exists('Laminas\Feed\Reader\Reader') && class_exists('Laminas\Http\Client')) {
            $this->requestResponse = \Laminas\Feed\Reader\Reader::import($url);
        } elseif (class_exists('Laminas\Feed\Reader\Reader')) {
            // If the http client in laminas is missing, fall back on our local wrappers. But instead of using
            // the netwrapper type RSS, we'll fetch the data as a regular data request to disable parts of the automation.
            /** @noinspection NullPointerExceptionInspection */
            $this->requestResponseRaw = (new NetWrapper())
                ->request(
                    $url,
                    [],
                    requestMethod::METHOD_GET,
                    dataType::NORMAL
                )->getBody();
            $this->requestResponse = \Laminas\Feed\Reader\Reader::importString($this->requestResponseRaw);
        } else {
            $naturalRequest = (new NetWrapper())
                ->request(
                    $url,
                    [],
                    requestMethod::METHOD_GET,
                    dataType::NORMAL
                );
            $this->requestResponseRaw = $naturalRequest->getBody();
            $this->requestResponse = $naturalRequest->getParsed();
        }

        return $this;
    }
}
