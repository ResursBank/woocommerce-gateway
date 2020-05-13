<?php

namespace TorneLIB\Module\Network;

/**
 * Class Cookie
 * @package TorneLIB\Module\Network
 * @version 6.1.0
 * @since 5.0.0
 * @deprecated Added since I don't know where it still might be used.
 */
class Cookie
{
    /**
     * @var string
     */
    private $cookieDefaultPath = '/';
    /**
     * @var string
     */
    private $cookieDefaultDomain;

    /**
     * @var bool
     */
    private $cookieUseSecure;

    /**
     * @var
     */
    private $cookieDefaultPrefix;

    /**
     * Set a cookie
     *
     * @param string $name
     * @param string $value
     * @param string $expire
     * @return bool
     */
    public function setCookie($name = '', $value = '', $expire = '')
    {
        $this->setCookieParameters();
        $defaultExpire = time() + 60 * 60 * 24 * 1;
        if (empty($expire)) {
            $expire = $defaultExpire;
        } else {
            if (is_string($expire)) {
                $expire = strtotime($expire);
            }
        }

        return setcookie(
            $this->cookieDefaultPrefix . $name,
            $value,
            $expire,
            $this->cookieDefaultPath,
            $this->cookieDefaultDomain,
            $this->cookieUseSecure
        );
    }

    /**
     * Prepare add-on parameters for setting a cookie.
     *
     * @param string $path
     * @param null $prefix
     * @param null $domain
     * @param null $secure
     * @return Cookie
     */
    public function setCookieParameters($path = "/", $prefix = null, $domain = null, $secure = null)
    {
        $this->cookieDefaultPath = $path;
        if (empty($this->cookieDefaultDomain)) {
            if (is_null($domain)) {
                $this->cookieDefaultDomain = "." . $_SERVER['HTTP_HOST'];
            } else {
                $this->cookieDefaultDomain = $domain;
            }
        }
        if (is_null($secure)) {
            if (isset($_SERVER['HTTPS'])) {
                if ($_SERVER['HTTPS'] == "true") {
                    $this->cookieUseSecure = true;
                } else {
                    $this->cookieUseSecure = false;
                }
            } else {
                $this->cookieUseSecure = false;
            }
        } else {
            $this->cookieUseSecure = $secure;
        }
        if (!is_null($prefix)) {
            $this->cookieDefaultPrefix = $prefix;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getCookieDefaultPath()
    {
        return $this->cookieDefaultPath;
    }

    /**
     * @param string $cookieDefaultPath
     * @return $this
     */
    public function setCookieDefaultPath($cookieDefaultPath)
    {
        $this->cookieDefaultPath = $cookieDefaultPath;

        return $this;
    }

    /**
     * @return string
     */
    public function getCookieDefaultDomain()
    {
        return $this->cookieDefaultDomain;
    }

    /**
     * @param string $cookieDefaultDomain
     * @return $this
     */
    public function setCookieDefaultDomain($cookieDefaultDomain)
    {
        $this->cookieDefaultDomain = $cookieDefaultDomain;

        return $this;
    }

    /**
     * @return bool
     */
    public function isCookieUseSecure()
    {
        return $this->cookieUseSecure;
    }

    /**
     * @param $cookieUseSecure
     * @return $this
     */
    public function setCookieUseSecure($cookieUseSecure)
    {
        $this->cookieUseSecure = $cookieUseSecure;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getCookieDefaultPrefix()
    {
        return $this->cookieDefaultPrefix;
    }

    /**
     * @param mixed $cookieDefaultPrefix
     * @return $this
     */
    public function setCookieDefaultPrefix($cookieDefaultPrefix)
    {
        $this->cookieDefaultPrefix = $cookieDefaultPrefix;

        return $this;
    }
}