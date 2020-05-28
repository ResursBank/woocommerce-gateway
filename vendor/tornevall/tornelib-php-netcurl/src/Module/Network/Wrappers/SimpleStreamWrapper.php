<?php
/**
 * Copyright © Tomas Tornevall / Tornevall Networks. All rights reserved.
 * See LICENSE for license details.
 */

namespace TorneLIB\Module\Network\Wrappers;

use TorneLIB\Exception\Constants;
use TorneLIB\Exception\ExceptionHandler;
use TorneLIB\Helpers\Version;
use TorneLIB\Model\Interfaces\WrapperInterface;
use TorneLIB\Model\Type\authSource;
use TorneLIB\Model\Type\authType;
use TorneLIB\Model\Type\dataType;
use TorneLIB\Model\Type\requestMethod;
use TorneLIB\Helpers\GenericParser;
use TorneLIB\Module\Config\WrapperConfig;
use TorneLIB\Utils\Generic;
use TorneLIB\Utils\Security;

try {
    Version::getRequiredVersion();
} catch (Exception $e) {
    die($e->getMessage());
}

/**
 * Class SimpleWrapper Fetching tool in the simplest form. Using file_get_contents.
 *
 * @package TorneLIB\Module\Network\Wrappers
 * @version 6.1.0
 */
class SimpleStreamWrapper implements WrapperInterface
{
    /**
     * @var WrapperConfig $CONFIG
     * @version 6.1.0
     */
    private $CONFIG;

    /**
     * @var
     * @since 6.1.0
     */
    private $streamContentResponseRaw;

    /**
     * @var array
     */
    private $streamContentResponseHeader = [];

    public function __construct()
    {
        // Base streamwrapper (file_get_contents, fopen, etc) is only allowed if allow_url_fopen is available.
        if (!Security::getIniSet('allow_url_fopen')) {
            throw new ExceptionHandler(
                sprintf(
                    'Wrapper class %s is not available on this platform since allow_url_fopen is disabled.',
                    __CLASS__
                ),
                Constants::LIB_METHOD_OR_LIBRARY_DISABLED
            );
        }

        $this->CONFIG = new WrapperConfig();
        $this->CONFIG->setStreamRequest(true);
        $this->CONFIG->setCurrentWrapper(__CLASS__);
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
     * @param WrapperConfig $config
     * @return SimpleStreamWrapper
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
     * @return SimpleStreamWrapper
     * @since 6.1.0
     */
    public function setAuthentication($username, $password, $authType = authType::BASIC)
    {
        $this->CONFIG->setAuthentication($username, $password, $authType, authSource::STREAM);

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
        return $this->streamContentResponseRaw;
    }

    /**
     * @inheritDoc
     */
    public function getParsed()
    {
        return GenericParser::getParsed(
            $this->getBody(),
            $this->getHeader('content-type')
        );
    }

    /**
     * @inheritDoc
     */
    public function getCode()
    {
        return $this->getHttpHead($this->getHeader('http'), 'code');
    }

    /**
     * @return int|string
     * @since 6.1.0
     */
    public function getHttpMessage()
    {
        return $this->getHttpHead($this->getHeader('http'), 'message');
    }

    /**
     * @param $string
     * @param string $returnData
     * @return int|string
     * @since 6.1.0
     */
    private function getHttpHead($string, $returnData = 'code')
    {
        return GenericParser::getHttpHead($string, $returnData);
    }

    /**
     * @param $key
     * @return string
     * @since 6.1.0
     */
    public function getHeader($key)
    {
        $return = '';

        if (strtolower($key) === 'http' &&
            isset($this->streamContentResponseHeader[0]) &&
            preg_match('/^http\//i', $this->streamContentResponseHeader[0])
        ) {
            return (string)$this->streamContentResponseHeader[0];
        }

        if (is_array($this->streamContentResponseHeader)) {
            foreach ($this->streamContentResponseHeader as $headerRow) {
                $rowExplode = explode(':', $headerRow, 2);
                if (isset($rowExplode[1]) && strtolower($key) === strtolower($rowExplode[0])) {
                    $return = (string)$rowExplode[1];
                }
            }
        }

        return $return;
    }

    /**
     * @param $proxyAddress
     * @param null $proxyType
     * @return $this
     * @since 6.1.0
     */
    public function setProxy($proxyAddress, $proxyType = null)
    {
        $this->CONFIG->setCurrentWrapper(__CLASS__);
        $this->CONFIG->setProxy($proxyAddress, $proxyType);

        return $this;
    }

    /**
     * @since 6.1.0
     */
    public function getStreamRequest()
    {
        $this->CONFIG->getStreamOptions();
        $this->setStreamRequestMethod();
        $this->setStreamRequestData();

        // Finalize.
        $this->getStreamDataContents();

        return $this;
    }

    /**
     * @return $this
     * @since 6.1.0
     */
    private function setStreamRequestData()
    {
        $requestData = $this->CONFIG->getRequestData();

        $this->CONFIG->setDualStreamHttp(
            'content',
            $requestData
        );

        switch ($this->CONFIG->getRequestDataType()) {
            case dataType::XML:
                $this->setStreamContentType('text/xml');
                break;
            case dataType::JSON:
                $this->setStreamContentType('application/json; charset=utf-8');
                break;
            default:
                $this->setStreamContentType('application/x-www-form-urlencoded');
                break;
        }

        return $this;
    }

    /**
     * @param $contentType
     * @return $this
     */
    private function setStreamContentType($contentType)
    {
        $this->CONFIG->setDualStreamHttp(
            'header',
            sprintf(
                'Content-Type: %s',
                $contentType
            )
        );

        return $this;
    }

    /**
     * @return false|string
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    public function getStreamDataContents()
    {
        // When requests are failing, this MAY throw warnings.
        // Usually we don't want this method to do this, on for example 404
        // errors, etc as we have our own exception handler below, which does
        // this in a correct way.
        $this->streamContentResponseRaw = @file_get_contents(
            $this->CONFIG->getRequestUrl(),
            false,
            $this->CONFIG->getStreamContext()
        );

        $this->streamContentResponseHeader = isset($http_response_header) ? $http_response_header : [];

        $httpExceptionMessage = $this->getHttpMessage();
        if (isset($php_errormsg) && !empty($php_errormsg)) {
            $httpExceptionMessage = $php_errormsg;
        }

        $this->CONFIG->getHttpException(
            $httpExceptionMessage,
            $this->getCode()
        );
    }

    /**
     * @return $this
     * @since 6.1.0
     */
    private function setStreamRequestMethod()
    {
        $requestMethod = $this->CONFIG->getRequestMethod();
        switch ($requestMethod) {
            case requestMethod::METHOD_POST:
                $this->CONFIG->setDualStreamHttp('method', 'POST');
                break;
            case requestMethod::METHOD_PUT:
                $this->CONFIG->setDualStreamHttp('method', 'PUT');
                break;
            case requestMethod::METHOD_DELETE:
                $this->CONFIG->setDualStreamHttp('method', 'DELETE');
                break;
            case requestMethod::METHOD_HEAD:
                $this->CONFIG->setDualStreamHttp('method', 'HEAD');
                break;
            case requestMethod::METHOD_REQUEST:
                $this->CONFIG->setDualStreamHttp('method', 'REQUEST');
                break;
            default:
                $this->CONFIG->setDualStreamHttp('method', 'GET');
                break;
        }

        return $this;
    }

    /**
     * @param $url
     * @param array $data
     * @param int $method
     * @param int $dataType
     * @return SimpleStreamWrapper
     * @version 6.1.0
     */
    public function request($url, $data = [], $method = requestMethod::METHOD_GET, $dataType = dataType::NORMAL)
    {
        if (!empty($url)) {
            $this->CONFIG->setRequestUrl($url);
        }
        if (is_array($data) && count($data)) {
            $this->CONFIG->setRequestData($data);
        }

        if ($this->CONFIG->getRequestMethod() !== $method) {
            $this->CONFIG->setRequestMethod($method);
        }

        if ($this->CONFIG->getRequestDataType() !== $dataType) {
            $this->CONFIG->setRequestDataType($dataType);
        }

        $this->getStreamRequest();

        return $this;
    }
}
