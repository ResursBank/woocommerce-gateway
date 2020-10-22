<?php
/**
 * Copyright © Tomas Tornevall / Tornevall Networks. All rights reserved.
 * See LICENSE for license details.
 */

namespace TorneLIB\Module\Config;

use Exception;
use TorneLIB\Exception\Constants;
use TorneLIB\Exception\ExceptionHandler;
use TorneLIB\Flags;

/**
 * Class SSL WrapperConfig for SSL related requests. Sets up stream contexts if necessary (for SOAP) and settings
 * for curl, etc.
 *
 * @package TorneLIB\Helpers
 * @since 6.1.0
 */
class WrapperSSL
{
    /**
     * @var string
     */
    private $version = '6.1.0';

    /**
     * @var bool Primary answer from this module if netcurl will be capable to handle SSL based traffic.
     */
    private $capable;

    /**
     * @var array
     */
    private $capabilities = [];

    /**
     * @var array Context of stream. As of PHP 5.6.0 the peer verifications are defaulting to true.
     */
    private $context = [
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'verify_host' => true,
            'allow_self_signed' => false,
            'SNI_enabled' => true,
        ],
    ];

    /**
     * @var array
     */
    private $securityLevelChanges = [];

    /**
     * SSL constructor.
     */
    public function __construct()
    {
        try {
            $this->capable = $this->setSslCapabilities();
        } catch (Exception $e) {
            $this->capable = false;
        }

        $this->setContextUserAgent();
    }

    /**
     * Checks if system has SSL capabilities.
     *
     * Replaces getCurlSslAvailable from v6.0 where everything is checked in the same method.
     *
     * @return bool
     * @throws Exception
     * @since 6.1.0
     */
    public function getSslCapabilities()
    {
        if (!($return = $this->capable)) {
            throw new ExceptionHandler(
                'NETCURL Exception: SSL capabilities is missing.',
                Constants::LIB_SSL_UNAVAILABLE
            );
        }

        return $return;
    }

    /**
     * @return bool
     * @since 6.1.0
     */
    private function setSslCapabilities()
    {
        $return = false;

        /** @noinspection PhpUndefinedMethodInspection */
        if (Flags::_isFlag('NETCURL_NOSSL_TEST')) {
            return $return;
        }

        $sslDriverError = [];

        if (!$this->getSslStreamWrapper()) {
            $sslDriverError[] = "SSL Failure: HTTPS wrapper can not be found";
        }
        if (!$this->getCurlSsl()) {
            $sslDriverError[] = 'SSL Failure: Protocol "https" not supported or disabled in libcurl';
        }

        if (!count($sslDriverError)) {
            $return = true;
        }

        return $return;
    }

    /**
     * @return bool
     * @since 6.1.0
     */
    private function getSslStreamWrapper()
    {
        $return = false;

        $streamWrappers = @stream_get_wrappers();
        if (!is_array($streamWrappers)) {
            $streamWrappers = [];
            $this->capabilities[] = 'stream';
        }
        if (in_array('https', array_map("strtolower", $streamWrappers), false)) {
            $return = true;
            $this->capabilities[] = 'curl';
        }

        return $return;
    }

    /**
     * @return bool
     * @since 6.1.0
     * @noinspection PhpComposerExtensionStubsInspection
     */
    private function getCurlSsl()
    {
        $return = false;
        if (function_exists('curl_version') && defined('CURL_VERSION_SSL')) {
            $curlVersionRequest = curl_version();

            if (isset($curlVersionRequest['features'])) {
                $return = ((bool)($curlVersionRequest['features'] & CURL_VERSION_SSL));
            }
        }

        return $return;
    }

    /**
     * If capable throws an exception for a specific driver, but that driver should not be used anyway, get a list of
     * working drivers here.
     *
     * @return array
     * @since 6.1.0
     */
    public function getCapabilities()
    {
        return $this->capabilities;
    }

    /**
     * Simplified SSL verification ruleset. Sets peer, peer_name and verify_host to be strictly verified. To change the
     * values, see getContext() and setContext(). You don't need to run this yourself as it runs on the defaults when
     * nothing else is set.
     *
     * @param bool $verifyBooleanValue Peer verification (default=true, always verify).
     * @param bool $selfsignedBooleanValue Allow self signed vertificates (default=false, never allow this).
     * @return mixed
     * @link https://www.php.net/manual/en/context.ssl.php
     * @since 6.1.0
     */
    public function setStrictVerification($verifyBooleanValue = true, $selfsignedBooleanValue = false)
    {
        $this->context['ssl']['verify_peer'] = $verifyBooleanValue;
        $this->context['ssl']['verify_peer_name'] = $verifyBooleanValue;
        $this->context['ssl']['verify_host'] = $verifyBooleanValue;
        $this->context['ssl']['allow_self_signed'] = $selfsignedBooleanValue;

        if (!$verifyBooleanValue || $selfsignedBooleanValue) {
            $this->securityLevelChanges[microtime(true)] = $this->context;
        }

        return $this;
    }

    /**
     * @return $this
     * @since 6.1.0
     */
    private function setContextUserAgent()
    {
        $this->context['http'] = [
            'user-agent' => sprintf('NETCURL/SSL-%s', $this->version),
        ];

        return $this;
    }

    /**
     * Get prepared stream context array.
     *
     * @return array
     * @since 6.1.0
     */
    public function getSslStreamContext()
    {
        // Create the stream with full context.
        return [
            'stream_context' => stream_context_create($this->context),
        ];
    }

    /**
     * @param $key
     * @return array
     * @since 6.1.0
     */
    public function getContext($key = null)
    {
        $return = $this->context['ssl'];

        if (!empty($key) && isset($this->context['ssl'][$key])) {
            $return = $this->context['ssl'][$key];
        }

        return $return;
    }

    /**
     * Configure your own context on fly. Great to use if you need to add your own cafile, etc.
     *
     * @param $key
     * @param $value
     * @return WrapperSSL
     * @since 6.1.0
     */
    public function setContext($key, $value)
    {
        $this->context['ssl'][$key] = $value;

        return $this;
    }

    /**
     * @return array
     * @since 6.1.0
     */
    public function getSecurityLevelChanges()
    {
        return $this->securityLevelChanges;
    }
}
