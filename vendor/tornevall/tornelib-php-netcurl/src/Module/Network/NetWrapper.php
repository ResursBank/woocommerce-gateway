<?php
/**
 * Copyright © Tomas Tornevall / Tornevall Networks. All rights reserved.
 * See LICENSE for license details.
 */

namespace TorneLIB\Module\Network;

use ReflectionException;
use TorneLIB\Exception\Constants;
use TorneLIB\Exception\ExceptionHandler;
use TorneLIB\IO\Data\Arrays;
use TorneLIB\IO\Data\Content;
use TorneLIB\Model\Interfaces\WrapperInterface;
use TorneLIB\Model\Type\authType;
use TorneLIB\Model\Type\dataType;
use TorneLIB\Model\Type\requestMethod;
use TorneLIB\Module\Config\WrapperConfig;
use TorneLIB\Module\Config\WrapperDriver;
use TorneLIB\Utils\Generic;
use TorneLIB\Utils\Security;

/**
 * Class NetWrapper
 * Taking over from v6.0 MODULE_CURL.
 *
 * @package TorneLIB\Module\Network
 * @version 6.1.0
 */
class NetWrapper implements WrapperInterface
{
    /**
     * @var WrapperConfig $CONFIG
     * @since 6.1.0
     */
    private $CONFIG;

    /**
     * @var bool
     * @since 6.1.0
     */
    private $isSoapRequest = false;

    /**
     * @var string $version Internal version.
     * @since 6.1.0
     */
    private $version;

    /**
     * Chosen wrapper.
     * @var string $selectedWrapper
     * @since 6.1.0
     */
    private $selectedWrapper;

    /**
     * @var array
     * @since 6.1.0
     */
    private $multiRequest = [];

    /**
     * @var bool
     * @since 6.1.0
     */
    private $allowInternalMulti = false;

    /**
     * @var bool
     * @since 6.1.0
     */
    private $instantCurlMultiErrors = false;

    public function __construct()
    {
        $this->initializeWrappers();
        return $this;
    }

    /**
     * @since 6.1.0
     */
    private function initializeWrappers()
    {
        WrapperDriver::initializeWrappers();
        $this->CONFIG = new WrapperConfig();
        return $this;
    }

    /**
     * Allows strict identification in user-agent header.
     * @param $activation
     * @param $allowPhpRelease
     * @return NetWrapper
     * @since 6.1.0
     */
    public function setIdentifiers($activation, $allowPhpRelease = false)
    {
        $this->CONFIG->setIdentifiers($activation, $allowPhpRelease);

        return $this;
    }

    /**
     * @return string
     * @throws ReflectionException
     * @since 6.1.0
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
     * @return bool
     * @since 6.1.0
     */
    public function getIsSoap()
    {
        return $this->isSoapRequest;
    }

    /**
     * @var WrapperInterface $instance The instance is normally the wrapperinterface.
     * @since 6.1.0
     */
    private $instance;

    /**
     * Get list of internal wrappers.
     *
     * @return mixed
     * @since 6.1.0
     */
    public function getWrappers()
    {
        return WrapperDriver::getWrappers();
    }

    /**
     * @return bool
     * @since 6.1.0
     */
    public function getAllowInternalMulti()
    {
        return $this->allowInternalMulti;
    }

    /**
     * @param bool $allowInternalMulti
     * @return NetWrapper
     * @since 6.1.0
     */
    public function setAllowInternalMulti($allowInternalMulti = false)
    {
        $this->allowInternalMulti = $allowInternalMulti;

        return $this;
    }

    /**
     * @param WrapperConfig $config
     * @return NetWrapper
     * @since 6.1.0
     */
    public function setConfig($config)
    {
        /** @var WrapperConfig CONFIG */
        $this->CONFIG = $config;

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
     * @return NetWrapper
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
     * Register an external wrapper/module/communicator.
     *
     * @since 6.1.0
     */
    public function register($wrapperClass, $tryFirst = false)
    {
        return WrapperDriver::register($wrapperClass, $tryFirst);
    }

    /**
     * Return body of the request.
     *
     * @param string $url
     * @return mixed
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    public function getBody($url = '')
    {
        if (($mInstance = $this->getMultiInstance($url)) && method_exists($mInstance, __FUNCTION__)) {
            return $mInstance->{__FUNCTION__}();
        } elseif (method_exists($this->instance, __FUNCTION__)) {
            return $this->instance->{__FUNCTION__}();
        }

        throw new ExceptionHandler(
            sprintf(
                '%s instance %s does not support %s.',
                __CLASS__,
                WrapperDriver::getInstanceClass(),
                __FUNCTION__
            )
        );
    }

    /**
     * @param string $url
     * @return mixed
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    public function getParsed($url = '')
    {
        if (($mInstance = $this->getMultiInstance($url)) && method_exists($mInstance, 'getParsed')) {
            return $mInstance->getParsed();
        } elseif (method_exists($this->instance, 'getBody')) {
            return $this->instance->getParsed();
        }

        throw new ExceptionHandler(
            sprintf(
                '%s instance %s does not support %s.',
                __CLASS__,
                WrapperDriver::getInstanceClass(),
                __FUNCTION__
            )
        );
    }

    /**
     * @param string $url
     * @return mixed|null
     * @since 6.1.0
     */
    private function getMultiInstance($url = '')
    {
        $return = null;
        if (isset($this->multiRequest[$url])) {
            $return = $this->multiRequest[$url];
        } elseif (is_object($this->instance)) {
            $return = $this->instance;
        }

        return $return;
    }

    /**
     * @param string $url
     * @return mixed
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    public function getCode($url = '')
    {
        if (($mInstance = $this->getMultiInstance($url)) && method_exists($mInstance, __FUNCTION__)) {
            return $mInstance->{__FUNCTION__}();
        } elseif (method_exists($this->instance, __FUNCTION__)) {
            return $this->instance->{__FUNCTION__}();
        }

        throw new ExceptionHandler(
            sprintf(
                '%s instance %s does not support %s.',
                __CLASS__,
                WrapperDriver::getInstanceClass(),
                __FUNCTION__
            )
        );
    }

    /**
     * @param $url
     * @return WrapperInterface
     * @since 6.1.0
     */
    public function getWrapper($url)
    {
        return $this->getMultiInstance($url);
    }

    /**
     * @param $requestArray
     * @return array
     * @since 6.1.0
     */
    private function handleMultiUrl($requestArray)
    {
        $return = [];
        $isAssocList = (new Arrays())->isAssoc($requestArray);
        foreach ($requestArray as $requestUrl => $requestData) {
            $currentRequestUrl = $requestUrl;
            if (!$isAssocList) {
                $currentRequestUrl = $requestData;
            }
            if (isset($requestData[3]) &&
                is_object($requestData[3]) &&
                get_class($requestData[3]) === 'TorneLIB\Module\Config\WrapperConfig'
            ) {
                $this->CONFIG = $requestData[3];
            }
            $return[$currentRequestUrl] = $this->request(
                $currentRequestUrl,
                isset($requestData[0]) ? $requestData[0] : [],
                isset($requestData[1]) ? $requestData[1] : requestMethod::METHOD_GET,
                isset($requestData[2]) ? $requestData[2] : dataType::NORMAL
            );
        }
        return $return;
    }

    /**
     * @param $url
     * @return NetWrapper
     * @since 6.1.0
     */
    private function getMultiInternalRequest($url)
    {
        $this->multiRequest = $this->handleMultiUrl($url);
        return $this;
    }

    /**
     * @inheritDoc
     * @since 6.1.0
     */
    public function request($url, $data = [], $method = requestMethod::METHOD_GET, $dataType = dataType::NORMAL)
    {
        if (is_array($url)) {
            // If url list an associative array or allowed to run arrays that is not associative, run this
            // kind of request instead of the default. The regular indexed arraylist will be passed over to
            // curl_multi requests instead of collecting the requests. Besides error handling in non-assoc
            // is not collected, any exception will be thrown immediately.
            if ($this->getAllowInternalMulti() || (new Arrays())->isAssoc($url)) {
                // As those requests are arrayed differently, the regular parameters above do not apply here.
                // If they are not associative, they will also lose all attributes which is why we want them
                // associative or not at all.
                return $this->getMultiInternalRequest($url);
            }
        }

        $this->CONFIG->setNetWrapper(true);
        $return = null;
        $requestexternalExecute = null;

        $externalWrapperList = WrapperDriver::getExternalWrappers();
        if (WrapperDriver::getRegisteredWrappersFirst() && count($externalWrapperList)) {
            try {
                $returnable = $this->requestExternalExecute($url, $data, $method, $dataType);
                if (!is_null($returnable)) {
                    return $returnable;
                }
            } catch (ExceptionHandler $requestexternalExecute) {
            }
        }

        // Run internal wrappers.
        if ($hasReturnedRequest = $this->getResultFromInternals(
            $url,
            $data,
            $method,
            $dataType
        )) {
            $return = $hasReturnedRequest;
        };

        $externalWrapperList = WrapperDriver::getExternalWrappers();
        // Internal handles are usually throwing execptions before landing here.
        if (is_null($return) &&
            !WrapperDriver::getRegisteredWrappersFirst() &&
            count($externalWrapperList)
        ) {
            // Last execution should render errors thrown from external executes.
            $returnable = $this->requestExternalExecute($url, $data, $method, $dataType);
            if (!is_null($returnable)) {
                return $returnable;
            }
        }

        $this->getInstantiationException($return, __CLASS__, __FILE__, $requestexternalExecute);

        return $return;
    }

    /**
     * Make request from built in wrappers (the internally supported).
     *
     * @param $url
     * @param array $data
     * @param int $method
     * @param int $dataType
     * @return mixed|null
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    private function getResultFromInternals(
        $url,
        $data = [],
        $method = requestMethod::METHOD_GET,
        $dataType = dataType::NORMAL
    ) {
        $return = null;

        if (!is_array($url) && preg_match('/\?wsdl|\&wsdl/i', $url)) {
            try {
                Security::getCurrentClassState('SoapClient');
                $dataType = dataType::SOAP;
            } catch (ExceptionHandler $e) {
                $method = requestMethod::METHOD_POST;
                $dataType = dataType::XML;
                if (!is_string($data) && !empty($data)) {
                    $data = (new Content())->getXmlFromArray($data);
                }
            }
        }

        // Example from tornelib-php-drivertest.
        // This allows us to add internal supported drivers without including them in this specific package.
        //$testWrapper = WrapperDriver::getWrapperAllowed('myNameSpace\myDriver');

        if ($dataType === dataType::SOAP && ($this->getProperInstanceWrapper('SoapClientWrapper'))) {
            $this->isSoapRequest = true;
            $this->instance->setConfig($this->getConfig());
            $return = $this->instance->request($url, $data, $method, $dataType);
        } elseif ($dataType === dataType::RSS_XML && $this->getProperInstanceWrapper('RssWrapper')) {
            $this->instance->setConfig($this->getConfig());
            $return = $this->instance->request($url, $data, $method, $dataType);
        } elseif ($this->getProperInstanceWrapper('CurlWrapper')) {
            $this->instance->setConfig($this->getConfig());
            $this->instance->setCurlMultiInstantException($this->instantCurlMultiErrors);
            $return = $this->instance->request($url, $data, $method, $dataType);
        } elseif ($this->getProperInstanceWrapper('SimpleStreamWrapper')) {
            $currentConfig = $this->getConfig();
            // Check if auth is properly set, in case default setup is used.
            $currentConfig->setAuthStream();
            $this->instance->setConfig($currentConfig);
            $return = $this->instance->request($url, $data, $method, $dataType);
        }

        return $return;
    }

    /**
     * Set up proxy.
     *
     * @param $proxyAddress Normal usage is address:post.
     * @param int $proxyType Default: 0 = HTTP
     * @return $this
     * @since 6.1.0
     */
    public function setProxy($proxyAddress, $proxyType = 0)
    {
        $this->CONFIG->setProxy($proxyAddress, $proxyType);

        return $this;
    }

    /**
     * Set up so that curlwrapper makes instant exceptions on curl_multi requests, if they occur. Mirrormethod.
     *
     * @param bool $throwInstant
     * @return $this
     * @since 6.1.0
     */
    public function setCurlMultiInstantException($throwInstant = true)
    {
        $this->instantCurlMultiErrors = $throwInstant;
        return $this;
    }

    /**
     * Get proper instance.
     *
     * @param $wrapperName
     * @return mixed|WrapperInterface
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    private function getProperInstanceWrapper($wrapperName)
    {
        $this->instance = WrapperDriver::getWrapperAllowed($wrapperName, true);

        if (!is_null($this->instance)) {
            $this->selectedWrapper = get_class($this->instance);
        }

        return $this->instance;
    }

    /**
     * Get current used wrapper class name (short or with full namespace).
     * @param bool $short
     * @return string
     * @since 6.1.0
     */
    public function getCurrentWrapperClass($short = false)
    {
        return $this->CONFIG->getCurrentWrapperClass($short);
    }

    /**
     * Check if return value is null and if, do thrown an exception. This is done if no instances has been successfully
     * created during request.
     *
     * @param $nullValue
     * @param $className
     * @param $functionName
     * @param $requestexternalExecute
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    private function getInstantiationException($nullValue, $className, $functionName, $requestexternalExecute)
    {
        if (is_null($nullValue)) {
            throw new ExceptionHandler(
                sprintf(
                    '%s instantiation failure: No communication wrappers currently available in function/class %s.',
                    $className,
                    $functionName
                ),
                Constants::LIB_NETCURL_NETWRAPPER_NO_DRIVER_FOUND,
                $requestexternalExecute
            );
        }
    }

    /**
     * Initiate an external wrapper request. This actually initiates a "wrapper loop" that runs through
     * each registered wrapper and uses the first that responds correctly. Method is collected here as it
     * runs both in the top of request (if prioritized like that) and in the bottom if developers primarily
     * prefers to use internal classes before their own.
     *
     * @param $url
     * @param array $data
     * @param int $method
     * @param int $dataType
     * @return mixed|null
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    private function requestExternalExecute(
        $url,
        $data = [],
        $method = requestMethod::METHOD_GET,
        $dataType = dataType::NORMAL
    ) {
        $externalHasErrors = false;
        $externalRequestException = null;
        $returnable = null;
        try {
            $returnable = $this->requestExternal(
                $url,
                $data,
                $method,
                $dataType
            );
        } catch (ExceptionHandler $externalRequestException) {
            // Ignore errors here as we have more to go.
            $externalHasErrors = true;
        }
        if (!$externalHasErrors) {
            return $returnable;
        }

        throw new ExceptionHandler(
            sprintf(
                'Internal %s error.',
                __FUNCTION__
            ),
            Constants::LIB_UNHANDLED,
            $externalRequestException
        );
    }

    /**
     * Make a request with help from external wrappers.
     *
     * @param $url
     * @param array $data
     * @param int $method
     * @param int $dataType
     * @return mixed|null
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    private function requestExternal(
        $url,
        $data = [],
        $method = requestMethod::METHOD_GET,
        $dataType = dataType::NORMAL
    ) {
        $return = null;
        $hasInternalSuccess = false;

        $externalWrapperList = WrapperDriver::getExternalWrappers();
        // Walk through external wrappers.
        foreach ($externalWrapperList as $wrapperClass) {
            $returnable = null;
            try {
                $this->CONFIG->setCurrentWrapper(get_class($wrapperClass));
                $returnable = call_user_func_array(
                    [
                        $wrapperClass,
                        'request',
                    ],
                    [
                        $url,
                        $data,
                        $method,
                        $dataType,
                    ]
                );
            } catch (\Exception $externalException) {

            }
            // Break on first success.
            if (!is_null($returnable)) {
                $hasInternalSuccess = true;
                $return = $returnable;
                break;
            }
        }

        if (!$hasInternalSuccess) {
            throw new ExceptionHandler(
                sprintf(
                    'An error occurred when configured external wrappers tried to communicate with %s.',
                    $url
                ),
                isset($externalException) ? $externalException->getCode() : Constants::LIB_UNHANDLED,
                isset($externalException) ? $externalException : null
            );
        }

        return $return;
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    public function __call($name, $arguments)
    {
        $requestType = substr($name, 0, 3);

        switch ($name) {
            case 'setAuth':
                // Abbreviation for setAuthentication.
                return call_user_func_array([$this, 'setAuthentication'], $arguments);
            default:
                break;
        }

        switch ($requestType) {
            default:
                if (method_exists($this->instance, $name)) {
                    if ($instanceRequest = call_user_func_array([$this->instance, $name], $arguments)) {
                        return $instanceRequest;
                    }
                } elseif (method_exists($this->CONFIG, $name)) {
                    call_user_func_array(
                        [
                            $this->CONFIG,
                            $name,
                        ], $arguments
                    );
                    break;
                }
                throw new ExceptionHandler(
                    sprintf('Undefined function: %s', $name),
                    Constants::LIB_METHOD_OR_LIBRARY_UNAVAILABLE,
                    null,
                    null,
                    $name
                );
                break;
        }

        return $this;
    }
}
