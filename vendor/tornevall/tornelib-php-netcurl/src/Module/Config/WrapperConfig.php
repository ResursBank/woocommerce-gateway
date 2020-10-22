<?php
/** @noinspection PhpUndefinedMethodInspection */
/** @noinspection PhpComposerExtensionStubsInspection */

/**
 * Copyright © Tomas Tornevall / Tornevall Networks. All rights reserved.
 * See LICENSE for license details.
 */

namespace TorneLIB\Module\Config;

use Exception;
use TorneLIB\Config\Flag;
use TorneLIB\Exception\Constants;
use TorneLIB\Exception\ExceptionHandler;
use TorneLIB\Flags;
use TorneLIB\Helpers\Browsers;
use TorneLIB\IO\Data\Content;
use TorneLIB\IO\Data\Strings;
use TorneLIB\Model\Type\authSource;
use TorneLIB\Model\Type\authType;
use TorneLIB\Model\Type\dataType;
use TorneLIB\Model\Type\requestMethod;
use TorneLIB\Utils\Ini;

/**
 * Class WrapperConfig
 * Configuration handler. All wrapper services that needs shared configuration like credentials, SSL setup, etc.
 *
 * @package Module\Config
 * @since 6.1.0
 * @version 6.1.2
 */
class WrapperConfig
{
    /**
     * @var string Requested URL.
     * @since 6.1.0
     */
    private $requestUrl = '';

    /**
     * @var array Postdata.
     * @since 6.1.0
     */
    private $requestData = [];

    /**
     * @var
     * @since 6.1.0
     */
    private $requestDataContainer;

    /**
     * @var int Default method. Postdata will in the case of GET generate postdata in the link.
     * @since 6.1.0
     */
    private $requestMethod = requestMethod::METHOD_GET;

    /**
     * Datatype to post in (default = uses ?key=value for GET and &key=value in body for POST).
     * @var int
     * @since 6.1.0
     */
    private $requestDataType = dataType::NORMAL;
    /**
     * @var array Options that sets up each request engine. On curl, it is CURLOPT.
     * @since 6.1.0
     */
    private $options = [];

    /**
     * @var string $currentWrapper
     * @since 6.1.0
     */
    private $currentWrapper;

    /**
     * @var bool
     * @since 6.1.0
     */
    private $isNetWrapper = false;

    /**
     * @var string
     * @since 6.1.0
     */
    private static $userAgentSignature;

    /**
     * Allow WrapperConfig to push out netcurl identification instead of a spoofed browser.
     * @var bool
     * @since 6.1.0
     */
    private $identifierAgent = false;

    /**
     * If netcurl identification is allowed, also allow PHP version to be pushed into the useragent, unless
     * it's already done somewhere else.
     * @var bool
     * @since 6.1.0
     */
    private $identifierAgentPhp = false;

    /**
     * @var bool
     * @since 6.1.0
     */
    private $isCustomUserAgent = false;

    /**
     * @var string
     * @since 6.1.0
     */
    private $proxyAddress = '';
    /**
     * @var int
     * @since 6.1.0
     */
    private $proxyType = 0;

    /**
     * @var array Initial SoapOptions
     *
     *   WSDL_CACHE_NONE = 0
     *   WSDL_CACHE_DISK = 1
     *   WSDL_CACHE_MEMORY = 2
     *   WSDL_CACHE_BOTH = 3
     *
     * @since 6.1.0
     */
    private $streamOptions = [
        'exceptions' => true,
        'trace' => true,
        'cache_wsdl' => 0,
        'stream_context' => null,
    ];

    /**
     * Static header content. Used to replicate through multiple instances but will never reset between requests.
     * @var array
     * @since 6.1.2
     */
    private $streamContextStatic = [];

    /**
     * Stored headers, used to replicate through multiple instances when NetWrapper is in use.
     * @var array
     * @since 6.1.2
     */
    private $storedHeaders = [];

    /**
     * @var array Authentication data.
     * @since 6.1.0
     */
    private $authData = ['username' => '', 'password' => '', 'type' => 1];

    /**
     * @var array Throwable HTTP codes.
     * @since 6.1.0
     */
    private $throwableHttpCodes;

    /**
     * @var array
     * @since 6.1.0
     */
    private $configData = [];

    /**
     * @var WrapperSSL SSL helper and context renderer.
     * @since 6.1.0
     */
    private $SSL;

    /**
     * User data that normally can not be overwritten more than once (when not exists).
     * @var array
     * @since 6.1.0
     */
    private $irreplacable = ['user_agent'];

    /**
     * If discovered soaprequest.
     * @var bool $isSoapRequest
     * @since 6.1.0
     */
    private $isSoapRequest = false;

    /**
     * If discovered stream request.
     * @var bool
     * @since 6.1.0
     */
    private $isStreamRequest = false;

    /**
     * @var
     * @since 6.1.0
     */
    private $authSource;

    /**
     * @var bool
     * @since 6.1.0
     */
    private $staging = false;

    /**
     * WrapperConfig constructor.
     * @since 6.1.0
     */
    public function __construct()
    {
        $this->SSL = new WrapperSSL();
        $this->setThrowableHttpCodes();
        $this->setCurlDefaults();
    }

    /**
     * @since 6.1.2
     */
    public function resetStreamData()
    {
        $this->streamOptions = [
            'exceptions' => true,
            'trace' => true,
            'cache_wsdl' => 0,
            'stream_context' => null,
        ];

        return $this;
    }

    /**
     * Returns compatibility functions from for example NetCurl 6.0.
     * @return array
     * @since 6.1.2
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    public function getCompatibilityMethods()
    {
        $return = [];
        if (class_exists('\TorneLIB\Compatibility\NetCurl\Methods')) {
            /** @noinspection PhpUndefinedClassInspection */
            $return = \TorneLIB\Compatibility\NetCurl\Methods::getCompatibilityMethods();
        }

        return $return;
    }

    /**
     * Preparing curl defaults in a way we like.
     * @return $this
     * @since 6.1.0
     */
    private function setCurlDefaults()
    {
        $this->setCurlConstants([
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_SSL_VERIFYPEER' => 1,
            'CURLOPT_SSL_VERIFYHOST' => 2,
            'CURLOPT_ENCODING' => 1,
            'CURLOPT_USERAGENT' => (new Browsers())->getBrowser(),
            'CURLOPT_SSLVERSION' => WrapperCurlOpt::NETCURL_CURL_SSLVERSION_DEFAULT,
            'CURLOPT_FOLLOWLOCATION' => false,
            'CURLOPT_HTTPHEADER' => ['Accept-Language: en'],
        ]);

        $this->setTimeout(10);

        return $this;
    }

    /**
     * Set up a list of which HTTP error codes that should be throwable (default: >= 400, <= 599).
     *
     * @param int $throwableMin Minimum value to throw on (Used with >=)
     * @param int $throwableMax Maxmimum last value to throw on (Used with <)
     * @return WrapperConfig
     * @since 6.0.6
     */
    public function setThrowableHttpCodes($throwableMin = 400, $throwableMax = 599)
    {
        $throwableMin = (int)$throwableMin > 0 ? $throwableMin : 400;
        $throwableMax = (int)$throwableMax > 0 ? $throwableMax : 599;
        $this->throwableHttpCodes[] = [$throwableMin, $throwableMax];

        return $this;
    }

    /**
     * Throw on any code that matches the store throwableHttpCode (use with setThrowableHttpCodes())
     *
     * @param string $httpMessageString
     * @param string $httpCode
     * @param null $previousException
     * @param null $extendException
     * @param bool $forceException
     * @throws ExceptionHandler
     * @since 6.0.6
     */
    public function getHttpException(
        $httpMessageString = '',
        $httpCode = '',
        $previousException = null,
        $extendException = null,
        $forceException = false
    ) {
        if (!is_array($this->throwableHttpCodes)) {
            $this->throwableHttpCodes = [];
        }
        foreach ($this->throwableHttpCodes as $codeListArray => $codeArray) {
            if ((isset($codeArray[1]) && $httpCode >= (int)$codeArray[0] && $httpCode <= (int)$codeArray[1]) || $forceException) {
                throw new ExceptionHandler(
                    sprintf(
                        'Error %d returned from server: "%s".',
                        $httpCode,
                        $httpMessageString
                    ),
                    $httpCode,
                    $previousException,
                    null,
                    null,
                    $extendException
                );
            }
        }
    }

    /**
     * Return the list of throwable http error codes (if set). For developers that needs to see which http codes
     * that is normally thrown on errors.
     *
     * @return array
     * @since 6.0.6
     */
    public function getThrowableHttpCodes()
    {
        return $this->throwableHttpCodes;
    }

    /**
     * Get current list of curlopts, etc.
     *
     * @return array
     * @since 6.1.0
     */
    public function getCurlDefaults()
    {
        return $this->options;
    }

    /**
     * While setting up curloptions, make sure no warnings leak from the setup if constants are missing in the system.
     * If the constants are missing, this probably means that curl is not properly installed. We've seen this in prior
     * versions of netcurl where missing constants either screams about missing constants or makes sites bail out.
     *
     * @param mixed $curlOptConstant
     * @return WrapperConfig
     * @since 6.1.0
     */
    private function setCurlConstants($curlOptConstant)
    {
        if (is_array($curlOptConstant)) {
            foreach ($curlOptConstant as $curlOptKey => $curlOptValue) {
                $constantValue = @constant($curlOptKey);
                if (empty($constantValue)) {
                    // Fall back to internally stored constants if curl is not there.
                    $constantValue = @constant('TorneLIB\Module\Config\WrapperCurlOpt::NETCURL_' . $curlOptKey);
                }
                $this->options[$constantValue] = $curlOptValue;
            }
        }

        return $this;
    }

    /**
     * Which URL is the current requested?
     *
     * @return string
     * @since 6.1.0
     */
    public function getRequestUrl()
    {
        return $this->requestUrl;
    }

    /**
     * Set up a new URL to be requested from the wrappers.
     *
     * @param string $requestUrl
     * @return WrapperConfig
     * @since 6.1.0
     */
    public function setRequestUrl($requestUrl)
    {
        $this->requestUrl = $requestUrl;

        return $this;
    }

    /**
     * Transform requested data into a proper format based on the content-type.
     *
     * @return array
     * @since 6.1.0
     */
    public function getRequestData()
    {
        $return = $this->requestData;

        // Return as is on string.
        if (!is_string($return)) {
            switch ($this->requestDataType) {
                case dataType::XML:
                    $this->requestDataContainer = (new Content())->getXmlFromArray($return);
                    $return = $this->requestDataContainer;
                    break;
                case dataType::JSON:
                    $this->requestDataContainer = $this->getJsonData($return);
                    $return = $this->requestDataContainer;
                    break;
                case dataType::NORMAL:
                    $requestQuery = '';
                    if ($this->requestMethod === requestMethod::METHOD_GET && !empty($this->requestData)) {
                        // Add POST data to request if anything else follows.
                        $requestQuery = '&';
                    }
                    $this->requestDataContainer = $this->requestData;
                    if (is_array($this->requestData) || is_object($this->requestData)) {
                        $httpQuery = http_build_query($this->requestData);
                        if (!empty($httpQuery)) {
                            $this->requestDataContainer = $requestQuery . $httpQuery;
                        }
                    }
                    $return = $this->requestDataContainer;
                    break;
                default:
                    break;
            }
        }

        return $return;
    }

    /**
     * Entry point.
     *
     * @return mixed
     * @since 6.1.1
     */
    public function getRequestDataContainer()
    {
        return $this->requestDataContainer;
    }

    /**
     * Handle json. Legacy. Maybe.
     *
     * @param $transformData
     * @return string
     * @since 6.1.0
     */
    private function getJsonData($transformData)
    {
        $return = $transformData;

        if (is_string($transformData)) {
            $stringTest = json_decode($transformData, false);
            if (is_object($stringTest) || is_array($stringTest)) {
                $return = $transformData;
            }
        } else {
            $return = json_encode($transformData);
        }

        return (string)$return;
    }

    /**
     * User input variables.
     *
     * @param array $requestData
     * @return WrapperConfig
     * @since 6.1.0
     */
    public function setRequestData($requestData)
    {
        $this->requestData = $requestData;

        return $this;
    }

    /**
     * Set up which method that should be used: POST, GET, DELETE, etc
     *
     * @param int $requestMethod
     * @return WrapperConfig
     * @since 6.1.0
     */
    public function setRequestMethod($requestMethod)
    {
        if (is_numeric($requestMethod)) {
            $this->requestMethod = $requestMethod;
        } else {
            $this->requestMethod = requestMethod::METHOD_GET;
        }

        return $this;
    }

    /**
     * Get information about the current request method that is used: POST, GET, DELETE, etc
     *
     * @return int
     * @since 6.1.0
     */
    public function getRequestMethod()
    {
        return $this->requestMethod;
    }

    /**
     * Flags registered in the Flags class.
     *
     * @return array
     * @since 6.1.0
     */
    public function getRequestFlags()
    {
        /** @noinspection PhpUndefinedMethodInspection */
        return Flags::_getAllFlags();
    }

    /**
     * @param array $requestFlags
     * @since 6.1.0
     */
    public function setRequestFlags($requestFlags)
    {
        /** @noinspection PhpUndefinedMethodInspection */
        Flags::_setFlags($requestFlags);
    }

    /**
     * @return array
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    public function getOptions()
    {
        $this->setHandledUserAgent();

        return $this->options;
    }

    /**
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    private function setHandledUserAgent()
    {
        $currentUserAgent = $this->getUserAgent();

        if ($this->getIdentifiers()) {
            if (!$this->getIsCustomUserAgent()) {
                // Reset if not custom already.
                $currentUserAgent = '';
            }

            $currentUserAgentArray = [
                $currentUserAgent,
                sprintf('netcurl-%s', NETCURL_VERSION),
                $this->getCurrentWrapperClass(true),
                $this->getNetWrapperString(),
                $this->getPhpString(),
            ];

            $currentUserAgent = $this->getUserAgentsMerged($currentUserAgentArray);
        }

        $this->setUserAgent($currentUserAgent);
    }

    /**
     * @param $userAgentArray
     * @return string
     * @since 6.1.0
     */
    private function getUserAgentsMerged($userAgentArray)
    {
        $return = [];

        if (is_array($userAgentArray)) {
            foreach ($userAgentArray as $item) {
                if (!empty($item)) {
                    $return[] = $item;
                }
            }
        }

        return implode(' +', $return);
    }

    /**
     * @return string
     * @since 6.1.0
     */
    private function getPhpString()
    {
        if ($this->identifierAgentPhp) {
            $phpString = sprintf('PHP-%s', PHP_VERSION);
        } else {
            $phpString = '';
        }
        return $phpString;
    }

    private function getNetWrapperString()
    {
        return $this->isNetWrapper ? 'NetWrapper' : 'Instant';
    }

    /**
     * Update stream options (which transforms into stream_context).
     *
     * @param array $streamOptions
     * @return WrapperConfig
     * @since 6.1.0
     */
    public function setStreamOptions($streamOptions)
    {
        $this->streamOptions = $streamOptions;

        return $this;
    }

    /**
     * @param $key
     * @param $value
     * @param null $subKey
     * @return WrapperConfig
     * @since 6.1.0
     */
    public function setStreamContext($key, $value, $subKey = null)
    {
        $currentStreamContext = $this->getStreamContext();

        if (is_resource($currentStreamContext)) {
            $currentStreamContext = stream_context_get_options($currentStreamContext);
        } else {
            $currentStreamContext = [];
        }

        if (is_array($currentStreamContext)) {
            if (is_null($subKey)) {
                if (
                    (isset($currentStreamContext[$key]) && $this->canOverwrite($key)) ||
                    !isset($currentStreamContext[$key])
                ) {
                    $currentStreamContext[$key] = $value;
                }
            } else {
                if (!isset($currentStreamContext[$subKey])) {
                    $currentStreamContext[$subKey] = [];
                }
                if (
                    (
                        isset($currentStreamContext[$subKey][$key]) && $this->canOverwrite($key)
                    ) ||
                    !isset($currentStreamContext[$subKey][$key])
                ) {
                    if (!isset($currentStreamContext[$subKey][$key])) {
                        $currentStreamContext[$subKey][$key] = $value;
                    } elseif ($key === 'header') {
                        $currentStreamContext[$subKey][$key] .= "\r\n" . $value;
                    } else {
                        // Overwrite if not header context.
                        $currentStreamContext[$subKey][$key] = $value;
                    }
                }
            }
        }

        // This can throw an exception if something is not properly set.
        // stream_context_create(): options should have the form ["wrappername"]["optionname"] = $value
        $this->streamOptions['stream_context'] = stream_context_create($currentStreamContext);

        return $this;
    }

    /**
     * @param $key
     * @return bool
     * @since 6.1.0
     */
    private function canOverwrite($key)
    {
        $dynamicOverwrites = Flag::getFlag('canoverwrite');

        $return = in_array(
            $key,
            array_map('strtolower', $this->irreplacable),
            false
        ) ? false : true;

        // Dynamic override.
        if (
            is_array($dynamicOverwrites) &&
            in_array(
                $key,
                $dynamicOverwrites,
                false
            )
        ) {
            $return = true;
        }

        return $return;
    }

    /**
     * @param bool $decoded
     * @return mixed
     * @since 6.1.0
     */
    public function getStreamContext($decoded = false)
    {
        return !$decoded ? $this->streamOptions['stream_context'] : stream_context_get_options($this->streamOptions['stream_context']);
    }

    /**
     * @param $contextBlock
     * @return array|null
     * @since 6.1.2
     */
    public function getContentFromStreamContext($contextBlock)
    {
        $return = null;
        if (is_resource($contextBlock)) {
            $return = stream_context_get_options($contextBlock);
        }
        return $return;
    }

    /**
     * Get current soapoptions.
     *
     * @return array
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    public function getStreamOptions()
    {
        $this->setRenderedStreamOptions();
        $this->setStreamContext('ssl', $this->SSL->getContext());

        return $this->streamOptions;
    }

    /**
     * Prepare streamoption array.
     *
     * @return $this
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    private function setRenderedStreamOptions()
    {
        $this->setRenderedUserAgent();

        return $this;
    }

    /**
     * Handle user-agent in streams.
     *
     * @return $this
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    private function setRenderedUserAgent()
    {
        $this->setDualStreamHttp('user_agent', $this->getUserAgent());

        return $this;
    }

    /**
     * @param array $options
     * @return WrapperConfig
     * @since 6.1.0
     */
    public function setOptions($options)
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Find out if there is a predefined constant for CURL-options and if the curl library actually exists.
     * If the constants don't exist, fall back to NETCURL constants so that we can still fetch the setup.
     *
     * @param $key
     * @return mixed|null
     * @since 6.1.0
     */
    private function getOptionCurl($key)
    {
        $return = null;

        if (false !== strpos($key, 'CURL')) {
            $constantValue = @constant('TorneLIB\Module\Config\WrapperCurlOpt::NETCURL_' . $key);
            if (!empty($constantValue)) {
                $return = $constantValue;
            }
        }

        return $return;
    }

    /**
     * @param $key
     * @param $value
     * @param bool $isSoap
     * @return $this
     * @since 6.1.0
     */
    public function setOption($key, $value, $isSoap = false)
    {
        $preKey = $this->getOptionCurl($key);
        if (!empty($preKey)) {
            $key = $preKey;
        }

        if (!$isSoap) {
            $this->options[$key] = $value;
        } else {
            $this->streamOptions[$key] = $value;
        }

        return $this;
    }

    /**
     * @param $proxyAddress
     * @param int $proxyType
     * @return WrapperConfig
     * @since 6.1.0
     */
    public function setProxy($proxyAddress, $proxyType = 0)
    {
        $this->proxyAddress = $proxyAddress;
        $this->proxyType = $proxyType;

        $this->setOption(WrapperCurlOpt::NETCURL_CURLOPT_PROXY, $proxyAddress);
        $this->setOption(WrapperCurlOpt::NETCURL_CURLOPT_PROXYTYPE, $proxyType);

        // If the current wrapper class that is used is. Saved for later use.
        //if ($this->getCurrentWrapperClass(true) === 'SimpleStreamWrapper') {}
        $this->setDualStreamHttp(
            'proxy',
            sprintf(
                'tcp://%s',
                $proxyAddress
            )
        );
        $this->setDualStreamHttp('request_fulluri', true);

        return $this;
    }

    /**
     * @return string
     * @since 6.1.0
     */
    public function getProxy()
    {
        return $this->proxyAddress;
    }

    /**
     * @return int
     * @since 6.1.0
     */
    public function getProxyType()
    {
        return $this->proxyType;
    }

    /**
     * @param $key
     * @param bool $isSoap
     * @return mixed
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    public function getOption($key, $isSoap = false)
    {
        $preKey = $this->getOptionCurl($key);
        if (!empty($preKey)) {
            $key = $preKey;
        }

        if (!$isSoap) {
            if (isset($this->options[$key])) {
                return $this->options[$key];
            }
        } elseif (isset($this->streamOptions[$key])) {
            return $this->streamOptions[$key];
        }

        throw new ExceptionHandler(
            sprintf('%s: Option "%s" not set.', __CLASS__, $key),
            Constants::LIB_UNHANDLED
        );
    }

    /**
     * @param $isSoapRequest
     * @since 6.1.0
     */
    public function setSoapRequest($isSoapRequest)
    {
        $this->isSoapRequest = $isSoapRequest;
    }

    /**
     * @param $isStreamRequest
     * @since 6.1.0
     */
    public function setStreamRequest($isStreamRequest)
    {
        $this->isStreamRequest = $isStreamRequest;
    }

    /**
     * @return bool
     * @since 6.1.0
     */
    public function getSoapRequest()
    {
        return $this->isSoapRequest;
    }

    /**
     * @return bool
     */
    public function getStreamRequest()
    {
        return $this->isStreamRequest;
    }

    /**
     * @param $key
     * @param $value
     * @return $this
     * @since 6.1.0
     */
    public function setStreamOption($key, $value)
    {
        return $this->setOption($key, $value, true);
    }

    /**
     * @param $key
     * @param $value
     * @return $this
     */
    public function setDualStreamHttp($key, $value)
    {
        $this->setStreamContext($key, $value, 'https');
        $this->setStreamContext($key, $value, 'http');

        return $this;
    }

    /**
     * @param string $key
     * @param string $value
     * @param false $static
     * @return $this
     * @since 6.1.2
     */
    public function setHeader($key = '', $value = '', $static = false)
    {
        $this->setDualStreamHttp('header', sprintf('%s: %s', $key, $value));
        $this->storedHeaders[$key] = $value;

        if ($static) {
            $this->streamContextStatic[$key] = $value;
        }

        return $this;
    }

    /**
     * If NetWrapper is the primary engine, we need to extract all headers from this section.
     *
     * @return array
     * @since 6.1.2
     */
    public function getHeader()
    {
        return array_merge($this->storedHeaders, $this->streamContextStatic);
    }

    /**
     * @return array
     * @since 6.1.2
     */
    public function getStreamHeader()
    {
        if (is_array($this->streamContextStatic)) {
            foreach ($this->streamContextStatic as $key => $value) {
                $this->setHeader($key, $value);
            }
        }

        return $this->streamContextStatic;
    }

    /**
     * @param $key
     * @return mixed
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    public function getStreamOption($key)
    {
        return $this->getOption($key, true);
    }

    /**
     * @param $key
     * @return $this
     * @since 6.1.0
     */
    public function deleteOption($key)
    {
        $preKey = $this->getOptionCurl($key);
        if (!empty($preKey)) {
            $key = $preKey;
        }

        if (isset($this->options[$key])) {
            unset($this->options[$key]);
        }

        return $this;
    }

    /**
     * Replace an option with another.
     *
     * @param $key
     * @param $value
     * @param $replace
     * @return $this
     * @since 6.1.0
     */
    public function replaceOption($key, $value, $replace)
    {
        $this->deleteOption($replace);
        $this->setOption($key, $value);
        return $this;
    }

    /**
     * Datatype of request (json, etc).
     * @param $requestDataType
     * @since 6.1.0
     */
    public function setRequestDataType($requestDataType)
    {
        $this->requestDataType = $requestDataType;
    }

    /**
     * Datatype of request (json, etc).
     * @return int
     * @since 6.1.0
     */
    public function getRequestDataType()
    {
        return $this->requestDataType;
    }

    /**
     * Set authdata.
     *
     * @param $username
     * @param $password
     * @param int $authType
     * @param int $authSource
     * @return WrapperConfig
     * @since 6.1.0
     */
    public function setAuthentication(
        $username,
        $password,
        $authType = authType::BASIC,
        $authSource = authSource::NORMAL
    ) {
        $this->authSource = $authSource;

        switch ($authSource) {
            case authSource::STREAM:
                if ($authType === authType::BASIC) {
                    $this->setDualStreamHttp(
                        'header',
                        'Authorization: Basic ' . base64_encode("$username:$password")
                    );
                }
                break;
            case authSource::SOAP:
                $this->authData['login'] = $username;
                $this->authData['password'] = $password;
                $this->setStreamOption('login', $this->authData['login']);
                $this->setStreamOption('password', $this->authData['password']);
                break;
            default:
                $this->authData['username'] = $username;
                $this->authData['password'] = $password;
                $this->authData['type'] = $authType;
                // Always push streamOptions for user/pass into the default flow to be compatible with soap
                // setups.
                $this->setStreamOption('login', $this->authData['username']);
                $this->setStreamOption('password', $this->authData['password']);
                break;
        }

        return $this;
    }

    /**
     * Replace current authdata manually to stream source if the default is set by mistake.
     *
     * @return $this
     * @since 6.1.0
     */
    public function setAuthStream()
    {
        if ($this->authSource === authSource::NORMAL) {
            $this->setAuthentication(
                $this->authData['username'],
                $this->authData['password'],
                $this->authData['type'],
                authSource::STREAM
            );
        }

        return $this;
    }

    /**
     * Get authdata.
     *
     * @return array
     * @since 6.1.0
     */
    public function getAuthentication()
    {
        return $this->authData;
    }

    /**
     * @param int $timeout Defaults to the default connect timeout in curl (300).
     * @param bool $useMillisec Set timeouts in milliseconds instead of seconds.
     * @return $this
     * @link https://curl.haxx.se/libcurl/c/curl_easy_setopt.html
     * @since 6.1.0
     */
    private function setTimeout($timeout = 300, $useMillisec = false)
    {
        /**
         * CURLOPT_TIMEOUT (Entire request) Everything has to be established and get finished on this time limit.
         * CURLOPT_CONNECTTIMEOUT (Connection phase) We set this to half the time of the entire timeout request.
         * CURLOPT_ACCEPTTIMEOUT (Waiting for connect back to be accepted). Defined in MS only.
         *
         * @link https://curl.haxx.se/libcurl/c/curl_easy_setopt.html
         */

        // Using internal WrapperCurlOpts if curl is not a present driver. Otherwise, this
        // setup may be a showstopper that no other driver can use.
        if (!$useMillisec) {
            $this->replaceOption(
                WrapperCurlOpt::NETCURL_CURLOPT_CONNECTTIMEOUT,
                ceil($timeout / 2),
                WrapperCurlOpt::NETCURL_CURLOPT_CONNECTTIMEOUT_MS
            );
            $this->replaceOption(
                WrapperCurlOpt::NETCURL_CURLOPT_TIMEOUT,
                ceil($timeout),
                WrapperCurlOpt::NETCURL_CURLOPT_TIMEOUT_MS
            );
        } else {
            $this->replaceOption(
                WrapperCurlOpt::NETCURL_CURLOPT_CONNECTTIMEOUT_MS,
                ceil($timeout / 2),
                WrapperCurlOpt::NETCURL_CURLOPT_CONNECTTIMEOUT
            );
            $this->replaceOption(
                WrapperCurlOpt::NETCURL_CURLOPT_TIMEOUT_MS,
                ceil($timeout),
                WrapperCurlOpt::NETCURL_CURLOPT_TIMEOUT
            );
        }

        $this->setStreamContext('timeout', (int)ceil($timeout), 'http');
        $this->setStreamContext('connection_timeout', (int)ceil($timeout / 2), 'http');

        return $this;
    }

    /**
     * @param $userAgentString
     * @return $this
     * @since 6.1.0
     */
    public function setUserAgent($userAgentString)
    {
        $this->isCustomUserAgent = true;

        $this->setOption(
            WrapperCurlOpt::NETCURL_CURLOPT_USERAGENT,
            $userAgentString
        );

        return $this;
    }

    /**
     * @return bool
     * @since 6.1.0
     */
    public function getIsCustomUserAgent()
    {
        return $this->isCustomUserAgent;
    }

    /**
     * Allows strict identification in user-agent header.
     * @param $activate
     * @param bool $allowPhpRelease
     * @since 6.1.0
     */
    public function setIdentifiers($activate, $allowPhpRelease = false)
    {
        $this->identifierAgent = $activate;
        $this->identifierAgentPhp = $allowPhpRelease;
    }

    /**
     * Status of identifierAgent, if enable or not.
     * @return bool
     * @since 6.1.0
     */
    public function getIdentifiers()
    {
        return $this->identifierAgent;
    }

    /**
     * @return mixed
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    public function getUserAgent()
    {
        $globalSignature = self::getSignature();
        if (!empty($globalSignature)) {
            return $globalSignature;
        }

        $return = $this->getOption(WrapperCurlOpt::NETCURL_CURLOPT_USERAGENT);

        if ($this->getSoapRequest() || $this->getStreamRequest()) {
            $currentStreamContext = $this->getStreamContext();
            if (!is_null($currentStreamContext)) {
                $currentStreamContext = stream_context_get_options($currentStreamContext);
            }

            // If it is already set from another place.
            if (isset($currentStreamContext['http']['user_agent'])) {
                $return = $currentStreamContext['http']['user_agent'];
            }
        }

        return $return;
    }

    /**
     * @param bool $staging
     * @return WrapperConfig
     * @since 6.1.0
     */
    public function setStaging($staging = true)
    {
        $this->staging = $staging;
        if (!$staging) {
            $this->setWsdlCache(3);
        } else {
            $this->setWsdlCache(0);
        }
        return $this;
    }

    /**
     * @return bool
     * @since 6.1.0
     */
    public function getStaging()
    {
        return $this->staging;
    }

    /**
     * @param bool $isProduction
     * @return $this
     * @since 6.1.0
     */
    public function setProduction($isProduction = true)
    {
        $this->setStaging($isProduction ? false : true);
        return $this;
    }

    /**
     * @return bool
     * @since 6.1.0
     */
    public function getProduction()
    {
        return !$this->getStaging();
    }

    /**
     * Returns internal information about the configured timeouts.
     *
     * @return array
     * @since 6.1.0
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function getTimeout()
    {
        $cTimeout = null;
        $eTimeout = null;

        $timeoutIsMillisec = false;
        if (isset($this->options[WrapperCurlOpt::NETCURL_CURLOPT_CONNECTTIMEOUT])) {
            $cTimeout = $this->options[WrapperCurlOpt::NETCURL_CURLOPT_CONNECTTIMEOUT]; // connectTimeout
        }
        if (isset($this->options[WrapperCurlOpt::NETCURL_CURLOPT_CONNECTTIMEOUT_MS])) {
            $cTimeout = $this->options[WrapperCurlOpt::NETCURL_CURLOPT_CONNECTTIMEOUT_MS]; // connectTimeout
            $timeoutIsMillisec = true;
        }

        if (isset($this->options[WrapperCurlOpt::NETCURL_CURLOPT_TIMEOUT])) {
            $eTimeout = $this->options[WrapperCurlOpt::NETCURL_CURLOPT_TIMEOUT];  // entireTimeout
        }
        if (isset($this->options[WrapperCurlOpt::NETCURL_CURLOPT_TIMEOUT_MS])) {
            $eTimeout = $this->options[WrapperCurlOpt::NETCURL_CURLOPT_TIMEOUT_MS];  // entireTimeout
            $timeoutIsMillisec = true;
        }

        return [
            'CONNECT' => $cTimeout,
            'REQUEST' => $eTimeout,
            'MILLISEC' => $timeoutIsMillisec,
        ];
    }

    /**
     * Quickset WSDL cache.
     *
     *   WSDL_CACHE_NONE = 0
     *   WSDL_CACHE_DISK = 1
     *   WSDL_CACHE_MEMORY = 2
     *   WSDL_CACHE_BOTH = 3
     *
     * @param int $cacheSet
     * @param null $ttlCache Cache lifetime. If null, this won't be set.
     * @return WrapperConfig
     * @since 6.1.0
     */
    public function setWsdlCache($cacheSet = 0, $ttlCache = null)
    {
        if (PHP_VERSION_ID >= 70000 && PHP_VERSION_ID < 70100) {
            // PHP 7.0 generates exit code 1 on this row, unless it's a string - don't ask why.
            $this->streamOptions['cache_wsdl'] = (string)$cacheSet;
        } else {
            $this->streamOptions['cache_wsdl'] = $cacheSet;
        }

        if ((int)$ttlCache > 0 && (new Ini())->getIniSettable('soap.wsdl_cache_ttl')) {
            ini_set('soap.wsdl_cache_ttl', $ttlCache);
        }

        return $this;
    }

    /**
     * @return mixed|null
     * @since 6.1.0
     */
    public function getWsdlCache()
    {
        return isset($this->streamOptions['cache_wsdl']) ? $this->streamOptions['cache_wsdl'] : null;
    }

    /**
     * @param array $funcArgs
     * @return bool
     * @throws Exception
     */
    public function getCompatibilityArguments($funcArgs = [])
    {
        $return = false;

        foreach ($funcArgs as $funcIndex => $funcValue) {
            switch ($funcIndex) {
                case 0:
                    if (!empty($funcValue)) {
                        $this->setRequestUrl($funcValue);
                        $return = true;
                    }
                    break;
                case 1:
                    if (is_array($funcValue) && count($funcValue)) {
                        $this->setRequestData($funcValue);
                        $return = true;
                    }
                    break;
                case 2:
                    $this->setRequestMethod($funcValue);
                    $return = true;
                    break;
                case 3:
                    $this->setRequestFlags(is_array($funcValue) ? $funcValue : []);
                    $return = true;
                    break;
            }
        }

        return $return;
    }

    /**
     * @param $isWrapped
     * @return $this
     */
    public function setNetWrapper($isWrapped)
    {
        $this->isNetWrapper = $isWrapped;

        return $this;
    }

    /**
     * Setting useragent statically and on global level.
     *
     * @param $userAgentSignature
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    public static function setSignature($userAgentSignature)
    {
        if (defined('WRAPPERCONFIG_NO_SIGNATURE')) {
            throw new ExceptionHandler(
                'You are not allowed to set global signature.',
                Constants::LIB_UNHANDLED
            );
        }
        self::$userAgentSignature = $userAgentSignature;
    }

    /**
     * Remove static useragent.
     * @since 6.1.0
     */
    public static function deleteSignature()
    {
        self::$userAgentSignature = null;
    }

    /**
     * @return string
     * @since 6.1.0
     */
    public static function getSignature()
    {
        if (defined('NO_SIGNATURE')) {
            return null;
        }

        return self::$userAgentSignature;
    }

    /**
     * @param $userAgentSignatureString
     * @return $this
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    public function setUserAgentSignature($userAgentSignatureString)
    {
        self::setSignature($userAgentSignatureString);
        return $this;
    }

    /**
     * @return string
     * @since 6.1.0
     */
    public function getUserAgentSignature()
    {
        return self::getSignature();
    }

    /**
     * @return string
     * @since 6.1.0
     */
    public function getCurrentWrapper()
    {
        return $this->currentWrapper;
    }

    /**
     * @param $currentWrapper
     * @return WrapperConfig
     * @since 6.1.0
     */
    public function setCurrentWrapper($currentWrapper)
    {
        if (!empty($currentWrapper)) {
            $this->currentWrapper = $currentWrapper;
        }

        return $this;
    }

    /**
     * @param bool $short
     * @return string
     * @since 6.1.0
     */
    public function getCurrentWrapperClass($short = false)
    {
        return !$short ? $this->currentWrapper : $this->getShortWrapperClass($this->currentWrapper);
    }

    /**
     * @param $namespaceClassName
     * @return mixed
     * @since 6.1.0
     */
    private function getShortWrapperClass($namespaceClassName)
    {
        $return = $namespaceClassName;

        if (!empty($this->currentWrapper)) {
            $wrapperClassExplode = explode('\\', $this->currentWrapper);
            if (is_array($wrapperClassExplode) && count($wrapperClassExplode)) {
                $return = $wrapperClassExplode[count($wrapperClassExplode) - 1];
            }
        }

        return $return;
    }

    /**
     * @param string $url
     * @param array $data
     * @param int $method
     * @param int $dataType
     * @return $this
     * @since 6.1.0
     */
    public function request($url = '', $data = [], $method = requestMethod::METHOD_GET, $dataType = dataType::NORMAL)
    {
        if (!empty($url)) {
            $this->setRequestUrl($url);
        }
        /** @noinspection CallableParameterUseCaseInTypeContextInspection */
        /** @var mixed $data */
        if ((is_string($data) && $data !== '') || (is_array($data) && count($data))) {
            $this->setRequestData($data);
        }

        if ($this->getRequestMethod() !== $method) {
            $this->setRequestMethod($method);
        }

        if ($this->getRequestDataType() !== $dataType) {
            $this->setRequestDataType($dataType);
        }

        return $this;
    }

    /**
     * Internal configset magics.
     *
     * @param $name
     * @param $arguments
     * @return $this|mixed
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    public function __call($name, $arguments)
    {
        $methodType = '';
        $cutAfter = 3;
        $allowedTypes = ['get', 'is', 'set'];
        foreach ($allowedTypes as $item) {
            $testItem = @substr($name, 0, strlen($item));
            if (in_array($testItem, $allowedTypes, false)) {
                $methodType = $testItem;
                $cutAfter = strlen($item);
            }
        }
        $methodName = (new Strings())->getCamelCase(substr($name, $cutAfter));

        switch (strtolower($methodType)) {
            case 'set':
                if (method_exists($this, sprintf('set%s', ucfirst($methodName)))) {
                    call_user_func_array(
                        [
                            $this,
                            sprintf('set%s', ucfirst($methodName)),
                        ],
                        $arguments
                    );
                }

                $this->configData[$methodName] = array_pop($arguments);
                break;
            case 'get' || 'is':
                if (method_exists($this, sprintf('%s%s', 'get', ucfirst($methodName))) ||
                    method_exists($this, sprintf('%s%s', 'is', ucfirst($methodName)))
                ) {
                    return call_user_func_array(
                        [
                            $this,
                            sprintf(
                                'get%s',
                                ucfirst($methodName)
                            ),
                        ],
                        []
                    );
                }

                if (isset($this->configData[$methodName])) {
                    return $this->configData[$methodName];
                }

                throw new ExceptionHandler('Variable not set.', Constants::LIB_CONFIGWRAPPER_VAR_NOT_SET);
            default:
                break;
        }

        return $this;
    }
}
