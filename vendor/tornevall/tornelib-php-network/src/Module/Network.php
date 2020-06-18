<?php

namespace TorneLIB\Module;

use Exception;
use TorneLIB\Exception\Constants;
use TorneLIB\Exception\ExceptionHandler;
use TorneLIB\IO\Data\Strings;
use TorneLIB\Module\Network\Address;
use TorneLIB\Module\Network\Cookie;
use TorneLIB\Module\Network\Domain;
use TorneLIB\Module\Network\Proxy;
use TorneLIB\Module\Network\Statics;

/**
 * Class Network
 * @package TorneLIB\Module
 */
class Network
{
    /**
     * @var $DEPRECATED DeprecateNet
     * @since 6.1.0
     */
    private $DEPRECATED;

    /**
     * @var array $classMap Defines where to find the modern versions and methods in this module.
     * @since 6.1.0
     */
    private $classMap = [
        'TorneLIB\Module\Network\Proxy',
        'TorneLIB\Module\Network\Domain',
        'TorneLIB\Module\Network\Address',
        'TorneLIB\Module\Network\Cookie',
    ];

    /**
     * @var $PROXY Proxy
     * @since 6.1.0
     */
    public $PROXY;

    /**
     * @var $ADDRESS Address
     */
    public $ADDRESS;

    /**
     * @var $DOMAIN Domain
     */
    public $DOMAIN;

    /**
     * @var $COOKIE Cookie
     */
    private $COOKIE;

    /**
     * MODULE_NETWORK constructor.
     *
     * @since 6.1.0
     */
    public function __construct()
    {
        $this->DEPRECATED = new DeprecateNet();
        $this->PROXY = new Proxy();
        $this->ADDRESS = new Address();
        $this->DOMAIN = new Domain();
        $this->COOKIE = new Cookie();
    }

    /**
     * Return information about proxy client ip addresses if any.
     *
     * @param bool $withValues When false, return all data regardless of if the headers are empty.
     * @return array
     * @since 6.1.0
     */
    public function getProxyData($withValues = true)
    {
        return $this->PROXY->getProxyData($withValues);
    }

    /**
     * @param bool $returnProtocol
     * @return mixed
     * @since 6.1.0
     */
    public function getIsSecureHttp($returnProtocol = false)
    {
        return Statics::getCurrentServerProtocol($returnProtocol);
    }

    /**
     * Make sure we always return a "valid" http-host from HTTP_HOST. If the variable is missing, this will fall back
     * to localhost.
     *
     * @return string
     * @sice 6.0.15
     */
    public function getHttpHost()
    {
        $httpHost = (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : "");
        if (empty($httpHost)) {
            $httpHost = "localhost";
        }

        return $httpHost;
    }

    /**
     * @return bool|string
     * @since 6.1.0
     */
    public function getProtocol()
    {
        return Statics::getCurrentServerProtocol(true);
    }

    /**
     * @return bool
     * @since 6.1.0
     */
    public function getIsHttps()
    {
        return (bool)Statics::getCurrentServerProtocol();
    }

    /*** Functions below has a key role in deprecation and compatibility ***/

    /**
     * @param $name
     * @return void|null
     * @throws Exception
     * @since 6.1.0
     */
    private function get($name)
    {
        $return = null;

        $what = lcfirst(substr($name, 3));
        if (isset($this->{$what})) {
            return $this->{$what};
        }

        throw new ExceptionHandler(
            'Variable does not exist.',
            Constants::LIB_CONFIGWRAPPER_VAR_NOT_SET
        );
    }

    /**
     * @param string $redirectToUrl
     * @param bool $replaceHeader
     * @param int $responseCode
     * @since 6.1.3
     */
    public function redirect($redirectToUrl = '', $replaceHeader = false, $responseCode = 301)
    {
        return (new Domain())->redirect($redirectToUrl, $replaceHeader, $responseCode);
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     * @throws Exception
     * @since 6.1.0
     */
    private function getByClassMap($name, $arguments)
    {
        foreach ($this->classMap as $className) {
            if (class_exists($className)) {
                $methods = get_class_methods($className);
                if (in_array($name, $methods)) {
                    $instance = new $className();
                    return call_user_func_array([$instance, $name], $arguments);
                }
            }
        }
        throw new ExceptionHandler(
            sprintf(
                '%sException: No method with name %s found %s.',
                __FUNCTION__,
                $name,
                __CLASS__
            ),
            Constants::LIB_METHOD_OR_LIBRARY_UNAVAILABLE
        );
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    private function getDeprecatedResponse($name, $arguments)
    {
        if (method_exists($this->DEPRECATED, $name) ||
            method_exists($this->DEPRECATED, Strings::returnCamelCase($name))
        ) {
            return call_user_func_array([$this->DEPRECATED, $name], $arguments);
        }

        throw new ExceptionHandler(
            sprintf(
                '%sException: No method with name %s found %s.',
                __FUNCTION__,
                $name,
                __CLASS__
            ),
            Constants::LIB_METHOD_OR_LIBRARY_UNAVAILABLE
        );
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    private function getStaticResponse($name, $arguments)
    {
        if (method_exists('TorneLIB\Module\Network\Statics', $name)) {
            return call_user_func_array(['TorneLIB\Module\Network\Statics', $name], $arguments);
        }

        throw new ExceptionHandler(
            sprintf(
                'No static method with name %s via %s.',
                $name,
                __CLASS__
            ),
            Constants::LIB_METHOD_OR_LIBRARY_UNAVAILABLE
        );
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
        $return = null;
        $throwThis = null;

        try {
            $return = $this->getStaticResponse($name, $arguments);
        } catch (ExceptionHandler $e) {
            $throwThis = $e;
        }

        try {
            $return = $this->getDeprecatedResponse($name, $arguments);
        } catch (ExceptionHandler $e) {
        }

        if (substr($name, 0, 3) === 'get') {
            try {
                $return = $this->get($name);
            } catch (ExceptionHandler $e) {
                $throwThis = $e;
            }
        }

        if (is_null($return)) {
            try {
                $return = $this->getByClassMap($name, $arguments);
            } catch (ExceptionHandler $e) {
                $throwThis = $e;
            }
        }

        if (is_null($return) && !is_null($throwThis)) {
            throw new ExceptionHandler(
                sprintf(
                    'Method "%s" for %s does not exist or has been deprecated',
                    $name,
                    __CLASS__
                ),
                Constants::LIB_METHOD_OR_LIBRARY_UNAVAILABLE,
                $throwThis
            );
        }

        return $return;
    }

    /**
     * @param $name
     * @return void|null
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    public function __get($name)
    {
        $return = null;

        if (!isset($this->{$name}) && isset($this->DEPRECATED->{$name})) {
            $return = $this->DEPRECATED->{$name};
            if (is_null($return)) {
                // Immediately return if still null.
                return $return;
            }
        }

        if (is_null($return)) {
            throw new ExceptionHandler(
                sprintf(
                    'Variable "%s" for %s does not exist or has been deprecated',
                    $name,
                    __CLASS__
                ),
                Constants::LIB_METHOD_OR_LIBRARY_UNAVAILABLE
            );
        }

        return $return;
    }
}
