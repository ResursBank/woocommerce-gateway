<?php
/**
 * Copyright © Tomas Tornevall / Tornevall Networks. All rights reserved.
 * See LICENSE for license details.
 */

namespace TorneLIB\Module\Network\Wrappers;

use TorneLIB\Helpers\Version;
use TorneLIB\Model\Interfaces\WrapperInterface;
use TorneLIB\Model\Type\authType;
use TorneLIB\Model\Type\dataType;
use TorneLIB\Model\Type\requestMethod;
use TorneLIB\Module\Config\WrapperConfig;
use TorneLIB\Module\Network\NetWrapper;
use TorneLIB\Utils\Generic;

try {
    Version::getRequiredVersion();
} catch (Exception $e) {
    die($e->getMessage());
}

/**
 * Class RssWrapper
 * @package TorneLIB\Module\Network\Wrappers
 * @link https://docs.laminas.dev/laminas-feed/consuming-rss/
 * @version 6.1.0
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
     * @return WrapperConfig
     * @since 6.1.0
     */
    public function getConfig()
    {
        return $this->CONFIG;
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
     */
    public function getVersion()
    {
        $return = $this->version;

        if (empty($return)) {
            $return = (new Generic())->getVersionByClassDoc(__CLASS__);
        }

        return $return;
    }

    /**
     * @inheritDoc
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
