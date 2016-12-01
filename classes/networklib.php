<?php

namespace TorneLIB;

/**
 * Libraries for handling network related things (currently not sockets). Conversion of Legacy TorneEngine and family.
 * As this library may run as stand alone code, exceptions are thrown as regular \Exception instead of a TorneLIB_Exception.
 *
 * Class TorneLIB_Network
 * @link https://docs.tornevall.net/x/KQCy Complete usage documentation (not automated)
 * @link https://developer.tornevall.net/apigen/TorneLIB-5.0/class-TorneLIB.TorneLIB_Network.html This document (APIGen automation)
 * @link https://developer.tornevall.net/download/TorneLIB-5.0/raw/tornevall_network.php Downloadable snapshot
 * @package TorneLIB
 */
class TorneLIB_Network
{
    function __construct()
    {
    }

    /**
     * Extract domain from URL-based string
     * @param string $url
     * @return array
     */
    public function getUrlDomain($url = '')
    {
        $urex = explode("/", preg_replace("[^(.*?)//(.*?)/(.*)]", '$2', $url . "/"));
        $urtype = preg_replace("[^(.*?)://(.*)]", '$1', $url . "/");
        return array($urex[0], $urtype);
    }

    /**
     * Extract domain name (zone name) from hostname
     * @param string $useHost Alternative hostname than the HTTP_HOST
     * @return string
     */
    public function getDomainName($useHost = "")
    {
        $currentHost = "";
        if (empty($useHost)) {
            if (isset($_SERVER['HTTP_HOST'])) {
                $currentHost = $_SERVER['HTTP_HOST'];
            }
        } else {
            $extractHost = $this->getUrlDomain($useHost);
            $currentHost = $extractHost[0];
        }
        if (!empty($currentHost)) {
            $thisdomainArray = explode(".", $currentHost);
            $thisdomain = $thisdomainArray[sizeof($thisdomainArray) - 2] . "." . $thisdomainArray[sizeof($thisdomainArray) - 1];
        }
        return (!empty($thisdomain) ? $thisdomain : null);
    }

    /**
     * Get reverse octets from ip address
     *
     * @param string $ipAddr
     * @param bool $returnIpType
     * @return int|string
     */
    public function getArpaFromAddr($ipAddr = '', $returnIpType = false)
    {
        if (long2ip(ip2long($ipAddr)) == "0.0.0.0") {
            if ($returnIpType === true) {
                $vArpaTest = $this->getArpaFromIpv6($ipAddr);    // PHP 5.3
                if (!empty($vArpaTest)) {
                    return TorneLIB_Network_IP::IPTYPE_V6;
                } else {
                    return TorneLIB_Network_IP::IPTYPE_NONE;
                }
            } else {
                return $this->getArpaFromIpv6($ipAddr);
            }
        } else {
            if ($returnIpType) {
                return TorneLIB_Network_IP::IPTYPE_V4;
            } else {
                return $this->getArpaFromIpv4($ipAddr);
            }
        }
    }

    /**
     * Get IP range from netmask
     *
     * @param null $mask
     * @return array
     */
    function getRangeFromMask($mask = null)
    {
        $addresses = array();
        @list($ip, $len) = explode('/', $mask);
        if (($min = ip2long($ip)) !== false) {
            $max = ($min | (1 << (32 - $len)) - 1);
            for ($i = $min; $i < $max; $i++) $addresses[] = long2ip($i);
        }
        return $addresses;
    }

    /**
     * Test if the given ip address is in the netmask range (not ipv6 compatible yet)
     *
     * @param $IP
     * @param $CIDR
     * @return bool
     */
    public function isIpInRange($IP, $CIDR)
    {
        list ($net, $mask) = explode("/", $CIDR);
        $ip_net = ip2long($net);
        $ip_mask = ~((1 << (32 - $mask)) - 1);
        $ip_ip = ip2long($IP);
        $ip_ip_net = $ip_ip & $ip_mask;
        return ($ip_ip_net == $ip_net);
    }

    /**
     * Translate ipv6 address to reverse octets
     *
     * @param string $ip
     * @return string
     */
    public function getArpaFromIpv6($ip = '::')
    {
        $unpack = @unpack('H*hex', inet_pton($ip));
        $hex = $unpack['hex'];
        return implode('.', array_reverse(str_split($hex)));
    }

    /**
     * Translate ipv4 address to reverse octets
     *
     * @param string $ipAddr
     * @return string
     */
    public function getArpaFromIpv4($ipAddr = '127.0.0.1')
    {
        return implode(".", array_reverse(explode(".", $ipAddr)));
    }

    /**
     * Translate ipv6 reverse octets to ipv6 address
     *
     * @param string $arpaOctets
     * @return string
     */
    public function getIpv6FromOctets($arpaOctets = '0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0')
    {
        return @inet_ntop(pack('H*', implode("", array_reverse(explode(".", preg_replace("/\.ip6\.arpa$|\.ip\.int$/", '', $arpaOctets))))));
    }

    function Redirect($redirectToUrl = '', $replaceHeader = false, $responseCode = 301)
    {
        header("Location: $redirectToUrl", $replaceHeader, $responseCode);
        exit;
    }

}

/**
 * Class TorneLIB_Network_IP
 *
 * IP Address Types class
 *
 * @package TorneLIB
 */
abstract class TorneLIB_Network_IP
{
    const IPTYPE_NONE = 0;
    const IPTYPE_V4 = 4;
    const IPTYPE_V6 = 6;
}


if (function_exists('curl_init')) {
    /**
     * Class Tornevall_cURL
     *
     * Versioning are based on TorneLIB v5, but follows its own standards in the chain.
     *
     * Library for handling calls with cURL. It works with high level of verbosity so parsing can be made easier. When using
     * the methods for GET, POST, PUT or DELETE we will always return a fully parsed response as an array in the following format
     *
     * <pre>
     * array(
     *  "header" => array("info", "full")
     *  "body" => '-body content-',
     *  "code" => numericResponseCodeFromPage,
     *  "parsed" => detectedParsedResponse
     * )
     * </pre>
     *
     * If we for example get a json-string as a response from a webpage, the parsed field will containg a parsed object for the result.
     *
     * Currently TorneLIB contains the TorneAPI-package, so the TorneAPI package should also have this version since it's the most updated
     * and proper version.
     *
     * @package TorneLIB
     * @link http://docs.tornevall.net/x/FoBU TorneLIB
     */
    class Tornevall_cURL
    {
        /**
         * Prepare TorneLIB_Network class if it exists (as of the november 2016 it does).
         *
         * @var TorneLIB_Network
         */
        private $NETWORK;

        /** @var string Internal version that is being used to find out if we are running the latest version of this library */
        private $TorneCurlVersion = "5.0.0";

        /** @var string Internal release snapshot that is being used to find out if we are running the latest version of this library */
        private $TorneCurlRelease = "20161129";

        /**
         * Autodetecting of SSL capabilities section
         *
         * Default settings: Always disabled, to let the system handle this automatically.
         * If there are problems reaching wsdl or connecting to https-based URLs, try set $testssl to true
         *
         */

        /**
         * @var bool $testssl
         *
         * For PHP >= 5.6.0: If defined, try to guess if there is valid certificate bundles when using for example https links (used with openssl).
         *
         * This function activated by setting this value to true, tries to detect whether sslVerify should be used or not.
         * The default value of this setting is normally false, since there should be no problems in a properly installed environment.
         */
        private $testssl = true;
        /** @var bool Do not test certificates on older PHP-version (< 5.6.0) if this is false */
        private $testssldeprecated = false;
        /** @var bool If there are problems with certificates bound to a host/peer, set this to false if necessary. Default is to always try to verify them */
        private $sslVerify = true;

        /** @var array Default paths to the certificates we are looking for */
        public $sslPemLocations = array('/etc/ssl/certs/cacert.pem', '/etc/ssl/certs/ca-certificates.crt');
        /** @var bool During tests this will be set to true if certificate files is found */
        private $hasCertFile = false;
        private $useCertFile = "";
        private $hasDefaultCertFile = false;
        private $openSslGuessed = false;
        /** @var bool During tests this will be set to true if certificate directory is found */
        private $hasCertDir = false;

        /** @var null Our communication channel */
        private $CurlSession = null;
        /** @var null URL to communicate with */
        private $CurlURL = null;

        /** @var array Default settings when initializing our curlsession */
        public $curlopt = array(
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_ENCODING => 1,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => 'TorneLIB-PHPcURL',
            CURLOPT_POST => true,
            CURLOPT_SSLVERSION => 4,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => array('Accept-Language: en'),
        );
        public $sslopt = array();

        /** @var array Interfaces to use */
        public $IpAddr = array();
        /** @var bool If more than one ip is set in the interfaces to use, this will make the interface go random */
        public $IpAddrRandom = true;
        private $CurlIp = null;
        private $CurlIpType = null;

        private $CookiePath = null;
        private $SaveCookies = false;
        private $CookieFile = null;
        private $CookiePathCreated = false;
        public $AllowTempAsCookiePath = false;

        /** @var null Sets a HTTP_REFERER to the http call */
        public $CurlReferer = null;

        /** @var string Use proxy */
        public $CurlProxy = '';

        /** @var bool Enable tunneling mode */
        public $CurlTunnel = false;
        /**
         * Die on use of proxy/tunnel on first try (Incomplete).
         *
         * This function is supposed to stop if the proxy fails on connection, so the library won't continue looking for a preferred exit point, since that will reveal the current unproxified address.
         *
         * @var bool
         */
        public $CurlProxyDeath = true;

        /**
         * How to resolve hosts (Default = Not set)
         *
         * RESOLVER_IPV4
         * RESOLVER_IPV6
         *
         * @var int
         */
        public $CurlResolve;

        /** @var string Sets another timeout in seconds when curl_exec should finish the current operation. Sets both TIMEOUT and CONNECTTIMEOUT */
        public $CurlTimeout = null;
        /** @var string Sets an encoding to the http call */
        public $CurlEncoding = null;
        /** @var bool Use cookies and save them if needed (Normally not needed, but enabled by default) */
        public $CurlUseCookies = true;
        private $CurlResolveForced = false;
        private $CurlResolveRetry = 0;
        private $CurlUserAgent = null;

        /** @var bool Try to automatically parse the retrieved body content. Supports, amongst others json, serialization, etc */
        public $CurlAutoParse = true;

        /**
         * Authentication
         */
        private $AuthData = array('Username'=> null, 'Password'=>null, 'Type' => CURL_AUTH_TYPES::AUTHTYPE_NONE);

        /** @var array Adding own headers to the HTTP-request here */
        public $CurlHeaders = array();

        /**
         * Set up if this library can throw exceptions, whenever it needs to do that.
         *
         * Note: This does not cover everything in the library. It was set up for handling SoapExceptions.
         *
         * @var bool
         */
        public $canThrow = true;

        /**
         * TorneLIB_CURL constructor.
         */
        public function __construct()
        {
            $this->CurlResolve = CURL_RESOLVER::RESOLVER_DEFAULT;
            $this->CurlUserAgent = 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 1.0.3705; .NET CLR 1.1.4322; Media Center PC 4.0; TorneLIB+cUrl '.$this->TorneCurlVersion.'/'.$this->TorneCurlRelease.')';
            if (class_exists('TorneLIB\TorneLIB_Network')) {
                $this->NETWORK = new TorneLIB_Network();
            }
            $this->openssl_guess();
            register_shutdown_function(array($this, 'tornecurl_terminate'));
        }

        /**
         * TorneCurl Termination Controller - Used amongst others, to make sure that empty cookiepaths created by this library gets removed if they are being used.
         */
        function tornecurl_terminate() {
            /*
             * If this indicates that we created the path, make sure it's removed if empty after session completion
             */
            if (!count(glob($this->CookiePath . "/*")) && $this->CookiePathCreated) {
                @rmdir($this->CookiePath);
            }
        }

        /**
         * Get this internal release version
         *
         * Requires the constant TORNELIB_ALLOW_VERSION_REQUESTS to return any information.
         *
         * @return string
         * @throws \Exception
         */
        public function getInternalRelease() {
            if (defined('TORNELIB_ALLOW_VERSION_REQUESTS') && TORNELIB_ALLOW_VERSION_REQUESTS === true) {
                return $this->TorneCurlVersion . "," . $this->TorneCurlRelease;
            }
            throw new \Exception("[".__CLASS__."] Version requests are not allowed", 403);
        }

        /**
         * @param string $libName
         * @return string
         */
        private function getHasUpdateState($libName = 'tornelib_curl') {
            /*
             * Currently only supporting this internal module (through $myRelease).
             */
            $myRelease = $this->getInternalRelease();
            $libRequest = (!empty($libName) ? "lib/".$libName : "");
            $getInfo = $this->doGet("https://api.tornevall.net/2.0/libs/getLibs/".$libRequest."/me/" . $myRelease);
            if (isset($getInfo['parsed']->response->getLibsResponse->you)) {
                $currentPublicVersion = $getInfo['parsed']->response->getLibsResponse->you;
                if ($currentPublicVersion->hasUpdate) {
                    if (isset($getInfo['parsed']->response->getLibsResponse->libs->tornelib_curl)) {
                        return $getInfo['parsed']->response->getLibsResponse->libs->tornelib_curl;
                    }
                } else {
                    return "";
                }
            }
            return "";
        }

        /**
         * Check against Tornevall Networks API if there are updates for this module
         *
         * @param string $libName
         * @return string
         */
        public function hasUpdate($libName = 'tornelib_curl') {
            if (!defined('TORNELIB_ALLOW_VERSION_REQUESTS')) {define('TORNELIB_ALLOW_VERSION_REQUESTS', true);}
            return $this->getHasUpdateState($libName);
        }

        /**
         * Set up a different user agent for this library
         *
         * To make proper identification of the library we are always appending TorbeLIB+cUrl to the chosen user agent string.
         *
         * @param null $CustomUserAgent
         */
        public function setUserAgent($CustomUserAgent = null) {
            if (!empty($CustomUserAgent)) {
                $this->CurlUserAgent = $CustomUserAgent . " +TorneLIB+cUrl ".$this->TorneCurlVersion.'/'.$this->TorneCurlRelease;
            } else {
                $this->CurlUserAgent = 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 1.0.3705; .NET CLR 1.1.4322; Media Center PC 4.0; TorneLIB+cUrl '.$this->TorneCurlVersion.'/'.$this->TorneCurlRelease.')';
            }
        }

        /**
         * cUrl initializer, if needed faster
         *
         * @return null|resource
         */
        public function init()
        {
            $this->CurlSession = curl_init($this->CurlURL);
            return $this->CurlSession;
        }

        /**
         * Initialize cookie storage
         *
         * @throws \Exception
         */
        private function initCookiePath()
        {
            /**
             * TORNEAPI_COOKIES has priority over TORNEAPI_PATH that is the default path
             */
            if (defined('TORNEAPI_COOKIES')) {
                $this->CookiePath = TORNEAPI_COOKIES;
            } else {
                if (defined('TORNEAPI_PATH')) {
                    $this->CookiePath = TORNEAPI_PATH . "/cookies";
                }
            }
            // If path is still empty after the above check, continue checking other paths
            if (empty($this->CookiePath) || (!empty($this->CookiePath) && !is_dir($this->CookiePath))) {
                // We could use /tmp as cookie path but it is not recommended (which means this permission is by default disabled
                if ($this->AllowTempAsCookiePath) {
                    if (is_dir("/tmp")) {
                        $this->CookiePath = "/tmp/";
                    }
                } else {
                    // However, if we still failed, we're trying to use a local directory
                    $realCookiePath = realpath(__DIR__ . "/../cookies");
                    if (empty($realCookiePath)) {
                        // Try to create a directory before bailing out
                        $getCookiePath = realpath(__DIR__ . "/../");
                        @mkdir($getCookiePath . "/cookies/");
                        $this->CookiePathCreated = true;
                        $this->CookiePath = realpath($getCookiePath . "/cookies/");
                    } else {
                        $this->CookiePath = realpath(__DIR__ . "/../cookies");
                    }
                    if (empty($this->CookiePath) || !is_dir($this->CookiePath)) {
                        throw new \Exception(__FUNCTION__ . ": Could not set up a proper cookiepath [To override this, use AllowTempAsCookiePath (not recommended)]", 1002);
                    }
                }
            }
        }

        /**
         * Returns an ongoing cUrl session - Normally you may get this from initSession (and normally you don't need this at all)
         *
         * @return null
         */
        public function getCurlSession()
        {
            return $this->CurlSession;
        }


        /**
         * Generate a correctified stream context depending on what happened in openssl_guess(), which also is running in this operation.
         *
         * Function created for moments when ini_set() fails in openssl_guess() and you don't want to "recalculate" the location of a valid certificates.
         * This normally occurs in improper configured environments (where this bulk of functions actually also has been tested in).
         * Recommendation of Usage: Do not copy only those functions, use the full version of tornevall_network.php since there may be dependencies in it.
         *
         * @return array
         * @link http://developer.tornevall.net/apigen/TorneLIB-5.0/class-TorneLIB.Tornevall_cURL.html sslStreamContextCorrection() is a part of TorneLIB 5.0, described here
         */
        public function sslStreamContextCorrection()
        {
            if (!$this->openSslGuessed) {
                $this->openssl_guess(true);
            }
            $caCert = $this->getCertFile();
            $sslVerify = true;
            $sslSetup = array();
            if (isset($this->sslVerify)) {
                $sslVerify = $this->sslVerify;
            }
            if (!empty($caCert)) {
                $sslSetup = array(
                    'cafile' => $caCert,
                    'verify_peer' => $sslVerify,
                    'verify_peer_name' => $sslVerify,
                    'verify_host' => $sslVerify,
                    'allow_self_signed' => true
                );
            }
            return $sslSetup;
        }

        /**
         * Automatically generates stream_context and appends it to whatever you need it for.
         *
         * Example:
         *  $appendArray = array('http' => array("user_agent" => "MyUserAgent"));
         *  $this->soapOptions = sslGetDefaultStreamContext($this->soapOptions, $appendArray);
         *
         * @param array $optionsArray
         * @param array $selfContext
         * @return array
         * @link http://developer.tornevall.net/apigen/TorneLIB-5.0/class-TorneLIB.Tornevall_cURL.html sslGetOptionsStream() is a part of TorneLIB 5.0, described here
         */
        public function sslGetOptionsStream($optionsArray = array(), $selfContext = array())
        {
            $streamContextOptions = array();
            if (empty($this->CurlUserAgent)) {
                $this->setUserAgent();
            }
            $streamContextOptions['http'] = array(
                "user_agent" => $this->CurlUserAgent
            );
            $sslCorrection = $this->sslStreamContextCorrection();
            if (count($sslCorrection)) {
                $streamContextOptions['ssl'] = $this->sslStreamContextCorrection();
            }
            foreach ($selfContext as $contextKey => $contextValue) {
                $streamContextOptions[$contextKey] = $contextValue;
            }
            $optionsArray['stream_context'] = stream_context_create($streamContextOptions);
            $this->sslopt = $optionsArray;
            return $optionsArray;
        }


        /**
         * SSL Cerificate Handler
         *
         * This method tries to handle SSL Certification locations where PHP can't handle that part itself. In some environments (normally customized), PHP sometimes have
         * problems with finding certificates, in case for example where they are not placed in standard locations. When running the testing, we will also try to set up
         * a correct location for the certificates, if any are found somewhere else.
         *
         * The default configuration for this method is to not run any test, since there should be no problems of running in properly installed environments.
         * If there are known problems in the environment that is being used, you can try to set $testssl to true.
         *
         * At first, the variable $testssl is used to automatically try to find out if there is valid certificate bundles installed on the running system. In PHP 5.6.0 and higher
         * this procedure is simplified with the help of openssl_get_cert_locations(), which gives us a default path to installed certificates. In this case we will first look there
         * for the certificate bundle. If we do fail there, or if your system is running something older, the testing are running in guessing mode.
         *
         * The method is untested in Windows server environments when using OpenSSL.
         *
         * @param bool $forceTesting Force testing even if $testssl is disabled
         * @link http://developer.tornevall.net/apigen/TorneLIB-5.0/class-TorneLIB.Tornevall_cURL.html openssl_guess() is a part of TorneLIB 5.0, described here
         * @return bool
         */
        private function openssl_guess($forceTesting = false)
        {
            $pemLocation = "";
            if ($this->testssl || $forceTesting) {
                $this->openSslGuessed = true;
                if (version_compare(PHP_VERSION, "5.6.0", ">=") && function_exists("openssl_get_cert_locations")) {
                    $locations = openssl_get_cert_locations();
                    if (is_array($locations)) {
                        if (isset($locations['default_cert_file'])) {
                            /* If it exists don't bother */
                            if (file_exists($locations['default_cert_file'])) {
                                $this->hasCertFile = true;
                                $this->useCertFile = $locations['default_cert_file'];
                                $this->hasDefaultCertFile = true;
                            }
                            if (file_exists($locations['default_cert_dir'])) {
                                $this->hasCertDir = true;
                            }
                            /* Sometimes certificates are located in a default location, which is /etc/ssl/certs - this part scans through such directories for a proper cert-file */
                            if (!$this->hasCertFile && is_array($this->sslPemLocations) && count($this->sslPemLocations)) {
                                /* Loop through suggested locations and set a cafile if found */
                                foreach ($this->sslPemLocations as $pemLocation) {
                                    if (file_exists($pemLocation)) {
                                        ini_set('openssl.cafile', $pemLocation);
                                        $this->useCertFile = $pemLocation;
                                        $this->hasCertFile = true;
                                    }
                                }
                            }
                        }
                    }
                    /* On guess, disable verification if failed */
                    if (!$this->hasCertFile) {
                        $this->setSslVerify(false);
                    }
                } else {
                    /* If we run on other PHP versions than 5.6.0 or higher, try to fall back into a known directory */
                    if ($this->testssldeprecated) {
                        if (!$this->hasCertFile && is_array($this->sslPemLocations) && count($this->sslPemLocations)) {
                            /* Loop through suggested locations and set a cafile if found */
                            foreach ($this->sslPemLocations as $pemLocation) {
                                if (file_exists($pemLocation)) {
                                    ini_set('openssl.cafile', $pemLocation);
                                    $this->useCertFile = $pemLocation;
                                    $this->hasCertFile = true;
                                }
                            }
                        }
                        if (!$this->hasCertFile) {
                            $this->setSslVerify(false);
                        }
                    }
                }
            }
            return $this->hasCertFile;
        }

        /**
         * Enable/disable SSL Certificate autodetection (and/or host/peer ssl verications)
         *
         * The $hostVerification-flag can also be called manually with setSslVerify()
         *
         * @param bool $enabledFlag
         * @param bool $hostVerification
         */
        public function setCertAuto($enabledFlag = true, $hostVerification = true)
        {
            $this->testssl = $enabledFlag;
            $this->testssldeprecated = $enabledFlag;
            $this->sslVerify = $hostVerification;
        }

        /**
         * Enable/disable SSL Peer/Host verification, if problems occur with certificates. If setCertAuto is enabled, this function will use best practice.
         *
         * @param bool|true $enabledFlag
         */
        public function setSslVerify($enabledFlag = true)
        {
            $this->sslVerify = $enabledFlag;
        }


        /**
         * TestCerts - Test if your webclient has certificates available (make sure the $testssldeprecated are enabled if you want to test older PHP-versions - meaning older than 5.6.0)
         *
         * Note: This function also forces full ssl certificate checking.
         *
         * @return bool
         */
        public function TestCerts()
        {
            return $this->openssl_guess(true);
        }

        /**
         * Return the current certificate bundle file, chosen by autodetection
         * @return string
         */
        public function getCertFile()
        {
            return $this->useCertFile;
        }

        /**
         * Returns true if the autodetected certificate bundle was one of the defaults (normally fetched from openssl_get_cert_locations()). Used for testings.
         *
         * @return bool
         */
        public function hasCertDefault()
        {
            return $this->hasDefaultCertFile;
        }

        /**
         * Extract domain name from URL
         *
         * @param string $url
         * @return array
         */
        private function ExtractDomain($url = '')
        {
            $urex = explode("/", preg_replace("[^(.*?)//(.*?)/(.*)]", '$2', $url . "/"));
            $urtype = preg_replace("[^(.*?)://(.*)]", '$1', $url . "/");
            return array($urex[0], $urtype);
        }

        /**
         * Translate ipv4 to reverse octets
         *
         * @param string $ip
         * @param bool $getiptype
         * @return string
         */
        private function v4arpa($ip = '', $getiptype = false)
        {
            return $this->NETWORK->getArpaFromIpv4($ip, $getiptype);
        }

        /**
         * ipv6-to-arpa-format-conversion
         *
         * @param string $ip
         * @return string
         */
        private function v6arpa($ip = '::')
        {
            return $this->NETWORK->getArpaFromAddr($ip);
        }

        /**
         * Translate ipv6-octets to ipv6-address
         *
         * @param string $arpaOctets
         * @return string
         */
        private function fromv6arpa($arpaOctets = '')
        {
            return $this->NETWORK->getIpv6FromOctets($arpaOctets);
        }

        /**
         * Get reverse octets from ip address
         *
         * @param string $ip
         * @param bool $getiptype
         * @return int|string
         */
        private function toarpa($ip = '', $getiptype = false)
        {
            return $this->NETWORK->getArpaFromAddr($ip, $getiptype);
        }


        /**
         * Making sure the $IpAddr contains valid address list
         *
         * @throws \Exception
         */
        private function handleIpList()
        {
            $this->CurlIp = null;
            $UseIp = "";
            if (is_array($this->IpAddr)) {
                if (count($this->IpAddr) == 1) {
                    $UseIp = (isset($this->IpAddr[0]) && !empty($this->IpAddr[0]) ? $this->IpAddr[0] : null);
                } elseif (count($this->IpAddr) > 1) {
                    if (!$this->IpAddrRandom) {
                        $UseIp = (isset($this->IpAddr[0]) && !empty($this->IpAddr[0]) ? $this->IpAddr[0] : null);
                    } else {
                        $IpAddrNum = rand(0, count($this->IpAddr) - 1);
                        $UseIp = $this->IpAddr[$IpAddrNum];
                    }
                }
            }

            $ipType = $this->NETWORK->getArpaFromAddr($UseIp, true);
            /*
             * Bind interface to specific ip only if any are found
             */
            if ($ipType == "0") {
                /*
                 * If the ip type is 0 and it shows up there is something defined here, throw an exception.
                 */
                if (!empty($UseIp)) {
                    throw new \Exception(__FUNCTION__ . ": " . $UseIp . " is not a valid ip-address", 1003);
                }
            } else {
                $this->CurlIp = $UseIp;
                curl_setopt($this->CurlSession, CURLOPT_INTERFACE, $UseIp);
                if ($ipType == 6) {
                    curl_setopt($this->CurlSession, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V6);
                    $this->CurlIpType = 6;
                } else {
                    curl_setopt($this->CurlSession, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
                    $this->CurlIpType = 4;
                }
            }
        }

        /**
         * Parse content and handle specially received content automatically
         *
         * If this functions receives a json string or any other special content (as PHP-serializations), it will try to convert that string automatically to a readable array.
         *
         * @param string $content
         * @return mixed|null
         */
        public function ParseContent($content = '', $isFullRequest = false, $contentType = null)
        {
            if ($isFullRequest) {
                $newContent = $this->ParseResponse($content);
                $content = $newContent['body'];
                $contentType = isset($newContent['header']['info']['Content-Type']) ? $newContent['header']['info']['Content-Type'] : null;
            }
            $parsedContent = null;
            $testJson = @json_decode($content);
            if (gettype($testJson) === "object") {
                $parsedContent = $testJson;
            }
            $testSerialization = @unserialize($content);
            if (gettype($testSerialization) === "object" || gettype($testSerialization) === "array") {
                $parsedContent = $testSerialization;
            }
            if (is_null($parsedContent) && (preg_match("/xml version/", $content) || preg_match("/rss version/", $content) || preg_match("/xml/i", $contentType))) {
                if (!empty(trim($content))) {
                    $simpleXML = new \SimpleXMLElement($content);
                    if (isset($simpleXML) && is_object($simpleXML)) {
                        return $simpleXML;
                    }
                } else {
                    return null;
                }
            }
            return $parsedContent;
        }

        /**
         * Get head and body from a request parsed
         *
         * @param string $content
         * @return array
         */
        public function getHeader($content = "") {
            return $this->ParseResponse($content . "\r\n\r\n", null);
        }

        /**
         * Parse response, in case of there is any followed traces from the curl engine, so we'll always land on the right ending stream
         *
         * @param string $content
         * @return array
         */
        private function ParseResponse($content = '')
        {
            if (!is_string($content)) {
                return $content;
            }
            list($header, $body) = explode("\r\n\r\n", $content, 2);
            $rows = explode("\n", $header);
            $response = explode(" ", $rows[0]);
            $code = isset($response[1]) ? $response[1] : -1;
            // If the first row of the body contains a HTTP/-string, we'll try to reparse it
            if (preg_match("/^HTTP\//", $body)) {
                $newBody = $this->ParseResponse($body, true);
                $header = $newBody['header'];
                $body = $newBody['body'];
            }

            // If response code starts with 3xx, this is probably a redirect
            if (preg_match("/^3/", $code)) {
                $redirectArray[] = array(
                    'header' => $header,
                    'body' => $body,
                    'code' => $code
                );
                $redirectContent = $this->ParseContent($body, false);
            }
            $headerInfo = $this->GetHeaderKeyArray($rows);
            $returnResponse = array(
                'header' => array('info' => $headerInfo, 'full' => $header),
                'body' => $body,
                'code' => $code
            );
            if ($this->CurlAutoParse) {
                $contentType = isset($headerInfo['Content-Type']) ? $headerInfo['Content-Type'] : null;
                $parsedContent = $this->ParseContent($returnResponse['body'], false, $contentType);
                $returnResponse['parsed'] = (!empty($parsedContent) ? $parsedContent : null);
            }
            return $returnResponse;
        }

        /**
         * Create an array of a header, with keys and values
         *
         * @param $HeaderRows
         * @return array
         */
        private function GetHeaderKeyArray($HeaderRows)
        {
            $headerInfo = array();
            foreach ($HeaderRows as $headRow) {
                $colon = array_map("trim", explode(":", $headRow, 2));
                if (isset($colon[1])) {
                    $headerInfo[$colon[0]] = $colon[1];
                } else {
                    $rowSpc = explode(" ", $headRow);
                    if (isset($rowSpc[0])) {
                        $headerInfo[$rowSpc[0]] = $headRow;
                    } else {
                        $headerInfo[$headRow] = $headRow;
                    }
                }
            }
            return $headerInfo;
        }

        /**
         * Return number of tries resolver has been working
         *
         * @return int
         */
        public function getRetries()
        {
            return $this->CurlResolveRetry;
        }

        /**
         * Call cUrl with a POST
         *
         * @param string $url
         * @param array $postData
         * @return array
         * @throws \Exception
         */
        public function doPost($url = '', $postData = array(), $postAs = CURL_POST_AS::POST_AS_NORMAL)
        {
            $content = $this->handleUrlCall($url, $postData, CURL_METHODS::METHOD_POST, $postAs);
            $ResponseArray = $this->ParseResponse($content);
            return $ResponseArray;
        }

        /**
         * @param string $url
         * @param array $postData
         * @return array
         */
        public function doPut($url = '', $postData = array(), $postAs = CURL_POST_AS::POST_AS_NORMAL)
        {
            $content = $this->handleUrlCall($url, $postData, CURL_METHODS::METHOD_PUT, $postAs);
            $ResponseArray = $this->ParseResponse($content);
            return $ResponseArray;
        }

        /**
         * @param string $url
         * @param array $postData
         * @return array
         */
        public function doDelete($url = '', $postData = array(), $postAs = CURL_POST_AS::POST_AS_NORMAL)
        {
            $content = $this->handleUrlCall($url, $postData, CURL_METHODS::METHOD_DELETE, $postAs);
            $ResponseArray = $this->ParseResponse($content);
            return $ResponseArray;
        }

        /**
         * Call cUrl with a GET
         *
         * @param string $url
         * @return array
         */
        public function doGet($url = '')
        {
            $content = $this->handleUrlCall($url, array(), CURL_METHODS::METHOD_GET);
            $ResponseArray = $this->ParseResponse($content);
            return $ResponseArray;
        }

        /**
         * Enable authentication with cURL.
         *
         * @param null $Username
         * @param null $Password
         * @param int $AuthType Falls back on CURLAUTH_ANY if none are given. CURL_AUTH_TYPES are minimalistic since it follows the standards of CURLAUTH_*
         */
        public function setAuthentication($Username = null, $Password = null, $AuthType = CURL_AUTH_TYPES::AUTHTYPE_BASIC) {
            $this->AuthData['Username'] = $Username;
            $this->AuthData['Password'] = $Password;
            $this->AuthData['Type'] = $AuthType;
        }

        /**
         * Fix problematic header data
         *
         * @param array $headerList
         * @return array
         */
        private function fixHttpHeaders($headerList = array()) {
            if (is_array($headerList) && count($headerList)) {
                $newHeader = array();
                foreach ($headerList as $headerKey => $headerValue) {
                    $testHead = explode(":", $headerValue, 2);
                    if (isset($testHead[1])) {
                        $newHeader[] = $headerValue;
                    } else {
                        if (!is_numeric($headerKey)) {
                            $newHeader[] = $headerKey . ": " . $headerValue;
                        }
                    }
                }
            }
            return $newHeader;
        }

        /**
         * cURL data handler, sets up cURL in what it believes is the correct set for you.
         *
         * @param string $url
         * @param array $postData
         * @param int $CurlMethod
         * @param int $postAs
         * @return mixed
         * @throws \Exception
         */
        private function handleUrlCall($url = '', $postData = array(), $CurlMethod = CURL_METHODS::METHOD_GET, $postAs = CURL_POST_AS::POST_AS_NORMAL)
        {
            if (!empty($url)) {
                $this->CurlURL = $url;
            }

            if (preg_match("/\?wsdl$|\&wsdl$/i", $this->CurlURL)) {
                $Soap = new Tornevall_SimpleSoap($this->CurlURL, $this->curlopt);
                $Soap->setThrowableState($this->canThrow);
                $Soap->setSoapAuthentication($this->AuthData);
                return $Soap->getSoap();
            }

            $this->initCookiePath();
            $this->init();
            $myIp = $this->handleIpList();
            curl_setopt($this->CurlSession, CURLOPT_URL, $this->CurlURL);

            if (is_array($postData)) {
                $postDataContainer = http_build_query($postData);
            } else {
                $postDataContainer = $postData;
            }

            $domainArray = $this->ExtractDomain($this->CurlURL);
            $domainName = null;
            $domainHash = null;
            if (isset($domainArray[0])) {
                $domainName = $domainArray[0];
                $domainHash = md5($domainName);
            }

            /**** CONDITIONAL SETUP ****/

            // Lazysession: Sets post data if any found and sends it even if the curl-method is GET or any other than POST
            if (!empty($postDataContainer)) {
                curl_setopt($this->CurlSession, CURLOPT_POSTFIELDS, $postDataContainer);
            }
            if ($CurlMethod == CURL_METHODS::METHOD_POST || $CurlMethod == CURL_METHODS::METHOD_PUT || $CurlMethod == CURL_METHODS::METHOD_DELETE) {
                if ($CurlMethod == CURL_METHODS::METHOD_PUT) {
                    curl_setopt($this->CurlSession, CURLOPT_CUSTOMREQUEST, "PUT");
                } else if ($CurlMethod == CURL_METHODS::METHOD_DELETE) {
                    curl_setopt($this->CurlSession, CURLOPT_CUSTOMREQUEST, "DELETE");
                } else {
                    curl_setopt($this->CurlSession, CURLOPT_POST, true);
                }

                if ($postAs == CURL_POST_AS::POST_AS_JSON) {
                    /*
                     * Using $jsonRealData to validate the string
                     */
                    $jsonRealData = null;
                    if (!is_string($postData)) {
                        $jsonRealData = json_encode($postData);
                    } else {
                        $testJsonData = json_decode($postData);
                        if (is_object($testJsonData)) {
                            $jsonRealData = $postData;
                        }
                    }
                    $this->CurlHeaders['Content-Type'] = "application/json";
                    $this->CurlHeaders['Content-Length'] = strlen($jsonRealData);
                    curl_setopt($this->CurlSession, CURLOPT_POSTFIELDS, $jsonRealData);
                }
            }

            // Self set timeouts
            if (isset($this->CurlTimeout) && $this->CurlTimeout > 0) {
                curl_setopt($this->CurlSession, CURLOPT_CONNECTTIMEOUT, ceil($this->CurlTimeout / 2));
                curl_setopt($this->CurlSession, CURLOPT_TIMEOUT, $this->CurlTimeout);
            }
            if (isset($this->CurlResolve) && $this->CurlResolve !== CURL_RESOLVER::RESOLVER_DEFAULT) {
                if ($this->CurlResolve == CURL_RESOLVER::RESOLVER_IPV4) {
                    curl_setopt($this->CurlSession, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
                }
                if ($this->CurlResolve == CURL_RESOLVER::RESOLVER_IPV6) {
                    curl_setopt($this->CurlSession, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V6);
                }
            }

            // If certificates missing
            if (!$this->TestCerts()) {
                // And we're allowed to run without them
                if (!$this->sslVerify) {
                    // Then disable the checking here
                    curl_setopt($this->CurlSession, CURLOPT_SSL_VERIFYHOST, 0);
                    curl_setopt($this->CurlSession, CURLOPT_SSL_VERIFYPEER, 0);
                }
            }
            curl_setopt($this->CurlSession, CURLOPT_VERBOSE, false);
            // Run from proxy
            if (isset($this->CurlProxy)) {
                curl_setopt($this->CurlSession, CURLOPT_PROXY, $this->CurlProxy);
                unset($this->CurlIp);
            }
            // Run in tunneling mode
            if (isset($this->CurlTunnel)) {
                curl_setopt($this->CurlSession, CURLOPT_HTTPPROXYTUNNEL, true);
                unset($this->CurlIp);
            }
            // Another HTTP_REFERER
            if (isset($this->CurlReferer) && !empty($this->CurlReferer)) {
                curl_setopt($this->CurlSession, CURLOPT_REFERER, $this->CurlReferer);
            }
            // If this is really necessary, allow it
            if (isset($this->CurlHeaders) && is_array($this->CurlHeaders) && count($this->CurlHeaders)) {
                $this->CurlHeaders = $this->fixHttpHeaders($this->CurlHeaders);
                curl_setopt($this->CurlSession, CURLOPT_HTTPHEADER, $this->CurlHeaders);
            }

            if (isset($this->CurlUserAgent) && !empty($this->CurlUserAgent)) {
                curl_setopt($this->CurlSession, CURLOPT_USERAGENT, $this->CurlUserAgent);
            }
            if (isset($this->CurlEncoding) && !empty($this->CurlEncoding)) {
                curl_setopt($this->CurlSession, CURLOPT_ENCODING, $this->CurlEncoding);
            }
            if (file_exists($this->CookiePath) && $this->CurlUseCookies && !empty($this->CurlURL)) {
                @file_put_contents($this->CookiePath . "/tmpcookie", "test");
                if (!file_exists($this->CookiePath . "/tmpcookie")) {
                    $this->SaveCookies = true;
                    $this->CookieFile = $domainHash;
                    curl_setopt($this->CurlSession, CURLOPT_COOKIEFILE, $this->CookiePath . "/" . $this->CookieFile);
                    curl_setopt($this->CurlSession, CURLOPT_COOKIEJAR, $this->CookiePath . "/" . $this->CookieFile);
                    curl_setopt($this->CurlSession, CURLOPT_COOKIE, 1);

                } else {
                    if (file_exists($this->CookiePath . "/tmpcookie")) {
                        unlink($this->CookiePath . "/tmpcookie");
                    }
                    $this->SaveCookies = false;
                }
            } else {
                $this->SaveCookies = false;
            }

            if (!empty($this->AuthData['Username']) && $this->AuthData['Type'] != CURL_AUTH_TYPES::AUTHTYPE_NONE) {
                $useAuth = CURLAUTH_ANY;
                if (CURL_AUTH_TYPES::AUTHTYPE_BASIC) { $useAuth = CURLAUTH_BASIC; }
                curl_setopt($this->CurlSession, CURLOPT_HTTPAUTH, $useAuth);
                curl_setopt($this->CurlSession, CURLOPT_USERPWD, $this->AuthData['Username'] . ':' . $this->AuthData['Password']);
            }

            /**** UNCONDITIONAL SETUP ****/
            curl_setopt($this->CurlSession, CURLOPT_HEADER, true);
            curl_setopt($this->CurlSession, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($this->CurlSession, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($this->CurlSession, CURLOPT_AUTOREFERER, true);
            curl_setopt($this->CurlSession, CURLINFO_HEADER_OUT, true);

            $returnContent = curl_exec($this->CurlSession);
            if (curl_errno($this->CurlSession)) {
                $errorCode = curl_errno($this->CurlSession);
                if ($this->CurlResolveForced && $this->CurlResolveRetry >= 2) {
                    throw new xception(__FUNCTION__ . ": Could not fetch url after internal retries", 1004);
                }
                if ($errorCode == CURLE_COULDNT_RESOLVE_HOST || $errorCode === 45) {
                    $this->CurlResolveRetry++;
                    unset($this->CurlIp);
                    $this->CurlResolveForced = true;
                    if ($this->CurlIpType == 6) {
                        $this->CurlResolve = CURL_RESOLVER::RESOLVER_IPV4;
                    }
                    if ($this->CurlIpType == 4) {
                        $this->CurlResolve = CURL_RESOLVER::RESOLVER_IPV6;
                    }
                    return $this->handleUrlCall($this->CurlURL, $postData, $CurlMethod);
                }
                throw new \Exception("PHPException at ".__FUNCTION__.": " . curl_error($this->CurlSession), curl_errno($this->CurlSession));
            }
            return $returnContent;
        }
    }
} else {
    throw new \Exception("curl library not found");
}

/**
 * Class TorneLIB_SimpleSoap Simple SOAP client.
 *
 * Masking no difference of a SOAP call and a regular GET/POST
 *
 * @package TorneLIB
 */
class Tornevall_SimpleSoap extends Tornevall_cURL {

    protected $soapClient;
    protected $soapOptions = array();
    protected $addSoapOptions = array(
        'exceptions' => true,
        'trace' => true,
        'cache_wsdl' => WSDL_CACHE_BOTH
    );
    private $soapUrl;
    private $AuthData;
    private $soapRequest;
    private $soapRequestHeaders;
    private $soapResponse;
    private $soapResponseHeaders;
    private $libResponse;
    private $canThrowSoapFaults = true;

    public $SoapFaultString = null;
    public $SoapFaultCode = 0;

    function __construct($Url, $SoapOptions = array())
    {
        $this->setUserAgent("TorneLIB-cUrlClient/SimpleSoap");
        $this->soapUrl = $Url;
        $this->sslGetOptionsStream();
        if (!count($SoapOptions)) {
            $this->soapOptions = $SoapOptions;
        }
        foreach ($this->addSoapOptions as $soapKey => $soapValue) {
            if (!isset($this->soapOptions[$soapKey])) {
                $this->soapOptions[$soapKey] = $soapValue;
            }
        }
    }
    public function setSoapAuthentication($AuthData = array()) {
        $this->AuthData = $AuthData;
        if (!empty($this->AuthData['Username']) && !empty($this->AuthData['Password']) && !isset($this->soapOptions['login']) && !isset($this->soapOptions['password'])) {
            $this->soapOptions['login'] = $this->AuthData['Username'];
            $this->soapOptions['password'] = $this->AuthData['Password'];
        }
    }
    public function setThrowableState($throwable = true) {
        $this->canThrowSoapFaults = $throwable;
    }

    public function getSoap()
    {
        $this->soapClient = null;
        if (gettype($this->sslopt['stream_context']) == "resource") {
            $this->soapOptions['stream_context'] = $this->sslopt['stream_context'];
        }
        $this->soapClient = new \SoapClient($this->soapUrl, $this->soapOptions);
        return $this;
    }

    function __call($name, $arguments)
    {
        $returnResponse = array(
            'header' => array('info' => null, 'full' => null),
            'body' => null,
            'code' => null
        );

        $SoapClientResponse = null;
        try {
            if (isset($arguments[0])) {
                $SoapClientResponse = $this->soapClient->$name($arguments[0]);
            } else {
                $SoapClientResponse = $this->soapClient->$name();
            }
        } catch (\SoapFault $e) {
            $this->soapRequest = $this->soapClient->__getLastRequest();
            $this->soapRequestHeaders = $this->soapClient->__getLastRequestHeaders();
            $this->soapResponse = $this->soapClient->__getLastResponse();
            $this->soapResponseHeaders = $this->soapClient->__getLastResponseHeaders();
            $parsedHeader = $this->getHeader($this->soapResponseHeaders);
            $returnResponse['header'] = $parsedHeader['header'];
            $returnResponse['code'] = isset($parsedHeader['code']) ? $parsedHeader['code'] : 0;
            $returnResponse['body'] = $this->soapResponse;
            /*
             * Collect the response received internally, before throwing
             */
            $this->libResponse = $returnResponse;
            if ($this->canThrowSoapFaults) {
                throw new \Exception($e->getMessage(), $e->getCode());
            }
            $this->SoapFaultString = $e->getMessage();
            $this->SoapFaultCode = $e->getCode();
        }

        $this->soapRequest = $this->soapClient->__getLastRequest();
        $this->soapRequestHeaders = $this->soapClient->__getLastRequestHeaders();
        $this->soapResponse = $this->soapClient->__getLastResponse();
        $this->soapResponseHeaders = $this->soapClient->__getLastResponseHeaders();
        $parsedHeader = $this->getHeader($this->soapResponseHeaders);
        $returnResponse['header'] = $parsedHeader['header'];
        $returnResponse['code'] = isset($parsedHeader['code']) ? $parsedHeader['code'] : 0;
        $returnResponse['body'] = $this->soapResponse;
        $returnResponse['parsed'] = $SoapClientResponse;
        if (isset($SoapClientResponse->return)) {
            $returnResponse['parsed'] = $SoapClientResponse->return;
        }
        $this->libResponse = $returnResponse;
        return $returnResponse;
    }

    /**
     * Get the SOAP response independently on exceptions or successes
     *
     * @return mixed
     */
    public function getLibResponse()
    {
        return $this->libResponse;
    }
}

/**
 * Class CURL_METHODS List of methods available in this library
 *
 * @package TorneLIB
 */
abstract class CURL_METHODS
{
    const METHOD_GET = 0;
    const METHOD_POST = 1;
    const METHOD_PUT = 2;
    const METHOD_DELETE = 3;
}

/**
 * Class CURL_RESOLVER Resolver methods that is available when trying to connect
 *
 * @package TorneLIB
 */
abstract class CURL_RESOLVER
{
    const RESOLVER_DEFAULT = 0;
    const RESOLVER_IPV4 = 1;
    const RESOLVER_IPV6 = 2;
}

/**
 * Class CURL_POST_AS Prepared formatting for POST-content in this library (Also available from for example PUT)
 *
 * @package TorneLIB
 */
abstract class CURL_POST_AS
{
    const POST_AS_NORMAL = 0;
    const POST_AS_JSON = 1;
}

/**
 * Class CURL_AUTH_TYPES Available authentication types for use with password protected sites
 *
 * Normally, this should not be necessary, since you can use the internal constants directly (for example basic authentication is reachable as CURLAUTH_BASIC). This is just a stupid helper.
 *
 * @package TorneLIB
 */
abstract class CURL_AUTH_TYPES {
    const AUTHTYPE_NONE = 0;
    const AUTHTYPE_BASIC = 1;
}
