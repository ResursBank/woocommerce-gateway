<?php

/**
 * Copyright 2018 Tomas Tornevall & Tornevall Networks
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * Tornevall Networks netCurl library - Yet another http- and network communicator library
 * Each class in this library has its own version numbering to keep track of where the changes are.
 * All since-markings are based on the major release of NetCurl.
 *
 * @package TorneLIB
 * @version 6.0.19
 */

namespace TorneLIB;

if ( ! class_exists( 'MODULE_CURL' ) && ! class_exists( 'TorneLIB\MODULE_CURL' ) ) {

	if ( ! defined( 'NETCURL_CURL_RELEASE' ) ) {
		define( 'NETCURL_CURL_RELEASE', '6.0.19' );
	}
	if ( ! defined( 'NETCURL_CURL_MODIFY' ) ) {
		define( 'NETCURL_CURL_MODIFY', '20180403' );
	}
	if ( ! defined( 'NETCURL_CURL_CLIENTNAME' ) ) {
		define( 'NETCURL_CURL_CLIENTNAME', 'MODULE_CURL' );
	}

	/**
	 * Class MODULE_CURL
	 *
	 * @package TorneLIB
	 * @link https://docs.tornevall.net/x/KQCy TorneLIBv5
	 * @link https://bitbucket.tornevall.net/projects/LIB/repos/tornelib-php-netcurl/browse Sources of TorneLIB
	 * @link https://docs.tornevall.net/x/KwCy Network & Curl v5 and v6 Library usage
	 * @link https://docs.tornevall.net/x/FoBU TorneLIB Full documentation
	 * @since 6.0.20
	 */
	class MODULE_CURL {

		//// PUBLIC VARIABLES
		/**
		 * Default settings when initializing our curlsession.
		 *
		 * Since v6.0.2 no urls are followed by default, it is set internally by first checking PHP security before setting this up.
		 * The reason of the change is not only the security, it is also about inheritage of options to SOAPClient.
		 *
		 * @var array
		 */
		private $curlopt = array(
			CURLOPT_CONNECTTIMEOUT => 6,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => 1,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_ENCODING       => 1,
			CURLOPT_TIMEOUT        => 10,
			CURLOPT_USERAGENT      => 'TorneLIB-PHPcURL',
			CURLOPT_POST           => true,
			CURLOPT_SSLVERSION     => 4,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_HTTPHEADER     => array( 'Accept-Language: en' ),
		);
		/** @var array User set SSL Options */
		private $sslopt = array();

		/** @var string $netCurlUrl Where to find NetCurl */
		private $netCurlUrl = 'http://www.netcurl.org/';

		/** @var array $NETCURL_POST_DATA Could also be a string */
		private $NETCURL_POST_DATA = array();
		private $NETCURL_POST_PREPARED_XML = '';
		/** @var NETCURL_POST_METHODS $NETCURL_POST_METHOD */
		private $NETCURL_POST_METHOD = NETCURL_POST_METHODS::METHOD_GET;
		/** @var NETCURL_POST_DATATYPES $NETCURL_POST_DATA_TYPE */
		private $NETCURL_POST_DATA_TYPE = NETCURL_POST_DATATYPES::DATATYPE_NOT_SET;

		private $NETCURL_ERRORHANDLER_HAS_ERRORS = false;
		private $NETCURL_ERRORHANDLER_RERUN = false;

		//// PUBLIC CONFIG THAT SHOULD GO PRIVATE
		/** @var array Interfaces to use */
		public $IpAddr = array();
		/** @var bool If more than one ip is set in the interfaces to use, this will make the interface go random */
		public $IpAddrRandom = true;
		/** @var null Sets a HTTP_REFERER to the http call */
		private $NETCURL_HTTP_REFERER;

		/** @var $POST_DATA_HANDLED */
		private $POST_DATA_HANDLED;
		/** @var $POSTDATACONTAINER */
		private $POSTDATACONTAINER;
		/** @var string $POST_DATA_REAL Post data as received from client */
		private $POST_DATA_REAL;

		/** @var array $NETCURL_RESPONSE_CONTAINER */
		protected $NETCURL_RESPONSE_CONTAINER;
		protected $NETCURL_RESPONSE_CONTAINER_PARSED;
		protected $NETCURL_RESPONSE_CONTAINER_BODY;
		protected $NETCURL_RESPONSE_CONTAINER_CODE;
		protected $NETCURL_RESPONSE_CONTAINER_HTTPMESSAGE;
		protected $NETCURL_RESPONSE_CONTAINER_HEADER;
		protected $NETCURL_RESPONSE_RAW;
		protected $NETCURL_REQUEST_HEADERS;
		protected $NETCURL_REQUEST_BODY;

		private $userAgents = array(
			'Mozilla' => 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 1.0.3705; .NET CLR 1.1.4322; Media Center PC 4.0;)'
		);

		/**
		 * Die on use of proxy/tunnel on first try (Incomplete).
		 *
		 * This function is supposed to stop if the proxy fails on connection, so the library won't continue looking for a preferred exit point, since that will reveal the current unproxified address.
		 *
		 * @var bool
		 */
		private $DIE_ON_LOST_PROXY = true;

		//// PRIVATE AND PROTECTED VARIABLES VARIABLES
		/**
		 * Prepare MODULE_NETWORK class if it exists (as of the november 2016 it does).
		 *
		 * @var MODULE_NETWORK
		 */
		private $NETWORK;
		/**
		 * @var NETCURL_DRIVER_CONTROLLER $DRIVER Communications driver controller
		 */
		private $DRIVER;
		/**
		 * @var MODULE_IO $IO
		 */
		private $IO;

		/** @var MODULE_SSL */
		private $SSL;
		private $TRUST_SSL_BUNDLES = false;

		/**
		 * Target environment (if target is production some debugging values will be skipped)
		 *
		 * @since 5.0.0
		 * @var int $TARGET_ENVIRONMENT
		 * @deprecated 6.0.20 Not in use
		 */
		private $TARGET_ENVIRONMENT = NETCURL_ENVIRONMENT::ENVIRONMENT_PRODUCTION;
		/** @var null Our communication channel */
		private $NETCURL_CURL_SESSION = null;
		/** @var null URL that was set to communicate with */
		private $CURL_STORED_URL = null;
		/**
		 * @var array $internalFlags Flags controller to change behaviour on internal function
		 * Chaining should eventually be default active in future (6.1?+)
		 */
		private $internalFlags = array( 'CHAIN' => true, 'SOAPCHAIN' => true );

		/**
		 * @var string $contentType Pre-Set content type, when installed modules needs to know in what format we are sending data
		 */
		private $contentType = '';

		/**
		 * @var array $DEBUG_DATA Debug data stored from session
		 */
		private $DEBUG_DATA = array(
			'data'     => array(
				'info' => array()
			),
			'soapData' => array(
				'info' => array()
			),
			'calls'    => 0
		);
		/**
		 * @var array Storage of invisible errors
		 * @since 6.0.20
		 */
		private $NETCURL_ERROR_CONTAINER = array();

		//// SSL AUTODETECTION CAPABILITIES
		/// DEFAULT: Most of the settings are set to be disabled, so that the system handles this automatically with defaults
		/// If there are problems reaching wsdl or connecting to https-based URLs, try set $testssl to true

		/**
		 * @var bool If SSL has been compiled in CURL, this will transform to true
		 * @since 6.0.20
		 */
		private $CURL_SSL_AVAILABLE = false;

		//// IP AND PROXY CONFIG
		private $CURL_IP_ADDRESS = null;
		private $CURL_IP_ADDRESS_TYPE = null;
		/** @var null CurlProxy, if set, we will try to proxify the traffic */
		private $CURL_PROXY_GATEWAY = null;
		/** @var null, if not set, but CurlProxy is, we will use HTTP as proxy (See CURLPROXY_* for more information) */
		private $CURL_PROXY_TYPE = null;
		/** @var bool Enable tunneling mode */
		private $CURL_TUNNEL = false;

		//// URL REDIRECT
		/** @var bool Decide whether the curl library should follow an url redirect or not */
		private $FOLLOW_LOCATION_ENABLE = true;
		/**
		 * @var array $REDIRECT_URLS List of redirections during curl calls
		 */
		private $REDIRECT_URLS = array();

		//// POST-GET-RESPONSE
		/**
		 * @var null A tempoary set of the response from the url called
		 * @deprecated 6.0.20
		 */
		private $TemporaryResponse = null;
		/**
		 * @var null Temporary response from external driver
		 * @deprecated 6.0.20
		 */
		private $TemporaryExternalResponse = null;
		/**
		 * @var array $ParseContainer
		 * @deprecated 6.0.20
		 */
		private $ParseContainer;

		/**
		 * @var NETCURL_POST_DATATYPES $FORCE_POST_TYPE What post type to use when using POST (Enforced)
		 */
		private $FORCE_POST_TYPE = null;
		/**
		 * @var string Current encoding
		 */
		public $HTTP_CHARACTER_ENCODING = null;
		/**
		 * @var array $CURL_RETRY_TYPES Counter for how many tries that has been done in a call
		 */
		private $CURL_RETRY_TYPES = array( 'resolve' => 0, 'sslunverified' => 0 );
		/** @var string Custom User-Agent sent in the HTTP-HEADER */
		private $HTTP_USER_AGENT;
		/**
		 * @var array Custom User-Agent Memory
		 */
		private $CUSTOM_USER_AGENT = array();
		/**
		 * @var bool Try to automatically parse the retrieved body content. Supports, amongst others json, serialization, etc
		 * @deprecated 6.0.20
		 */
		public $CurlAutoParse = true;
		/** @var bool Allow parsing of content bodies (tags) */
		private $allowParseHtml = false;
		private $NETCURL_RETURN_RESPONSE_TYPE = NETCURL_RESPONSETYPE::RESPONSETYPE_ARRAY;
		/** @var array Authentication */
		private $AuthData = array(
			'Username' => null,
			'Password' => null,
			'Type'     => NETCURL_AUTH_TYPES::AUTHTYPE_NONE
		);
		/** @var array Adding own headers to the HTTP-request here */
		private $NETCURL_HTTP_HEADERS = array();
		private $NETCURL_HEADERS_SYSTEM_DEFINED = array();
		private $NETCURL_HEADERS_USER_DEFINED = array();
		private $allowCdata = false;
		private $useXmlSerializer = false;
		/**
		 * Store information about the URL call and if the SSL was unsafe (disabled)
		 * @var bool
		 */
		protected $unsafeSslCall = false;

		//// COOKIE CONFIGS
		private $useLocalCookies = false;
		/**
		 * To which path we store cookies
		 * @var string $COOKIE_PATH
		 */
		private $COOKIE_PATH = '';
		/**
		 * Allow saving cookies
		 * @var bool
		 */
		private $SaveCookies = false;
		/**
		 * @var string $CookieFile The name of the file to save cookies in
		 */
		private $CookieFile = '';
		/**
		 * @var bool $UseCookieExceptions
		 * @deprecated 6.0.20
		 */
		private $UseCookieExceptions = false;
		/**
		 * @var bool $CurlUseCookies
		 * @deprecated 6.0.20
		 */
		public $CurlUseCookies = true;
		/**
		 * @var bool
		 * @since 6.0.20
		 */
		private $NETCURL_USE_COOKIES = true;

		//// RESOLVING AND TIMEOUTS

		/**
		 * How to resolve hosts (Default = Not set)
		 *
		 * RESOLVER_IPV4
		 * RESOLVER_IPV6
		 *
		 * @var int
		 * @deprecated 6.0.20
		 */
		public $CurlResolve;

		/**
		 * @var NETCURL_RESOLVER $CURL_RESOLVE_TYPE
		 * @since 6.0.20
		 */
		private $CURL_RESOLVE_TYPE = NETCURL_RESOLVER::RESOLVER_DEFAULT;
		/**
		 * @var bool
		 */
		private $CURL_RESOLVER_FORCED = false;

		/** @var string Sets another timeout in seconds when curl_exec should finish the current operation. Sets both TIMEOUT and CONNECTTIMEOUT */
		private $NETCURL_CURL_TIMEOUT;

		//// EXCEPTION HANDLING
		/** @var array Throwable http codes */
		private $throwableHttpCodes;
		/** @var bool By default, this library does not store any curl_getinfo during exceptions */
		private $canStoreSessionException = false;
		/** @var array An array that contains each curl_exec (curl_getinfo) when an exception are thrown */
		private $sessionsExceptions = array();
		/** @var bool The soapTryOnce variable */
		private $SoapTryOnce = true;
		private $curlConstantsOpt = array();
		private $curlConstantsErr = array();

		/**
		 * Set up if this library can throw exceptions, whenever it needs to do that.
		 *
		 * Note: This does not cover everything in the library. It was set up for handling SoapExceptions.
		 *
		 * @var bool
		 * @deprecated 6.0.20
		 */
		public $canThrow = true;

		/**
		 * @var bool
		 * @since 6.0.20
		 */
		private $NETCURL_CAN_THROW = true;

		/**
		 * MODULE_CURL constructor.
		 *
		 * @param string $requestUrl
		 * @param array $requestPostData
		 * @param int $requestPostMethod
		 * @param array $requestFlags
		 *
		 * @throws \Exception
		 */
		public function __construct( $requestUrl = '', $requestPostData = array(), $requestPostMethod = NETCURL_POST_METHODS::METHOD_POST, $requestFlags = array() ) {
			register_shutdown_function( array( $this, 'netcurl_terminate' ) );

			// PHP versions not supported to chaining gets the chaining parameter disabled by default.
			if ( version_compare( PHP_VERSION, "5.4.0", "<" ) ) {
				try {
					$this->setFlag( 'NOCHAIN', true );
				} catch ( \Exception $ignoreEmptyException ) {
					// This will never occur
				}
			}
			if ( is_array( $requestFlags ) && count( $requestFlags ) ) {
				$this->setFlags( $requestFlags );
			}

			$this->NETWORK = new MODULE_NETWORK();
			$this->DRIVER  = new NETCURL_DRIVER_CONTROLLER();
			if ( class_exists( 'TorneLIB\MODULE_IO' ) ) {
				$this->IO = new MODULE_IO();
			}
			$this->setConstantsContainer();
			$this->setPreparedAuthentication();
			$this->CurlResolve        = NETCURL_RESOLVER::RESOLVER_DEFAULT;
			$this->throwableHttpCodes = array();
			$this->getSslDriver();

			$this->HTTP_USER_AGENT = $this->userAgents['Mozilla'] . ' ' . NETCURL_CURL_CLIENTNAME . '-' . NETCURL_RELEASE . "/" . __CLASS__ . "-" . NETCURL_CURL_RELEASE . ' (' . $this->netCurlUrl . ')';
			if ( ! empty( $requestUrl ) ) {
				$this->CURL_STORED_URL = $requestUrl;
				$InstantResponse       = null;
				if ( $requestPostMethod == NETCURL_POST_METHODS::METHOD_GET ) {
					$InstantResponse = $this->doGet( $requestUrl );
				} else if ( $requestPostMethod == NETCURL_POST_METHODS::METHOD_POST ) {
					$InstantResponse = $this->doPost( $requestUrl, $requestPostData );
				} else if ( $requestPostMethod == NETCURL_POST_METHODS::METHOD_PUT ) {
					$InstantResponse = $this->doPut( $requestUrl, $requestPostData );
				} else if ( $requestPostMethod == NETCURL_POST_METHODS::METHOD_DELETE ) {
					$InstantResponse = $this->doDelete( $requestUrl, $requestPostData );
				}

				return $InstantResponse;
			}

			return null;
		}

		/**
		 * @deprecated 6.0.20
		 */
		public function init() {
			$this->initializeNetCurl();
		}

		/**
		 * Initialize NetCURL module and requirements
		 *
		 * @return resource
		 * @throws \Exception
		 * @since 6.0.20
		 */
		public function initializeNetCurl() {
			$this->initCookiePath();
			if ( ! $this->isFlag( 'NOTHROWABLES' ) ) {
				$this->setThrowableHttpCodes();
			}
			if ( ! is_object( $this->DRIVER->getDriver() ) && $this->DRIVER->getDriver() == NETCURL_NETWORK_DRIVERS::DRIVER_CURL ) {
				$this->initCurl();
			}

			return $this->NETCURL_CURL_SESSION;
		}

		/**
		 * Store constants of curl errors and curlOptions
		 * @since 6.0.20
		 */
		private function setConstantsContainer() {
			try {
				$constants = @get_defined_constants();
				foreach ( $constants as $constKey => $constInt ) {
					if ( preg_match( "/^curlopt/i", $constKey ) ) {
						$this->curlConstantsOpt[ $constInt ] = $constKey;
					}
					if ( preg_match( "/^curle/i", $constKey ) ) {
						$this->curlConstantsErr[ $constInt ] = $constKey;
					}
				}
			} catch ( \Exception $constantException ) {
			}
			unset( $constants );
		}

		/**
		 * Set up authentication
		 *
		 * @since 6.0.20
		 */
		private function setPreparedAuthentication() {
			$authFlags = $this->getFlag( 'auth' );
			if ( is_array( $authFlags ) && isset( $authFlags['username'] ) && isset( $authFlags['password'] ) ) {
				$this->setAuthentication( $authFlags['username'], $authFlags['password'], isset( $authFlags['type'] ) ? $authFlags['type'] : NETCURL_AUTH_TYPES::AUTHTYPE_BASIC );
			}
		}

		/**
		 * Initialize SSL driver and prepare
		 *
		 * @throws \Exception
		 * @since 6.0.20
		 */
		private function getSslDriver() {
			$curlSslDriver = MODULE_SSL::getCurlSslAvailable();
			// If no errors occurs here, we'll say that SSL is available on the system
			if ( ! count( $curlSslDriver ) ) {
				$this->CURL_SSL_AVAILABLE = true;
			}
			$this->SSL = new MODULE_SSL( $this );
		}

		/**
		 * Ask this module whether there are available modules for use with http calls or not. Can also be set up to return a complete list of modules
		 *
		 * @param bool $getAsList
		 * @param bool $ignoreException Do not throw any exceptions on testing
		 *
		 * @return bool|array
		 * @throws \Exception
		 * @since 6.0.14
		 * @deprecated Use NETCURL_DRIVER_CONTROLLER::
		 */
		public function getAvailableDrivers( $getAsList = false, $ignoreException = false ) {
			return $this->DRIVER->getSystemWideDrivers();
		}

		/**
		 * Get a list of all available and supported Addons for the module
		 *
		 * @return array
		 * @throws \Exception
		 * @since 6.0.14
		 * @deprecated
		 */
		public function getSupportedDrivers() {
			return $this->DRIVER->getSystemWideDrivers();
		}

		/**
		 * If the internal driver is available, we also consider curl available
		 *
		 * @return bool
		 * @throws \Exception
		 * @deprecated Use NETCURL_DRIVER_CONTROLLER->hasCurl or the static method NETCURL_DRIVER_CONTROLLER::getCurl()
		 */
		private function hasCurl() {
			return $this->DRIVER->hasCurl();
		}

		/**
		 * Is internal curl configured?
		 * @return bool
		 * @throws \Exception
		 * @since 6.0.20
		 */
		private function isCurl() {
			try {
				if ( ! is_object( $this->DRIVER->getDriver() ) && $this->DRIVER->getDriver() == NETCURL_NETWORK_DRIVERS::DRIVER_CURL ) {
					return true;
				}
			} catch ( \Exception $e ) {

			}

			return false;
		}

		/**
		 * Automatically find the best suited driver for communication IF curl does not exist. If curl exists, internal driver will always be picked as first option
		 *
		 * @return int|null|string
		 * @throws \Exception
		 * @since 6.0.14
		 */
		public function setDriverAuto() {
			return $this->DRIVER->getAutodetectedDriver();
		}

		/**
		 * @return array
		 * @since 6.0
		 */
		public function getDebugData() {
			return $this->DEBUG_DATA;
		}

		/**
		 * @param bool $useCookies
		 *
		 * @since 6.0.20
		 */
		public function setUseCookies( $useCookies = true ) {
			$this->NETCURL_USE_COOKIES = $useCookies;
		}

		/**
		 * @return bool
		 * @since 6.0.20
		 */
		public function getUseCookies() {
			return $this->NETCURL_USE_COOKIES;
		}

		/**
		 * @param int $curlResolveType
		 *
		 * @since 6.0.20
		 */
		public function setCurlResolve( $curlResolveType = NETCURL_RESOLVER::RESOLVER_DEFAULT ) {
			$this->CURL_RESOLVE_TYPE = $curlResolveType;
		}

		/**
		 * @return NETCURL_RESOLVER
		 * @since 6.0.20
		 */
		public function getCurlResove() {
			return $this->CURL_RESOLVE_TYPE;
		}

		/**
		 * Enable or disable the ability to let netcurl throw exceptions on places where it is not always necessary.
		 *
		 * This function has minor effects on newer netcurls since throwing exxceptions should be considered necessary in many situations to handle errors.
		 *
		 * @param bool $netCurlCanThrow
		 *
		 * @since 6.0.20
		 */
		public function setThrowable( $netCurlCanThrow = true ) {
			$this->NETCURL_CAN_THROW = $netCurlCanThrow;
		}

		/**
		 * Is netcurl allowed to throw exceptions on places where it is not always necessary?
		 * @return bool
		 * @since 6.0.20
		 */
		public function getThrowable() {
			return $this->NETCURL_CAN_THROW;
		}

		/**
		 * Termination Controller
		 *
		 * As of 6.0.20 cookies will be only stored if there is a predefined cookiepath or if system tempdir is allowed
		 * @since 5.0
		 */
		function netcurl_terminate() {
		}

		/**
		 * @param array $arrayData
		 *
		 * @return bool
		 * @since 6.0
		 */
		function isAssoc( array $arrayData ) {
			if ( array() === $arrayData ) {
				return false;
			}

			return array_keys( $arrayData ) !== range( 0, count( $arrayData ) - 1 );
		}

		/**
		 * Set multiple flags
		 *
		 * @param array $flags
		 *
		 * @throws \Exception
		 * @since 6.0.10
		 */
		private function setFlags( $flags = array() ) {
			if ( $this->isAssoc( $flags ) ) {
				foreach ( $flags as $flagKey => $flagData ) {
					$this->setFlag( $flagKey, $flagData );
				}
			} else {
				foreach ( $flags as $flagKey ) {
					$this->setFlag( $flagKey, true );
				}
			}
			if ( $this->isFlag( 'NOCHAIN' ) ) {
				$this->unsetFlag( 'CHAIN' );
			}
		}

		/**
		 * Return all flags
		 *
		 * @return array
		 *
		 * @since 6.0.10
		 */
		public function getFlags() {
			return $this->internalFlags;
		}

		/**
		 * @param string $setContentTypeString
		 *
		 * @since 6.0.17
		 */
		public function setContentType( $setContentTypeString = 'application/json; charset=utf-8' ) {
			$this->contentType = $setContentTypeString;
		}

		/**
		 * @since 6.0.17
		 */
		public function getContentType() {
			return $this->contentType;
		}

		/**
		 * @param int $driverId
		 * @param array $parameters
		 * @param null $ownClass
		 *
		 * @return int|NETCURL_DRIVERS_INTERFACE
		 * @throws \Exception
		 * @since 6.0.20
		 */
		public function setDriver( $driverId = NETCURL_NETWORK_DRIVERS::DRIVER_NOT_SET, $parameters = array(), $ownClass = null ) {
			return $this->DRIVER->setDriver( $driverId, $parameters, $ownClass );
		}

		/**
		 * Returns current chosen driver (if none is preset and curl exists, we're trying to use internals)
		 *
		 * @since 6.0.15
		 */
		public function getDriver( $byId = false ) {
			if ( ! $byId ) {
				$this->currentDriver = $this->DRIVER->getDriver();
			} else {
				if ( $this->isFlag( 'IS_SOAP' ) ) {
					return NETCURL_NETWORK_DRIVERS::DRIVER_SOAPCLIENT;
				}

				return $this->DRIVER->getDriverById();
			}

			return $this->currentDriver;
		}

		/**
		 * @return int|NETCURL_DRIVERS_INTERFACE
		 * @since 6.0.20
		 */
		public function getDriverById() {
			return $this->getDriver( true );
		}

		/**
		 * Get current configured http-driver
		 *
		 * @return mixed
		 * @throws \Exception
		 * @since 6.0.14
		 * @deprecated 6.0.20
		 */
		public function getDrivers() {
			return $this->getAvailableDrivers( true );
		}

		/**
		 * @return string
		 * @since 6.0.14
		 * @deprecated 6.0.20
		 */
		private function getDisabledFunctions() {
			return @ini_get( 'disable_functions' );
		}

		/**
		 * Set up driver by class name
		 *
		 * @param int $driverId
		 * @param string $className
		 * @param array $parameters
		 *
		 * @return bool
		 * @since 6.0.14
		 * @deprecated 6.0.20
		 */
		private function setDriverByClass( $driverId = NETCURL_NETWORK_DRIVERS::DRIVER_NOT_SET, $className = '', $parameters = null ) {
			if ( class_exists( $className ) ) {
				if ( is_null( $parameters ) ) {
					$this->Drivers[ $driverId ] = new $className();
				} else {
					$this->Drivers[ $driverId ] = new $className( $parameters );
				}

				return true;
			}

			return false;
		}

		/**
		 * Check if driver with id is available and prepared
		 *
		 * @param int $driverId
		 *
		 * @return bool
		 * @since 6.0.14
		 * @deprecated 6.0.20
		 */
		private function getIsDriver( $driverId = NETCURL_NETWORK_DRIVERS::DRIVER_NOT_SET ) {
			if ( isset( $this->Drivers[ $driverId ] ) && is_object( $this->Drivers[ $driverId ] ) ) {
				return true;
			}

			return false;
		}


		/**
		 * Set timeout for CURL, normally we'd like a quite short timeout here. Default: CURL default
		 *
		 * Affects connect and response timeout by below values:
		 *   CURLOPT_CONNECTTIMEOUT = ceil($timeout/2)    - How long a request is allowed to wait for conneciton, curl default = 300
		 *   CURLOPT_TIMEOUT = ceil($timeout)             - How long a request is allowed to take, curl default = never timeout (0)
		 *
		 * @param int $timeout
		 *
		 * @since 6.0.13
		 */
		public function setTimeout( $timeout = 6 ) {
			$this->NETCURL_CURL_TIMEOUT = $timeout;
		}

		/**
		 * Get current timeout setting
		 * @return array
		 * @since 6.0.13
		 */
		public function getTimeout() {
			$returnTimeouts = array(
				'connecttimeout' => ceil( $this->NETCURL_CURL_TIMEOUT / 2 ),
				'requesttimeout' => ceil( $this->NETCURL_CURL_TIMEOUT )
			);
			if ( empty( $this->NETCURL_CURL_TIMEOUT ) ) {
				$returnTimeouts = array(
					'connecttimeout' => 300,
					'requesttimeout' => 0
				);
			}

			return $returnTimeouts;
		}

		/**
		 * Initialize cookie handler
		 *
		 * @return bool
		 * @since 6.0
		 */
		private function initCookiePath() {
			// Method rewrite as of NetCurl 6.0.20
			if ( $this->isFlag( 'NETCURL_DISABLE_CURL_COOKIES' ) || ! $this->useLocalCookies ) {
				return false;
			}

			try {
				$ownCookiePath = $this->getFlag( 'NETCURL_COOKIE_LOCATION' );
				if ( ! empty( $ownCookiePath ) ) {
					return $this->setCookiePathUserDefined( $ownCookiePath );
				}

				return $this->setCookiePathBySystem();

			} catch ( \Exception $e ) {
				// Something happened, so we won't try this again
				return false;
			}
		}

		/**
		 * Sets, if defined by user, up a cookie directory storage
		 *
		 * @param $ownCookiePath
		 *
		 * @return bool
		 * @since 6.0.20
		 */
		private function setCookiePathUserDefined( $ownCookiePath ) {
			if ( is_dir( $ownCookiePath ) ) {
				$this->COOKIE_PATH = $ownCookiePath;

				return true;
			} else {
				@mkdir( $ownCookiePath );
				if ( is_dir( $ownCookiePath ) ) {
					$this->COOKIE_PATH = $ownCookiePath;

					return true;
				}

				return false;
			}

		}

		/**
		 * Sets up cookie path if allowed, to system default storage path
		 * @return bool
		 * @since 6.0.20
		 */
		private function setCookiePathBySystem() {
			$sysTempDir = sys_get_temp_dir();
			if ( empty( $this->COOKIE_PATH ) ) {
				if ( $this->isFlag( 'NETCURL_COOKIE_TEMP_LOCATION' ) ) {
					if ( ! empty( $sysTempDir ) ) {
						if ( is_dir( $sysTempDir ) ) {
							$this->COOKIE_PATH = $sysTempDir;
							@mkdir( $sysTempDir . "/netcurl/" );
							if ( is_dir( $sysTempDir . "/netcurl/" ) ) {
								$this->COOKIE_PATH = $sysTempDir . "/netcurl/";
							}

							return true;
						} else {
							return false;
						}
					}
				}
			}

			return false;
		}

		/**
		 * Set internal flag parameter.
		 *
		 * @param string $flagKey
		 * @param string $flagValue Nullable since 6.0.10 = If null, then it is considered a true boolean, set setFlag("key") will always be true as an activation key
		 *
		 * @return bool If successful
		 * @throws \Exception
		 * @since 6.0.9
		 */
		public function setFlag( $flagKey = '', $flagValue = null ) {
			if ( ! empty( $flagKey ) ) {
				if ( is_null( $flagValue ) ) {
					$flagValue = true;
				}
				$this->internalFlags[ $flagKey ] = $flagValue;

				return true;
			}
			throw new \Exception( "Flags can not be empty", $this->NETWORK->getExceptionCode( 'NETCURL_SETFLAG_KEY_EMPTY' ) );
		}

		/**
		 * @param string $flagKey
		 *
		 * @return bool
		 * @since 6.0.10
		 */
		public function unsetFlag( $flagKey = '' ) {
			if ( $this->hasFlag( $flagKey ) ) {
				unset( $this->internalFlags[ $flagKey ] );

				return true;
			}

			return false;
		}

		/**
		 * @param string $flagKey
		 *
		 * @return bool
		 * @since 6.0.13 Consider using unsetFlag
		 */
		public function removeFlag( $flagKey = '' ) {
			return $this->unsetFlag( $flagKey );
		}

		/**
		 * @param string $flagKey
		 *
		 * @return bool
		 * @since 6.0.13 Consider using unsetFlag
		 */
		public function deleteFlag( $flagKey = '' ) {
			return $this->unsetFlag( $flagKey );
		}

		/**
		 * @since 6.0.13
		 */
		public function clearAllFlags() {
			$this->internalFlags = array();
		}

		/**
		 * Get internal flag
		 *
		 * @param string $flagKey
		 *
		 * @return mixed|null
		 * @since 6.0.9
		 */
		public function getFlag( $flagKey = '' ) {
			if ( isset( $this->internalFlags[ $flagKey ] ) ) {
				return $this->internalFlags[ $flagKey ];
			}

			return null;
		}

		/**
		 * Check if flag is set and true
		 *
		 * @param string $flagKey
		 *
		 * @return bool
		 * @since 6.0.9
		 */
		public function isFlag( $flagKey = '' ) {
			if ( $this->hasFlag( $flagKey ) ) {
				return ( $this->getFlag( $flagKey ) === 1 || $this->getFlag( $flagKey ) === true ? true : false );
			}

			return false;
		}

		/**
		 * Check if there is an internal flag set with current key
		 *
		 * @param string $flagKey
		 *
		 * @return bool
		 * @since 6.0.9
		 */
		public function hasFlag( $flagKey = '' ) {
			if ( ! is_null( $this->getFlag( $flagKey ) ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Enable chained mode ($Module->doGet(URL)->getParsedResponse()"
		 *
		 * @param bool $enable
		 *
		 * @throws \Exception
		 * @since 6.0.14
		 */
		public function setChain( $enable = true ) {
			if ( $enable ) {
				$this->setFlag( 'CHAIN' );
			} else {
				$this->unsetFlag( 'CHAIN' );
			}
		}

		/**
		 * @return bool
		 * @since 6.0
		 */
		public function getIsChained() {
			return $this->isFlag( 'CHAIN' );
		}

		//// EXCEPTION HANDLING

		/**
		 * Throw on any code that matches the store throwableHttpCode (use with setThrowableHttpCodes())
		 *
		 * @param string $message
		 * @param string $code
		 *
		 * @throws \Exception
		 * @since 6.0.6
		 */
		private function throwCodeException( $message = '', $code = '' ) {
			if ( ! is_array( $this->throwableHttpCodes ) ) {
				$this->throwableHttpCodes = array();
			}
			foreach ( $this->throwableHttpCodes as $codeListArray => $codeArray ) {
				if ( isset( $codeArray[1] ) && $code >= intval( $codeArray[0] ) && $code <= intval( $codeArray[1] ) ) {
					throw new \Exception( NETCURL_CURL_CLIENTNAME . " HTTP Response Exception: " . $message, $code );
				}
			}
		}

		//// SESSION

		/**
		 * Returns an ongoing cUrl session - Normally you may get this from initSession (and normally you don't need this at all)
		 *
		 * @return null
		 * @since 6.0
		 */
		public function getCurlSession() {
			return $this->NETCURL_CURL_SESSION;
		}


		//// PUBLIC SETTERS & GETTERS

		/**
		 * Allow fallback tests in SOAP mode
		 *
		 * Defines whether, when there is a SOAP-call, we should try to make the SOAP initialization twice.
		 * This is a kind of fallback when users forget to add ?wsdl or &wsdl in urls that requires this to call for SOAP.
		 * It may happen when setting NETCURL_POST_DATATYPES to a SOAP-call but, the URL is not defined as one.
		 * Setting this to false, may suppress important errors, since this will suppress fatal errors at first try.
		 *
		 * @param bool $enabledMode
		 *
		 * @since 6.0.9
		 */
		public function setSoapTryOnce( $enabledMode = true ) {
			$this->SoapTryOnce = $enabledMode;
		}

		/**
		 * Get the state of soapTryOnce
		 *
		 * @return bool
		 * @since 6.0.9
		 */
		public function getSoapTryOnce() {
			return $this->SoapTryOnce;
		}


		/**
		 * Set the curl libraray to die, if no proxy has been successfully set up (Currently not active in module)
		 *
		 * @param bool $dieEnabled
		 *
		 * @since 6.0.9
		 */
		public function setDieOnNoProxy( $dieEnabled = true ) {
			$this->DIE_ON_LOST_PROXY = $dieEnabled;
		}

		/**
		 * Get the state of whether the library should bail out if no proxy has been successfully set
		 *
		 * @return bool
		 * @since 6.0.9
		 */
		public function getDieOnNoProxy() {
			return $this->DIE_ON_LOST_PROXY;
		}

		/**
		 * Set up a list of which HTTP error codes that should be throwable (default: >= 400, <= 599)
		 *
		 * @param int $throwableMin Minimum value to throw on (Used with >=)
		 * @param int $throwableMax Maxmimum last value to throw on (Used with <)
		 *
		 * @since 6.0.6
		 */
		public function setThrowableHttpCodes( $throwableMin = 400, $throwableMax = 599 ) {
			$throwableMin               = intval( $throwableMin ) > 0 ? $throwableMin : 400;
			$throwableMax               = intval( $throwableMax ) > 0 ? $throwableMax : 599;
			$this->throwableHttpCodes[] = array( $throwableMin, $throwableMax );
		}

		/**
		 * Return the list of throwable http error codes (if set)
		 *
		 * @return array
		 * @since 6.0.6
		 */
		public function getThrowableHttpCodes() {
			return $this->throwableHttpCodes;
		}

		/**
		 * When using soap/xml fields returned as CDATA will be returned as text nodes if this is disabled (default: diabled)
		 *
		 * @param bool $enabled
		 *
		 * @since 5.0.0
		 */
		public function setCdata( $enabled = true ) {
			$this->allowCdata = $enabled;
		}

		/**
		 * Get current state of the setCdata
		 *
		 * @return bool
		 * @since 5.0.0
		 */
		public function getCdata() {
			return $this->allowCdata;
		}

		/**
		 * Enable the use of local cookie storage
		 *
		 * Use this only if necessary and if you are planning to cookies locally while, for example, needs to set a logged in state more permanent during get/post/etc
		 *
		 * @param bool $enabled
		 *
		 * @since 5.0.0
		 */
		public function setLocalCookies( $enabled = false ) {
			$this->useLocalCookies = $enabled;
		}

		/**
		 * Returns the current setting whether to use local cookies or not
		 * @return bool
		 * @since 6.0.6
		 */
		public function getLocalCookies() {
			return $this->useLocalCookies;
		}

		/**
		 * @return string
		 * @since 6.0.20
		 */
		public function getCookiePath() {
			$this->initCookiePath();

			return $this->COOKIE_PATH;
		}

		/**
		 * Enforce a response type if you're not happy with the default returned array.
		 *
		 * @param int $NETCURL_RETURN_RESPONSE_TYPE
		 *
		 * @since 5.0.0
		 */
		public function setResponseType( $NETCURL_RETURN_RESPONSE_TYPE = NETCURL_RESPONSETYPE::RESPONSETYPE_ARRAY ) {
			$this->NETCURL_RETURN_RESPONSE_TYPE = $NETCURL_RETURN_RESPONSE_TYPE;
		}

		/**
		 * Return the value of how the responses are returned
		 *
		 * @return int
		 * @since 6.0.6
		 */
		public function getResponseType() {
			return $this->NETCURL_RETURN_RESPONSE_TYPE;
		}

		/**
		 * Enforce a specific type of post method
		 *
		 * To always send PostData, even if it is not set in the doXXX-method, you can use this setting to enforce - for example - JSON posts
		 * $myLib->setPostTypeDefault(NETCURL_POST_DATATYPES::DATATYPE_JSON)
		 *
		 * @param int $postType
		 *
		 * @since 6.0.6
		 */
		public function setPostTypeDefault( $postType = NETCURL_POST_DATATYPES::DATATYPE_NOT_SET ) {
			$this->FORCE_POST_TYPE = $postType;
		}

		/**
		 * Returns what to use as post method (NETCURL_POST_DATATYPES) on default. Returns null if none are set (= no overrides will be made)
		 * @return NETCURL_POST_DATATYPES
		 * @since 6.0.6
		 */
		public function getPostTypeDefault() {
			return $this->FORCE_POST_TYPE;
		}

		/**
		 * Enforces CURLOPT_FOLLOWLOCATION to act different if not matching with the internal rules
		 *
		 * @param bool $setEnabledState
		 *
		 * @since 5.0
		 */
		public function setEnforceFollowLocation( $setEnabledState = true ) {
			$this->FOLLOW_LOCATION_ENABLE = $setEnabledState;
		}

		/**
		 * Returns the boolean value of followLocationSet (see setEnforceFollowLocation)
		 * @return bool
		 * @since 6.0.6
		 */
		public function getEnforceFollowLocation() {
			return $this->FOLLOW_LOCATION_ENABLE;
		}

		/**
		 * Switch over to forced debugging
		 *
		 * To not break production environments by setting for example _DEBUG_TCURL_UNSET_LOCAL_PEM_LOCATION, switching over to test mode is required
		 * to use those variables.
		 *
		 * @since 5.0
		 * @deprecated 6.0.20 Not in use
		 */
		public function setTestEnabled() {
			$this->TARGET_ENVIRONMENT = NETCURL_ENVIRONMENT::ENVIRONMENT_TEST;
		}

		/**
		 * Returns current target environment
		 * @return int
		 * @since 6.0.6
		 * @deprecated 6.0.20 Not in use
		 */
		public function getTestEnabled() {
			return $this->TARGET_ENVIRONMENT;
		}

		/**
		 * Allow the initCookie-function to throw exceptions if the local cookie store can not be created properly
		 *
		 * Exceptions are invoked, normally when the function for initializing cookies can not create the storage directory. This is something you should consider disabled in a production environment.
		 *
		 * @param bool $enabled
		 *
		 * @deprecated 6.0.20 No longer in use
		 */
		public function setCookieExceptions( $enabled = false ) {
			$this->UseCookieExceptions = $enabled;
		}

		/**
		 * Returns the boolean value set (eventually) from setCookieException
		 * @return bool
		 * @since 6.0.6
		 * @deprecated 6.0.20 No longer in use
		 */
		public function getCookieExceptions() {
			return $this->UseCookieExceptions;
		}

		/**
		 * Set up whether we should allow html parsing or not
		 *
		 * @param bool $enabled
		 *
		 * @since 6.0
		 * @deprecated 6.0.20
		 */
		public function setParseHtml( $enabled = false ) {
			$this->allowParseHtml = $enabled;
		}

		/**
		 * Return the boolean of the setParseHtml
		 * @return bool
		 * @since 6.0.20
		 */
		public function getParseHtml() {
			return $this->allowParseHtml;
		}

		/**
		 * Set up a different user agent for this library
		 *
		 * To make proper identification of the library we are always appending TorbeLIB+cUrl to the chosen user agent string.
		 *
		 * @param string $CustomUserAgent
		 * @param array $inheritAgents Updates an array that might have lost some data
		 *
		 * @since 6.0
		 */
		public function setUserAgent( $CustomUserAgent = "", $inheritAgents = array() ) {

			if ( is_array( $inheritAgents ) && count( $inheritAgents ) ) {
				foreach ( $inheritAgents as $inheritedAgentName ) {
					if ( ! in_array( trim( $inheritedAgentName ), $this->CUSTOM_USER_AGENT ) ) {
						$this->CUSTOM_USER_AGENT[] = trim( $inheritedAgentName );
					}
				}
			}

			if ( ! empty( $CustomUserAgent ) ) {
				$this->mergeUserAgent( $CustomUserAgent );
			} else {
				$this->HTTP_USER_AGENT = $this->userAgents['Mozilla'] . ' +TorneLIB-NetCURL-' . NETCURL_RELEASE . " +" . NETCURL_CURL_CLIENTNAME . "+-" . NETCURL_CURL_RELEASE . ' (' . $this->netCurlUrl . ')';
			}
		}

		/**
		 * @param string $CustomUserAgent
		 *
		 * @since 6.0.20
		 */
		private function mergeUserAgent( $CustomUserAgent = "" ) {
			$trimmedUserAgent = trim( $CustomUserAgent );
			if ( ! in_array( $trimmedUserAgent, $this->CUSTOM_USER_AGENT ) ) {
				$this->CUSTOM_USER_AGENT[] = $trimmedUserAgent;
			}

			// NETCURL_CURL_CLIENTNAME . '-' . NETCURL_RELEASE . "/" . __CLASS__ . "-" . NETCURL_CURL_RELEASE
			$this->HTTP_USER_AGENT = implode( " ", $this->CUSTOM_USER_AGENT ) . " +TorneLIB-NETCURL-" . NETCURL_RELEASE . " +" . NETCURL_CURL_CLIENTNAME . "-" . NETCURL_CURL_RELEASE . " (" . $this->netCurlUrl . ")";
		}

		/**
		 * Returns the current set user agent
		 *
		 * @return string
		 * @since 6.0
		 */
		public function getUserAgent() {
			return $this->HTTP_USER_AGENT;
		}

		/**
		 * Get the value of customized user agent
		 *
		 * @return array
		 * @since 6.0.6
		 */
		public function getCustomUserAgent() {
			return $this->CUSTOM_USER_AGENT;
		}

		/**
		 * @param string $refererString
		 *
		 * @since 6.0.9
		 */
		public function setReferer( $refererString = "" ) {
			$this->NETCURL_HTTP_REFERER = $refererString;
		}

		/**
		 * @return null
		 * @since 6.0.9
		 */
		public function getReferer() {
			return $this->NETCURL_HTTP_REFERER;
		}

		/**
		 * If XML/Serializer exists in system, use that parser instead of SimpleXML
		 *
		 * @param bool $useIfExists
		 */
		public function setXmlSerializer( $useIfExists = true ) {
			$this->useXmlSerializer = $useIfExists;
		}

		/**
		 * Get the boolean value of whether to try to use XML/Serializer functions when fetching XML data
		 *
		 * @return bool
		 * @since 6.0.6
		 */
		public function getXmlSerializer() {
			return $this->useXmlSerializer;
		}

		/**
		 * Customize the curlopt configuration
		 *
		 * @param array|string $curlOptArrayOrKey If arrayed, there will be multiple options at once
		 * @param null $curlOptValue If not null, and the first parameter is not an array, this is taken as a single update value
		 *
		 * @throws \Exception
		 * @since 6.0
		 */
		public function setCurlOpt( $curlOptArrayOrKey = array(), $curlOptValue = null ) {
			if ( $this->DRIVER->hasCurl() ) {
				if ( is_null( $this->NETCURL_CURL_SESSION ) ) {
					$this->initializeNetCurl();
				}
				if ( is_array( $curlOptArrayOrKey ) ) {
					foreach ( $curlOptArrayOrKey as $key => $val ) {
						$this->curlopt[ $key ] = $val;
						curl_setopt( $this->NETCURL_CURL_SESSION, $key, $val );
					}
				}
				if ( ! is_array( $curlOptArrayOrKey ) && ! empty( $curlOptArrayOrKey ) && ! is_null( $curlOptValue ) ) {
					$this->curlopt[ $curlOptArrayOrKey ] = $curlOptValue;
					curl_setopt( $this->NETCURL_CURL_SESSION, $curlOptArrayOrKey, $curlOptValue );
				}
			}
		}

		/**
		 * curlops that can be overridden
		 *
		 * @param array|string $curlOptArrayOrKey
		 * @param null $curlOptValue
		 *
		 * @throws \Exception
		 * @since 6.0
		 */
		private function setCurlOptInternal( $curlOptArrayOrKey = array(), $curlOptValue = null ) {
			if ( $this->DRIVER->hasCurl() ) {
				if ( is_null( $this->NETCURL_CURL_SESSION ) ) {
					$this->initializeNetCurl();
				}
				if ( ! is_array( $curlOptArrayOrKey ) && ! empty( $curlOptArrayOrKey ) && ! is_null( $curlOptValue ) ) {
					if ( ! isset( $this->curlopt[ $curlOptArrayOrKey ] ) ) {
						$this->curlopt[ $curlOptArrayOrKey ] = $curlOptValue;
						curl_setopt( $this->NETCURL_CURL_SESSION, $curlOptArrayOrKey, $curlOptValue );
					}
				}
			}
		}

		/**
		 * @return array
		 * @since 6.0.9
		 */
		public function getCurlOpt() {
			return $this->curlopt;
		}

		/**
		 * Easy readable curlopts
		 *
		 * @return array
		 * @since 6.0.10
		 */
		public function getCurlOptByKeys() {
			$return = array();
			if ( is_array( $this->curlConstantsOpt ) ) {
				$currentCurlOpt = $this->getCurlOpt();
				foreach ( $currentCurlOpt as $curlOptKey => $curlOptValue ) {
					if ( isset( $this->curlConstantsOpt[ $curlOptKey ] ) ) {
						$return[ $this->curlConstantsOpt[ $curlOptKey ] ] = $curlOptValue;
					} else {
						$return[ $curlOptKey ] = $curlOptValue;
					}
				}
			}

			return $return;
		}

		/**
		 * Set up special SSL option array for communicators
		 *
		 * @param array $sslOptArray
		 *
		 * @since 6.0.9
		 */
		public function setSslOpt( $sslOptArray = array() ) {
			foreach ( $sslOptArray as $key => $val ) {
				$this->sslopt[ $key ] = $val;
			}
		}

		/**
		 * Get current setup for SSL options
		 *
		 * @return array
		 * @since 6.0.9
		 */
		public function getSslOpt() {
			return $this->sslopt;
		}


		//// SINGLE PUBLIC GETTERS

		/**
		 * Get the current version of the module
		 *
		 * @param bool $fullRelease
		 *
		 * @return string
		 * @since 5.0
		 */
		public function getVersion( $fullRelease = false ) {
			if ( ! $fullRelease ) {
				return NETCURL_CURL_RELEASE;
			} else {
				return NETCURL_CURL_RELEASE . "-" . NETCURL_CURL_MODIFY;
			}
		}

		/**
		 * Get this internal release version
		 *
		 * @return string
		 * @throws \Exception
		 * @deprecated 6.0.0 Use tag control
		 */
		public function getInternalRelease() {
			if ( $this->isFlag( 'NETCURL_ALLOW_VERSION_REQUESTS' ) ) {
				return NETCURL_CURL_RELEASE . "," . NETCURL_CURL_MODIFY;
			}
			throw new \Exception( NETCURL_CURL_CLIENTNAME . " internalReleaseException [" . __CLASS__ . "]: Version requests are not allowed in current state (permissions required)", 403 );
		}

		/**
		 * Get store exceptions
		 * @return array
		 * @since 6.0
		 */
		public function getStoredExceptionInformation() {
			return $this->sessionsExceptions;
		}

		/// SPECIAL FEATURES

		/**
		 * @return bool
		 * @since 6.0.20
		 */
		public function hasErrors() {
			if ( is_array( $this->NETCURL_ERROR_CONTAINER ) && ! count( $this->NETCURL_ERROR_CONTAINER ) ) {
				return false;
			}

			return true;
		}

		/**
		 * @return array
		 * @since 6.0
		 * @todo Check new error vars (those uppercased)
		 */
		public function getErrors() {
			return $this->NETCURL_ERROR_CONTAINER;
		}

		/**
		 * Check against Tornevall Networks API if there are updates for this module
		 *
		 * @param string $libName
		 *
		 * @return string
		 * @throws \Exception
		 * @deprecated 6.0.20
		 */
		public function hasUpdate( $libName = 'tornelib_curl' ) {
			if ( ! $this->isFlag( 'NETCURL_ALLOW_VERSION_REQUESTS' ) ) {
				$this->setFlag( 'NETCURL_ALLOW_VERSION_REQUESTS', true );
			}

			return $this->getHasUpdateState( $libName );
		}

		/**
		 * @param string $libName
		 *
		 * @return string
		 * @throws \Exception
		 * @deprecated 6.0.20
		 */
		private function getHasUpdateState( $libName = 'tornelib_curl' ) {
			// Currently only supporting this internal module (through $myRelease).
			$myRelease  = NETCURL_RELEASE;
			$libRequest = ( ! empty( $libName ) ? "lib/" . $libName : "" );
			$getInfo    = $this->doGet( "https://api.tornevall.net/2.0/libs/getLibs/" . $libRequest . "/me/" . $myRelease );
			if ( isset( $getInfo['parsed']->response->getLibsResponse->you ) ) {
				$currentPublicVersion = $getInfo['parsed']->response->getLibsResponse->you;
				if ( $currentPublicVersion->hasUpdate ) {
					if ( isset( $getInfo['parsed']->response->getLibsResponse->libs->tornelib_curl ) ) {
						return $getInfo['parsed']->response->getLibsResponse->libs->tornelib_curl;
					}
				}
			}

			return "";
		}

		/**
		 * Returns true if SSL verification was unset during the URL call
		 *
		 * @return bool
		 * @since 6.0.10
		 */
		public function getSslIsUnsafe() {
			return $this->unsafeSslCall;
		}


		/// CONFIGURATORS

		/**
		 * Generate a corrected stream context
		 *
		 * @return void
		 * @link https://phpdoc.tornevall.net/TorneLIBv5/source-class-TorneLIB.Tornevall_cURL.html sslStreamContextCorrection() is a part of TorneLIB 5.0, described here
		 * @since 6.0
		 */
		public function sslStreamContextCorrection() {
			$this->SSL->getSslStreamContext();
		}

		/**
		 * Automatically generates stream_context and appends it to whatever you need it for.
		 *
		 * Example:
		 *  $addonContextData = array('http' => array("user_agent" => "MyUserAgent"));
		 *  $this->soapOptions = sslGetDefaultStreamContext($this->soapOptions, $addonContextData);
		 *
		 * @param array $optionsArray
		 * @param array $addonContextData
		 *
		 * @return array
		 * @throws \Exception
		 * @link http://developer.tornevall.net/apigen/TorneLIB-5.0/class-TorneLIB.Tornevall_cURL.html sslGetOptionsStream() is a part of TorneLIB 5.0, described here
		 * @since 6.0
		 */
		public function sslGetOptionsStream( $optionsArray = array(), $addonContextData = array() ) {
			return $this->SSL->getSslStream( $optionsArray, $addonContextData );
		}

		/**
		 * Set and/or append certificate bundle locations to current configuration
		 *
		 * @param array $locationArrayOrString
		 *
		 * @return bool
		 * @throws \Exception
		 * @since 6.0
		 */
		public function setSslPemLocations( $locationArrayOrString = array() ) {
			$this->setTrustedSslBundles( true );

			return $this->SSL->setPemLocation( $locationArrayOrString );
		}

		/**
		 * Get current certificate bundle locations
		 *
		 * @return array
		 * @deprecated 6.0.20 Use MODULE_SSL
		 */
		public function getSslPemLocations() {
			return $this->SSL->getPemLocations();
		}

		/**
		 * Enable/disable SSL Certificate autodetection (and/or host/peer ssl verications)
		 *
		 * The $hostVerification-flag can also be called manually with setSslVerify()
		 *
		 * @param bool $enabledFlag
		 * @param bool $hostVerification
		 *
		 * @deprecated 6.0.20 Use setSslVerify
		 */
		public function setCertAuto( $enabledFlag = true, $hostVerification = true ) {
			$this->SSL->setStrictVerification( $enabledFlag );
		}

		/**
		 * Allow fallbacks of SSL verification if Peer/Host checking fails. This is actually kind of another way to disable strict checking of certificates. THe difference, however, is that NetCurl will first try to make a proper call, before fallback.
		 *
		 * @param bool $strictCertificateVerification
		 * @param bool $prohibitSelfSigned
		 *
		 * @return void
		 * @since 6.0
		 */
		public function setSslVerify( $strictCertificateVerification = true, $prohibitSelfSigned = true ) {
			$this->SSL->setStrictVerification( $strictCertificateVerification, $prohibitSelfSigned );
		}

		/**
		 * Return the boolean value set in setSslVerify
		 *
		 * @return bool
		 * @since 6.0.6
		 */
		public function getSslVerify() {
			return $this->SSL->getStrictVerification();
		}

		/**
		 * @param bool $sslFailoverEnabled
		 *
		 * @since 6.0.20
		 */
		public function setStrictFallback( $sslFailoverEnabled = false ) {
			$this->SSL->setStrictFallback( $sslFailoverEnabled );
		}

		/**
		 * @return bool
		 * @since 6.0.20
		 */
		public function getStrictFallback() {
			return $this->SSL->getStrictFallback();
		}

		/**
		 * While doing SSL calls, and SSL certificate verifications is failing, enable the ability to skip SSL verifications.
		 *
		 * Normally, we want a valid SSL certificate while doing https-requests, but sometimes the verifications must be disabled. One reason of this is
		 * in cases, when crt-files are missing and PHP can not under very specific circumstances verify the peer. To allow this behaviour, the client
		 * must use this function.
		 *
		 * @param bool $allowStrictFallback
		 *
		 * @since 5.0
		 * @deprecated 6.0.20 Use setStrictFallback
		 */
		public function setSslUnverified( $allowStrictFallback = false ) {
			$this->SSL->setStrictFallback( $allowStrictFallback );
		}

		/**
		 * Return the boolean value set from setSslUnverified
		 * @return bool
		 * @since 6.0.6
		 * @deprecated 6.0.20 Use getStrictFallback
		 */
		public function getSslUnverified() {
			return $this->SSL->getStrictFallback();
		}

		/**
		 * TestCerts - Test if your webclient has certificates available (make sure the $testssldeprecated are enabled if you want to test older PHP-versions - meaning older than 5.6.0)
		 *
		 * Note: This function also forces full ssl certificate checking.
		 *
		 * @return bool
		 * @throws \Exception
		 * @deprecated 6.0.20
		 */
		public function TestCerts() {
			$certificateBundleData = $this->SSL->getSslCertificateBundle();

			return ( ! empty( $certificateBundleData ) ? true : false );
		}

		/**
		 * Return the current certificate bundle file, chosen by autodetection
		 * @return string
		 * @deprecated 6.0.20
		 */
		public function getCertFile() {
			return $this->SSL->getSslCertificateBundle();
		}

		/**
		 * Returns true if the autodetected certificate bundle was one of the defaults (normally fetched from openssl_get_cert_locations()). Used for testings.
		 *
		 * @return bool
		 * @throws \Exception
		 * @deprecated 6.0.20
		 */
		public function hasCertDefault() {
			return $this->TestCerts();
		}

		/**
		 * @return bool
		 * @since 6.0.20
		 */
		public function hasSsl() {
			return MODULE_SSL::hasSsl();
		}

		//// IP SETUP

		/**
		 * Making sure the $IpAddr contains valid address list
		 * Pick up externally selected outgoing ip if any requested
		 *
		 * @throws \Exception
		 * @since 5.0
		 * @todo Split code (try to fix all if/elses)
		 */
		private function handleIpList() {
			$this->CURL_IP_ADDRESS = null;
			$UseIp                 = "";
			if ( is_array( $this->IpAddr ) ) {
				if ( count( $this->IpAddr ) == 1 ) {
					$UseIp = ( isset( $this->IpAddr[0] ) && ! empty( $this->IpAddr[0] ) ? $this->IpAddr[0] : null );
				} elseif ( count( $this->IpAddr ) > 1 ) {
					if ( ! $this->IpAddrRandom ) {
						// If we have multiple ip addresses in the list, but the randomizer is not active, always use the first address in the list.
						$UseIp = ( isset( $this->IpAddr[0] ) && ! empty( $this->IpAddr[0] ) ? $this->IpAddr[0] : null );
					} else {
						$IpAddrNum = rand( 0, count( $this->IpAddr ) - 1 );
						$UseIp     = $this->IpAddr[ $IpAddrNum ];
					}
				}
			} else if ( ! empty( $this->IpAddr ) ) {
				$UseIp = $this->IpAddr;
			}

			$ipType = $this->NETWORK->getArpaFromAddr( $UseIp, true );
			// Bind interface to specific ip only if any are found
			if ( $ipType == "0" ) {
				// If the ip type is 0 and it shows up there is something defined here, throw an exception.
				if ( ! empty( $UseIp ) ) {
					throw new \Exception( NETCURL_CURL_CLIENTNAME . " " . __FUNCTION__ . " exception: " . $UseIp . " is not a valid ip-address", $this->NETWORK->getExceptionCode( 'NETCURL_IPCONFIG_NOT_VALID' ) );
				}
			} else {
				$this->CURL_IP_ADDRESS = $UseIp;
				curl_setopt( $this->NETCURL_CURL_SESSION, CURLOPT_INTERFACE, $UseIp );
				if ( $ipType == 6 ) {
					curl_setopt( $this->NETCURL_CURL_SESSION, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V6 );
					$this->CURL_IP_ADDRESS_TYPE = 6;
				} else {
					curl_setopt( $this->NETCURL_CURL_SESSION, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
					$this->CURL_IP_ADDRESS_TYPE = 4;
				}
			}
		}

		/**
		 * Set up a proxy
		 *
		 * @param $ProxyAddr
		 * @param int $ProxyType
		 *
		 * @throws \Exception
		 * @since 6.0
		 */
		public function setProxy( $ProxyAddr, $ProxyType = CURLPROXY_HTTP ) {
			$this->CURL_PROXY_GATEWAY = $ProxyAddr;
			$this->CURL_PROXY_TYPE    = $ProxyType;
			// Run from proxy on request
			$this->setCurlOptInternal( CURLOPT_PROXY, $this->CURL_PROXY_GATEWAY );
			if ( isset( $this->CURL_PROXY_TYPE ) && ! empty( $this->CURL_PROXY_TYPE ) ) {
				$this->setCurlOptInternal( CURLOPT_PROXYTYPE, $this->CURL_PROXY_TYPE );
			}
		}

		/**
		 * Get proxy settings
		 *
		 * @return array
		 * @since 6.0.11
		 */
		public function getProxy() {
			return array(
				'curlProxy'     => $this->CURL_PROXY_GATEWAY,
				'curlProxyType' => $this->CURL_PROXY_TYPE
			);
		}

		/**
		 * Enable curl tunneling
		 *
		 * @param bool $curlTunnelEnable
		 *
		 * @throws \Exception
		 * @since 6.0.11
		 */
		public function setTunnel( $curlTunnelEnable = true ) {
			// Run in tunneling mode
			$this->CURL_TUNNEL = $curlTunnelEnable;
			$this->setCurlOptInternal( CURLOPT_HTTPPROXYTUNNEL, $curlTunnelEnable );
		}

		/**
		 * Return state of curltunneling
		 *
		 * @return bool
		 * @since 6.0
		 */
		public function getTunnel() {
			return $this->CURL_TUNNEL;
		}


		/**
		 * @param string $byWhat
		 *
		 * @return array
		 * @since 6.0.20
		 */
		private function extractParsedDom( $byWhat = 'Id' ) {
			$validElements = array( 'Id', 'ClosestTag', 'Nodes' );
			if ( in_array( $byWhat, $validElements ) && isset( $this->NETCURL_RESPONSE_CONTAINER_PARSED[ 'By' . $byWhat ] ) ) {
				return $this->NETCURL_RESPONSE_CONTAINER_PARSED[ 'By' . $byWhat ];
			}

			return array();
		}

		private function netcurl_split_get_base() {

		}

		/**
		 * @param string $rawInput
		 * @param bool $internalRaw
		 *
		 * @return $this|array|NETCURL_HTTP_OBJECT
		 * @throws \Exception
		 * @todo Can this be split up?
		 */
		public function netcurl_split_raw( $rawInput = null, $internalRaw = false ) {
			$rawDataTest = $this->getRaw();
			if ( $internalRaw && is_null( $rawInput ) && ! empty( $rawDataTest ) ) {
				$this->netcurl_split_raw( $rawDataTest );

				return $this;
			}

			// Standard response output
			$arrayedResponse = array(
				'header' => array(),
				'body'   => '',
				'code'   => 0,
				'parsed' => ''
			);

			// explodeRaw usages - header and body
			$explodeRaw        = explode( "\r\n\r\n", $rawInput . "\r\n", 2 );
			$header            = isset( $explodeRaw[0] ) ? $explodeRaw[0] : "";
			$body              = isset( $explodeRaw[1] ) ? $explodeRaw[1] : "";
			$rows              = explode( "\n", $header );
			$response          = explode( " ", isset( $rows[0] ) ? $rows[0] : null );
			$shortCodeResponse = explode( " ", isset( $rows[0] ) ? $rows[0] : null, 3 );
			$httpMessage       = isset( $shortCodeResponse[2] ) ? $shortCodeResponse[2] : null;
			$code              = isset( $response[1] ) ? $response[1] : null;

			// If the first row of the body contains a HTTP/-string, we'll try to reparse it
			if ( preg_match( "/^HTTP\//", $body ) ) {
				$this->netcurl_split_raw( $body );
				$header = $this->getHeader();
				$body   = $this->getBody();
				$rows   = explode( "\n", $header );
			}

			$headerInfo = $this->GetHeaderKeyArray( $rows );

			// If response code starts with 3xx, this is probably a redirect
			if ( preg_match( "/^3/", $code ) ) {
				$this->REDIRECT_URLS[] = $this->CURL_STORED_URL;
				$redirectArray[]       = array(
					'header' => $header,
					'body'   => $body,
					'code'   => $code
				);
				if ( $this->isFlag( 'FOLLOWLOCATION_INTERNAL' ) ) {
					//$transferByLocation = array( 300, 301, 302, 307, 308 );
					if ( isset( $headerInfo['Location'] ) ) {
						$newLocation = $headerInfo['Location'];
						if ( ! preg_match( "/^http/i", $newLocation ) ) {
							$this->CURL_STORED_URL .= $newLocation;
						} else {
							$this->CURL_STORED_URL = $newLocation;
						}
						/** @var MODULE_CURL $newRequest */
						$newRequest = $this->doRepeat();
						// Make sure getRaw exists (this might fail from PHP 5.3)
						if ( method_exists( $newRequest, 'getRaw' ) ) {
							$rawRequest = $newRequest->getRaw();

							return $this->netcurl_split_raw( $rawRequest );
						}
					}
				}
			}
			$arrayedResponse       = array(
				'header' => array( 'info' => $headerInfo, 'full' => $header ),
				'body'   => $body,
				'code'   => $code
			);
			$returnResponse['URL'] = $this->CURL_STORED_URL;
			$returnResponse['ip']  = isset( $this->CURL_IP_ADDRESS ) ? $this->CURL_IP_ADDRESS : null;  // Will only be filled if there is custom address set.

			$contentType           = isset( $headerInfo['Content-Type'] ) ? $headerInfo['Content-Type'] : null;
			$arrayedResponse['ip'] = $this->CURL_IP_ADDRESS;

			// Store data that can be stored before tryiing to handle the parsed parts
			$this->NETCURL_RESPONSE_RAW                   = $rawInput;
			$this->NETCURL_RESPONSE_CONTAINER             = $arrayedResponse;
			$this->NETCURL_RESPONSE_CONTAINER_CODE        = trim( $code );
			$this->NETCURL_RESPONSE_CONTAINER_HTTPMESSAGE = trim( $httpMessage );
			$this->NETCURL_RESPONSE_CONTAINER_BODY        = $body;
			$this->NETCURL_RESPONSE_CONTAINER_HEADER      = $header;
			$this->throwCodeException( trim( $httpMessage ), $code );

			if ( $this->isFlag( 'IS_SOAP' ) && ! $this->isFlag( 'ALLOW_PARSE_SOAP' ) ) {
				$arrayedResponse['parsed'] = null;

				return $arrayedResponse;
			}

			// php 5.3 compliant
			$NCP                                     = new NETCURL_PARSER( $arrayedResponse['body'], $contentType );
			$parsedContent                           = $NCP->getParsedResponse();
			$arrayedResponse['parsed']               = $parsedContent;
			$this->NETCURL_RESPONSE_CONTAINER_PARSED = $parsedContent;


			if ( $this->NETCURL_RETURN_RESPONSE_TYPE == NETCURL_RESPONSETYPE::RESPONSETYPE_OBJECT ) {
				return new NETCURL_HTTP_OBJECT( $arrayedResponse['header'], $arrayedResponse['body'], $arrayedResponse['code'], $arrayedResponse['parsed'], $this->CURL_STORED_URL, $this->CURL_IP_ADDRESS );
			}
			if ( $this->isFlag( 'CHAIN' ) && ! $this->isFlag( 'IS_SOAP' ) ) {
				return $this;
			}

			return $arrayedResponse;
		}

		/**
		 * @param string $netCurlResponse
		 *
		 * @return string|void
		 * @throws \Exception
		 */
		private function netcurl_parse( $netCurlResponse = '' ) {
			if ( $this->isFlag( 'NOCHAIN' ) ) {
				$this->unsetFlag( 'CHAIN' );
			}

			if ( ! is_string( $netCurlResponse ) ) {
				// This method exists in external drivers interface. Do not mistakenly consider it the internal getRaw()
				if ( method_exists( $netCurlResponse, 'getRawResponse' ) ) {
					$htmlResponseData = $netCurlResponse->getRawResponse();
				} else {
					return $netCurlResponse;
				}
			} else {
				$htmlResponseData = $netCurlResponse;
			}

			$parsedResponse = $this->netcurl_split_raw( $htmlResponseData );

			return $parsedResponse;
		}

		/**
		 * @return mixed
		 * @since 6.0.20
		 */
		public function getRaw() {
			return $this->NETCURL_RESPONSE_RAW;
		}

		/**
		 * Get head and body from a request parsed
		 *
		 * @param string $content
		 *
		 * @return array
		 * @throws \Exception
		 * @since 6.0
		 */
		public function getHeader( $content = "" ) {
			if ( ! empty( $content ) ) {
				$this->netcurl_split_raw( $content );
			}

			return $this->NETCURL_RESPONSE_CONTAINER_HEADER;
		}

		/**
		 * @return array
		 * @since 6.0.20
		 */
		public function getDomByNodes() {
			return $this->extractParsedDom( 'Nodes' );
		}

		/**
		 * @return array
		 * @since 6.0.20
		 */
		public function getDomById() {
			return $this->extractParsedDom( 'Id' );
		}

		/**
		 * @return array
		 * @since 6.0.20
		 */
		public function getDomByClosestTag() {
			return $this->extractParsedDom( 'ClosestTag' );
		}

		/**
		 * Extract a parsed response from a webrequest
		 *
		 * @param null $inputResponse
		 *
		 * @return null
		 * @throws \Exception
		 * @since 6.0.20
		 */
		public function getParsed( $inputResponse = null ) {
			$returnThis = null;

			$this->getParsedExceptionCheck( $inputResponse );

			// When curl is disabled or missing, this might be returned chained
			if ( is_object( $inputResponse ) ) {
				$returnThis = $this->getParsedByObjectMethod( $inputResponse );
				if ( ! is_null( $returnThis ) ) {
					return $returnThis;
				}
			}
			if ( is_null( $inputResponse ) && ! empty( $this->NETCURL_RESPONSE_CONTAINER_PARSED ) ) {
				return $this->NETCURL_RESPONSE_CONTAINER_PARSED;
			} else if ( is_array( $inputResponse ) ) {
				return $this->getParsedByDeprecated( $inputResponse );
			}

			$returnThis = $this->getParsedUntouched( $inputResponse );

			return $returnThis;
		}

		/**
		 * @param $inputResponse
		 *
		 * @return bool
		 * @throws \Exception
		 * @since 6.0.20
		 */
		private function getParsedExceptionCheck( $inputResponse ) {
			// If the input response is an array and contains the deprecated editon of an error code
			if ( is_array( $inputResponse ) ) {
				if ( isset( $inputResponse['code'] ) && $inputResponse['code'] >= 400 ) {
					throw new \Exception( NETCURL_CURL_CLIENTNAME . " parseResponse exception - Unexpected response code from server: " . $inputResponse['code'], $inputResponse['code'] );
				}
			}

			return false;
		}

		/**
		 * @param $inputResponse
		 *
		 * @return null
		 * @since 6.0.20
		 */
		private function getParsedByObjectMethod( $inputResponse ) {
			if ( method_exists( $inputResponse, "getParsedResponse" ) ) {
				return $inputResponse->getParsedResponse();
			} else if ( isset( $inputResponse->NETCURL_RESPONSE_CONTAINER_PARSED ) ) {
				return $inputResponse->NETCURL_RESPONSE_CONTAINER_PARSED;
			}

			return null;
		}

		/**
		 * @param $inputResponse
		 *
		 * @return mixed
		 * @since 6.0.20
		 */
		private function getParsedByDeprecated( $inputResponse ) {
			// Return a deprecated answer
			if ( isset( $inputResponse['parsed'] ) ) {
				return $inputResponse['parsed'];
			}
		}

		/**
		 * @param $inputResponse
		 *
		 * @return null
		 * @since 6.0.20
		 */
		private function getParsedUntouched( $inputResponse ) {
			if ( is_array( $inputResponse ) ) {
				// This might already be parsed, if the array reaches this point
				return $inputResponse;
			} else if ( is_object( $inputResponse ) ) {
				// This is an object. Either it is ourselves or it is an already parsed object
				return $inputResponse;
			}

			return null;
		}

		/**
		 * @param null $ResponseContent
		 *
		 * @return int
		 * @since 6.0.20
		 */
		public function getCode( $ResponseContent = null ) {
			if ( method_exists( $ResponseContent, "getCode" ) ) {
				return $ResponseContent->getCode();
			}

			if ( is_null( $ResponseContent ) && ! empty( $this->NETCURL_RESPONSE_CONTAINER_CODE ) ) {
				return (int) $this->NETCURL_RESPONSE_CONTAINER_CODE;
			} else if ( isset( $ResponseContent['code'] ) ) {
				return (int) $ResponseContent['code'];
			}

			return 0;
		}

		/**
		 * @param null $ResponseContent
		 *
		 * @return int
		 * @since 6.0.20
		 */
		public function getMessage( $ResponseContent = null ) {
			if ( method_exists( $ResponseContent, "getMessage" ) ) {
				return $ResponseContent->getMessage();
			}

			if ( is_null( $ResponseContent ) && ! empty( $this->NETCURL_RESPONSE_CONTAINER_HTTPMESSAGE ) ) {
				return (string) $this->NETCURL_RESPONSE_CONTAINER_HTTPMESSAGE;
			}

			return null;
		}


		/**
		 * @param null $ResponseContent
		 *
		 * @return null
		 * @since 6.0.20
		 */
		public function getBody( $ResponseContent = null ) {
			if ( method_exists( $ResponseContent, "getResponseBody" ) ) {
				return $ResponseContent->getResponseBody();
			}

			if ( is_null( $ResponseContent ) && ! empty( $this->NETCURL_RESPONSE_CONTAINER_BODY ) ) {
				return $this->NETCURL_RESPONSE_CONTAINER_BODY;
			} else if ( isset( $ResponseContent['body'] ) ) {
				return $ResponseContent['body'];
			}

			return null;
		}

		/**
		 * @return mixed
		 * @since 6.0.20
		 */
		public function getRequestHeaders() {
			return $this->NETCURL_REQUEST_CONTAINER;
		}

		/**
		 * @return mixed
		 * @since 6.0.20
		 */
		public function getRequestBody() {
			return $this->NETCURL_REQUEST_BODY;
		}

		/**
		 * @param null $ResponseContent
		 *
		 * @return null|string
		 * @since 6.0.20
		 */
		public function getUrl( $ResponseContent = null ) {
			if ( method_exists( $ResponseContent, "getResponseUrl" ) ) {
				return $ResponseContent->getResponseUrl();
			}

			if ( is_null( $ResponseContent ) && ! empty( $this->CURL_STORED_URL ) ) {
				return $this->CURL_STORED_URL;
			} else if ( isset( $ResponseContent['URL'] ) ) {
				return $ResponseContent['URL'];
			}

			return '';
		}


		/**
		 * Extract a specific key from a parsed webrequest
		 *
		 * @param $keyName
		 * @param null $responseContent
		 *
		 * @return mixed|null
		 * @throws \Exception
		 * @since 6.0.20
		 */
		public function getValue( $keyName = null, $responseContent = null ) {
			$testInternalParsed = $this->getParsed();
			if ( is_null( $responseContent ) && ! empty( $testInternalParsed ) ) {
				$responseContent = $testInternalParsed;
			}

			if ( is_string( $keyName ) ) {
				$ParsedValue = $this->getParsed( $responseContent );
				if ( is_array( $ParsedValue ) && isset( $ParsedValue[ $keyName ] ) ) {
					return $ParsedValue[ $keyName ];
				}
				if ( is_object( $ParsedValue ) && isset( $ParsedValue->$keyName ) ) {
					return $ParsedValue->{$keyName};
				}
			} else {
				if ( is_null( $responseContent ) && ! empty( $this->NETCURL_RESPONSE_CONTAINER ) ) {
					$responseContent = $this->NETCURL_RESPONSE_CONTAINER;
				}
				$Parsed       = $this->getParsed( $responseContent );
				$hasRecursion = false;
				if ( is_array( $keyName ) ) {
					$TheKeys  = array_reverse( $keyName );
					$Eternity = 0;
					while ( count( $TheKeys ) || $Eternity ++ <= 20 ) {
						$hasRecursion = false;
						$CurrentKey   = array_pop( $TheKeys );
						if ( is_array( $Parsed ) ) {
							if ( isset( $Parsed[ $CurrentKey ] ) ) {
								$hasRecursion = true;
							}
						} else if ( is_object( $Parsed ) ) {
							if ( isset( $Parsed->{$CurrentKey} ) ) {
								$hasRecursion = true;
							}
						} else {
							// If there are still keys to scan, all tests above has failed
							if ( count( $TheKeys ) ) {
								$hasRecursion = false;
							}
							break;
						}
						if ( $hasRecursion ) {
							$Parsed = $this->getValue( $CurrentKey, array( 'parsed' => $Parsed ) );
							// Break if this was the last one
							if ( ! count( $TheKeys ) ) {
								break;
							}
						}
					}
					if ( $hasRecursion ) {
						return $Parsed;
					} else {
						throw new \Exception( NETCURL_CURL_CLIENTNAME . " getParsedValue exception: Requested key was not found in parsed response", $this->NETWORK->getExceptionCode( 'NETCURL_GETPARSEDVALUE_KEY_NOT_FOUND' ) );
					}
				}
			}

			return null;
		}

		/**
		 * @return array
		 * @since 6.0
		 */
		public function getRedirectedUrls() {
			return $this->REDIRECT_URLS;
		}

		/**
		 * Create an array of a header, with keys and values
		 *
		 * @param array $HeaderRows
		 *
		 * @return array
		 * @since 6.0
		 */
		private function GetHeaderKeyArray( $HeaderRows = array() ) {
			$headerInfo = array();
			if ( is_array( $HeaderRows ) ) {
				foreach ( $HeaderRows as $headRow ) {
					$colon = array_map( "trim", explode( ":", $headRow, 2 ) );
					if ( isset( $colon[1] ) ) {
						$headerInfo[ $colon[0] ] = $colon[1];
					} else {
						$rowSpc = explode( " ", $headRow );
						if ( isset( $rowSpc[0] ) ) {
							$headerInfo[ $rowSpc[0] ] = $headRow;
						} else {
							$headerInfo[ $headRow ] = $headRow;
						}
					}
				}
			}

			return $headerInfo;
		}

		/**
		 * Check if SOAP exists in system
		 *
		 * @param bool $extendedSearch Extend search for SOAP (unsafe method, looking for constants defined as SOAP_*)
		 *
		 * @return bool
		 * @since 6.0
		 */
		public function hasSoap( $extendedSearch = false ) {
			return $this->DRIVER->hasSoap( $extendedSearch );
		}

		/**
		 * Return number of tries, arrayed, that different parts of netcurl has been trying to make a call
		 *
		 * @return array
		 * @since 6.0.8
		 */
		public function getRetries() {
			return $this->CURL_RETRY_TYPES;
		}

		/**
		 * Defines if this library should be able to store the curl_getinfo() for each curl_exec that generates an exception
		 *
		 * @param bool $Activate
		 *
		 * @since 6.0.6
		 */
		public function setStoreSessionExceptions( $Activate = false ) {
			$this->canStoreSessionException = $Activate;
		}

		/**
		 * Returns the boolean value of whether exceptions can be stored in memory during calls
		 *
		 * @return bool
		 * @since 6.0.6
		 */
		public function getStoreSessionExceptions() {
			return $this->canStoreSessionException;
		}

		/**
		 * @return array|null|string|NETCURL_CURLOBJECT|void
		 * @throws \Exception
		 * @since 6.0.20
		 */
		function doRepeat() {
			if ( $this->NETCURL_POST_METHOD == NETCURL_POST_METHODS::METHOD_GET ) {
				return $this->doGet( $this->CURL_STORED_URL, $this->NETCURL_POST_DATA_TYPE );
			} else if ( $this->NETCURL_POST_METHOD == NETCURL_POST_METHODS::METHOD_POST ) {
				return $this->doPost( $this->CURL_STORED_URL, $this->POST_DATA_REAL, $this->NETCURL_POST_DATA_TYPE );
			} else if ( $this->NETCURL_POST_METHOD == NETCURL_POST_METHODS::METHOD_PUT ) {
				return $this->doPost( $this->CURL_STORED_URL, $this->POST_DATA_REAL, $this->NETCURL_POST_DATA_TYPE );
			} else if ( $this->NETCURL_POST_METHOD == NETCURL_POST_METHODS::METHOD_DELETE ) {
				return $this->doPost( $this->CURL_STORED_URL, $this->POST_DATA_REAL, $this->NETCURL_POST_DATA_TYPE );
			}
		}

		/**
		 * Call cUrl with a POST
		 *
		 * @param string $url
		 * @param array $postData
		 * @param int $postAs
		 *
		 * @return null|void
		 * @throws \Exception
		 * @since 5.0
		 */
		public function doPost( $url = '', $postData = array(), $postAs = NETCURL_POST_DATATYPES::DATATYPE_NOT_SET ) {
			$response = null;
			if ( ! empty( $url ) ) {
				$content  = $this->executeUrlCall( $url, $postData, NETCURL_POST_METHODS::METHOD_POST, $postAs );
				$response = $this->netcurl_parse( $content );
			}

			return $response;
		}

		/**
		 * @param string $url
		 * @param array $postData
		 * @param int $postAs
		 *
		 * @return null|string|void
		 * @throws \Exception
		 * @since 5.0
		 */
		public function doPut( $url = '', $postData = array(), $postAs = NETCURL_POST_DATATYPES::DATATYPE_NOT_SET ) {
			$response = null;
			if ( ! empty( $url ) ) {
				$content  = $this->executeUrlCall( $url, $postData, NETCURL_POST_METHODS::METHOD_PUT, $postAs );
				$response = $this->netcurl_parse( $content );
			}

			return $response;
		}

		/**
		 * @param string $url
		 * @param array $postData
		 * @param int $postAs
		 *
		 * @return null|string|void
		 * @throws \Exception
		 * @since 5.0
		 */
		public function doDelete( $url = '', $postData = array(), $postAs = NETCURL_POST_DATATYPES::DATATYPE_NOT_SET ) {
			$response = null;
			if ( ! empty( $url ) ) {
				$content  = $this->executeUrlCall( $url, $postData, NETCURL_POST_METHODS::METHOD_DELETE, $postAs );
				$response = $this->netcurl_parse( $content );
			}

			return $response;
		}

		/**
		 * Call cUrl with a GET
		 *
		 * @param string $url
		 * @param int $postAs
		 *
		 * @return array|null|string|NETCURL_CURLOBJECT
		 * @throws \Exception
		 * @since 5.0
		 */
		public function doGet( $url = '', $postAs = NETCURL_POST_DATATYPES::DATATYPE_NOT_SET ) {
			$response = null;
			if ( ! empty( $url ) ) {
				$content  = $this->executeUrlCall( $url, array(), NETCURL_POST_METHODS::METHOD_GET, $postAs );
				$response = $this->netcurl_parse( $content );
			}

			return $response;
		}

		/**
		 * Enable authentication with cURL.
		 *
		 * @param null $Username
		 * @param null $Password
		 * @param int $AuthType Falls back on CURLAUTH_ANY if none are given. NETCURL_AUTH_TYPES are minimalistic since it follows the standards of CURLAUTH_
		 *
		 * @throws \Exception
		 * @since 6.0
		 */
		public function setAuthentication( $Username = null, $Password = null, $AuthType = NETCURL_AUTH_TYPES::AUTHTYPE_BASIC ) {
			$this->AuthData['Username'] = $Username;
			$this->AuthData['Password'] = $Password;
			$this->AuthData['Type']     = $AuthType;
			if ( $AuthType !== NETCURL_AUTH_TYPES::AUTHTYPE_NONE ) {
				// Default behaviour on authentications via SOAP should be to catch authfail warnings
				$this->setFlag( "SOAPWARNINGS", true );
			}
		}

		/**
		 * Fix problematic header data by converting them to proper outputs.
		 *
		 * @param array $headerList
		 *
		 * @since 6.0
		 */
		private function fixHttpHeaders( $headerList = array() ) {
			if ( is_array( $headerList ) && count( $headerList ) ) {
				foreach ( $headerList as $headerKey => $headerValue ) {
					$testHead = explode( ":", $headerValue, 2 );
					if ( isset( $testHead[1] ) ) {
						$this->NETCURL_HTTP_HEADERS[] = $headerValue;
					} else {
						if ( ! is_numeric( $headerKey ) ) {
							$this->NETCURL_HTTP_HEADERS[] = $headerKey . ": " . $headerValue;
						}
					}
				}
			}
		}

		/**
		 * Add extra curl headers
		 *
		 * @param string $key
		 * @param string $value
		 *
		 * @since 6.0
		 */
		public function setCurlHeader( $key = '', $value = '' ) {
			if ( ! empty( $key ) ) {
				$this->NETCURL_HEADERS_USER_DEFINED[ $key ] = $value;
			}
		}

		/**
		 * Return user defined headers
		 *
		 * @return array
		 * @since 6.0.6
		 */
		public function getCurlHeader() {
			return $this->NETCURL_HEADERS_USER_DEFINED;
		}

		/**
		 * Make sure that postdata is correctly rendered to interfaces before sending it
		 *
		 * @return string
		 * @throws \Exception
		 * @since 6.0.15
		 */
		private function executePostData() {
			$this->POST_DATA_REAL = $this->NETCURL_POST_DATA;
			$postDataContainer    = $this->NETCURL_POST_DATA;
			$POST_AS_DATATYPE     = $this->NETCURL_POST_DATA_TYPE;

			// Enforce postAs: If you'd like to force everything to use json you can for example use: $myLib->setPostTypeDefault(NETCURL_POST_DATATYPES::DATATYPE_JSON)
			if ( ! is_null( $this->FORCE_POST_TYPE ) ) {
				$POST_AS_DATATYPE = $this->FORCE_POST_TYPE;
			}
			$parsedPostData = $this->NETCURL_POST_DATA;
			if ( is_array( $this->NETCURL_POST_DATA ) || is_object( $this->NETCURL_POST_DATA ) ) {
				$postDataContainer = http_build_query( $this->NETCURL_POST_DATA );
			}
			$this->POSTDATACONTAINER = $postDataContainer;

			if ( $POST_AS_DATATYPE == NETCURL_POST_DATATYPES::DATATYPE_JSON ) {
				$parsedPostData = $this->transformPostDataJson();
			} else if ( ( $POST_AS_DATATYPE == NETCURL_POST_DATATYPES::DATATYPE_XML || $POST_AS_DATATYPE == NETCURL_POST_DATATYPES::DATATYPE_SOAP_XML ) ) {
				$parsedPostData = $this->transformPostDataXml();
			}

			$this->POST_DATA_HANDLED = $parsedPostData;

			return $parsedPostData;
		}

		/**
		 * @return array|null|string
		 * @since 6.0.20
		 */
		private function transformPostDataJson() {
			// Using $jsonRealData to validate the string
			$jsonRealData = null;
			if ( ! is_string( $this->NETCURL_POST_DATA ) ) {
				$jsonRealData = json_encode( $this->NETCURL_POST_DATA );
			} else {
				$testJsonData = json_decode( $this->NETCURL_POST_DATA );
				if ( is_object( $testJsonData ) || is_array( $testJsonData ) ) {
					$jsonRealData = $this->NETCURL_POST_DATA;
				}
			}

			return $jsonRealData;
		}

		/**
		 * @return mixed|null|string
		 * @since 6.0.20
		 */
		private function transformPostDataXml() {
			$this->setContentType( 'text/xml' ); // ; charset=utf-8
			$this->setCurlHeader( 'Content-Type', $this->getContentType() );
			$parsedPostData = null;
			if ( ! empty( $this->NETCURL_POST_PREPARED_XML ) ) {
				$parsedPostData = $this->NETCURL_POST_PREPARED_XML;
			} else {
				try {
					if ( is_array( $this->NETCURL_POST_DATA ) && count( $this->NETCURL_POST_DATA ) ) {
						if ( ! is_null( $this->IO ) ) {
							$parsedPostData = $this->IO->renderXml( $this->NETCURL_POST_DATA );
						} else {
							throw new \Exception( NETCURL_CURL_CLIENTNAME . " can not render XML data properly, since the IO library is not initialized", $this->NETWORK->getExceptionCode( 'NETCURL_PARSE_XML_FAILURE' ) );
						}
					}
				} catch ( \Exception $e ) {
					// Silently fail and return nothing if prepared data is failing
				}
			}

			return $parsedPostData;
		}

		/**
		 * Make sure that we are allowed to do things
		 *
		 * @param bool $checkSafeMode If true, we will also check if safe_mode is active
		 * @param bool $mockSafeMode If true, NetCurl will pretend safe_mode is true (for testing)
		 *
		 * @return bool If true, PHP is in secure mode and won't allow things like follow-redirects and setting up different paths for certificates, etc
		 * @since 6.0.20
		 */
		public function getIsSecure( $checkSafeMode = true, $mockSafeMode = false ) {
			$currentBaseDir = trim( ini_get( 'open_basedir' ) );
			if ( $checkSafeMode ) {
				if ( $currentBaseDir == '' && ! $this->getSafeMode( $mockSafeMode ) ) {
					return false;
				}

				return true;
			} else {
				if ( $currentBaseDir == '' ) {
					return false;
				}

				return true;
			}

			return false;
		}

		/**
		 * Get safe_mode status (mockable)
		 *
		 * @param bool $mockedSafeMode When active, this always returns true
		 *
		 * @return bool
		 * @since 6.0.20
		 */
		private function getSafeMode( $mockedSafeMode = false ) {
			if ( $mockedSafeMode ) {
				return true;
			}

			// There is no safe mode in PHP 5.4.0 and above
			if ( version_compare( PHP_VERSION, '5.4.0', '>=' ) ) {
				return false;
			}

			return ( filter_var( ini_get( 'safe_mode' ), FILTER_VALIDATE_BOOLEAN ) );
		}

		/**
		 * Trust the pems defined from SSL_MODULE
		 *
		 * @param bool $iTrustBundlesSetBySsl If this is false, NetCurl will trust internals (PHP + Curl) rather than pre-set pem bundles
		 *
		 * @since 6.0.20
		 */
		public function setTrustedSslBundles( $iTrustBundlesSetBySsl = false ) {
			$this->TRUST_SSL_BUNDLES = $iTrustBundlesSetBySsl;
			if ( $iTrustBundlesSetBySsl ) {
				$this->setSslUserAgent();
			}
		}

		/**
		 * The current status of trusted pems
		 *
		 * @return bool
		 * @since 6.0.20
		 */
		public function getTrustedSslBundles() {
			return $this->TRUST_SSL_BUNDLES;
		}

		/**
		 * @since 6.0.20
		 */
		private function setSslUserAgent() {
			$this->setUserAgent( NETCURL_SSL_CLIENTNAME . "-" . NETCURL_SSL_RELEASE );
		}

		private function internal_curl_configure_ssl() {
			$certificateBundle = $this->SSL->getSslCertificateBundle();
			// Change default behaviour for SSL certificates only if PHP is not in a secure mode (checking open_basedir only).
			if ( ! $this->getIsSecure( false ) ) {
				$this->setSslUserAgent();
				// If strict certificate verification is disabled, we will push some curlopts into unsafe mode.
				if ( ! $this->SSL->getStrictVerification() ) {
					$this->setCurlOpt( CURLOPT_SSL_VERIFYHOST, 0 );
					$this->setCurlOpt( CURLOPT_SSL_VERIFYPEER, 0 );
					$ignoreBundle        = true;
					$this->unsafeSslCall = true;
				} else {
					// From libcurl 7.28.1 CURLOPT_SSL_VERIFYHOST is deprecated. However, using the value 1 can be used
					// as of PHP 5.4.11, where the deprecation notices was added. The deprecation has started before libcurl
					// 7.28.1 (this was discovered on a server that was running PHP 5.5 and libcurl-7.22). In full debug
					// even libcurl-7.22 was generating this message, so from PHP 5.4.11 we are now enforcing the value 2
					// for CURLOPT_SSL_VERIFYHOST instead. The reason of why we are using the value 1 before this version
					// is actually a lazy thing, as we don't want to break anything that might be unsupported before this version.

					// Those settings are probably default in CURL.
					if ( version_compare( PHP_VERSION, '5.4.11', ">=" ) ) {
						$this->setCurlOptInternal( CURLOPT_SSL_VERIFYHOST, 2 );
					} else {
						$this->setCurlOptInternal( CURLOPT_SSL_VERIFYHOST, 1 );
					}
					$this->setCurlOptInternal( CURLOPT_SSL_VERIFYPEER, 1 );

					try {
						if ( $this->getTrustedSslBundles() ) {
							if ( $this->getFlag( 'OVERRIDE_CERTIFICATE_BUNDLE' ) ) {
								$certificateBundle = $this->getFlag( 'OVERRIDE_CERTIFICATE_BUNDLE' );
							}
							$this->setCurlOptInternal( CURLOPT_CAINFO, $certificateBundle );
							$this->setCurlOptInternal( CURLOPT_CAPATH, dirname( $certificateBundle ) );
						}
					} catch ( \Exception $e ) {
						// Silently ignore errors
					}

				}
			}
		}

		/**
		 * Initializes internal curl driver
		 *
		 * @param bool $reinitialize
		 *
		 * @throws \Exception
		 * @since 6.0.20
		 */
		private function initCurl( $reinitialize = false ) {
			if ( is_null( $this->NETCURL_CURL_SESSION ) || $reinitialize ) {
				$this->NETCURL_CURL_SESSION = curl_init( $this->CURL_STORED_URL );
			}
			$this->NETCURL_HTTP_HEADERS = array();
			// CURL CONDITIONAL SETUP
			$this->internal_curl_configure_cookies();
			$this->internal_curl_configure_ssl();
			$this->internal_curl_configure_follow();
			$this->internal_curl_configure_postdata();
			$this->internal_curl_configure_timeouts();
			$this->internal_curl_configure_resolver();
			$this->internal_curl_confiure_proxy_tunnels();
			$this->internal_curl_configure_clientdata();
			$this->internal_curl_configure_userauth();

			// CURL UNCONDITIONAL SETUP
			$this->setCurlOptInternal( CURLOPT_VERBOSE, false );

			// This curlopt makes it possible to make a call to a specific ip address and still use the HTTP_HOST (Must override)
			$this->setCurlOpt( CURLOPT_URL, $this->CURL_STORED_URL );

			// Things that should be overwritten if set by someone else
			$this->setCurlOpt( CURLOPT_HEADER, true );
			$this->setCurlOpt( CURLOPT_RETURNTRANSFER, true );
			$this->setCurlOpt( CURLOPT_AUTOREFERER, true );
			$this->setCurlOpt( CURLINFO_HEADER_OUT, true );
		}

		/**
		 * Set up rules of follow for curl
		 *
		 * @throws \Exception
		 * @since 6.0.20
		 */
		private function internal_curl_configure_follow() {
			// Find out if CURLOPT_FOLLOWLOCATION can be set by user/developer or not.
			//
			// Make sure the safety control occurs even when the enforcing parameter is false.
			// This should prevent problems when $this->>followLocationSet is set to anything else than false
			// and security settings are higher for PHP. From v6.0.2, the in this logic has been simplified
			// to only set any flags if the security levels of PHP allows it, and only if the follow flag is enabled.
			//
			// Refers to http://php.net/manual/en/ini.sect.safe-mode.php
			if ( ! $this->getIsSecure( true ) ) {
				// To disable the default behaviour of this function, use setEnforceFollowLocation([bool]).
				if ( $this->FOLLOW_LOCATION_ENABLE ) {
					// Since setCurlOptInternal is not an overrider, using the overrider here, will have no effect on the curlopt setting
					// as it has already been set from our top defaults. This has to be pushed in, by force.
					$this->setCurlOpt( CURLOPT_FOLLOWLOCATION, $this->FOLLOW_LOCATION_ENABLE );
				}
			}
		}

		/**
		 * Prepare postdata for curl
		 *
		 * @throws \Exception
		 * @since 6.0.20
		 */
		private function internal_curl_configure_postdata() {
			// Lazysession: Sets post data if any found and sends it even if the curl-method is GET or any other than POST
			// The postdata section must overwrite others, since the variables are set more than once depending on how the data
			// changes or gets converted. The internal curlOpt setter don't overwrite variables if they are alread set.
			if ( ! empty( $this->POSTDATACONTAINER ) ) {
				$this->setCurlOpt( CURLOPT_POSTFIELDS, $this->POSTDATACONTAINER );
			}
			if ( $this->NETCURL_POST_METHOD == NETCURL_POST_METHODS::METHOD_POST || $this->NETCURL_POST_METHOD == NETCURL_POST_METHODS::METHOD_PUT || $this->NETCURL_POST_METHOD == NETCURL_POST_METHODS::METHOD_DELETE ) {
				if ( $this->NETCURL_POST_METHOD == NETCURL_POST_METHODS::METHOD_PUT ) {
					$this->setCurlOpt( CURLOPT_CUSTOMREQUEST, 'PUT' );
				} else if ( $this->NETCURL_POST_METHOD == NETCURL_POST_METHODS::METHOD_DELETE ) {
					$this->setCurlOpt( CURLOPT_CUSTOMREQUEST, 'DELETE' );
				} else {
					$this->setCurlOpt( CURLOPT_POST, true );
				}

				if ( $this->NETCURL_POST_DATA_TYPE == NETCURL_POST_DATATYPES::DATATYPE_JSON ) {
					// Using $jsonRealData to validate the string
					$this->NETCURL_HEADERS_SYSTEM_DEFINED['Content-Type']   = 'application/json; charset=utf-8';
					$this->NETCURL_HEADERS_SYSTEM_DEFINED['Content-Length'] = strlen( $this->POST_DATA_HANDLED );
					$this->setCurlOpt( CURLOPT_POSTFIELDS, $this->POST_DATA_HANDLED );  // overwrite old
				} else if ( ( $this->NETCURL_POST_DATA_TYPE == NETCURL_POST_DATATYPES::DATATYPE_XML || $this->NETCURL_POST_DATA_TYPE == NETCURL_POST_DATATYPES::DATATYPE_SOAP_XML ) ) {
					$this->NETCURL_HEADERS_SYSTEM_DEFINED['Content-Type']   = 'text/xml'; // ; charset=utf-8
					$this->NETCURL_HEADERS_SYSTEM_DEFINED['Content-Length'] = strlen( $this->NETCURL_POST_DATA );
					$this->setCurlOpt( CURLOPT_CUSTOMREQUEST, 'POST' );
					$this->setCurlOpt( CURLOPT_POSTFIELDS, $this->POST_DATA_HANDLED );
				}
			}
		}

		/**
		 * Configure curltimeouts
		 *
		 * @throws \Exception
		 * @since 6.0.20
		 */
		private function internal_curl_configure_timeouts() {
			// Self set timeouts, making sure the timeout set in the public is an integer over 0. Otherwise this falls back to the curldefauls.
			if ( isset( $this->NETCURL_CURL_TIMEOUT ) && $this->NETCURL_CURL_TIMEOUT > 0 ) {
				$this->setCurlOptInternal( CURLOPT_CONNECTTIMEOUT, ceil( $this->NETCURL_CURL_TIMEOUT / 2 ) );
				$this->setCurlOptInternal( CURLOPT_TIMEOUT, ceil( $this->NETCURL_CURL_TIMEOUT ) );
			}
		}

		/**
		 * Configure how to handle DNS resolver
		 *
		 * @throws \Exception
		 * @since 6.0.20
		 */
		private function internal_curl_configure_resolver() {
			if ( isset( $this->CurlResolve ) && $this->CurlResolve !== NETCURL_RESOLVER::RESOLVER_DEFAULT ) {
				if ( $this->CurlResolve == NETCURL_RESOLVER::RESOLVER_IPV4 ) {
					$this->setCurlOptInternal( CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
				}
				if ( $this->CurlResolve == NETCURL_RESOLVER::RESOLVER_IPV6 ) {
					$this->setCurlOptInternal( CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V6 );
				}
			}
		}

		/**
		 * Prepare proxy and tunneling mode
		 *
		 * @since 6.0.20
		 */
		private function internal_curl_confiure_proxy_tunnels() {
			// Tunnel and proxy setup. If this is set, make sure the default IP setup gets cleared out.
			if ( ! empty( $this->CURL_PROXY_GATEWAY ) && ! empty( $this->CURL_PROXY_TYPE ) ) {
				unset( $this->CURL_IP_ADDRESS );
			}
			if ( $this->getTunnel() ) {
				unset( $this->CURL_IP_ADDRESS );
			}
		}

		/**
		 * Prepare user agent and referers
		 *
		 * @throws \Exception
		 * @since 6.0.20
		 */
		private function internal_curl_configure_clientdata() {
			if ( isset( $this->NETCURL_HTTP_REFERER ) && ! empty( $this->NETCURL_HTTP_REFERER ) ) {
				$this->setCurlOptInternal( CURLOPT_REFERER, $this->NETCURL_HTTP_REFERER );
			}
			if ( isset( $this->HTTP_USER_AGENT ) && ! empty( $this->HTTP_USER_AGENT ) ) {
				$this->setCurlOpt( CURLOPT_USERAGENT, $this->HTTP_USER_AGENT ); // overwrite old
			}
			if ( isset( $this->HTTP_CHARACTER_ENCODING ) && ! empty( $this->HTTP_CHARACTER_ENCODING ) ) {
				$this->setCurlOpt( CURLOPT_ENCODING, $this->HTTP_CHARACTER_ENCODING ); // overwrite old
			}
		}

		/**
		 * Prepare cookies if requested
		 *
		 * @throws \Exception
		 * @since 6.0.20
		 */
		private function internal_curl_configure_cookies() {
			if ( file_exists( $this->COOKIE_PATH ) && $this->getUseCookies() && ! empty( $this->CURL_STORED_URL ) ) {
				$domainArray = $this->NETWORK->getUrlDomain( $this->CURL_STORED_URL );
				$domainHash  = '';
				if ( isset( $domainArray[0] ) ) {
					$domainHash = sha1( $domainArray[0] );
				}

				@file_put_contents( $this->COOKIE_PATH . "/tmpcookie", "test" );
				if ( ! file_exists( $this->COOKIE_PATH . "/tmpcookie" ) ) {
					$this->SaveCookies = true;
					$this->CookieFile  = $domainHash;
					$this->setCurlOptInternal( CURLOPT_COOKIEFILE, $this->COOKIE_PATH . "/" . $this->CookieFile );
					$this->setCurlOptInternal( CURLOPT_COOKIEJAR, $this->COOKIE_PATH . "/" . $this->CookieFile );
					$this->setCurlOptInternal( CURLOPT_COOKIE, 1 );
				} else {
					if ( file_exists( $this->COOKIE_PATH . "/tmpcookie" ) ) {
						unlink( $this->COOKIE_PATH . "/tmpcookie" );
					}
					$this->SaveCookies = false;
				}
			} else {
				$this->SaveCookies = false;
			}
		}

		/**
		 * Prepare http-headers
		 *
		 * @throws \Exception
		 * @since 6.0.20
		 */
		private function internal_curl_configure_headers() {
			if ( $this->isCurl() ) {
				if ( isset( $this->NETCURL_HTTP_HEADERS ) && is_array( $this->NETCURL_HTTP_HEADERS ) && count( $this->NETCURL_HTTP_HEADERS ) ) {
					$this->setCurlOpt( CURLOPT_HTTPHEADER, $this->NETCURL_HTTP_HEADERS ); // overwrite old
				}
			}
		}

		/**
		 * Set up authentication data
		 *
		 * @throws \Exception
		 * @since 6.0.20
		 */
		private function internal_curl_configure_userauth() {
			if ( ! empty( $this->AuthData['Username'] ) ) {
				$useAuth = $this->AuthData['Type'];
				if ( $this->AuthData['Type'] != NETCURL_AUTH_TYPES::AUTHTYPE_NONE ) {
					$useAuth = CURLAUTH_ANY;
					if ( $this->AuthData['Type'] == NETCURL_AUTH_TYPES::AUTHTYPE_BASIC ) {
						$useAuth = CURLAUTH_BASIC;
					}
				}
				$this->setCurlOptInternal( CURLOPT_HTTPAUTH, $useAuth );
				$this->setCurlOptInternal( CURLOPT_USERPWD, $this->AuthData['Username'] . ':' . $this->AuthData['Password'] );
			}
		}

		/**
		 * Add debug data
		 *
		 * @param $returnContent
		 *
		 * @since 6.0.20
		 */
		private function internal_curl_execute_add_debug( $returnContent ) {
			if ( curl_errno( $this->NETCURL_CURL_SESSION ) ) {
				$this->DEBUG_DATA['data']['url'][] = array(
					'url'       => $this->CURL_STORED_URL,
					'opt'       => $this->getCurlOptByKeys(),
					'success'   => false,
					'exception' => curl_error( $this->NETCURL_CURL_SESSION )
				);

				if ( $this->canStoreSessionException ) {
					$this->sessionsExceptions[] = array(
						'Content'     => $returnContent,
						'SessionInfo' => curl_getinfo( $this->NETCURL_CURL_SESSION )
					);
				}
			}
		}

		/**
		 * Handle curl-errors
		 *
		 * @return bool
		 * @throws \Exception
		 * @since 6.0.20
		 */
		private function internal_curl_errors() {
			$this->NETCURL_ERRORHANDLER_HAS_ERRORS = false;
			$this->NETCURL_ERRORHANDLER_RERUN      = false;

			$errorCode    = curl_errno( $this->NETCURL_CURL_SESSION ) > 0 ? curl_errno( $this->NETCURL_CURL_SESSION ) : null;
			$errorMessage = curl_error( $this->NETCURL_CURL_SESSION ) != '' ? curl_error( $this->NETCURL_CURL_SESSION ) : null;

			if ( ! is_null( $errorCode ) || ! is_null( $errorMessage ) ) {
				$this->NETCURL_ERRORHANDLER_HAS_ERRORS = true;
				$this->internal_curl_error_ssl( $errorCode, $errorMessage );

				// Special case: Resolver failures
				if ( $this->CURL_RESOLVER_FORCED && $this->CURL_RETRY_TYPES['resolve'] >= 2 ) {
					throw new \Exception( NETCURL_CURL_CLIENTNAME . " exception in " . __FUNCTION__ . ": The maximum tries of curl_exec() for " . $this->CURL_STORED_URL . " has been reached without any successful response. Normally, this happens after " . $this->CURL_RETRY_TYPES['resolve'] . " CurlResolveRetries and might be connected with a bad URL or similar that can not resolve properly.\nCurl error message follows: " . $errorMessage, $errorCode );
				}
				$this->internal_curl_error_resolver( $errorCode, $errorMessage );
			}

			if ( $this->NETCURL_ERRORHANDLER_HAS_ERRORS && ! $this->NETCURL_ERRORHANDLER_RERUN ) {
				throw new \Exception( NETCURL_CURL_CLIENTNAME . " exception from PHP/CURL at " . __FUNCTION__ . ": " . curl_error( $this->NETCURL_CURL_SESSION ), curl_errno( $this->NETCURL_CURL_SESSION ) );
			}

			return $this->NETCURL_ERRORHANDLER_HAS_ERRORS;
		}

		/**
		 * @param $errorCode
		 * @param $errorMessage
		 *
		 * @since 6.0.20
		 */
		private function internal_curl_error_resolver( $errorCode, $errorMessage ) {
			if ( $errorCode == CURLE_COULDNT_RESOLVE_HOST || $errorCode === 45 ) {
				$this->NETCURL_ERROR_CONTAINER[] = array( 'code' => $errorCode, 'message' => $errorMessage );
				unset( $this->CURL_IP_ADDRESS );
				$this->CURL_RESOLVER_FORCED = true;
				if ( $this->CURL_IP_ADDRESS_TYPE == 6 ) {
					$this->setCurlResolve( NETCURL_RESOLVER::RESOLVER_IPV4 );
					$this->CURL_IP_ADDRESS_TYPE = 4;
				} else if ( $this->CURL_IP_ADDRESS_TYPE == 4 ) {
					$this->setCurlResolve( NETCURL_RESOLVER::RESOLVER_IPV6 );
					$this->CURL_IP_ADDRESS_TYPE = 6;
				} else {
					$this->CURL_IP_ADDRESS_TYPE = 4;
					$this->setCurlResolve( NETCURL_RESOLVER::RESOLVER_IPV4 );
				}
				if ( $this->CURL_RETRY_TYPES['resolve'] <= 2 ) {
					$this->NETCURL_ERRORHANDLER_RERUN = true;
				}
				$this->CURL_RETRY_TYPES['resolve'] ++;
			}
		}

		/**
		 * Redirects to sslVerificationAdjustment
		 *
		 * @param $errorCode
		 * @param $errorMessage
		 *
		 * @throws \Exception
		 * @since 6.0.20
		 */
		private function internal_curl_error_ssl( $errorCode, $errorMessage ) {
			$this->sslVerificationAdjustment( $errorCode, $errorMessage );
		}

		/**
		 * @param $errorCode
		 * @param $errorMessage
		 *
		 * @throws \Exception
		 */
		private function sslVerificationAdjustment( $errorCode, $errorMessage ) {
			// Special case: SSL failures (CURLE_SSL_CACERT = 60)
			if ( $this->SSL->getStrictFallback() ) {
				if ( $errorCode == CURLE_SSL_CACERT ) {
					if ( $this->CURL_RETRY_TYPES['sslunverified'] >= 2 ) {
						throw new \Exception( NETCURL_CURL_CLIENTNAME . " exception in " . __FUNCTION__ . ": The maximum tries of curl_exec() for " . $this->CURL_STORED_URL . ", during a try to make a SSL connection to work, has been reached without any successful response. This normally happens when allowSslUnverified is activated in the library and " . $this->CURL_RETRY_TYPES['resolve'] . " tries to fix the problem has been made, but failed.\nCurl error message follows: " . $errorMessage, $errorCode );
					} else {
						$this->NETCURL_ERROR_CONTAINER[] = array( 'code' => $errorCode, 'message' => $errorMessage );
						$this->setSslVerify( false, false );
						$this->unsafeSslCall = true;
						$this->CURL_RETRY_TYPES['sslunverified'] ++;
						$this->NETCURL_ERRORHANDLER_RERUN = true;
					}
				}
				if ( false === strpos( $errorMessage, '14090086' ) && false === strpos( $errorMessage, '1407E086' ) ) {
					$this->NETCURL_ERROR_CONTAINER[] = array( 'code' => $errorCode, 'message' => $errorMessage );
					$this->setSslVerify( false, false );
					$this->unsafeSslCall = true;
					$this->CURL_RETRY_TYPES['sslunverified'] ++;
					$this->NETCURL_ERRORHANDLER_RERUN = true;
				}

			}
		}

		/**
		 * Check if NetCurl is allowed to rerun curl-call
		 *
		 * @return bool
		 * @since 6.0.20
		 */
		private function internal_curl_can_rerun() {
			return $this->NETCURL_ERRORHANDLER_RERUN;
		}

		/**
		 * @return mixed
		 * @throws \Exception
		 * @since 6.0.20
		 */
		private function internal_curl_execute() {
			$returnContent = curl_exec( $this->NETCURL_CURL_SESSION );
			$this->internal_curl_execute_add_debug( $returnContent );

			if ( $this->internal_curl_errors() ) {
				if ( $this->internal_curl_can_rerun() ) {
					return $this->executeUrlCall( $this->CURL_STORED_URL, $this->POST_DATA_HANDLED, $this->NETCURL_POST_METHOD );
				}
			}

			return $returnContent;
		}

		/**
		 * Run SOAP calls if any
		 *
		 * @return null|MODULE_SOAP
		 * @throws \Exception
		 * @since 6.0.20
		 */
		private function internal_soap_checker() {

			$isSoapRequest = false;

			if ( $this->NETCURL_POST_DATA_TYPE == NETCURL_POST_DATATYPES::DATATYPE_SOAP ) {
				$isSoapRequest = true;
			}
			if ( preg_match( "/\?wsdl$|\&wsdl$/i", $this->CURL_STORED_URL ) && $this->NETCURL_POST_DATA_TYPE == NETCURL_POST_DATATYPES::DATATYPE_NOT_SET ) {
				$isSoapRequest = true;
			}

			// SOAP HANDLER: Override with SoapClient just before the real curl_exec is the most proper way to handle inheritages
			if ( $isSoapRequest ) {
				if ( ! $this->hasSoap() ) {
					throw new \Exception( NETCURL_CURL_CLIENTNAME . " " . __FUNCTION__ . " exception: SoapClient is not available in this system", $this->NETWORK->getExceptionCode( 'NETCURL_SOAPCLIENT_CLASS_MISSING' ) );
				}
				if ( ! $this->isFlag( 'NOSOAPWARNINGS' ) ) {
					$this->setFlag( "SOAPWARNINGS", true );
				} else {
					$this->unsetFlag( 'SOAPWARNINGS' );
				}

				return $this->executeHttpSoap( $this->CURL_STORED_URL, $this->NETCURL_POST_DATA, $this->NETCURL_POST_DATA_TYPE );
			}
			$this->unsetFlag( 'IS_SOAP' );
			if ( $this->isFlag( 'WAS_SOAP_CHAIN' ) ) {
				// Enable chaining if flags was reset by SOAP
				$this->setChain( true );
				$this->unsetFlag( 'WAS_SOAP_CHAIN' );
			}

			return null;

		}

		/**
		 * cURL data handler, sets up cURL in what it believes is the correct set for you.
		 *
		 * @param string $url
		 * @param array $postData
		 * @param int $postMethod
		 * @param int $postDataType
		 *
		 * @return mixed
		 * @throws \Exception
		 * @since 6.0
		 */
		private function executeUrlCall( $url = '', $postData = array(), $postMethod = NETCURL_POST_METHODS::METHOD_GET, $postDataType = NETCURL_POST_DATATYPES::DATATYPE_NOT_SET ) {
			$currentDriver = $this->getDriver();
			$returnContent = null;

			if ( ! empty( $url ) ) {
				$this->CURL_STORED_URL = $url;
			}
			$this->NETCURL_POST_DATA      = $postData;
			$this->NETCURL_POST_METHOD    = $postMethod;
			$this->NETCURL_POST_DATA_TYPE = $postDataType;
			$this->DEBUG_DATA['calls'] ++;

			// Initialize drivers
			$this->executePostData();
			$this->initializeNetCurl();
			$this->handleIpList();

			// Headers used by any
			$this->fixHttpHeaders( $this->NETCURL_HEADERS_USER_DEFINED );
			$this->fixHttpHeaders( $this->NETCURL_HEADERS_SYSTEM_DEFINED );
			// This must run after http headers fix
			$this->internal_curl_configure_headers();
			$soapResponseTest = $this->internal_soap_checker();

			if ( ! is_null( $soapResponseTest ) ) {
				return $soapResponseTest;
			}

			if ( $currentDriver === NETCURL_NETWORK_DRIVERS::DRIVER_CURL ) {
				try {
					$returnContent                     = $this->internal_curl_execute();
					$this->DEBUG_DATA['data']['url'][] = array(
						'url'       => $this->CURL_STORED_URL,
						'opt'       => $this->getCurlOptByKeys(),
						'success'   => true,
						'exception' => null
					);
				} catch ( \Exception $e ) {
					throw new \Exception( NETCURL_CURL_CLIENTNAME . " exception from PHP/CURL at " . __FUNCTION__ . ": " . $e->getMessage(), $e->getCode(), $e );
				}
			} else {
				if ( is_object( $currentDriver ) && method_exists( $currentDriver, 'executeNetcurlRequest' ) ) {
					$returnContent = $currentDriver->executeNetcurlRequest( $this->CURL_STORED_URL, $this->POST_DATA_HANDLED, $this->NETCURL_POST_METHOD, $this->NETCURL_POST_DATA_TYPE );
				}
			}

			return $returnContent;
		}

		/**
		 * SOAPClient detection method (moved from primary curl executor to make it possible to detect soapcalls from other Addons)
		 *
		 * @param string $url
		 * @param array $postData
		 * @param int $CurlMethod
		 *
		 * @return MODULE_SOAP
		 * @throws \Exception
		 * @since 6.0.14
		 */
		private function executeHttpSoap( $url = '', $postData = array(), $CurlMethod = NETCURL_POST_METHODS::METHOD_GET ) {
			$Soap = new MODULE_SOAP( $this->CURL_STORED_URL, $this );

			// Proper inherits
			foreach ( $this->getFlags() as $flagKey => $flagValue ) {
				$this->setFlag( $flagKey, $flagValue );
				$Soap->setFlag( $flagKey, $flagValue );
			}

			$this->setFlag( 'WAS_SOAP_CHAIN', $this->getIsChained() );
			$Soap->setFlag( 'WAS_SOAP_CHAIN', $this->getIsChained() );
			$this->setChain( false );
			$Soap->setFlag( 'IS_SOAP' );
			$this->setFlag( 'IS_SOAP' );

			/** @since 6.0.20 */
			$Soap->setChain( false );
			if ( $this->hasFlag( 'SOAPCHAIN' ) ) {
				$Soap->setFlag( 'SOAPCHAIN', $this->getFlag( 'SOAPCHAIN' ) );
			}
			$Soap->setCustomUserAgent( $this->CUSTOM_USER_AGENT );
			$Soap->setThrowableState( $this->NETCURL_CAN_THROW );
			$Soap->setSoapAuthentication( $this->AuthData );
			$Soap->setSoapTryOnce( $this->SoapTryOnce );
			try {
				$getSoapResponse                       = $Soap->getSoap();
				$this->DEBUG_DATA['soapdata']['url'][] = array(
					'url'       => $this->CURL_STORED_URL,
					'opt'       => $this->getCurlOptByKeys(),
					'success'   => true,
					'exception' => null,
					'previous'  => null
				);
			} catch ( \Exception $getSoapResponseException ) {

				$this->sslVerificationAdjustment( $getSoapResponseException->getCode(), $getSoapResponseException->getMessage() );

				$this->DEBUG_DATA['soapdata']['url'][] = array(
					'url'       => $this->CURL_STORED_URL,
					'opt'       => $this->getCurlOptByKeys(),
					'success'   => false,
					'exception' => $getSoapResponseException,
					'previous'  => $getSoapResponseException->getPrevious()
				);

				if ( $this->NETCURL_ERRORHANDLER_RERUN ) {
					return $this->executeHttpSoap( $url, $postData, $CurlMethod );
				}

				switch ( $getSoapResponseException->getCode() ) {
					default:
						throw new \Exception( NETCURL_CURL_CLIENTNAME . " exception from SoapClient: [" . $getSoapResponseException->getCode() . "] " . $getSoapResponseException->getMessage(), $getSoapResponseException->getCode() );
				}

			}

			return $getSoapResponse;

		}



		/// DEPRECATIONS TO MOVE


		/**
		 * Using WordPress curl driver to make webcalls
		 *
		 * SOAPClient is currently not supported through this interface, so this library will fall back to SimpleSoap before reaching this point if wsdl links are used
		 *
		 * @param string $url
		 * @param array $postData
		 * @param int $CurlMethod
		 * @param int $postAs
		 *
		 * @return $this
		 * @throws \Exception
		 * @since 6.0.14
		 * @deprecated 6.0.20
		 */
		private function executeWpHttp( $url = '', $postData = array(), $CurlMethod = NETCURL_POST_METHODS::METHOD_GET, $postAs = NETCURL_POST_DATATYPES::DATATYPE_NOT_SET ) {
			$parsedResponse = null;
			if ( isset( $this->Drivers[ NETCURL_NETWORK_DRIVERS::DRIVER_WORDPRESS ] ) ) {
				/** @noinspection PhpUndefinedClassInspection */
				/** @var $worker \WP_Http */
				$worker = $this->Drivers[ NETCURL_NETWORK_DRIVERS::DRIVER_WORDPRESS ];
			} else {
				throw new \Exception( NETCURL_CURL_CLIENTNAME . " " . __FUNCTION__ . " exception: Could not find any available transport for WordPress Driver", $this->NETWORK->getExceptionCode( 'NETCURL_WP_TRANSPORT_ERROR' ) );
			}

			if ( ! is_null( $worker ) ) {
				/** @noinspection PhpUndefinedMethodInspection */
				$transportInfo = $worker->_get_first_available_transport( array() );
			}
			if ( empty( $transportInfo ) ) {
				throw new \Exception( NETCURL_CURL_CLIENTNAME . " " . __FUNCTION__ . " exception: Could not find any available transport for WordPress Driver", $this->NETWORK->getExceptionCode( 'NETCURL_WP_TRANSPORT_ERROR' ) );
			}

			$postThis = array( 'body' => $this->POST_DATA_REAL );
			if ( $postAs == NETCURL_POST_DATATYPES::DATATYPE_JSON ) {
				$postThis['headers'] = array( "content-type" => "application-json" );
				$postThis['body']    = $this->POST_DATA_HANDLED;
			}

			$wpResponse = null;
			if ( $CurlMethod == NETCURL_POST_METHODS::METHOD_GET ) {
				/** @noinspection PhpUndefinedMethodInspection */
				$wpResponse = $worker->get( $url, $postThis );
			} else if ( $CurlMethod == NETCURL_POST_METHODS::METHOD_POST ) {
				/** @noinspection PhpUndefinedMethodInspection */
				$wpResponse = $worker->post( $url, $postThis );
			} else if ( $CurlMethod == NETCURL_POST_METHODS::METHOD_REQUEST ) {
				/** @noinspection PhpUndefinedMethodInspection */
				$wpResponse = $worker->request( $url, $postThis );
			}
			if ( $CurlMethod == NETCURL_POST_METHODS::METHOD_HEAD ) {
				/** @noinspection PhpUndefinedMethodInspection */
				$wpResponse = $worker->head( $url, $postThis );
			}

			/** @noinspection PhpUndefinedClassInspection */
			/** @var $httpResponse \WP_HTTP_Requests_Response */
			$httpResponse = $wpResponse['http_response'];
			/** @noinspection PhpUndefinedClassInspection */
			/** @var $httpReponseObject \Requests_Response */
			/** @noinspection PhpUndefinedMethodInspection */
			$httpResponseObject              = $httpResponse->get_response_object();
			$rawResponse                     = $httpResponseObject->raw;
			$this->TemporaryExternalResponse = array( 'worker' => $worker, 'request' => $wpResponse );
			$this->ParseResponse( $rawResponse );

			return $this;
		}


		//////// LONG TIME DEPRECATIONS

		/**
		 * @param null $responseInData
		 *
		 * @return int
		 * @since 6.0
		 * @deprecated 6.0.20 Use getCode
		 */
		public function getResponseCode( $responseInData = null ) {
			return $this->getCode( $responseInData );
		}

		/**
		 * @param null $responseInData
		 *
		 * @return null
		 * @since 6.0
		 * @deprecated 6.0.20 Use getBody
		 */
		public function getResponseBody( $responseInData = null ) {
			return $this->getBody( $responseInData );
		}

		/**
		 * @param null $responseInData
		 *
		 * @return string
		 * @since 6.0.16
		 * @deprecated 6.0.20 Use getUrl
		 */
		public function getResponseUrl( $responseInData = null ) {
			return $this->getUrl( $responseInData );
		}

		/**
		 * @param null $inputResponse
		 *
		 * @return null
		 * @throws \Exception
		 * @since 6.0
		 * @deprecated 6.0.20
		 */
		public function getParsedResponse( $inputResponse = null ) {
			return $this->getParsed( $inputResponse );
		}

		/**
		 * @param null $keyName
		 * @param null $responseContent
		 *
		 * @return mixed|null
		 * @throws \Exception
		 * @since 6.0
		 * @deprecated 6.0.20
		 */
		public function getParsedValue( $keyName = null, $responseContent = null ) {
			return $this->getValue( $keyName, $responseContent );
		}



		//////// DEPRECATED FUNCTIONS BEGIN /////////

		/**
		 * Get what external driver see
		 *
		 * @return null
		 * @since 6.0
		 * @deprecated 6.0.20
		 */
		public function getExternalDriverResponse() {
			return $this->TemporaryExternalResponse;
		}

		/**
		 * Guzzle wrapper
		 *
		 * @param string $url
		 * @param array $postData
		 * @param int $CurlMethod
		 * @param int $postAs
		 *
		 * @return $this|MODULE_CURL
		 * @throws \Exception
		 * @since 6.0
		 * @deprecated 6.0.20
		 */
		private function executeGuzzleHttp( $url = '', $postData = array(), $CurlMethod = NETCURL_POST_METHODS::METHOD_GET, $postAs = NETCURL_POST_DATATYPES::DATATYPE_NOT_SET ) {
			/** @noinspection PhpUndefinedClassInspection */
			/** @noinspection PhpUndefinedNamespaceInspection */
			/** @var $gResponse \GuzzleHttp\Psr7\Response */
			$gResponse   = null;
			$rawResponse = null;
			$gBody       = null;

			$myChosenGuzzleDriver = $this->getDriver();
			/** @noinspection PhpUndefinedClassInspection */
			/** @noinspection PhpUndefinedNamespaceInspection */
			/** @var $worker \GuzzleHttp\Client */
			$worker                 = $this->Drivers[ $myChosenGuzzleDriver ];
			$postOptions            = array();
			$postOptions['headers'] = array();
			$contentType            = $this->getContentType();

			if ( $postAs === NETCURL_POST_DATATYPES::DATATYPE_JSON ) {
				$postOptions['headers']['Content-Type'] = 'application/json; charset=utf-8';
				if ( is_string( $postData ) ) {
					$jsonPostData = @json_decode( $postData );
					if ( is_object( $jsonPostData ) ) {
						$postData = $jsonPostData;
					}
				}
				$postOptions['json'] = $postData;
			} else {
				if ( is_array( $postData ) ) {
					$postOptions['form_params'] = $postData;
				}
			}

			$hasAuth = false;
			if ( isset( $this->AuthData['Username'] ) ) {
				$hasAuth = true;
				if ( $this->AuthData['Type'] == NETCURL_AUTH_TYPES::AUTHTYPE_BASIC ) {
					$postOptions['headers']['Accept'] = '*/*';
					if ( ! empty( $contentType ) ) {
						$postOptions['headers']['Content-Type'] = $contentType;
					}
					$postOptions['auth'] = array(
						$this->AuthData['Username'],
						$this->AuthData['Password']
					);
					//$postOptions['headers']['Authorization'] = 'Basic ' . base64_encode($this->AuthData['Username'] . ":" . $this->AuthData['Password']);
				}
			}

			if ( method_exists( $worker, 'request' ) ) {
				if ( $CurlMethod == NETCURL_POST_METHODS::METHOD_GET ) {
					/** @noinspection PhpUndefinedMethodInspection */
					$gRequest = $worker->request( 'GET', $url, $postOptions );
				} else if ( $CurlMethod == NETCURL_POST_METHODS::METHOD_POST ) {
					/** @noinspection PhpUndefinedMethodInspection */
					$gRequest = $worker->request( 'POST', $url, $postOptions );
				} else if ( $CurlMethod == NETCURL_POST_METHODS::METHOD_PUT ) {
					/** @noinspection PhpUndefinedMethodInspection */
					$gRequest = $worker->request( 'PUT', $url, $postOptions );
				} else if ( $CurlMethod == NETCURL_POST_METHODS::METHOD_DELETE ) {
					/** @noinspection PhpUndefinedMethodInspection */
					$gRequest = $worker->request( 'DELETE', $url, $postOptions );
				} else if ( $CurlMethod == NETCURL_POST_METHODS::METHOD_HEAD ) {
					/** @noinspection PhpUndefinedMethodInspection */
					$gRequest = $worker->request( 'HEAD', $url, $postOptions );
				}
			} else {
				throw new \Exception( NETCURL_CURL_CLIENTNAME . " streams for guzzle is probably missing as I can't find the request method in the current class", $this->NETWORK->getExceptionCode( 'NETCURL_GUZZLESTREAM_MISSING' ) );
			}
			/** @noinspection PhpUndefinedVariableInspection */
			$this->TemporaryExternalResponse = array( 'worker' => $worker, 'request' => $gRequest );
			/** @noinspection PhpUndefinedMethodInspection */
			$gHeaders = $gRequest->getHeaders();
			/** @noinspection PhpUndefinedMethodInspection */
			$gBody = $gRequest->getBody()->getContents();
			/** @noinspection PhpUndefinedMethodInspection */
			$statusCode = $gRequest->getStatusCode();
			/** @noinspection PhpUndefinedMethodInspection */
			$statusReason = $gRequest->getReasonPhrase();
			/** @noinspection PhpUndefinedMethodInspection */
			$rawResponse .= "HTTP/" . $gRequest->getProtocolVersion() . " " . $gRequest->getStatusCode() . " " . $gRequest->getReasonPhrase() . "\r\n";
			$rawResponse .= "X-NetCurl-ClientDriver: " . $this->getDriver() . "\r\n";
			if ( is_array( $gHeaders ) ) {
				foreach ( $gHeaders as $hParm => $hValues ) {
					$rawResponse .= $hParm . ": " . implode( "\r\n", $hValues ) . "\r\n";
				}
			}
			$rawResponse .= "\r\n" . $gBody;

			// Prevent problems during authorization. Unsupported media type checks defaults to application/json
			if ( $hasAuth && $statusCode == 415 ) {
				// Ask service for content types at first. If nothing found, run self set application/json.
				$contentTypeRequest = $gRequest->getHeader( 'content-type' );
				if ( empty( $contentTypeRequest ) ) {
					$this->setContentType();
				} else {
					$this->setContentType( $contentTypeRequest );
				}

				return $this->executeGuzzleHttp( $url, $postData, $CurlMethod, $postAs );
			}

			$this->ParseResponse( $rawResponse );
			$this->throwCodeException( $statusCode, $statusReason );

			return $this;
		}

		/**
		 * Execution of http-calls via external Addons
		 *
		 * @param string $url
		 * @param array $postData
		 * @param int $CurlMethod
		 * @param int $postAs
		 *
		 * @return bool|MODULE_CURL|MODULE_SOAP
		 * @throws \Exception
		 * @since 6.0.14
		 * @deprecated 6.0.20
		 */
		private function executeHttpExternal( $url = '', $postData = array(), $CurlMethod = NETCURL_POST_METHODS::METHOD_GET, $postAs = NETCURL_POST_DATATYPES::DATATYPE_NOT_SET ) {
			if ( preg_match( "/\?wsdl$|\&wsdl$/i", $this->CURL_STORED_URL ) || $postAs == NETCURL_POST_DATATYPES::DATATYPE_SOAP ) {
				if ( ! $this->hasSoap() ) {
					throw new \Exception( NETCURL_CURL_CLIENTNAME . " " . __FUNCTION__ . " exception: SoapClient is not available in this system", $this->NETWORK->getExceptionCode( 'NETCURL_SOAPCLIENT_CLASS_MISSING' ) );
				}

				return $this->executeHttpSoap( $url, $this->POST_DATA_HANDLED, $CurlMethod );
			}
			$guzDrivers = array(
				NETCURL_NETWORK_DRIVERS::DRIVER_GUZZLEHTTP,
				NETCURL_NETWORK_DRIVERS::DRIVER_GUZZLEHTTP_STREAM
			);
			if ( $this->getDriver() == NETCURL_NETWORK_DRIVERS::DRIVER_WORDPRESS ) {
				return $this->executeWpHttp( $url, $this->POST_DATA_HANDLED, $CurlMethod, $postAs );
			} else if ( in_array( $this->getDriver(), $guzDrivers ) ) {
				return $this->executeGuzzleHttp( $url, $this->POST_DATA_HANDLED, $CurlMethod, $postAs );
			} else {
				return false;
			}
		}

		/**
		 * Experimental: Convert DOMDocument to an array
		 *
		 * @param array $childNode
		 * @param string $getAs
		 *
		 * @return array
		 * @since 6.0
		 * @deprecated 6.0.20 Use parser instead
		 */
		private function getChildNodes( $childNode = array(), $getAs = '' ) {
			$childNodeArray      = array();
			$childAttributeArray = array();
			$childIdArray        = array();
			$returnContext       = "";
			if ( is_object( $childNode ) ) {
				/** @var \DOMNodeList $nodeItem */
				foreach ( $childNode as $nodeItem ) {
					if ( is_object( $nodeItem ) ) {
						if ( isset( $nodeItem->tagName ) ) {
							if ( strtolower( $nodeItem->tagName ) == "title" ) {
								$elementData['pageTitle'] = $nodeItem->nodeValue;
							}
							$elementData            = array( 'tagName' => $nodeItem->tagName );
							$elementData['id']      = $nodeItem->getAttribute( 'id' );
							$elementData['name']    = $nodeItem->getAttribute( 'name' );
							$elementData['context'] = $nodeItem->nodeValue;
							/** @since 6.0.20 Saving innerhtml */
							$elementData['innerhtml'] = $nodeItem->ownerDocument->saveHTML( $nodeItem );
							if ( $nodeItem->hasChildNodes() ) {
								$elementData['childElement'] = $this->getChildNodes( $nodeItem->childNodes, $getAs );
							}
							$identificationName = $nodeItem->tagName;
							if ( empty( $identificationName ) && ! empty( $elementData['name'] ) ) {
								$identificationName = $elementData['name'];
							}
							if ( empty( $identificationName ) && ! empty( $elementData['id'] ) ) {
								$identificationName = $elementData['id'];
							}
							$childNodeArray[] = $elementData;
							if ( ! isset( $childAttributeArray[ $identificationName ] ) ) {
								$childAttributeArray[ $identificationName ] = $elementData;
							} else {
								$childAttributeArray[ $identificationName ][] = $elementData;
							}

							$idNoName = $nodeItem->tagName;
							// Forms without id namings will get the tagname. This will open up for reading forms and other elements without id's.
							// NOTE: If forms are not tagged with an id, the form will not render "properly" and the form fields might pop outside the real form.
							if ( empty( $elementData['id'] ) ) {
								$elementData['id'] = $idNoName;
							}

							if ( ! empty( $elementData['id'] ) ) {
								if ( ! isset( $childIdArray[ $elementData['id'] ] ) ) {
									$childIdArray[ $elementData['id'] ] = $elementData;
								} else {
									$childIdArray[ $elementData['id'] ][] = $elementData;
								}
							}
						}
					}
				}
			}
			if ( empty( $getAs ) || $getAs == "domnodes" ) {
				$returnContext = $childNodeArray;
			} else if ( $getAs == "tagnames" ) {
				$returnContext = $childAttributeArray;
			} else if ( $getAs == "id" ) {
				$returnContext = $childIdArray;
			}

			return $returnContext;
		}

		/**
		 * @return array
		 *
		 * @since 6.0.16
		 * @deprecated 6.0.20
		 */
		public function getTemporaryResponse() {
			return $this->TemporaryResponse;
		}

		/**
		 * Parse content and handle specially received content automatically
		 *
		 * If this functions receives a json string or any other special content (as PHP-serializations), it will try to convert that string automatically to a readable array.
		 *
		 * @param string $content
		 * @param bool $isFullRequest
		 * @param null $contentType
		 *
		 * @return array|mixed|null
		 * @throws \Exception
		 * @since 6.0
		 * @deprecated 6.0.20
		 */
		public function ParseContent( $content = '', $isFullRequest = false, $contentType = null ) {
			if ( $isFullRequest ) {
				$newContent  = $this->ParseResponse( $content );
				$content     = trim( $newContent['body'] );
				$contentType = isset( $newContent['header']['info']['Content-Type'] ) ? $newContent['header']['info']['Content-Type'] : null;
			}
			$parsedContent     = null;
			$testSerialization = null;
			$testJson          = @json_decode( $content );
			if ( gettype( $testJson ) === "object" || ( ! empty( $testJson ) && is_array( $testJson ) ) ) {
				$parsedContent = $testJson;
			} else {
				if ( is_string( $content ) ) {
					$testSerialization = @unserialize( $content );
					if ( gettype( $testSerialization ) == "object" || gettype( $testSerialization ) === "array" ) {
						$parsedContent = $testSerialization;
					}
				}
			}
			if ( is_null( $parsedContent ) && ( preg_match( "/xml version/", $content ) || preg_match( "/rss version/", $content ) || preg_match( "/xml/i", $contentType ) ) ) {
				$trimmedContent        = trim( $content ); // PHP 5.3: Can't use function return value in write context
				$overrideXmlSerializer = false;
				if ( $this->useXmlSerializer ) {
					$serializerPath = stream_resolve_include_path( 'XML/Unserializer.php' );
					if ( ! empty( $serializerPath ) ) {
						$overrideXmlSerializer = true;
						/** @noinspection PhpIncludeInspection */
						require_once( 'XML/Unserializer.php' );
					}
				}

				if ( class_exists( 'SimpleXMLElement' ) && ! $overrideXmlSerializer ) {
					if ( ! empty( $trimmedContent ) ) {
						if ( ! $this->allowCdata ) {
							$simpleXML = new \SimpleXMLElement( $content, LIBXML_NOCDATA );
						} else {
							$simpleXML = new \SimpleXMLElement( $content );
						}
						if ( isset( $simpleXML ) && ( is_object( $simpleXML ) || is_array( $simpleXML ) ) ) {
							return $simpleXML;
						}
					} else {
						return null;
					}
				} else {
					// Returns empty class if the SimpleXMLElement is missing.
					if ( $overrideXmlSerializer ) {
						/** @noinspection PhpUndefinedClassInspection */
						$xmlSerializer = new \XML_Unserializer();
						/** @noinspection PhpUndefinedMethodInspection */
						$xmlSerializer->unserialize( $content );

						/** @noinspection PhpUndefinedMethodInspection */
						return $xmlSerializer->getUnserializedData();
					}

					return new \stdClass();
				}
			}

			// TODO: Rebuild parser to include HTML rendered data regardless of the parsed content type
			if ( ! empty( $content ) && empty( $parsedContent ) && $this->getParseHtml() ) {
				$parsedContent                 = array();
				$parsedContent['ByNodes']      = array();
				$parsedContent['ByClosestTag'] = array();
				$parsedContent['ById']         = array();

				if ( class_exists( 'DOMDocument' ) ) {
					/** @var \DOMDocument $DOM */
					$DOM = new \DOMDocument();
					libxml_use_internal_errors( true );
					$DOM->loadHTML( $content );
					if ( isset( $DOM->childNodes->length ) && $DOM->childNodes->length > 0 ) {
						$elementsByTagName = $DOM->getElementsByTagName( '*' );
						$childNodeArray    = $this->getChildNodes( $elementsByTagName );
						$childTagArray     = $this->getChildNodes( $elementsByTagName, 'tagnames' );
						$childIdArray      = $this->getChildNodes( $elementsByTagName, 'id' );

						if ( is_array( $childNodeArray ) && count( $childNodeArray ) ) {
							$parsedContent['ByNodes'] = $childNodeArray;
						}
						if ( is_array( $childTagArray ) && count( $childTagArray ) ) {
							$parsedContent['ByClosestTag'] = $childTagArray;
						}
						if ( is_array( $childIdArray ) && count( $childIdArray ) ) {
							$parsedContent['ById'] = $childIdArray;
						}
					}
				} else {
					throw new \Exception( NETCURL_CURL_CLIENTNAME . " HtmlParse exception: Can not parse DOMDocuments without the DOMDocuments class", $this->NETWORK->getExceptionCode( "NETCURL_DOMDOCUMENT_CLASS_MISSING" ) );
				}
			}

			$this->ParseContainer = $parsedContent;

			return $parsedContent;
		}

		// Created for future use
		/*public function __call( $name, $arguments ) {

			// WARNING: Experimental
			if ( $this->isFlag( 'XMLSOAP' ) && $this->IO->getHasXmlSerializer() && $this->NETCURL_POST_DATA_TYPE == NETCURL_POST_DATATYPES::DATATYPE_SOAP_XML ) {
				$this->setContentType( 'text/xml' ); // ; charset=utf-8
				$this->setCurlHeader( 'Content-Type', $this->getContentType() );
				$soapifyArray = array(
					'Body' => array(
						$name => array()
					)
				);
				$this->IO->setXmlSimple( true );
				$this->IO->setSoapXml( true );
				$this->NETCURL_POST_PREPARED_XML = $this->IO->renderXml( $soapifyArray, false, TORNELIB_CRYPTO_TYPES::TYPE_NONE, $name, 'SOAP-ENV' );

				return $this->doPost( $this->CURL_STORED_URL, $this->NETCURL_POST_PREPARED_XML, NETCURL_POST_DATATYPES::DATATYPE_XML );
			}

			throw new \Exception( NETCURL_CURL_CLIENTNAME . " exception: Function " . $name . " does not exist!", $this->NETWORK->getExceptionCode( "NETCURL_UNEXISTENT_FUNCTION" ) );
		}*/


	}

	if ( ! class_exists( 'Tornevall_cURL' ) && ! class_exists( 'TorneLIB\Tornevall_cURL' ) ) {
		/**
		 * Class MODULE_CURL
		 * @package TorneLIB
		 * @throws \Exception
		 * @deprecated 6.0.20
		 */
		class Tornevall_cURL extends MODULE_CURL {
			function __construct( $requestUrl = '', $requestPostData = array(), $requestPostMethod = NETCURL_POST_METHODS::METHOD_POST, $requestFlags = array() ) {
				return parent::__construct( $requestUrl, $requestPostData, $requestPostMethod );
			}
		}
	}
}
