<?php
/**
 * Copyright © Tomas Tornevall / Tornevall Networks. All rights reserved.
 * See LICENSE for license details.
 */

namespace TorneLIB;

use ReflectionException;
use TorneLIB\Exception\Constants;
use TorneLIB\Exception\ExceptionHandler;
use TorneLIB\Model\Type\dataType;
use TorneLIB\Model\Type\requestMethod;
use TorneLIB\Module\Config\WrapperConfig;
use TorneLIB\Module\Network\NetWrapper;
use TorneLIB\Utils\Generic;

/**
 * Class MODULE_CURL
 * Passthrough client that v6.0 remember.
 *
 * @package TorneLIB
 * @since 6.0.20
 * @deprecated You should consider NetWrapper instead.
 */
class MODULE_CURL
{
    /**
     * @var WrapperConfig $CONFIG
     * @since 6.1.0
     */
    private $CONFIG;

    /**
     * @var NetWrapper
     * @since 6.1.0
     */
    private $netWrapper;

    /**
     * @var Flags
     * @since 6.1.0
     */
    private $flags;

    /**
     * @var array
     * @since 6.1.0
     */
    private $deprecatedRequest = [
        'get' => requestMethod::METHOD_GET,
        'post' => requestMethod::METHOD_POST,
        'put' => requestMethod::METHOD_PUT,
        'delete' => requestMethod::METHOD_DELETE,
        'head' => requestMethod::METHOD_HEAD,
        'request' => requestMethod::METHOD_REQUEST,
    ];

    /**
     * Obsolete or deprecated methods removed from netcurl that still wants errormessages.
     *
     * @var array
     * @since 6.1.0
     */
    private $deprecatedMethod = [
        'setChain',
    ];

    /**
     * MODULE_CURL constructor.
     * @since 6.1.0
     */
    public function __construct()
    {
        $this->netWrapper = new NetWrapper();
        $this->flags = new Flags();
        $this->CONFIG = $this->netWrapper->getConfig();
    }

    /**
     * @return string|null
     * @throws ExceptionHandler
     * @throws ReflectionException
     * @since 6.1.2
     */
    public function getVersion()
    {
        return isset($this->version) && !empty($this->version) ?
            $this->version : (new Generic())->getVersionByAny(__DIR__, 3, WrapperConfig::class);
    }

    /**
     * @param string $url
     * @param dataType|int $postDataType
     * @return mixed|null
     * @throws ExceptionHandler
     * @deprecated Avoid this method. Use request.
     * @since 6.1.0
     */
    public function doGet($url = '', $postDataType = dataType::NORMAL)
    {
        return $this->netWrapper->request($url, [], requestMethod::METHOD_GET, $postDataType);
    }

    /**
     * Allows strict identification in user-agent header.
     * @param $activation
     * @param bool $allowPhpRelease
     * @return MODULE_CURL
     * @since 6.1.0
     */
    public function setIdentifiers($activation, $allowPhpRelease = false)
    {
        $this->netWrapper->setIdentifiers($activation, $allowPhpRelease);

        return $this;
    }

    /**
     * @param $requestType
     * @return int|requestMethod
     * @since 6.1.0
     */
    private function getDeprecatedRequest($requestType)
    {
        return isset($this->deprecatedRequest[$requestType]) ? $this->deprecatedRequest[$requestType] : requestMethod::METHOD_GET;
    }

    /**
     * @param $deprecatedRequestName
     * @param $arguments
     * @return mixed|NetWrapper|null
     * @throws ExceptionHandler
     * @since 6.1.1
     */
    private function getDeprecatedRequestResult($deprecatedRequestName, $arguments)
    {
        if ($deprecatedRequestName === 'get') {
            $return = $this->netWrapper->request(
                isset($arguments[0]) ? $arguments[0] : null,
                [],
                $this->getDeprecatedRequest('get'),
                isset($arguments[1]) ? $arguments[1] : null
            );
        } else {
            $return = $this->netWrapper->request(
                isset($arguments[0]) ? $arguments[0] : null,
                isset($arguments[1]) ? $arguments[1] : null,
                isset($deprecatedRequestName) ? $this->getDeprecatedRequest(
                    $deprecatedRequestName
                ) : $this->getDeprecatedRequest('post'),
                isset($arguments[2]) ? $arguments[2] : null
            );
        }

        return $return;
    }

    /**
     * @param $name
     * @return $this
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    private function isObsolete($name)
    {
        if (in_array($name, $this->deprecatedMethod, false)) {
            throw new ExceptionHandler(
                sprintf(
                    'This method (%s) is obsolete and no longer part of %s.',
                    $name,
                    __CLASS__
                ),
                Constants::LIB_METHOD_OBSOLETE
            );
        }

        return $this;
    }

    /**
     * Callable functions that should be propagated (eventually) to other wrapper parts.
     *
     * @param $name
     * @param $arguments
     * @return mixed|null
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    public function __call($name, $arguments)
    {
        // Ignore failures.
        $requestType = substr($name, 0, 3);
        $requestName = lcfirst(substr($name, 3));
        $deprecatedRequest = substr($name, 0, 2);
        $deprecatedRequestName = lcfirst(substr($name, 2));

        $this->isObsolete($name);

        if (method_exists($this, $name)) {
            return call_user_func_array(
                [$this, $name],
                $arguments
            );
        }

        if (method_exists($this->CONFIG, $name)) {
            return call_user_func_array(
                [$this->CONFIG, $name],
                $arguments
            );
        }

        if ((bool)preg_match('/^(.*)Flag$/', $name)) {
            return call_user_func_array([$this->flags, $name], $arguments);
        }

        if ($requestType === 'set') {
            $arguments = array_merge([$requestName], $arguments);
            return call_user_func_array([$this->flags, 'setFlag'], $arguments);
        }

        if ($requestType === 'get') {
            $arguments = array_merge([$requestName], $arguments);
            $getFlagResponse = call_user_func_array([$this->flags, 'getFlag'], $arguments);

            if (!is_null($this->netWrapper) && method_exists($this->netWrapper, $name)) {
                return call_user_func_array(
                    [$this->netWrapper, $name],
                    $arguments
                );
            }

            return $getFlagResponse;
        }

        if ($deprecatedRequest === 'do') {
            return $this->getDeprecatedRequestResult($deprecatedRequestName, $arguments);
        }

        return null;
    }
}
