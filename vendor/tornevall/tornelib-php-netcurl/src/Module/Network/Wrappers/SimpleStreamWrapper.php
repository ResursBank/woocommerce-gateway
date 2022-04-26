<?php
/**
 * Copyright © Tomas Tornevall / Tornevall Networks. All rights reserved.
 * See LICENSE.md for license details.
 */

namespace TorneLIB\Module\Network\Wrappers;

use Exception;
use ReflectionException;
use TorneLIB\Exception\Constants;
use TorneLIB\Exception\ExceptionHandler;
use TorneLIB\Helpers\GenericParser;
use TorneLIB\Helpers\Version;
use TorneLIB\Model\Interfaces\WrapperInterface;
use TorneLIB\Model\Type\AuthSource;
use TorneLIB\Model\Type\AuthType;
use TorneLIB\Model\Type\DataType;
use TorneLIB\Model\Type\RequestMethod;
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
 */
class SimpleStreamWrapper implements WrapperInterface
{
    // Note to self: Where are the static headers? Well, they are not here. For all streams
    // we use WrapperConfig to store header setups.

    /**
     * @var WrapperConfig $CONFIG
     * @since 6.1.0
     */
    private $CONFIG;

    /**
     * @var
     * @since 6.1.0
     */
    private $streamContentResponseRaw;

    /**
     * @var array
     * @since 6.1.0
     */
    private $streamContentResponseHeader = [];

    /**
     * @var
     * @since 6.1.5
     */
    private $currentErrorHandler;

    /**
     * @var array $streamWarningException
     * @since 6.1.5
     */
    private $streamWarningException = ['code' => 0, 'string' => null];

    /**
     * Timeout control for the stream.
     * @var int $streamWarningExceptionCount
     * @since 6.1.6
     */
    private $streamClientTimeBegin = 0;

    /**
     * Timeout control for the stream.
     * @var int $streamClientTimeEnd
     * @since 6.1.6
     */
    private $streamClientTimeEnd = 0;

    /**
     * SimpleStreamWrapper constructor.
     * @throws ExceptionHandler
     */
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
     * @since 6.1.2
     */
    public function __destruct()
    {
        $this->CONFIG->resetStreamData();
    }

    /**
     * @inheritDoc
     * @return string
     * @throws ExceptionHandler
     * @throws ReflectionException
     */
    public function getVersion()
    {
        return isset($this->version) && !empty($this->version) ?
            $this->version : (new Generic())->getVersionByAny(__DIR__, 3, WrapperConfig::class);
    }

    /**
     * @param $timeout
     * @param false $useMillisec
     * @return $this
     * @since 6.1.3
     */
    public function setTimeout($timeout, $useMillisec = false)
    {
        $this->CONFIG->setTimeout($timeout, $useMillisec);
        return $this;
    }

    /**
     * @return array
     * @since 6.1.3
     */
    public function getTimeout()
    {
        return $this->CONFIG->getTimeout();
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
     * @param $username
     * @param $password
     * @param int $authType
     * @return SimpleStreamWrapper
     * @since 6.1.0
     */
    public function setAuthentication($username, $password, $authType = AuthType::BASIC)
    {
        $this->CONFIG->setAuthentication($username, $password, $authType, AuthSource::STREAM);

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
     * @return array|mixed
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
    public function getBody()
    {
        return $this->streamContentResponseRaw;
    }

    /**
     * @param $key
     * @return string
     * @since 6.1.0
     */
    public function getHeader($key)
    {
        $return = '';

        if (isset($this->streamContentResponseHeader[0]) &&
            strtolower($key) === 'http' &&
            (bool)preg_match('/^http\//i', $this->streamContentResponseHeader[0])
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
     * @param string $key
     * @param string $value
     * @param false $static
     * @return WrapperConfig
     * @since 6.1.2
     */
    public function setHeader($key = '', $value = '', $static = false)
    {
        return $this->setStreamHeader($key, $value, $static);
    }

    /**
     * @param mixed $key
     * @param string $value
     * @param false $static
     * @return SimpleStreamWrapper|WrapperConfig
     * @since 6.1.2
     */
    public function setStreamHeader($key = '', $value = '', $static = false)
    {
        if (is_array($key) && empty($value)) {
            // Handle as bulk if this request (for example) comes from NetWrapper.
            foreach ($key as $getKey => $getValue) {
                $this->setStreamHeader($getKey, $getValue, false);
            }
            return $this;
        }

        return $this->CONFIG->setHeader($key, $value, $static);
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
     * @param $url
     * @param array $data
     * @param int $method
     * @param int $dataType
     * @return SimpleStreamWrapper
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    public function request($url, $data = [], $method = RequestMethod::GET, $dataType = DataType::NORMAL)
    {
        $this->CONFIG->resetStreamData();
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

    /**
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    public function getStreamRequest()
    {
        $this->CONFIG->getStreamOptions();
        $this->setStreamRequestMethod();
        $this->setStreamRequestData();

        // Make sure static headers are joined first.
        $this->CONFIG->getStreamHeader();

        // Finalize.
        $this->getStreamDataContents();

        return $this;
    }

    /**
     * @return $this
     * @since 6.1.0
     */
    private function setStreamRequestMethod()
    {
        $requestMethod = $this->CONFIG->getRequestMethod();
        switch ($requestMethod) {
            case RequestMethod::POST:
                $this->CONFIG->setDualStreamHttp('method', 'POST');
                break;
            case RequestMethod::PUT:
                $this->CONFIG->setDualStreamHttp('method', 'PUT');
                break;
            case RequestMethod::DELETE:
                $this->CONFIG->setDualStreamHttp('method', 'DELETE');
                break;
            case RequestMethod::HEAD:
                $this->CONFIG->setDualStreamHttp('method', 'HEAD');
                break;
            case RequestMethod::REQUEST:
                $this->CONFIG->setDualStreamHttp('method', 'REQUEST');
                break;
            case RequestMethod::PATCH:
                $this->CONFIG->setDualStreamHttp('method', 'PATCH');
                break;
            default:
                $this->CONFIG->setDualStreamHttp('method', 'GET');
                break;
        }

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
            case DataType::XML:
                $this->setStreamContentType('text/xml');
                break;
            case DataType::JSON:
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
     * @since 6.1.0
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
     * @return float
     * @throws ExceptionHandler
     * @since 6.1.6
     */
    public function getTotalRequestTime()
    {
        if ($this->streamClientTimeBegin === 0 && $this->streamClientTimeEnd === 0) {
            throw new ExceptionHandler(
                'You need to make a request before getting the timediff.',
                Constants::LIB_NETCURL_SOAP_REQUEST_TIMER_NOT_READY
            );
        }
        return (float)$this->streamClientTimeEnd - $this->streamClientTimeBegin;
    }

    /**
     * @return false|string
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    public function getStreamDataContents()
    {
        $this->setStreamErrorHandler();
        $this->streamClientTimeBegin = microtime(true);

        // When requests are failing, this MAY throw warnings.
        // Usually we don't want this method to do this, on for example 404
        // errors, etc as we have our own exception handler below, which does
        // this in a correct way.
        try {
            $this->streamContentResponseRaw = file_get_contents(
                $this->CONFIG->getRequestUrl(),
                false,
                $this->CONFIG->getStreamContext()
            );
        } catch (ExceptionHandler $e) {
            $this->streamClientTimeEnd = microtime(true);
            $streamClientRequestTimeDiff = $this->streamClientTimeEnd - $this->streamClientTimeBegin;

            if ($e->getCode() === 2) {
                $currentTimeout = $this->CONFIG->getTimeout();
                if ($streamClientRequestTimeDiff >= $currentTimeout['CONNECT'] &&
                    $streamClientRequestTimeDiff >= $currentTimeout['REQUEST']
                ) {
                    $message = sprintf(
                        '%s [streamClientRequestTime: %s]',
                        $e->getMessage(),
                        $this->getTotalRequestTime()
                    );
                    throw new ExceptionHandler($message, Constants::LIB_NETCURL_TIMEOUT);
                } elseif (preg_match('/Connection refused/', $e->getMessage())) {
                    // Try to detect this error by string. May be destroyed by locales.
                    throw new ExceptionHandler($e->getMessage(), Constants::LIB_NETCURL_CONNECTION_REFUSED);
                }
            }
            throw $e;
        }
        // Also calculate success requests.
        $this->streamClientTimeEnd = microtime(true);

        // Restore the errorhandler immediately after request if no exceptions are detected during first request.
        restore_error_handler();

        $this->streamContentResponseHeader = isset($http_response_header) ? $http_response_header : [];
        $httpExceptionMessage = $this->getHttpMessage();
        if (isset($php_errormsg) && !empty($php_errormsg)) {
            $httpExceptionMessage = $php_errormsg;
        }

        $this->CONFIG->getHttpException(
            $httpExceptionMessage,
            $this->getCode()
        );

        return $this;
    }

    /**
     * @since 6.1.5
     */
    private function setStreamErrorHandler()
    {
        // This feature very much looks like the soapFault catcher. However
        // streams works a bit different from SoapClient despite the fact that they
        // may use the same communications engine to produce content. So instead of
        // silently return from this point, we will throw an exception properly instead,
        // even if it is only of code 2.
        $this->currentErrorHandler = set_error_handler(function ($errNo, $errStr) {
            if (empty($this->stremWarningException['string'])) {
                $this->streamWarningException['code'] = $errNo;
                $this->streamWarningException['string'] = $errStr;
            }
            restore_error_handler();
            throw new ExceptionHandler($errStr, $errNo);
        }, E_WARNING);
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
     * @inheritDoc
     */
    public function getCode()
    {
        return $this->getHttpHead($this->getHeader('http'), 'code');
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     * @throws ExceptionHandler
     * @since 6.1.2
     */
    public function __call($name, $arguments)
    {
        $return = null;

        $compatibilityMethods = $this->CONFIG->getCompatibilityMethods();
        if (isset($compatibilityMethods[$name])) {
            $name = $compatibilityMethods[$name];
            $return = call_user_func_array([$this, $name], $arguments);
        } elseif (method_exists($this->CONFIG, $name)) {
            /**
             * Makeing sure setSsl and other configuration is callable from the StreamWrapper.
             * @since 6.1.5
             */
            $return = call_user_func_array(
                [
                    $this->CONFIG,
                    $name,
                ],
                $arguments
            );
        }

        if (!is_null($return)) {
            return $return;
        }

        throw new ExceptionHandler(
            sprintf('Function "%s" not available.', $name),
            Constants::LIB_METHOD_OR_LIBRARY_UNAVAILABLE
        );
    }
}
