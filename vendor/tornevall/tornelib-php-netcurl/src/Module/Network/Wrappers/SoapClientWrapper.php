<?php /** @noinspection PhpComposerExtensionStubsInspection */

/**
 * Copyright © Tomas Tornevall / Tornevall Networks. All rights reserved.
 * See LICENSE for license details.
 */

namespace TorneLIB\Module\Network\Wrappers;

use Exception;
use ReflectionException;
use SoapClient;
use SoapFault;
use TorneLIB\Exception\Constants;
use TorneLIB\Exception\ExceptionHandler;
use TorneLIB\Helpers\GenericParser;
use TorneLIB\IO\Data\Strings;
use TorneLIB\Model\Interfaces\WrapperInterface;
use TorneLIB\Model\Type\authSource;
use TorneLIB\Model\Type\authType;
use TorneLIB\Model\Type\dataType;
use TorneLIB\Model\Type\requestMethod;
use TorneLIB\Module\Config\WrapperConfig;
use TorneLIB\Utils\Generic;
use TorneLIB\Utils\Security;

/**
 * Class SoapClientWrapper
 *
 * @package TorneLIB\Module\Network\Wrappers
 */
class SoapClientWrapper implements WrapperInterface
{
    /**
     * @var WrapperConfig $CONFIG
     * @since 6.1.0
     */
    private $CONFIG;

    /**
     * @var SoapClient $soapClient
     * @since 6.1.0
     */
    private $soapClient;

    /**
     * @var $soapClientResponse
     * @since 6.1.0
     */
    private $soapClientResponse;

    /**
     * @var array $soapClientContent
     * @since 6.1.0
     */
    private $soapClientContent = [
        'lastRequest' => null,
        'lastRequestHeaders' => null,
        'lastResponse' => null,
        'lastResponseHeaders' => null,
        'functions' => null,
    ];

    /**
     * @var string
     * @since 6.1.0
     */
    private $soapProxyHost = '';

    /**
     * @var int
     * @since 6.1.0
     */
    private $soapProxyPort = 80;

    /**
     * @var array
     * @since 6.1.0
     */
    private $soapProxyOptions = [];

    /**
     * The header that the soapResponse are returning, converted to an array.
     *
     * @var array $responseHeaderArray
     * @since 6.1.0
     */
    private $responseHeaderArray = [];

    /**
     * @var array $soapWarningException
     * @since 6.1.0
     */
    private $soapWarningException = ['code' => 0, 'string' => null];

    /**
     * Reuse soapClient session if this is true. By means, it will be reinitialized on each call otherwise.
     * @var bool
     * @since 6.1.0
     */
    private $reuseSoapClient = false;

    /**
     * @var
     */
    private $currentErrorHandler;

    /**
     * SoapClientWrapper constructor.
     * @throws ExceptionHandler
     * @throws Exception
     */
    public function __construct()
    {
        Security::getCurrentClassState('SoapClient');

        $this->CONFIG = new WrapperConfig();
        $this->CONFIG->setSoapRequest(true);
        $this->CONFIG->setCurrentWrapper(__CLASS__);
        $this->getPriorCompatibilityArguments(func_get_args());
    }

    /**
     * If staging is false, we're considering production mode.
     * @param bool $staging
     * @return WrapperConfig
     * @since 6.1.0
     */
    public function setStaging($staging = true)
    {
        return $this->CONFIG->setStaging($staging);
    }

    /**
     * @return bool
     * @since 6.1.0
     */
    public function getStaging()
    {
        return $this->CONFIG->getStaging();
    }

    /**
     * @param bool $production
     * @return WrapperConfig
     * @since 6.1.0
     */
    public function setProduction($production = true)
    {
        return $this->CONFIG->setProduction($production);
    }

    /**
     * @return mixed
     * @since 6.1.0
     */
    public function getProduction()
    {
        return $this->CONFIG->getProduction();
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
     * Reverse compatibility with v6.0 - returns true if any of the settings here are touched.
     * Main function as it is duplicated is moved into WrapprConfig->getCompatibilityArguments()
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
     * @return mixed
     * @since 6.1.0
     */
    public function getLastRequest()
    {
        return (string)$this->soapClientContent['lastRequest'];
    }

    /**
     * @return mixed
     * @since 6.1.0
     */
    public function getLastRequestHeaders()
    {
        return (string)$this->soapClientContent['lastRequestHeaders'];
    }

    /**
     * @return bool
     * @since 6.1.0;
     */
    public function getLastResponse()
    {
        return (string)$this->soapClientContent['lastResponse'];
    }

    /**
     * @return string
     * @since 6.1.0
     */
    public function getLastResponseHeaders()
    {
        return (string)$this->soapClientContent['lastResponseHeaders'];
    }

    /**
     * Returns an array of function from the soapcall.
     *
     * @return array
     * @since 6.1.0
     */
    public function getFunctions()
    {
        return (array)$this->soapClientContent['functions'];
    }

    /**
     * @param WrapperConfig $config
     * @return SoapClientWrapper
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
     * @return SoapClientWrapper
     * @since 6.1.0
     */
    public function setAuthentication($username, $password, $authType = authType::ANY)
    {
        $this->CONFIG->setAuthentication($username, $password, $authType, authSource::SOAP);

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
     * @throws ExceptionHandler
     * @throws SoapFault
     * @since 6.1.0
     */
    private function getSoapClient()
    {
        $this->CONFIG->getOptions();
        $this->getSoapInitErrorHandler();
        $streamOpt = $this->getPreparedProxyOptions($this->getConfig()->getStreamOptions());
        // version_compare(PHP_VERSION, '7.1.0', '>=')
        if (PHP_VERSION_ID >= 70100) {
            $this->soapClient = new SoapClient(
                $this->getConfig()->getRequestUrl(),
                $streamOpt
            );
        } else {
            // Suppress fatals in older releases.
            $this->soapClient = @(new SoapClient(
                $this->getConfig()->getRequestUrl(),
                $streamOpt
            ));
        }
    }

    /**
     * @param $streamOptions
     * @return mixed
     * @since 6.1.0
     */
    private function getPreparedProxyOptions($streamOptions)
    {
        if (!empty($this->soapProxyHost)) {
            $streamOptions['proxy_host'] = $this->soapProxyHost;
            $streamOptions['proxy_port'] = $this->soapProxyPort;
        }

        if (is_array($this->soapProxyOptions)) {
            /** @noinspection AdditionOperationOnArraysInspection */
            // + is intended.
            $streamOptions += $this->soapProxyOptions;
        }

        return $streamOptions;
    }

    /**
     * SOAP initializer.
     * Formerly known as a simpleSoap getSoap() variant.
     *
     * @return $this
     * @throws ExceptionHandler
     * @throws Exception
     * @since 6.1.0
     */
    private function getSoapInit()
    {
        try {
            $this->getSoapClient();
        } catch (Exception $soapException) {
            if ((int)$soapException->getCode()) {
                throw $soapException;
            }

            // Trying to prevent dual requests during a soap-transfer. In v6.0, there was dual initializations of
            // soapclient when potential authfail errors occurred.
            if ((int)$this->soapWarningException['code']) {
                $code = $this->getHttpHead($this->soapWarningException['string']);
                $message = $this->getHttpHead($this->soapWarningException['string'], 'message');

                $this->CONFIG->getHttpException(
                    (int)$code > 0 && !empty($message) ? $message : $this->soapWarningException['string'],
                    (int)$code > 0 ? $code : $this->soapWarningException['code'],
                    null,
                    $this,
                    true
                );
            }
        }
        // Restore the errorhandler immediately after soaprequest if no exceptions are detected during first request.
        restore_error_handler();

        return $this;
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
     * Initialize SoapExceptions for special occasions.
     *
     * @return $this
     * @since 6.1.0
     * @noinspection SuspiciousAssignmentsInspection
     */
    private function getSoapInitErrorHandler()
    {
        // No inspections on this, it is handled properly handled despite the immediate overrider.
        // The overrider is present as it has to be nulled out after each use.
        if (!is_null($this->currentErrorHandler)) {
            restore_error_handler();
            $this->currentErrorHandler = null;
            $this->soapWarningException['code'] = null;
            $this->soapWarningException['string'] = null;
        }
        $this->currentErrorHandler = set_error_handler(function ($errNo, $errStr) {
            if (empty($this->soapWarningException['string'])) {
                $this->soapWarningException['code'] = $errNo;
                $this->soapWarningException['string'] = $errStr;
            }
            restore_error_handler();
            return false;
        }, E_WARNING);

        return $this;
    }

    /**
     * @return mixed
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    public function getStreamContext()
    {
        $currentStreamContext = $this->getConfig()->getStreamContext();
        if (is_null($currentStreamContext)) {
            // Not properly initialized yet.
            $this->getConfig()->getStreamOptions();
            $currentStreamContext = $this->getConfig()->getStreamContext();
        }

        if (is_resource($currentStreamContext)) {
            $currentStreamContext = stream_context_get_options($currentStreamContext);
        }

        return $currentStreamContext;
    }

    /**
     * Interface Request Method. Barely not in used in this service.
     *
     * @param $url
     * @param array $data
     * @param int $method Not in use.
     * @param int $dataType Not in use, as we are located in the world of SOAP.
     * @return $this|mixed
     * @since 6.1.0
     */
    public function request($url, $data = [], $method = requestMethod::METHOD_GET, $dataType = dataType::SOAP)
    {
        if (!empty($url)) {
            $this->CONFIG->setRequestUrl($url);
        }
        if (is_array($data) && count($data)) {
            $this->CONFIG->setRequestData($data);
        }

        if ($this->CONFIG->getRequestDataType() !== $dataType) {
            $this->CONFIG->setRequestDataType($dataType);
        }

        return $this;
    }

    /**
     * @return SoapClientWrapper
     * @since 6.1.0
     */
    private function setMergedSoapResponse()
    {
        foreach ($this->soapClientContent as $soapMethod => $value) {
            $methodName = sprintf(
                '__get%s',
                ucfirst($soapMethod)
            );
            $this->soapClientContent[$soapMethod] = $this->getFromSoap($methodName);
        }

        return $this;
    }

    /**
     * @param $methodName
     * @return mixed|null
     * @since 6.1.0
     */
    private function getFromSoap($methodName)
    {
        $return = null;

        if (!empty($this->soapClient) && method_exists($this->soapClient, $methodName)) {
            $return = call_user_func_array([$this->soapClient, $methodName], []);
        }

        return $return;
    }

    /**
     * @param $userAgentString
     * @return WrapperConfig
     * @since 6.1.0
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function setUserAgent($userAgentString)
    {
        return $this->CONFIG->setUserAgent($userAgentString);
    }

    /**
     * @return mixed
     * @throws ExceptionHandler
     * @since 6.1.0
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function getUserAgent()
    {
        return $this->CONFIG->getUserAgent();
    }

    /**
     * @param $methodContent
     * @param $name
     * @param $arguments
     * @return mixed
     * @since 6.1.0
     */
    private function getMagicGettableCall($methodContent, $name, $arguments)
    {
        $return = null;

        if (isset($this->soapClientContent[$methodContent])) {
            $return = $this->soapClientContent[$methodContent];
        } elseif (method_exists($this, $name)) {
            $return = call_user_func_array(
                [
                    $this,
                    $name,
                ],
                $arguments
            );
        }

        return $return;
    }

    /**
     * @param $name
     * @param $arguments
     * @return $this
     * @since 6.1.0
     */
    private function getMagicSettableCall($name, $arguments)
    {
        $return = null;

        if (method_exists($this, $name)) {
            call_user_func_array(
                [
                    $this,
                    $name,
                ],
                $arguments
            );

            $return = $this;
        } elseif (method_exists($this->CONFIG, $name)) {
            call_user_func_array(
                [
                    $this->CONFIG,
                    $name,
                ],
                $arguments
            );

            $return = $this;
        }

        return $return;
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     * @throws Exception
     * @since 6.1.0
     */
    private function execSoap($name, $arguments)
    {
        $return = null;

        /*$soapMethods = [];
        if (method_exists($this->soapClient, '__getFunctions')) {
            $soapMethods = $this->soapClient->__getFunctions();
        }*/

        try {
            // Giving the soapcall a more natural touch with call_user_func_array. Besides, this also means
            // we don't have to check for arguments.
            if (!empty($this->soapClient)) {
                $return = call_user_func_array([$this->soapClient, $name], $arguments);
            } else {
                throw new ExceptionHandler(
                    'SoapClient instance was never initialized.',
                    Constants::LIB_NETCURL_SOAPINSTANCE_MISSING
                );
            }
        } catch (Exception $soapFault) {
            // Public note: Those exceptions may be thrown by the soap-api or when the wsdl is cache and there is
            // for example authorization problems. This is why the soapResponse is fetched and analyzed before
            // giving up.

            // Initialize a merged soapResponse of what's left in this exception - and to see if it was a real
            // api request or a local one.
            $this->setMergedSoapResponse();

            if (!is_null($this->soapClientContent['lastResponseHeaders'])) {
                // Pick up the http-head response from the soapResponseHeader.
                $httpHeader = $this->getHeader('http');

                // Check if it is time to throw something specific.
                $this->CONFIG->getHttpException(
                    $this->getHttpHead($httpHeader, 'message'),
                    $this->getHttpHead($httpHeader),
                    $soapFault,
                    $this
                );
            }

            // Continue throw the soapFault as it.
            throw $soapFault;
        }

        return $return;
    }

    /**
     * Dynamically fetch responses from a soapClientResponse.
     * @param $soapClientResponse
     * @return mixed
     * @since 6.1.0
     */
    private function getSoapResponse($soapClientResponse)
    {
        if (isset($soapClientResponse->return)) {
            $return = $soapClientResponse->return;
        } else {
            $return = $soapClientResponse;
        }

        return $return;
    }

    /**
     * @return int
     * @since 6.1.0
     */
    public function getCode()
    {
        return (int)$this->getHttpHead($this->getHeader('http'));
    }

    /**
     * @param $name
     * @param $arguments
     * @return $this|mixed|null
     * @since 6.1.0
     */
    private function getInternalMagics($name, $arguments)
    {
        $return = null;

        $method = substr($name, 0, 3);
        $methodContent = (new Strings())->getCamelCase(substr($name, 3));

        switch (strtolower($method)) {
            case 'get':
                $getResponse = $this->getMagicGettableCall($methodContent, $name, $arguments);
                if (!is_null($getResponse)) {
                    $return = $getResponse;
                }
                break;
            case 'set':
                $getResponse = $this->getMagicSettableCall($name, $arguments);
                if (!is_null($getResponse)) {
                    $return = $getResponse;
                }
                break;
            default:
                break;
        }

        return $return;
    }

    /**
     * @return mixed
     * @since 6.1.0
     */
    public function getParsed()
    {
        return $this->getSoapResponse($this->soapClientResponse);
    }

    /**
     * @return mixed
     * @since 6.1.0
     */
    public function getBody()
    {
        return $this->getLastResponse();
    }

    /**
     * @param bool $asArray
     * @param bool $lCase
     * @return mixed
     * @since 6.1.0
     */
    public function getHeaders($asArray = false, $lCase = false)
    {
        $return = $this->getLastResponseHeaders();

        if ($asArray) {
            $return = $this->getHeaderArray($this->getLastResponseHeaders(), $lCase);
        }

        return $return;
    }

    /**
     * @param $header
     * @param bool $lCase
     * @return array
     * @since 6.1.0
     */
    private function getHeaderArray($header, $lCase = false)
    {
        $this->responseHeaderArray = [];

        if (is_string($header)) {
            $headerSplit = explode("\n", $header);
            if (is_array($headerSplit)) {
                foreach ($headerSplit as $headerRow) {
                    $this->getHeaderRow($headerRow, $lCase);
                }
            }
        }

        return $this->responseHeaderArray;
    }

    /**
     * @param $header
     * @param bool $lCase
     * @return int
     * @since 6.1.0
     */
    private function getHeaderRow($header, $lCase = false)
    {
        $headSplit = explode(':', $header, 2);
        $spacedSplit = explode(' ', $header, 2);

        if (count($headSplit) < 2) {
            if (count($spacedSplit) > 1) {
                $splitName = !$lCase ? $spacedSplit[0] : strtolower($spacedSplit[0]);

                if ((bool)preg_match('/^http\/(.*?)$/i', $splitName)) {
                    $httpSplitName = explode("/", $splitName, 2);
                    $realSplitName = !$lCase ? $httpSplitName[0] : strtolower($httpSplitName[0]);

                    if (!isset($this->responseHeaderArray[$realSplitName])) {
                        $this->responseHeaderArray[$realSplitName] = trim($spacedSplit[1]);
                    } else {
                        $this->responseHeaderArray[$realSplitName][] = trim($spacedSplit[1]);
                    }
                }

                $this->responseHeaderArray[$splitName][] = trim($spacedSplit[1]);
            }
            return strlen($header);
        }

        $splitName = !$lCase ? $headSplit[0] : strtolower($headSplit[0]);
        $this->responseHeaderArray[$splitName][] = trim($headSplit[1]);
        return strlen($header);
    }

    /**
     * @param $proxyAddress
     * @param array $proxyOptions
     * @return $this
     * @since 6.1.0
     */
    public function setProxy($proxyAddress, $proxyOptions = [])
    {
        $this->CONFIG->setCurrentWrapper(__CLASS__);

        // Add user specific proxy options here as they are not pushed into any stream_context.
        // This is used for proxy authentications.
        $this->soapProxyOptions = $proxyOptions;

        $proxyData = explode(':', $proxyAddress);
        if (isset($proxyData[1])) {
            // SoapClient does not accept stream_context setups, so we'll split it up here and pushing
            // the proxy in from another direction.
            $this->soapProxyHost = $proxyData[0];
            $this->soapProxyPort = $proxyData[1];
        }

        return $this;
    }

    /**
     * @param null $key
     * @return mixed
     * @since 6.1.0
     */
    private function getHeader($key = null)
    {
        if (is_null($key)) {
            $return = $this->getHeaders();
        } else {
            $return = $this->getHeaders(true, true);
        }

        if (isset($return[strtolower($key)])) {
            $return = $return[strtolower($key)];
        }

        return $return;
    }

    /**
     * @param bool $reuseEnabled
     * @return SoapClientWrapper
     * @since 6.1.0
     */
    public function setReuseSoapClient($reuseEnabled = false)
    {
        $this->reuseSoapClient = $reuseEnabled;

        return $this;
    }

    /**
     * @return bool
     * @since 6.1.0
     */
    public function getReuseSoapClient()
    {
        return $this->reuseSoapClient;
    }

    /**
     * Dynamic SOAP-requests passing through.
     *
     * @param $name
     * @param $arguments
     * @return SoapClientWrapper
     * @throws ExceptionHandler
     * @throws Exception
     * @since 6.1.0
     */
    public function __call($name, $arguments)
    {
        if (null !== ($internalResponse = $this->getInternalMagics($name, $arguments))) {
            return $internalResponse;
        }

        // Making sure we initialize the soapclient if not already done and only when asked to reuse old sessions.
        // Set higher priority for internal requests and configuration. If there is no reuse setting active
        // it has to be reinitialized as there may be several sessions after each other with different credentials
        // etc.
        if (is_null($this->soapClient) || !$this->getReuseSoapClient()) {
            $this->getSoapInit();
        }

        $this->soapClientResponse = $this->execSoap($name, $arguments);
        $this->setMergedSoapResponse();

        // Return as the last version, if return exists as a response point, we use this part primarily.
        return $this->getSoapResponse($this->soapClientResponse);
    }
}
