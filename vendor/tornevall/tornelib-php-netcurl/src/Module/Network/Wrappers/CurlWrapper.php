<?php
/**
 * Copyright © Tomas Tornevall / Tornevall Networks. All rights reserved.
 * See LICENSE for license details.
 */

/** @noinspection PhpComposerExtensionStubsInspection */

namespace TorneLIB\Module\Network\Wrappers;

use Exception;
use ReflectionException;
use TorneLIB\Config\Flag;
use TorneLIB\Exception\Constants;
use TorneLIB\Exception\ExceptionHandler;
use TorneLIB\Helpers\GenericParser;
use TorneLIB\Helpers\Version;
use TorneLIB\Model\Interfaces\WrapperInterface;
use TorneLIB\Model\Type\authType;
use TorneLIB\Model\Type\dataType;
use TorneLIB\Model\Type\requestMethod;
use TorneLIB\Module\Config\WrapperConfig;
use TorneLIB\Module\Config\WrapperCurlOpt;
use TorneLIB\Utils\Generic;
use TorneLIB\Utils\Security;

try {
    Version::getRequiredVersion();
} catch (Exception $e) {
    die($e->getMessage());
}

/**
 * Class CurlWrapper.
 *
 * Wrapper to make calls directly to the curl engine. This should not be used primarily if auto detection is the
 * preferred way to fetch data.
 *
 * @package TorneLIB\Module\Network\Wrappers
 */
class CurlWrapper implements WrapperInterface
{
    /**
     * @var WrapperConfig $CONFIG
     * @since 6.1.0
     */
    private $CONFIG;

    /**
     * @var resource cURL simple handle
     * @since 6.1.0
     */
    private $curlHandle;

    /**
     * @var
     * @since 6.1.0
     */
    private $curlResponse;

    /**
     * @var int
     * @since 6.1.0
     */
    private $curlHttpCode = 0;

    /**
     * @var array
     * @since 6.1.0
     */
    private $curlMultiHttpCode = [];

    /**
     * @var array
     * @since 6.1.0
     */
    private $curlResponseHeaders = [];

    /**
     * @var bool
     * @since 6.1.0
     */
    private $isCurlMulti = false;

    /**
     * @var resource cURL multi handle
     * @since 6.1.0
     */
    private $curlMultiHandle;

    /**
     * @var
     * @since 6.1.0
     */
    private $curlMultiErrors;

    /**
     * @var bool
     * @since 6.1.0
     */
    private $instantCurlMultiErrors = false;

    /**
     * @var array
     * @since 6.1.0
     */
    private $curlMultiHandleObjects = [];

    /**
     * @var
     * @since 6.1.0
     */
    private $curlMultiResponse;

    /**
     * @var array
     * @since 6.1.0
     */
    private $customPreHeaders = [];

    /**
     * Static headers that will not reset between each request-init.
     * @var array
     * @since 6.1.2
     */
    private $customPreHeadersStatic = [];

    /**
     * @var array
     * @since 6.1.0
     */
    private $customHeaders = [];

    /**
     * @var string Custom content type.
     * @since 6.1.0
     */
    private $contentType = '';

    /**
     * @var bool If resources are checked strictly.
     * @since 6.1.2
     */
    private $strictResource = false;

    /**
     * CurlWrapper constructor.
     *
     * @throws ExceptionHandler
     * @throws Exception
     * @since 6.1.0
     */
    public function __construct()
    {
        // Make sure there are available drivers before using the wrapper.
        Security::getCurrentFunctionState('curl_init');
        Security::getCurrentFunctionState('curl_exec');

        $this->CONFIG = new WrapperConfig();
        $this->CONFIG->setCurrentWrapper(__CLASS__);
        $hasConstructorArguments = $this->getPriorCompatibilityArguments(func_get_args());

        if ($hasConstructorArguments) {
            $this->initCurlHandle();
        }
    }

    /**
     * Reverse compatibility with v6.0 - returns true if any of the settings here are touched.
     *
     * @param array $funcArgs
     * @return bool
     * @throws Exception
     * @since 6.1.0
     */
    private function getPriorCompatibilityArguments($funcArgs = [])
    {
        return $this->CONFIG->getCompatibilityArguments($funcArgs);
    }

    /**
     * Initialize simple or multi curl handles.
     *
     * @return $this
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    private function initCurlHandle()
    {
        $this->isCurlMulti = false;

        if (is_string($this->CONFIG->getRequestUrl())) {
            $requestUrl = $this->CONFIG->getRequestUrl();
            if (!empty($requestUrl) &&
                filter_var($this->CONFIG->getRequestUrl(), FILTER_VALIDATE_URL)
            ) {
                $this->curlHandle = curl_init();
                $this->setupHandle($this->curlHandle, $this->CONFIG->getRequestUrl());
            } else {
                $this->curlHandle = curl_init();
                $this->setupHandle($this->curlHandle, null);
            }
        } else {
            // Prepare for multiple curl requests.
            $requestUrlArray = $this->CONFIG->getRequestUrl();
            if (is_array($requestUrlArray) && count($requestUrlArray)) {
                $this->isCurlMulti = true;
                $this->curlMultiHandle = curl_multi_init();
                foreach ($requestUrlArray as $url) {
                    $this->curlMultiHandleObjects[$url] = curl_init();
                    $this->setupHandle(
                        $this->curlMultiHandleObjects[$url],
                        $url
                    );
                }
                $this->setCurlMultiHandles();
            }
        }

        return $this;
    }

    /**
     * Major initializer.
     *
     * @param $curlHandle
     * @param $url
     * @return $this
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    private function setupHandle($curlHandle, $url)
    {
        $this->setCurlAuthentication($curlHandle);
        $this->setCurlDynamicValues($curlHandle);
        $this->setCurlSslValues($curlHandle);
        $this->setCurlStaticValues($curlHandle);
        $this->setCurlPostData($curlHandle);
        $this->setCurlRequestMethod($curlHandle);
        $this->setCurlCustomHeaders($curlHandle);
        if (!empty($url)) {
            $this->setOptionCurl($curlHandle, CURLOPT_URL, $url);
        }

        return $this;
    }

    /**
     * @param $curlHandle
     * @return CurlWrapper
     * @since 6.1.0
     */
    private function setCurlAuthentication($curlHandle)
    {
        $authData = $this->getAuthentication();
        if (!empty($authData['password'])) {
            $this->setOptionCurl($curlHandle, CURLOPT_HTTPAUTH, $authData['type']);
            $this->setOptionCurl(
                $curlHandle,
                CURLOPT_USERPWD,
                sprintf('%s:%s', $authData['username'], $authData['password'])
            );
        }

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
     * Set curloptions.
     *
     * @param $curlHandle
     * @param $curlOpt
     * @param $value
     * @return bool
     * @since 6.1.0
     */
    public function setOptionCurl($curlHandle, $curlOpt, $value)
    {
        $this->CONFIG->setOption($curlOpt, $value);
        return curl_setopt($curlHandle, $curlOpt, $value);
    }

    /**
     * @param $curlHandle
     * @return CurlWrapper
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    private function setCurlDynamicValues($curlHandle)
    {
        foreach ($this->CONFIG->getOptions() as $curlKey => $curlValue) {
            $this->setOptionCurl($curlHandle, $curlKey, $curlValue);
        }

        return $this;
    }

    /**
     * @param $curlHandle
     * @return CurlWrapper
     * @since 6.1.0
     */
    private function setCurlSslValues($curlHandle)
    {
        // version_compare(PHP_VERSION, '5.4.11', ">=")
        if (PHP_VERSION_ID >= 50411) {
            $this->setOptionCurl($curlHandle, WrapperCurlOpt::NETCURL_CURLOPT_SSL_VERIFYHOST, 2);
        } else {
            $this->setOptionCurl($curlHandle, WrapperCurlOpt::NETCURL_CURLOPT_SSL_VERIFYHOST, 1);
        }
        // CURLOPT_SSL_VERIFYPEER is available starting with PHP 7.1
        $this->setOptionCurl($curlHandle, WrapperCurlOpt::NETCURL_CURLOPT_SSL_VERIFYPEER, 1);

        return $this;
    }

    /**
     * Values set here can not be changed via any other part of the wrapper.
     *
     * @param $curlHandle
     * @return $this
     * @since 6.1.0
     */
    private function setCurlStaticValues($curlHandle)
    {
        $this->setOptionCurl($curlHandle, CURLOPT_RETURNTRANSFER, true);
        $this->setOptionCurl($curlHandle, CURLOPT_HEADER, false);
        $this->setOptionCurl($curlHandle, CURLOPT_AUTOREFERER, true);
        $this->setOptionCurl($curlHandle, CURLINFO_HEADER_OUT, true);
        $this->setOptionCurl($curlHandle, CURLOPT_HEADERFUNCTION, [$this, 'getCurlHeaderRow']);

        return $this;
    }

    /**
     * @param $curlHandle
     * @return CurlWrapper
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    private function setCurlPostData($curlHandle)
    {
        $requestData = $this->CONFIG->getRequestData();

        switch ($this->CONFIG->getRequestDataType()) {
            case dataType::XML:
                $this->setCurlPostXmlHeader($curlHandle, $requestData);
                break;
            case dataType::JSON:
                $this->setCurlPostJsonHeader($curlHandle, $requestData);
                break;
            default:
                if ($this->CONFIG->getRequestMethod() === requestMethod::METHOD_POST) {
                    $this->setOptionCurl($curlHandle, CURLOPT_POST, true);
                }
                $this->setOptionCurl($curlHandle, CURLOPT_POSTFIELDS, $requestData);
                break;
        }

        return $this;
    }

    /**
     * @param $curlHandle
     * @param $requestData
     * @return $this
     * @throws ExceptionHandler
     * @since 6.1.0
     * @todo Convert arrayed data to XML.
     */
    public function setCurlPostXmlHeader($curlHandle, $requestData)
    {
        if (is_array($requestData)) {
            throw new ExceptionHandler(
                'Convert arrayed data to XML error - no data present!',
                Constants::LIB_UNHANDLED
            );
        }

        $this->customPreHeaders['Content-Type'] = 'Content-Type: text/xml; charset=utf-8';
        $this->customPreHeaders['Content-Length'] = strlen($requestData);
        $this->setOptionCurl($curlHandle, CURLOPT_POSTFIELDS, $requestData);

        return $this;
    }

    /**
     * @param $curlHandle
     * @param $requestData
     * @return $this
     * @since 6.1.0
     */
    private function setCurlPostJsonHeader($curlHandle, $requestData)
    {
        $jsonContentType = 'application/json; charset=utf-8';

        $testContentType = $this->getContentType();
        if (false !== stripos($testContentType, "json")) {
            $jsonContentType = $testContentType;
        }

        $this->customPreHeaders['Content-Type'] = $jsonContentType;
        $this->customPreHeaders['Content-Length'] = strlen($requestData);
        $this->setOptionCurl($curlHandle, CURLOPT_POSTFIELDS, $requestData);

        return $this;
    }

    /**
     * @return string
     * @since 6.0.17
     */
    public function getContentType()
    {
        return $this->contentType;
    }

    /**
     * @param string $setContentTypeString
     * @return CurlWrapper
     * @since 6.0.17
     */
    public function setContentType($setContentTypeString = 'application/json; charset=utf-8')
    {
        $this->contentType = $setContentTypeString;

        return $this;
    }

    /**
     * @param $curlHandle
     * @return CurlWrapper
     * @since 6.1.0
     */
    private function setCurlRequestMethod($curlHandle)
    {
        switch ($this->CONFIG->getRequestMethod()) {
            case requestMethod::METHOD_POST:
                $this->setOptionCurl($curlHandle, CURLOPT_CUSTOMREQUEST, 'POST');
                break;
            case requestMethod::METHOD_DELETE:
                $this->setOptionCurl($curlHandle, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            case requestMethod::METHOD_HEAD:
                $this->setOptionCurl($curlHandle, CURLOPT_CUSTOMREQUEST, 'HEAD');
                break;
            case requestMethod::METHOD_PUT:
                $this->setOptionCurl($curlHandle, CURLOPT_CUSTOMREQUEST, 'PUT');
                break;
            case requestMethod::METHOD_REQUEST:
                $this->setOptionCurl($curlHandle, CURLOPT_CUSTOMREQUEST, 'REQUEST');
                break;
            default:
                // Making sure we send data in proper formatting if there is bad user configuration.
                // Bad configuration is when both GET+POST data parameters are sent as a GET when the
                // correct set up in that case is a POST.
                $this->setOptionCurl($curlHandle, CURLOPT_CUSTOMREQUEST, 'GET');
                break;
        }

        return $this;
    }

    /**
     * @param $curlHandle
     * @return CurlWrapper
     * @since 6.1.0
     */
    private function setCurlCustomHeaders($curlHandle)
    {
        $this->setProperCustomHeader();
        $this->setupHeaders($curlHandle);
        return $this;
    }

    /**
     * Fix problematic header data by converting them to proper outputs.
     *
     * @return $this
     * @since 6.1.0
     */
    private function setProperCustomHeader()
    {
        // Merge static header data into customPreHeaders.
        foreach ($this->customPreHeadersStatic as $headerKey => $headerValue) {
            $this->customPreHeaders[$headerKey] = $headerValue;
        }

        foreach ($this->customPreHeaders as $headerKey => $headerValue) {
            $testHead = explode(":", $headerValue, 2);
            if (isset($testHead[1])) {
                $this->customHeaders[] = $headerValue;
            } elseif (!is_numeric($headerKey)) {
                $this->customHeaders[] = $headerKey . ": " . $headerValue;
            }
            unset($this->customPreHeaders[$headerKey]);
        }

        return $this;
    }

    /**
     * @param $curlHandle
     * @return $this
     * @since 6.1.0
     */
    private function setupHeaders($curlHandle)
    {
        if (count($this->customHeaders)) {
            $this->setOptionCurl($curlHandle, CURLOPT_HTTPHEADER, $this->customHeaders);
        }

        return $this;
    }

    /**
     * @return $this
     * @since 6.1.0
     */
    private function setCurlMultiHandles()
    {
        $reqUrlArray = (array)$this->CONFIG->getRequestUrl();
        foreach ($reqUrlArray as $url) {
            curl_multi_add_handle($this->curlMultiHandle, $this->curlMultiHandleObjects[$url]);
        }

        return $this;
    }

    /**
     * @return string
     * @throws ExceptionHandler
     * @throws ReflectionException
     * @since 6.1.0
     */
    public function getVersion()
    {
        return isset($this->version) && !empty($this->version) ?
            $this->version : (new Generic())->getVersionByAny(__DIR__, 3, WrapperConfig::class);
    }

    /**
     * Destructor for cleaning up resources.
     * @since 6.1.0
     */
    public function __destruct()
    {
        if ($this->isCurlResource($this->curlHandle)) {
            curl_close($this->curlHandle);
        }
        if ($this->isCurlMulti) {
            curl_multi_close($this->curlMultiHandle);
        }
        $this->resetCurlRequest();
    }

    /**
     * Reset curl on each new curlrequest to make sure old responses is no longer present.
     * @since 6.1.0
     */
    private function resetCurlRequest()
    {
        /** @noinspection PhpUndefinedMethodInspection */
        $this->strictResource = Flag::isFlag('strict_resource');
        $this->customHeaders = [];
        $this->curlResponseHeaders = [];
        $this->curlMultiErrors = null;
        $this->curlMultiResponse = null;
        $this->curlMultiHandle = null;
        $this->curlMultiHandleObjects = [];
        return $this;
    }

    /**
     * @param mixed $key
     * @param string $value
     * @param bool $static
     * @return CurlWrapper
     * @since 6.0
     */
    public function setCurlHeader($key = '', $value = '', $static = false)
    {
        if (is_array($key) && empty($value)) {
            // Handle as bulk if this request (for example) comes from NetWrapper.
            foreach ($key as $getKey => $getValue) {
                $this->setCurlHeader($getKey, $getValue);
            }
            return $this;
        }

        if (!empty($key)) {
            if (!is_array($key)) {
                $this->customPreHeaders[$key] = $value;
                if ($static) {
                    $this->customPreHeadersStatic[$key] = $value;
                }
            } else {
                foreach ($key as $arrayKey => $arrayValue) {
                    $this->customPreHeaders[$arrayKey] = $arrayValue;
                    if ($static) {
                        $this->customPreHeadersStatic[$arrayKey] = $arrayValue;
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Same as setCurlHeader but streamlined compatibility.
     *
     * @param string $key
     * @param string $value
     * @param false $static
     * @return $this
     * @since 6.1.2
     */
    public function setHeader($key = '', $value = '', $static = false)
    {
        return $this->setCurlHeader($key, $value, $static);
    }

    /**
     * @param $proxyAddress
     * @param int $proxyType Default: 0 = HTTP
     * @return CurlWrapper
     * @since 6.1.0
     */
    public function setProxy($proxyAddress, $proxyType = 0)
    {
        $this->CONFIG->setOption(WrapperCurlOpt::NETCURL_CURLOPT_PROXY, $proxyAddress);
        $this->CONFIG->setOption(WrapperCurlOpt::NETCURL_CURLOPT_PROXYTYPE, $proxyType);

        return $this;
    }

    /**
     * Enable instant exceptions on curl_multi errors.
     * @param bool $throwInstant
     * @return CurlWrapper
     * @since 6.1.0
     */
    public function setCurlMultiInstantException($throwInstant = true)
    {
        $this->instantCurlMultiErrors = $throwInstant;
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
     * @param WrapperConfig $config
     * @return CurlWrapper
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
     * @return CurlWrapper
     * @since 6.1.0
     */
    public function setAuthentication($username, $password, $authType = authType::BASIC)
    {
        $this->CONFIG->setAuthentication($username, $password, $authType);

        return $this;
    }

    /**
     * @param string $url
     * @return int
     * @throws ExceptionHandler
     * @since 6.0
     */
    public function getCode($url = '')
    {
        if (is_array($this->curlMultiResponse) &&
            count($this->curlMultiResponse) === 1
        ) {
            $url = (string)key($this->curlMultiResponse);
        }

        if (!$this->isCurlMulti) {
            $return = $this->curlHttpCode;
        } elseif (isset($this->curlMultiHttpCode[$url])) {
            $return = $this->curlMultiHttpCode[$url];
        } else {
            if (empty($url)) {
                throw new ExceptionHandler(
                    sprintf(
                        'Can not use %s without an url in a curl_multi request.',
                        __FUNCTION__
                    )
                );
            }
            $return = $this->curlMultiHttpCode;
        }

        return $return;
    }

    /**
     * Get parsed response. No longer using IO.
     *
     * @param string $url
     * @return mixed
     * @throws ExceptionHandler
     * @since 6.0
     */
    public function getParsed($url = '')
    {
        if (is_array($this->curlMultiResponse) && count($this->curlMultiResponse) === 1) {
            $url = (string)key($this->curlMultiResponse);
        }

        return GenericParser::getParsed(
            $this->getBody($url),
            $this->getHeader('content-type', $url)
        );
    }

    /**
     * @param string $url
     * @return mixed
     * @throws ExceptionHandler
     * @since 6.0
     */
    public function getBody($url = '')
    {
        if (is_array($this->curlMultiResponse) && count($this->curlMultiResponse) === 1) {
            $url = (string)key($this->curlMultiResponse);
        }

        if (!$this->isCurlMulti) {
            $return = $this->curlResponse;
        } elseif (isset($this->curlMultiResponse[$url])) {
            $return = $this->curlMultiResponse[$url];
        } else {
            // Sort out one request.
            if (empty($url)) {
                throw new ExceptionHandler(
                    sprintf(
                        'Can not use %s without an url in a curl_multi request.',
                        __FUNCTION__
                    )
                );
            }
            $return = $this->curlMultiResponse;
        }

        return $return;
    }

    /**
     * @param string $specificKey
     * @param string $specificUrl
     * @return string
     * @throws ExceptionHandler
     * @since 6.0
     */
    public function getHeader($specificKey = '', $specificUrl = '')
    {
        $return = [];

        $headerRequest = is_array($this->curlResponseHeaders) ? $this->curlResponseHeaders : [];

        if ($this->isCurlMulti) {
            if (is_array($this->curlResponseHeaders) && count($this->curlResponseHeaders) === 1) {
                $headerRequest = array_pop($this->curlResponseHeaders);
            } else {
                if (empty($specificUrl)) {
                    throw new ExceptionHandler(
                        'You must specify the URL from which you want to retrieve headers.',
                        Constants::LIB_MULTI_HEADER
                    );
                }
                $headerRequest = isset($this->curlResponseHeaders[$specificUrl]) &&
                is_array($this->curlResponseHeaders[$specificUrl]) ? $this->curlResponseHeaders[$specificUrl] : [];
            }
        }

        if (is_array($headerRequest) && count($headerRequest)) {
            foreach ($headerRequest as $headKey => $headArray) {
                // Something has pushed in duplicates of a header row, so lets pop one.
                if (count($headArray) > 1) {
                    $headArray = array_pop($headArray);
                }
                if (is_array($headArray) && count($headArray) === 1) {
                    if (!$specificKey) {
                        $return[] = sprintf("%s: %s", $headKey, array_pop($headArray));
                    } elseif (strtolower($specificKey) === strtolower($headKey)) {
                        $return[] = sprintf("%s", array_pop($headArray));
                    } elseif (strtolower($specificKey) === 'http') {
                        if (0 === stripos($headKey, "http")) {
                            $return[] = sprintf("%s", array_pop($headArray));
                        }
                    }
                }
            }
        }

        return implode("\n", $return);
    }

    /**
     * @param string $url
     * @param array $data
     * @param int $method
     * @param int $dataType
     * @return $this
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    public function request($url = '', $data = [], $method = requestMethod::METHOD_GET, $dataType = dataType::NORMAL)
    {
        $this->CONFIG->request($url, $data, $method, $dataType);
        $this->getCurlRequest();

        return $this;
    }

    /**
     * The curl_exec part.
     * @return $this
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    public function getCurlRequest()
    {
        $this->resetCurlRequest();
        $this->initCurlHandle();

        if (!$this->isCurlMulti && $this->isCurlResource($this->getCurlHandle())) {
            $this->curlResponse = curl_exec($this->curlHandle);
            // Friendly anti-backfire support.
            $this->curlHttpCode = curl_getinfo(
                $this->curlHandle,
                defined('CURLINFO_RESPONSE_CODE') ? CURLINFO_RESPONSE_CODE : 2097154
            );
            $this->getCurlException($this->curlHandle, $this->curlHttpCode);
        } elseif ($this->isCurlResource($this->curlMultiHandle)) {
            $this->curlMultiResponse = $this->getCurlMultiRequest();
            $this->getCurlExceptions();
        }

        return $this;
    }

    /**
     * From PHP 8.0 curl are returned as objects instead of resources (like CurlHandle, CurlMultiHandle, etc).
     * When investigation started, I did not know how much things was affected so this method was written to
     * easier change future changes.
     * @param $resource
     * @return bool
     * @since 6.1.1
     */
    private function isCurlResource($resource)
    {
        $return = !empty($resource);
        if ($this->strictResource) {
            $return = is_resource($resource) || is_object($resource);
        }

        return $return;
    }

    /**
     * Returns simple curl handle only.
     *
     * @return resource
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    public function getCurlHandle()
    {
        $return = null;
        if ($this->isCurlResource($this->curlHandle)) {
            $return = $this->curlHandle;
        } elseif ($this->isCurlResource($this->curlMultiHandle) && count($this->curlMultiHandleObjects)) {
            $return = $this->curlMultiHandle;
        } else {
            $return = $this->initCurlHandle()->getCurlHandle();
        }

        return $return;
    }

    /**
     * @param $curlHandle
     * @param $httpCode
     * @return CurlWrapper
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    private function getCurlException($curlHandle, $httpCode)
    {
        $errorString = curl_error($curlHandle);
        $errorCode = curl_errno($curlHandle);
        if ($errorCode) {
            throw new ExceptionHandler(
                sprintf(
                    'curl error (%s): %s',
                    $errorCode,
                    $errorString
                ),
                $errorCode,
                null,
                null,
                null,
                $this
            );
        }

        $httpHead = $this->getHeader('http');
        if (empty($errorString) && !empty($httpHead)) {
            $errorString = $httpHead;
        }
        $this->CONFIG->getHttpException($errorString, $httpCode, null, $this);

        return $this;
    }

    /**
     * @return array
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    private function getCurlMultiRequest()
    {
        $return = [];

        do {
            $status = curl_multi_exec($this->curlMultiHandle, $active);
            if ($active) {
                curl_multi_select($this->curlMultiHandle);
            }
        } while ($active && $status === CURLM_OK);

        foreach ($this->curlMultiHandleObjects as $url => $curlHandleObject) {
            $return[$url] = curl_multi_getcontent($curlHandleObject);
            $this->curlMultiHttpCode[$url] = GenericParser::getHttpHead($this->getHeader('http', $url));
            $this->getCurlMultiErrors($curlHandleObject, $url);
            curl_multi_remove_handle($this->curlMultiHandle, $curlHandleObject);
        }

        return $return;
    }

    /**
     * Get errors from a curl_multi handle.
     * Use getCurlException in future, if possible.
     *
     * @param $curlMultiHandle
     * @param $url
     * @return CurlWrapper
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    private function getCurlMultiErrors($curlMultiHandle, $url)
    {
        $internalCurlErrorCode = curl_errno($curlMultiHandle);
        $internalCurlErrorMessage = curl_error($curlMultiHandle);

        $curlHttpDataCode = GenericParser::getHttpHead($this->getHeader('http', $url));
        $curlHttpDataMessage = GenericParser::getHttpHead($this->getHeader('http', $url), 'message');

        if ($internalCurlErrorCode) {
            try {
                $this->CONFIG->getHttpException(
                    $internalCurlErrorMessage,
                    $internalCurlErrorCode,
                    null,
                    $this,
                    true
                );
            } catch (ExceptionHandler $curlMultiException) {
                $this->curlMultiErrors[$url] = $curlMultiException;
                // If instant curl errors are requested, throw on first error.
                if ($this->instantCurlMultiErrors) {
                    throw $curlMultiException;
                }
            }
        }

        try {
            $this->CONFIG->getHttpException(
                $curlHttpDataMessage,
                $curlHttpDataCode,
                null,
                $this
            );
        } catch (ExceptionHandler $curlMultiException) {
            $this->curlMultiErrors[$url] = $curlMultiException;
            // If instant curl errors are requested, throw on first error.
            if ($this->instantCurlMultiErrors) {
                throw $curlMultiException;
            }
        }

        return $this;
    }

    /**
     * @return $this
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    public function getCurlExceptions()
    {
        if (is_array($this->curlMultiErrors)) {
            if (count($this->curlMultiErrors) === 1) {
                /** @var ExceptionHandler $exceptionHandler */
                $exceptionHandler = array_pop($this->curlMultiErrors);
                throw new ExceptionHandler(
                    sprintf(
                        'curl_multi request found one error in %s: %s',
                        key($this->curlMultiErrors),
                        $exceptionHandler->getMessage()
                    ),
                    $exceptionHandler->getCode(),
                    $exceptionHandler,
                    null,
                    null,
                    $this
                );
            }

            if (count($this->curlMultiErrors) > 1) {
                throw new ExceptionHandler(
                    'Multiple errors discovered in curl_multi request. Details are attached to this ExceptionHandler.',
                    Constants::LIB_NETCURL_CURL_MULTI_EXCEPTION_DISCOVERY,
                    null,
                    null,
                    null,
                    $this
                );
            }
        }

        return $this;
    }

    /**
     * @param $curlHandle
     * @param $header
     * @return int
     * @since 6.1.0
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function getCurlHeaderRow($curlHandle, $header)
    {
        $headSplit = explode(':', $header, 2);
        $spacedSplit = explode(' ', $header, 2);

        if (count($headSplit) < 2) {
            if (count($spacedSplit) > 1) {
                if (!$this->isCurlMulti) {
                    $this->curlResponseHeaders[$spacedSplit[0]][] = trim($spacedSplit[1]);
                } else {
                    $urlinfo = curl_getinfo($curlHandle, CURLINFO_EFFECTIVE_URL);
                    $this->curlResponseHeaders[$urlinfo][$spacedSplit[0]][] = trim($spacedSplit[1]);
                }
            }
            return strlen($header);
        }

        if (!$this->isCurlMulti) {
            $this->curlResponseHeaders[$headSplit[0]][] = trim($headSplit[1]);
        } else {
            $urlinfo = curl_getinfo($curlHandle, CURLINFO_EFFECTIVE_URL);
            if (!isset($this->curlResponseHeaders[$urlinfo])) {
                $this->curlResponseHeaders[$urlinfo] = [];
            }
            $this->curlResponseHeaders[$urlinfo][$headSplit[0]][] = trim($headSplit[1]);
        }

        return strlen($header);
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
        }

        if (!is_null($return)) {
            return $return;
        }
        throw new ExceptionHandler(
            sprintf('Function "%s" not available.', $name),
            Constants::LIB_METHOD_OR_LIBRARY_UNAVAILABLE
        );
    }


    /**
     * @param $url
     * @throws ExceptionHandler
     * @since 6.1.0
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function throwExceptionInvalidUrl($url)
    {
        if (!empty($url)) {
            throw new ExceptionHandler(
                sprintf(
                    '%s is not a valid URL.',
                    $url
                ),
                Constants::LIB_INVALID_URL
            );
        }

        throw new ExceptionHandler(
            'URL must not be empty.',
            Constants::LIB_EMPTY_URL
        );
    }
}
