<?php

namespace Resursbank\RBEcomPHP;

// Make sure this library won't conflict with others
if ( ! class_exists( 'TorneLIB_Network' ) && ! class_exists( 'TorneLIB\TorneLIB_Network' ) ) {
    /**
     * Library for handling network related things (currently not sockets). A conversion of a legacy PHP library called "TorneEngine" and family.
     *
     * Class TorneLIB_Network
     * @version 6.0.1
     * @link https://phpdoc.tornevall.net/TorneLIBv5/class-TorneLIB.TorneLIB_Network.html PHPDoc/Staging - TorneLIB_Network
     * @link https://docs.tornevall.net/x/KQCy TorneLIB (PHP) Landing documentation
     * @link https://bitbucket.tornevall.net/projects/LIB/repos/tornelib-php/browse Sources of TorneLIB
     * @package TorneLIB
     */
	class TorneLIB_Network {
		/** @var array Headers from the webserver that may contain potential proxies */
		private $proxyHeaders = array(
			'HTTP_VIA',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_FORWARDED',
			'HTTP_CLIENT_IP',
			'HTTP_FORWARDED_FOR_IP',
			'VIA',
			'X_FORWARDED_FOR',
			'FORWARDED_FOR',
			'X_FORWARDED',
			'FORWARDED',
			'CLIENT_IP',
			'FORWARDED_FOR_IP',
			'HTTP_PROXY_CONNECTION'
		);

		/** @var array Stored list of what the webserver revealed */
		private $clientAddressList = array();
		private $cookieDefaultPath = "/";
		private $cookieUseSecure;
		private $cookieDefaultDomain;
		private $cookieDefaultPrefix;

		function __construct() {
			// Initiate and get client headers.
			$this->renderProxyHeaders();
		}

		/**
		 * Extract domain from URL-based string.
		 *
		 * To make a long story short: This is a very unclever function from the birth of the developer (in a era when documentation was not "necessary" to read and stupidity ruled the world).
		 * As some functions still uses this, we chose to keep it, but do it "right".
		 *
		 * @param string $urlIn
		 * @param bool $validateHost Validate that the hostname do exist
		 *
		 * @return array
		 */
		public function getUrlDomain( $urlIn = '', $validateHost = false ) {
			// If the scheme is forgotten, add it to keep normal hosts validatable too.
			if ( ! preg_match( "/\:\/\//", $urlIn ) ) {
				$urlIn = "http://" . $urlIn;
			}
			$urlParsed = parse_url( $urlIn );
			if ( ! isset( $urlParsed['host'] ) || ! $urlParsed['scheme'] ) {
				return array( null, null, null );
			}
			if ( $validateHost ) {
				// Make sure that the host is not invalid
				$hostRecord = dns_get_record( $urlParsed['host'], DNS_ANY );
				if ( ! count( $hostRecord ) ) {
					return array( null, null, null );
				}
			}

			return array(
				isset( $urlParsed['host'] ) ? $urlParsed['host'] : null,
				isset( $urlParsed['scheme'] ) ? $urlParsed['scheme'] : null,
				isset( $urlParsed['path'] ) ? $urlParsed['path'] : null
			);
		}

		/**
		 * Extract urls from a text string and return as array
		 *
		 * @param $stringWithUrls
		 * @param int $offset
		 * @param int $urlLimit
		 * @param array $protocols
		 *
		 * @return array
		 */
		public function getUrlsFromHtml( $stringWithUrls, $offset = - 1, $urlLimit = - 1, $protocols = array( "http" ) ) {
			$returnArray = array();
			// Pick up all urls
			foreach ( $protocols as $protocol ) {
				preg_match_all( "/src=\"$protocol(.*?)\"|src='$protocol(.*?)'/is", $stringWithUrls, $matches );
				if ( isset( $matches[1] ) && count( $matches[1] ) ) {
					$urls = $matches[1];
				}
				if ( count( $urls ) ) {
					foreach ( $urls as $url ) {
						$prependUrl    = $protocol . $url;
						$returnArray[] = $prependUrl;
					}
				}
			}
			// Start at a specific offset if defined
			if ( count( $returnArray ) && $offset > - 1 && $offset <= $returnArray ) {
				$allowedOffset  = 0;
				$returnNewArray = array();
				$urlCount       = 0;
				for ( $offsetIndex = 0; $offsetIndex < count( $returnArray ); $offsetIndex ++ ) {
					if ( $offsetIndex == $offset ) {
						$allowedOffset = true;
					}
					if ( $allowedOffset ) {
						// Break when requested limit has beenreached
						$urlCount ++;
						if ( $urlLimit > - 1 && $urlCount > $urlLimit ) {
							break;
						}
						$returnNewArray[] = $returnArray[ $offsetIndex ];
					}
				}
				$returnArray = $returnNewArray;
			}

			return $returnArray;
		}

		/**
		 * Set a cookie
		 *
		 * @param string $name
		 * @param string $value
		 * @param string $expire
		 *
		 * @return bool
		 */
		public function setCookie( $name = '', $value = '', $expire = '' ) {
			$this->setCookieParameters();
			$defaultExpire = time() + 60 * 60 * 24 * 1;
			if ( empty( $expire ) ) {
				$expire = $defaultExpire;
			} else if ( is_string( $expire ) ) {
				$expire = strtotime( $expire );
			}

			return setcookie( $this->cookieDefaultPrefix . $name, $value, $expire, $this->cookieDefaultPath, $this->cookieDefaultDomain, $this->cookieUseSecure );
		}

		/**
		 * Prepare addon parameters for setting a cookie
		 *
		 * @param string $path
		 * @param null $prefix
		 * @param null $domain
		 * @param null $secure
		 */
		public function setCookieParameters( $path = "/", $prefix = null, $domain = null, $secure = null ) {
			$this->cookieDefaultPath = $path;
			if ( empty( $this->cookieDefaultDomain ) ) {
				if ( is_null( $domain ) ) {
					$this->cookieDefaultDomain = "." . $_SERVER['HTTP_HOST'];
				} else {
					$this->cookieDefaultDomain = $domain;
				}
			}
			if ( is_null( $secure ) ) {
				if ( isset( $_SERVER['HTTPS'] ) ) {
					if ( $_SERVER['HTTPS'] == "true" ) {
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
			if ( ! is_null( $prefix ) ) {
				$this->cookieDefaultPrefix = $prefix;
			}
		}


		/**
		 * Render a list of client ip addresses (if exists). This requires that the server exposes the REMOTE_ADDR
		 */
		private function renderProxyHeaders() {
			if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
				$this->clientAddressList = array( 'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] );
				foreach ( $this->proxyHeaders as $proxyVar ) {
					if ( isset( $_SERVER[ $proxyVar ] ) ) {
						$this->clientAddressList[ $proxyVar ] = $_SERVER[ $proxyVar ];
					}
				}
			}
		}

		/**
		 * Returns a list of header where the browser client might reveal anything about proxy usage.
		 *
		 * @return array
		 */
		public function getProxyHeaders() {
			return $this->clientAddressList;
		}

		/**
		 * Extract domain name (zone name) from hostname
		 *
		 * @param string $useHost Alternative hostname than the HTTP_HOST
		 *
		 * @return string
		 */
		public function getDomainName( $useHost = "" ) {
			$currentHost = "";
			if ( empty( $useHost ) ) {
				if ( isset( $_SERVER['HTTP_HOST'] ) ) {
					$currentHost = $_SERVER['HTTP_HOST'];
				}
			} else {
				$extractHost = $this->getUrlDomain( $useHost );
				$currentHost = $extractHost[0];
			}
			// Do this, only if it's a real domain (if scripts are running from console, there might be a loss of this hostname (or if it is a single name, like localhost)
			if ( ! empty( $currentHost ) && preg_match( "/\./", $currentHost ) ) {
				$thisdomainArray = explode( ".", $currentHost );
				$thisdomain      = $thisdomainArray[ sizeof( $thisdomainArray ) - 2 ] . "." . $thisdomainArray[ sizeof( $thisdomainArray ) - 1 ];
			}

			return ( ! empty( $thisdomain ) ? $thisdomain : null );
		}

		/**
		 * base64_encode
		 *
		 * @param $data
		 *
		 * @return string
		 */
		public function base64url_encode( $data ) {
			return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
		}

		/**
		 * base64_decode
		 *
		 * @param $data
		 *
		 * @return string
		 */
		public function base64url_decode( $data ) {
			return base64_decode( str_pad( strtr( $data, '-_', '+/' ), strlen( $data ) % 4, '=', STR_PAD_RIGHT ) );
		}


		/**
		 * Get reverse octets from ip address
		 *
		 * @param string $ipAddr
		 * @param bool $returnIpType
		 *
		 * @return int|string
		 */
		public function getArpaFromAddr( $ipAddr = '', $returnIpType = false ) {
			if ( filter_var( $ipAddr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) !== false ) {
				if ( $returnIpType === true ) {
					$vArpaTest = $this->getArpaFromIpv6( $ipAddr );    // PHP 5.3
					if ( ! empty( $vArpaTest ) ) {
						return TorneLIB_Network_IP::IPTYPE_V6;
					} else {
						return TorneLIB_Network_IP::IPTYPE_NONE;
					}
				} else {
					return $this->getArpaFromIpv6( $ipAddr );
				}
			} else if ( filter_var( $ipAddr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) !== false ) {
				if ( $returnIpType ) {
					return TorneLIB_Network_IP::IPTYPE_V4;
				} else {
					return $this->getArpaFromIpv4( $ipAddr );
				}
			} else {
				if ( $returnIpType ) {
					return TorneLIB_Network_IP::IPTYPE_NONE;
				}
			}

			return "";
		}

		/**
		 * Get IP range from netmask
		 *
		 * @param null $mask
		 *
		 * @return array
		 */
		function getRangeFromMask( $mask = null ) {
			$addresses = array();
			@list( $ip, $len ) = explode( '/', $mask );
			if ( ( $min = ip2long( $ip ) ) !== false ) {
				$max = ( $min | ( 1 << ( 32 - $len ) ) - 1 );
				for ( $i = $min; $i < $max; $i ++ ) {
					$addresses[] = long2ip( $i );
				}
			}

			return $addresses;
		}

		/**
		 * Test if the given ip address is in the netmask range (not ipv6 compatible yet)
		 *
		 * @param $IP
		 * @param $CIDR
		 *
		 * @return bool
		 */
		public function isIpInRange( $IP, $CIDR ) {
			list ( $net, $mask ) = explode( "/", $CIDR );
			$ip_net    = ip2long( $net );
			$ip_mask   = ~( ( 1 << ( 32 - $mask ) ) - 1 );
			$ip_ip     = ip2long( $IP );
			$ip_ip_net = $ip_ip & $ip_mask;

			return ( $ip_ip_net == $ip_net );
		}

		/**
		 * Translate ipv6 address to reverse octets
		 *
		 * @param string $ipAddr
		 *
		 * @return string
		 */
		public function getArpaFromIpv6( $ipAddr = '::' ) {
			if ( filter_var( $ipAddr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) === false ) {
				return null;
			}
			$unpackedAddr = @unpack( 'H*hex', inet_pton( $ipAddr ) );
			$hex          = $unpackedAddr['hex'];

			return implode( '.', array_reverse( str_split( $hex ) ) );
		}

		/**
		 * Translate ipv4 address to reverse octets
		 *
		 * @param string $ipAddr
		 *
		 * @return string
		 */
		public function getArpaFromIpv4( $ipAddr = '127.0.0.1' ) {
			if ( filter_var( $ipAddr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) !== false ) {
				return implode( ".", array_reverse( explode( ".", $ipAddr ) ) );
			}

			return null;
		}

		/**
		 * Translate ipv6 reverse octets to ipv6 address
		 *
		 * @param string $arpaOctets
		 *
		 * @return string
		 */
		public function getIpv6FromOctets( $arpaOctets = '0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0' ) {
			return @inet_ntop( pack( 'H*', implode( "", array_reverse( explode( ".", preg_replace( "/\.ip6\.arpa$|\.ip\.int$/", '', $arpaOctets ) ) ) ) ) );
		}

		function Redirect( $redirectToUrl = '', $replaceHeader = false, $responseCode = 301 ) {
			header( "Location: $redirectToUrl", $replaceHeader, $responseCode );
			exit;
		}

	}

	/** @noinspection PhpUndefinedClassInspection */

	/**
	 * Class TorneLIB_Network_IP IP Address Types class
	 * @package TorneLIB
	 */
	abstract class TorneLIB_Network_IP {
		const IPTYPE_NONE = 0;
		const IPTYPE_V4 = 4;
		const IPTYPE_V6 = 6;
	}

	/**
	 * Class Tornevall_cURL
	 *
	 * @package TorneLIB
	 * @version 6.0.3
	 * @link https://phpdoc.tornevall.net/TorneLIBv5/source-class-TorneLIB.Tornevall_cURL.html PHPDoc/Staging - Tornevall_cURL
	 * @link https://docs.tornevall.net/x/KQCy TorneLIB (PHP) Landing documentation
	 * @link https://bitbucket.tornevall.net/projects/LIB/repos/tornelib-php/browse Sources of TorneLIB
	 * @link https://docs.tornevall.net/x/KwCy Network & Curl Library usage
	 * @link https://docs.tornevall.net/x/FoBU TorneLIB Full documentation
	 */
	class Tornevall_cURL {
		/**
		 * Prepare TorneLIB_Network class if it exists (as of the november 2016 it does).
		 *
		 * @var TorneLIB_Network
		 */
		private $NETWORK;

		/** @var string Internal version that is being used to find out if we are running the latest version of this library */
		private $TorneCurlVersion = "6.0.3";
		private $CurlVersion = null;

		/** @var string Internal release snapshot that is being used to find out if we are running the latest version of this library */
		private $TorneCurlRelease = "20170906";

		/**
		 * Target environment (if target is production some debugging values will be skipped)
		 *
		 * @since 5.0.0-20170210
		 * @var int
		 */
		private $TargetEnvironment = TORNELIB_CURL_ENVIRONMENT::ENVIRONMENT_PRODUCTION;

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

		private $sslDriverError = array();
		private $sslCurlDriver = false;

		/**
		 * Allow https calls to unverified peers/hosts
		 *
		 * @since 5.0.0-20170210
		 * @var bool
		 */
		private $allowSslUnverified = false;

		/** @var array Default paths to the certificates we are looking for */
		public $sslPemLocations = array( '/etc/ssl/certs/cacert.pem', '/etc/ssl/certs/ca-certificates.crt' );
		/** @var bool For debugging only */
		public $_DEBUG_TCURL_UNSET_LOCAL_PEM_LOCATION = false;
		/** @var bool During tests this will be set to true if certificate files is found */
		private $hasCertFile = false;
		/** @var string Defines what file to use as a certificate bundle */
		private $useCertFile = "";
		/** @var bool Shows if the certificate file found has been found internally or if it was set by user */
		private $hasDefaultCertFile = false;
		/** @var bool Shows if the certificate check has been runned */
		private $openSslGuessed = false;
		/** @var bool During tests this will be set to true if certificate directory is found */
		private $hasCertDir = false;

		/** @var null Our communication channel */
		private $CurlSession = null;
		/** @var null URL to communicate with */
		private $CurlURL = null;

		/** @var null A tempoary set of the response from the url called */
		private $TemporaryResponse = null;

		/**
		 * Default settings when initializing our curlsession.
		 *
		 * Since v6.0.2 no urls are followed by default, it is set internally by first checking PHP security before setting this up.
		 * The reason of the change is not only the security, it is also about inheritage of options to SOAPClient.
		 *
		 * @var array
		 */
		public $curlopt = array(
			CURLOPT_CONNECTTIMEOUT => 5,
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

		private $redirectedUrls = array();

		/** @var array User set SSL Options */
		public $sslopt = array();

		/** @var bool Decide whether the curl library should follow an url redirect or not */
		private $followLocationSet = true;

		/** @var array Interfaces to use */
		public $IpAddr = array();
		/** @var bool If more than one ip is set in the interfaces to use, this will make the interface go random */
		public $IpAddrRandom = true;
		private $CurlIp = null;
		private $CurlIpType = null;

		private $useLocalCookies = false;
		private $CookiePath = null;
		private $SaveCookies = false;
		private $CookieFile = null;
		private $CookiePathCreated = false;
		private $UseCookieExceptions = false;
		public $AllowTempAsCookiePath = false;

		/** @var null Sets a HTTP_REFERER to the http call */
		public $CurlReferer = null;

		/** @var null CurlProxy, if set, we will try to proxify the traffic */
		private $CurlProxy = null;
		/** @var null, if not set, but CurlProxy is, we will use HTTP as proxy (See CURLPROXY_* for more information) */
		private $CurlProxyType = null;

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
		/** @var string Custom User-Agent sent in the HTTP-HEADER */
		private $CurlUserAgent;
		/** @var string Custom User-Agent Memory */
		private $CustomUserAgent;

		/** @var bool Try to automatically parse the retrieved body content. Supports, amongst others json, serialization, etc */
		public $CurlAutoParse = true;
		/** @var bool Allow parsing of content bodies (tags) */
		private $allowParseHtml = false;
		private $ResponseType = TORNELIB_CURL_RESPONSETYPE::RESPONSETYPE_ARRAY;

		/**
		 * Authentication
		 */
		private $AuthData = array( 'Username' => null, 'Password' => null, 'Type' => CURL_AUTH_TYPES::AUTHTYPE_NONE );

		/** @var array Adding own headers to the HTTP-request here */
		private $CurlHeaders = array();
		private $CurlHeadersSystem = array();
		private $CurlHeadersUserDefined = array();
		private $allowCdata = false;
		private $useXmlSerializer = false;

		/**
		 * Set up if this library can throw exceptions, whenever it needs to do that.
		 *
		 * Note: This does not cover everything in the library. It was set up for handling SoapExceptions.
		 *
		 * @var bool
		 */
		public $canThrow = true;

		/** @var bool By default, this library does not store any curl_getinfo during exceptions */
		private $canStoreSessionException = false;
		/** @var array An array that contains each curl_exec (curl_getinfo) when an exception are thrown */
		private $sessionsExceptions = array();

		/**
		 * Defines whether, when there is an incoming SOAP-call, we should try to make the SOAP initialization twice.
		 * This is a kind of fallback when users forget to add ?wsdl or &wsdl in urls that requires this to call for SOAP.
		 * It may happen when setting CURL_POST_AS to a SOAP-call but, the URL is not defined as one.
		 * Setting this to false, may suppress important errors, since this will suppress fatal errors at first try.
		 *
		 * @var bool
		 */
		public $SoapTryOnce = true;

		/**
		 * Tornevall_cURL constructor.
		 *
		 * @param string $PreferredURL
		 * @param array $PreparedPostData
		 * @param int $PreferredMethod
		 *
		 * @throws \Exception
		 */
		public function __construct( $PreferredURL = '', $PreparedPostData = array(), $PreferredMethod = CURL_METHODS::METHOD_POST ) {
			if ( ! function_exists( 'curl_init' ) ) {
				throw new \Exception( "curl library not found" );
			}
			// Common ssl checkers (if they fail, there is a sslDriverError to recall
			if ( ! in_array( 'https', @stream_get_wrappers() ) ) {
				$this->sslDriverError[] = "SSL Failure: HTTPS wrapper can not be found";
			}
			if ( ! extension_loaded( 'openssl' ) ) {
				$this->sslDriverError[] = "SSL Failure: HTTPS extension can not be found";
			}
			// Initial setup
			$this->CurlUserAgent = 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 1.0.3705; .NET CLR 1.1.4322; Media Center PC 4.0; +TorneLIB+cUrl ' . $this->TorneCurlVersion . '/' . $this->TorneCurlRelease . ')';
			if ( function_exists( 'curl_version' ) ) {
				$CurlVersionRequest = curl_version();
				$this->CurlVersion  = $CurlVersionRequest['version'];
				if ( defined( 'CURL_VERSION_SSL' ) ) {
					if ( isset( $CurlVersionRequest['features'] ) ) {
						$this->sslCurlDriver = ( $CurlVersionRequest['features'] & CURL_VERSION_SSL ? true : false );
						if ( ! $this->sslCurlDriver ) {
							$this->sslDriverError[] = 'SSL Failure: Protocol "https" not supported or disabled in libcurl';
						}
					} else {
						$this->sslDriverError[] = "SSL Failure: CurlVersionFeaturesList does not return any feature (this should not be happen)";
					}
				}
			}
			// If any of the above triggered an error, set curlDriver to false, as there may be problems during the
			// urlCall anyway. This library does not throw any error itself in those erros, since most of this kind of problems
			// are handled by curl itself. However, this opens for self checking in an early state through the hasSsl() function
			// and could be triggered long before the url calls are sent (and by means warn the developer that implements this solution
			// that there are an upcoming problem with the SSL support).
			if ( count( $this->sslDriverError ) ) {
				$this->sslCurlDriver = false;
			}
			$this->CurlResolve = CURL_RESOLVER::RESOLVER_DEFAULT;
			$this->NETWORK     = new TorneLIB_Network();
			$this->openssl_guess();
			register_shutdown_function( array( $this, 'tornecurl_terminate' ) );

			if ( ! empty( $PreferredURL ) ) {
				$InstantResponse = null;
				if ( $PreferredMethod == CURL_METHODS::METHOD_GET ) {
					$InstantResponse = $this->doGet( $PreferredURL );
				} else if ( $PreferredMethod == CURL_METHODS::METHOD_POST ) {
					$InstantResponse = $this->doPost( $PreferredURL, $PreparedPostData );
				} else if ( $PreferredMethod == CURL_METHODS::METHOD_PUT ) {
					$InstantResponse = $this->doPut( $PreferredURL, $PreparedPostData );
				} else if ( $PreferredMethod == CURL_METHODS::METHOD_DELETE ) {
					$InstantResponse = $this->doDelete( $PreferredURL, $PreparedPostData );
				}

				return $InstantResponse;
			}

			return null;
		}

		public function getVersion( $fullRelease = false ) {
			if ( ! $fullRelease ) {
				return $this->TorneCurlVersion;
			} else {
				return $this->TorneCurlVersion . "-" . $this->TorneCurlRelease;
			}
		}

		/**
		 * TorneCurl Termination Controller - Used amongst others, to make sure that empty cookiepaths created by this library gets removed if they are being used.
		 */
		function tornecurl_terminate() {
			/*
         * If this indicates that we created the path, make sure it's removed if empty after session completion
         */
			if ( ! count( glob( $this->CookiePath . "/*" ) ) && $this->CookiePathCreated ) {
				@rmdir( $this->CookiePath );
			}
		}

		/**
		 * When using soap/xml fields returned as CDATA will be returned as text nodes if this is disabled (default: diabled)
		 *
		 * @param bool $enabled
		 */
		public function setCdata( $enabled = true ) {
			$this->allowCdata = $enabled;
		}

		/**
		 * Get current state of the setCdata
		 *
		 * @return bool
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
		 */
		public function setLocalCookies( $enabled = false ) {
			$this->useLocalCookies = $enabled;
		}

		/**
		 * Enforce a response type if you're not happy with the default returned array.
		 *
		 * @param int $ResponseType
		 *
		 * @since 5.0.0/2017.4
		 */
		public function setResponseType( $ResponseType = TORNELIB_CURL_RESPONSETYPE::RESPONSETYPE_ARRAY ) {
			$this->ResponseType = $ResponseType;
		}

		/**
		 * Enforces CURLOPT_FOLLOWLOCATION to act different if not matching with the internal rules
		 *
		 * @param bool $setEnabledState
		 *
		 * @since 5.0.0/2017.4
		 */
		public function setEnforceFollowLocation( $setEnabledState = true ) {
			$this->followLocationSet     = $setEnabledState;
		}


		/**
		 * Switch over to forced debugging
		 *
		 * To not break production environments by setting for example _DEBUG_TCURL_UNSET_LOCAL_PEM_LOCATION, switching over to test mode is required
		 * to use those variables.
		 *
		 * @since 5.0.0-20170210
		 */
		public function setTestEnabled() {
			$this->TargetEnvironment = TORNELIB_CURL_ENVIRONMENT::ENVIRONMENT_TEST;
		}

		/**
		 * Allow the initCookie-function to throw exceptions if the local cookie store can not be created properly
		 *
		 * Exceptions are invoked, normally when the function for initializing cookies can not create the storage directory. This is something you should consider disabled in a production environment.
		 *
		 * @param bool $enabled
		 */
		public function setCookieExceptions( $enabled = false ) {
			$this->UseCookieExceptions = $enabled;
		}

		public function setParseHtml( $enabled = false ) {
			$this->allowParseHtml = $enabled;
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
			if ( defined( 'TORNELIB_ALLOW_VERSION_REQUESTS' ) && TORNELIB_ALLOW_VERSION_REQUESTS === true ) {
				return $this->TorneCurlVersion . "," . $this->TorneCurlRelease;
			}
			throw new \Exception( "[" . __CLASS__ . "] Version requests are not allowed", 403 );
		}

		/**
		 * @param string $libName
		 *
		 * @return string
		 */
		private function getHasUpdateState( $libName = 'tornelib_curl' ) {
			/*
         * Currently only supporting this internal module (through $myRelease).
         */
			$myRelease  = $this->getInternalRelease();
			$libRequest = ( ! empty( $libName ) ? "lib/" . $libName : "" );
			$getInfo    = $this->doGet( "https://api.tornevall.net/2.0/libs/getLibs/" . $libRequest . "/me/" . $myRelease );
			if ( isset( $getInfo['parsed']->response->getLibsResponse->you ) ) {
				$currentPublicVersion = $getInfo['parsed']->response->getLibsResponse->you;
				if ( $currentPublicVersion->hasUpdate ) {
					if ( isset( $getInfo['parsed']->response->getLibsResponse->libs->tornelib_curl ) ) {
						return $getInfo['parsed']->response->getLibsResponse->libs->tornelib_curl;
					}
				} else {
					return "";
				}
			}

			return "";
		}

		public function getStoredExceptionInformation() {
			return $this->sessionsExceptions;
		}

		/**
		 * Check against Tornevall Networks API if there are updates for this module
		 *
		 * @param string $libName
		 *
		 * @return string
		 */
		public function hasUpdate( $libName = 'tornelib_curl' ) {
			if ( ! defined( 'TORNELIB_ALLOW_VERSION_REQUESTS' ) ) {
				define( 'TORNELIB_ALLOW_VERSION_REQUESTS', true );
			}

			return $this->getHasUpdateState( $libName );
		}

		/**
		 * Set up a different user agent for this library
		 *
		 * To make proper identification of the library we are always appending TorbeLIB+cUrl to the chosen user agent string.
		 *
		 * @param null $CustomUserAgent
		 */
		public function setUserAgent( $CustomUserAgent = "" ) {
			if ( ! empty( $CustomUserAgent ) ) {
				$this->CustomUserAgent .= preg_replace( "/\s+$/", '', $CustomUserAgent );
				$this->CurlUserAgent   = $this->CustomUserAgent . " +TorneLIB+cUrl " . $this->TorneCurlVersion . '/' . $this->TorneCurlRelease;
			} else {
				$this->CurlUserAgent = 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 1.0.3705; .NET CLR 1.1.4322; Media Center PC 4.0; TorneLIB+cUrl ' . $this->TorneCurlVersion . '/' . $this->TorneCurlRelease . ')';
			}
		}

		/**
		 * Returns the current set user agent
		 *
		 * @return string
		 */
		public function getUserAgent() {
			return $this->CurlUserAgent;
		}

		public function getCustomUserAgent() {
			return $this->CustomUserAgent;
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
		 * cUrl initializer, if needed faster
		 *
		 * @return null|resource
		 */
		public function init() {
			$this->initCookiePath();
			$this->CurlSession = curl_init( $this->CurlURL );
			return $this->CurlSession;
		}

		/**
		 * Initialize cookie storage
		 *
		 * @throws \Exception
		 */
		private function initCookiePath() {
			if ( defined( 'TORNELIB_DISABLE_CURL_COOKIES' ) || ! $this->useLocalCookies ) {
				return;
			}

			/**
			 * TORNEAPI_COOKIES has priority over TORNEAPI_PATH that is the default path
			 */
			if ( defined( 'TORNEAPI_COOKIES' ) ) {
				$this->CookiePath = TORNEAPI_COOKIES;
			} else {
				if ( defined( 'TORNEAPI_PATH' ) ) {
					$this->CookiePath = TORNEAPI_PATH . "/cookies";
				}
			}
			// If path is still empty after the above check, continue checking other paths
			if ( empty( $this->CookiePath ) || ( ! empty( $this->CookiePath ) && ! is_dir( $this->CookiePath ) ) ) {
				// We could use /tmp as cookie path but it is not recommended (which means this permission is by default disabled
				if ( $this->AllowTempAsCookiePath ) {
					if ( is_dir( "/tmp" ) ) {
						$this->CookiePath = "/tmp/";
					}
				} else {
					// However, if we still failed, we're trying to use a local directory
					$realCookiePath = realpath( __DIR__ . "/../cookies" );
					if ( empty( $realCookiePath ) ) {
						// Try to create a directory before bailing out
						$getCookiePath = realpath( __DIR__ . "/../" );
						@mkdir( $getCookiePath . "/cookies/" );
						$this->CookiePathCreated = true;
						$this->CookiePath        = realpath( $getCookiePath . "/cookies/" );
					} else {
						$this->CookiePath = realpath( __DIR__ . "/../cookies" );
					}
					if ( $this->UseCookieExceptions && ( empty( $this->CookiePath ) || ! is_dir( $this->CookiePath ) ) ) {
						throw new \Exception( __FUNCTION__ . ": Could not set up a proper cookiepath [To override this, use AllowTempAsCookiePath (not recommended)]", 1002 );
					}
				}
			}
		}

		/**
		 * Returns an ongoing cUrl session - Normally you may get this from initSession (and normally you don't need this at all)
		 *
		 * @return null
		 */
		public function getCurlSession() {
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
		 * @link https://phpdoc.tornevall.net/TorneLIBv5/source-class-TorneLIB.Tornevall_cURL.html sslStreamContextCorrection() is a part of TorneLIB 5.0, described here
		 */
		public function sslStreamContextCorrection() {
			if ( ! $this->openSslGuessed ) {
				$this->openssl_guess( true );
			}
			$caCert    = $this->getCertFile();
			$sslVerify = true;
			$sslSetup  = array();
			if ( isset( $this->sslVerify ) ) {
				$sslVerify = $this->sslVerify;
			}
			if ( ! empty( $caCert ) ) {
				$sslSetup = array(
					'cafile'            => $caCert,
					'verify_peer'       => $sslVerify,
					'verify_peer_name'  => $sslVerify,
					'verify_host'       => $sslVerify,
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
		 *
		 * @return array
		 * @link http://developer.tornevall.net/apigen/TorneLIB-5.0/class-TorneLIB.Tornevall_cURL.html sslGetOptionsStream() is a part of TorneLIB 5.0, described here
		 */
		public function sslGetOptionsStream( $optionsArray = array(), $selfContext = array() ) {
			$streamContextOptions = array();
			if ( empty( $this->CurlUserAgent ) ) {
				$this->setUserAgent();
			}
			$streamContextOptions['http'] = array(
				"user_agent" => $this->CurlUserAgent
			);
			$sslCorrection                = $this->sslStreamContextCorrection();
			if ( count( $sslCorrection ) ) {
				$streamContextOptions['ssl'] = $this->sslStreamContextCorrection();
			}
			foreach ( $selfContext as $contextKey => $contextValue ) {
				$streamContextOptions[ $contextKey ] = $contextValue;
			}
			$optionsArray['stream_context'] = stream_context_create( $streamContextOptions );
			$this->sslopt                   = $optionsArray;

			return $optionsArray;
		}


		/**
		 * SSL Cerificate Handler
		 *
		 * This method tries to handle SSL Certification locations where PHP can't handle that part itself. In some environments (normally customized), PHP sometimes have
		 * problems with finding certificates, in case for example where they are not placed in standard locations. When running the testing, we will also try to set up
		 * a correct location for the certificates, if any are found somewhere else.
		 *
		 * The default configuration of this method is to run tests, but only for PHP 5.6.0 or higher.
		 * If you know that you're running something older you may want to consider enabling testssldeprecated.
		 *
		 * At first, the variable $testssl is used to automatically try to find out if there is valid certificate bundle installed on the running system. In PHP 5.6.0 and higher
		 * this procedure is simplified with the help of openssl_get_cert_locations(), which gives us a default path to installed certificates. In this case we will first look there
		 * for the certificate bundle. If we do fail there, or if your system is running something older, the testing are running in guessing mode.
		 *
		 * The method is untested in Windows server environments when using OpenSSL.
		 *
		 * @param bool $forceTesting Force testing even if $testssl is disabled
		 *
		 * @link https://docs.tornevall.net/x/KwCy#TheNetworkandcURLclass(tornevall_network.php)-SSLCertificatesandverification
		 * @return bool
		 */
		private function openssl_guess( $forceTesting = false ) {
			/*
         * The certificate location here will be set up for the curl engine later on, during preparation of the connection.
         * NOTE: ini_set() does not work for setting up the cafile, this has to be done through php.ini, .htaccess, httpd.conf or .user.ini
         */
			if ( ini_get( 'open_basedir' ) == '' ) {
				if ( $this->testssl || $forceTesting ) {
					$this->openSslGuessed = true;
					if ( version_compare( PHP_VERSION, "5.6.0", ">=" ) && function_exists( "openssl_get_cert_locations" ) ) {
						$locations = openssl_get_cert_locations();
						if ( is_array( $locations ) ) {
							if ( isset( $locations['default_cert_file'] ) ) {
								// If it exists, we don't have to bother anymore
								if ( file_exists( $locations['default_cert_file'] ) ) {
									$this->hasCertFile        = true;
									$this->useCertFile        = $locations['default_cert_file'];
									$this->hasDefaultCertFile = true;
								}
								if ( file_exists( $locations['default_cert_dir'] ) ) {
									$this->hasCertDir = true;
								}
								// For unit testing
								if ( $this->TargetEnvironment == TORNELIB_CURL_ENVIRONMENT::ENVIRONMENT_TEST && isset( $this->_DEBUG_TCURL_UNSET_LOCAL_PEM_LOCATION ) && $this->_DEBUG_TCURL_UNSET_LOCAL_PEM_LOCATION === true ) {
									// Enforce wrong certificate location
									$this->hasCertFile = false;
									$this->useCertFile = null;
								}
							}
							// Check if the above control was successful - switch over to pemlocations if not.
							if ( ! $this->hasCertFile && is_array( $this->sslPemLocations ) && count( $this->sslPemLocations ) ) {
								// Loop through suggested locations and set the cafile in a variable if it's found.
								foreach ( $this->sslPemLocations as $pemLocation ) {
									if ( file_exists( $pemLocation ) ) {
										$this->useCertFile = $pemLocation;
										$this->hasCertFile = true;
									}
								}
							}
						}
						// On guess, disable verification if failed (if allowed)
						if ( ! $this->hasCertFile && $this->allowSslUnverified ) {
							$this->setSslVerify( false );
						}
					} else {
						// If we run on other PHP versions than 5.6.0 or higher, try to fall back into a known directory
						if ( $this->testssldeprecated ) {
							if ( ! $this->hasCertFile && is_array( $this->sslPemLocations ) && count( $this->sslPemLocations ) ) {
								// Loop through suggested locations and set the cafile in a variable if it's found.
								foreach ( $this->sslPemLocations as $pemLocation ) {
									if ( file_exists( $pemLocation ) ) {
										$this->useCertFile = $pemLocation;
										$this->hasCertFile = true;
									}
								}
								// For unit testing
								if ( $this->TargetEnvironment == TORNELIB_CURL_ENVIRONMENT::ENVIRONMENT_TEST && isset( $this->_DEBUG_TCURL_UNSET_LOCAL_PEM_LOCATION ) && $this->_DEBUG_TCURL_UNSET_LOCAL_PEM_LOCATION === true ) {
									// Enforce wrong certificate location
									$this->hasCertFile = false;
									$this->useCertFile = null;
								}
							}
							// Check if the above control was successful - switch over to pemlocations if not.
							if ( ! $this->hasCertFile && $this->allowSslUnverified ) {
								$this->setSslVerify( false );
							}
						}
					}
				}
			} else {
				// Assume there is a valid certificate if jailed by open_basedir
				$this->hasCertFile = true;

				return true;
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
		public function setCertAuto( $enabledFlag = true, $hostVerification = true ) {
			$this->testssl           = $enabledFlag;
			$this->testssldeprecated = $enabledFlag;
			$this->sslVerify         = $hostVerification;
		}

		/**
		 * Enable/disable SSL Peer/Host verification, if problems occur with certificates. If setCertAuto is enabled, this function will use best practice.
		 *
		 * @param bool $enabledFlag
		 *
		 * @throws \Exception
		 */
		public function setSslVerify( $enabledFlag = true ) {
			if ( $this->allowSslUnverified ) {
				$this->sslVerify = $enabledFlag;
			} else {
				throw new \Exception( "setSslUnverified(true) has not been set." );
			}
		}

		/**
		 * While doing SSL calls, and SSL certificate verifications is failing, enable the ability to skip SSL verifications.
		 *
		 * Normally, we want a valid SSL certificate while doing https-requests, but sometimes the verifications must be disabled. One reason of this is
		 * in cases, when crt-files are missing and PHP can not under very specific circumstances verify the peer. To allow this behaviour, the client
		 * MUST use this function.
		 *
		 * @since 5.0.0-20170210
		 *
		 * @param bool $enabledFlag
		 */
		public function setSslUnverified( $enabledFlag = false ) {
			$this->allowSslUnverified = $enabledFlag;
		}

		/**
		 * TestCerts - Test if your webclient has certificates available (make sure the $testssldeprecated are enabled if you want to test older PHP-versions - meaning older than 5.6.0)
		 *
		 * Note: This function also forces full ssl certificate checking.
		 *
		 * @return bool
		 */
		public function TestCerts() {
			return $this->openssl_guess( true );
		}

		/**
		 * Return the current certificate bundle file, chosen by autodetection
		 * @return string
		 */
		public function getCertFile() {
			return $this->useCertFile;
		}

		/**
		 * Returns true if the autodetected certificate bundle was one of the defaults (normally fetched from openssl_get_cert_locations()). Used for testings.
		 *
		 * @return bool
		 */
		public function hasCertDefault() {
			return $this->hasDefaultCertFile;
		}

		public function hasSsl() {
			return $this->sslCurlDriver;
		}

		/**
		 * Extract domain name from URL
		 *
		 * @param string $url
		 *
		 * @return array
		 */
		private function ExtractDomain( $url = '' ) {
			$urex   = explode( "/", preg_replace( "[^(.*?)//(.*?)/(.*)]", '$2', $url . "/" ) );
			$urtype = preg_replace( "[^(.*?)://(.*)]", '$1', $url . "/" );

			return array( $urex[0], $urtype );
		}

		/** @noinspection PhpUnusedPrivateMethodInspection */
		/**
		 * Translate ipv4 to reverse octets
		 *
		 * @param string $ip
		 * @param bool $getiptype
		 *
		 * @return string
		 */
		private function v4arpa( $ip = '', $getiptype = false ) {
			return $this->NETWORK->getArpaFromIpv4( $ip, $getiptype );
		}

		/** @noinspection PhpUnusedPrivateMethodInspection */
		/**
		 * ipv6-to-arpa-format-conversion
		 *
		 * @param string $ip
		 *
		 * @return string
		 */
		private function v6arpa( $ip = '::' ) {
			return $this->NETWORK->getArpaFromAddr( $ip );
		}

		/** @noinspection PhpUnusedPrivateMethodInspection */
		/**
		 * Translate ipv6-octets to ipv6-address
		 *
		 * @param string $arpaOctets
		 *
		 * @return string
		 */
		private function fromv6arpa( $arpaOctets = '' ) {
			return $this->NETWORK->getIpv6FromOctets( $arpaOctets );
		}

		/** @noinspection PhpUnusedPrivateMethodInspection */
		/**
		 * Get reverse octets from ip address
		 *
		 * @param string $ip
		 * @param bool $getiptype
		 *
		 * @return int|string
		 */
		private function toarpa( $ip = '', $getiptype = false ) {
			return $this->NETWORK->getArpaFromAddr( $ip, $getiptype );
		}


		/**
		 * Making sure the $IpAddr contains valid address list
		 *
		 * @throws \Exception
		 */
		private function handleIpList() {
			$this->CurlIp = null;
			$UseIp        = "";
			if ( is_array( $this->IpAddr ) ) {
				if ( count( $this->IpAddr ) == 1 ) {
					$UseIp = ( isset( $this->IpAddr[0] ) && ! empty( $this->IpAddr[0] ) ? $this->IpAddr[0] : null );
				} elseif ( count( $this->IpAddr ) > 1 ) {
					if ( ! $this->IpAddrRandom ) {
						/*
                     * If we have multiple ip addresses in the list, but the randomizer is not active, always use the first address in the list.
                     */
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
			/*
         * Bind interface to specific ip only if any are found
         */
			if ( $ipType == "0" ) {
				/*
             * If the ip type is 0 and it shows up there is something defined here, throw an exception.
             */
				if ( ! empty( $UseIp ) ) {
					throw new \Exception( __FUNCTION__ . ": " . $UseIp . " is not a valid ip-address", 1003 );
				}
			} else {
				$this->CurlIp = $UseIp;
				curl_setopt( $this->CurlSession, CURLOPT_INTERFACE, $UseIp );
				if ( $ipType == 6 ) {
					curl_setopt( $this->CurlSession, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V6 );
					$this->CurlIpType = 6;
				} else {
					curl_setopt( $this->CurlSession, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
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
		 * @param bool $isFullRequest
		 * @param null $contentType
		 *
		 * @return array|mixed|null
		 * @throws \Exception
		 */
		public function ParseContent( $content = '', $isFullRequest = false, $contentType = null ) {
			if ( $isFullRequest ) {
				$newContent  = $this->ParseResponse( $content );
				$content     = $newContent['body'];
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
				$trimmedContent = trim( $content ); // PHP 5.3: Can't use function return value in write context

				$overrideXmlSerializer = false;
				if ( $this->useXmlSerializer ) {
					$serializerPath = stream_resolve_include_path( 'XML/Unserializer.php' );
					if ( ! empty( $serializerPath ) ) {
						$overrideXmlSerializer = true;
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
					/*
                 * Returns empty class if the SimpleXMLElement is missing.
                 */
					if ( $overrideXmlSerializer ) {
						$xmlSerializer = new \XML_Unserializer();
						$xmlSerializer->unserialize( $content );

						return $xmlSerializer->getUnserializedData();
					}

					return new \stdClass();
				}
			}
			if ( $this->allowParseHtml && empty( $parsedContent ) ) {
				if ( class_exists( 'DOMDocument' ) ) {
					$DOM = new \DOMDocument();
					libxml_use_internal_errors( true );
					$DOM->loadHTML( $content );
					if ( isset( $DOM->childNodes->length ) && $DOM->childNodes->length > 0 ) {
						$elementsByTagName = $DOM->getElementsByTagName( '*' );
						$childNodeArray    = $this->getChildNodes( $elementsByTagName );
						$childTagArray     = $this->getChildNodes( $elementsByTagName, 'tagnames' );
						$childIdArray      = $this->getChildNodes( $elementsByTagName, 'id' );
						$parsedContent     = array(
							'ByNodes'      => array(),
							'ByClosestTag' => array(),
							'ById'         => array()
						);
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
					throw new \Exception( "Can not parse DOMDocuments without the DOMDocuments class" );
				}
			}

			return $parsedContent;
		}

		/**
		 * Experimental: Convert DOMDocument to an array
		 *
		 * @param array $childNode
		 * @param string $getAs
		 *
		 * @return array
		 */
		private function getChildNodes( $childNode = array(), $getAs = '' ) {
			$childNodeArray      = array();
			$childAttributeArray = array();
			$childIdArray        = array();
			$returnContext       = "";
			if ( is_object( $childNode ) ) {
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
		 * Get head and body from a request parsed
		 *
		 * @param string $content
		 *
		 * @return array
		 */
		public function getHeader( $content = "" ) {
			return $this->ParseResponse( $content . "\r\n\r\n" );
		}

		/**
		 * Extract a parsed response from a webrequest
		 *
		 * @param null $ResponseContent
		 *
		 * @return null
		 */
		public function getParsedResponse( $ResponseContent = null ) {
			if ( is_null( $ResponseContent ) && ! empty( $this->TemporaryResponse ) ) {
				return $this->TemporaryResponse['parsed'];
			} else if ( isset( $ResponseContent['parsed'] ) ) {
				return $ResponseContent['parsed'];
			}

			return null;
		}

		/**
		 * @param null $ResponseContent
		 *
		 * @return int
		 */
		public function getResponseCode( $ResponseContent = null ) {
			if ( is_null( $ResponseContent ) && ! empty( $this->TemporaryResponse ) ) {
				return (int) $this->TemporaryResponse['code'];
			} else if ( isset( $ResponseContent['code'] ) ) {
				return (int) $ResponseContent['code'];
			}

			return 0;
		}

		/**
		 * @param null $ResponseContent
		 *
		 * @return null
		 */
		public function getResponseBody( $ResponseContent = null ) {
			if ( is_null( $ResponseContent ) && ! empty( $this->TemporaryResponse ) ) {
				return $this->TemporaryResponse['body'];
			} else if ( isset( $ResponseContent['body'] ) ) {
				return $ResponseContent['body'];
			}

			return null;
		}

		/**
		 * Extract a specific key from a parsed webrequest
		 *
		 * @param $KeyName
		 * @param null $ResponseContent
		 *
		 * @return mixed|null
		 * @throws \Exception
		 */
		public function getParsedValue( $KeyName = null, $ResponseContent = null ) {
			if ( is_string( $KeyName ) ) {
				$ParsedValue = $this->getParsedResponse( $ResponseContent );
				if ( is_array( $ParsedValue ) && isset( $ParsedValue[ $KeyName ] ) ) {
					return $ParsedValue[ $KeyName ];
				}
				if ( is_object( $ParsedValue ) && isset( $ParsedValue->$KeyName ) ) {
					return $ParsedValue->$KeyName;
				}
			} else {
				if ( is_null( $ResponseContent ) && ! empty( $this->TemporaryResponse ) ) {
					$ResponseContent = $this->TemporaryResponse;
				}
				$Parsed       = $this->getParsedResponse( $ResponseContent );
				$hasRecursion = false;
				if ( is_array( $KeyName ) ) {
					$TheKeys  = array_reverse( $KeyName );
					$Eternity = 0;
					while ( count( $TheKeys ) || $Eternity ++ <= 20 ) {
						$hasRecursion = false;
						$CurrentKey   = array_pop( $TheKeys );
						if ( is_array( $Parsed ) ) {
							if ( isset( $Parsed[ $CurrentKey ] ) ) {
								$hasRecursion = true;
							}
						} else if ( is_object( $Parsed ) ) {
							if ( isset( $Parsed->$CurrentKey ) ) {
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
							$Parsed = $this->getParsedValue( $CurrentKey, array( 'parsed' => $Parsed ) );
							// Break if this was the last one
							if ( ! count( $TheKeys ) ) {
								break;
							}
						}
					}
					if ( $hasRecursion ) {
						return $Parsed;
					} else {
						throw new \Exception( "Requested key was not found in parsed response" );
					}
				}
			}

			return null;
		}

		public function getRedirectedUrls() {
			return $this->redirectedUrls;
		}

		/**
		 * Parse response, in case of there is any followed traces from the curl engine, so we'll always land on the right ending stream
		 *
		 * @param string $content
		 *
		 * @return array|string|TORNELIB_CURLOBJECT
		 */
		private function ParseResponse( $content = '' ) {
			if ( ! is_string( $content ) ) {
				return $content;
			}
			list( $header, $body ) = explode( "\r\n\r\n", $content, 2 );
			$rows     = explode( "\n", $header );
			$response = explode( " ", $rows[0] );
			$code     = isset( $response[1] ) ? $response[1] : - 1;
			// If the first row of the body contains a HTTP/-string, we'll try to reparse it
			if ( preg_match( "/^HTTP\//", $body ) ) {
				$newBody = $this->ParseResponse( $body );
				$header  = $newBody['header'];
				$body    = $newBody['body'];
			}

			// If response code starts with 3xx, this is probably a redirect
			if ( preg_match( "/^3/", $code ) ) {
				$this->redirectedUrls[] = $this->CurlURL;
				$redirectArray[] = array(
					'header' => $header,
					'body'   => $body,
					'code'   => $code
				);
				//$redirectContent = $this->ParseContent($body, false);
			}
			$headerInfo     = $this->GetHeaderKeyArray( $rows );
			$returnResponse = array(
				'header' => array( 'info' => $headerInfo, 'full' => $header ),
				'body'   => $body,
				'code'   => $code
			);
			if ( $this->CurlAutoParse ) {
				$contentType              = isset( $headerInfo['Content-Type'] ) ? $headerInfo['Content-Type'] : null;
				$parsedContent            = $this->ParseContent( $returnResponse['body'], false, $contentType );
				$returnResponse['parsed'] = ( ! empty( $parsedContent ) ? $parsedContent : null );
			}
			$returnResponse['URL'] = $this->CurlURL;
			$returnResponse['ip']  = isset( $this->CurlIp ) ? $this->CurlIp : null;  // Will only be filled if there is custom address set.

			if ( $this->ResponseType == TORNELIB_CURL_RESPONSETYPE::RESPONSETYPE_OBJECT ) {
				// This is probably not necessary and will not be the default setup after all.
				$returnResponseObject         = new TORNELIB_CURLOBJECT();
				$returnResponseObject->header = $returnResponse['header'];
				$returnResponseObject->body   = $returnResponse['body'];
				$returnResponseObject->code   = $returnResponse['code'];
				$returnResponseObject->parsed = $returnResponse['parsed'];
				$returnResponseObject->url    = $returnResponse['URL'];
				$returnResponseObject->ip     = $returnResponse['ip'];

				return $returnResponseObject;
			}
			$this->TemporaryResponse = $returnResponse;

			return $returnResponse;
		}

		/**
		 * Create an array of a header, with keys and values
		 *
		 * @param $HeaderRows
		 *
		 * @return array
		 */
		private function GetHeaderKeyArray( $HeaderRows ) {
			$headerInfo = array();
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

			return $headerInfo;
		}

		/**
		 * Return number of tries resolver has been working
		 *
		 * @return int
		 */
		public function getRetries() {
			return $this->CurlResolveRetry;
		}

		/**
		 * Defines if this library should be able to store the curl_getinfo() for each curl_exec that generates an exception
		 *
		 * @param bool $Activate
		 */
		public function setStoreSessionExceptions( $Activate = false ) {
			$this->canStoreSessionException = $Activate;
		}

		/**
		 * Call cUrl with a POST
		 *
		 * @param string $url
		 * @param array $postData
		 * @param int $postAs
		 *
		 * @return array
		 */
		public function doPost( $url = '', $postData = array(), $postAs = CURL_POST_AS::POST_AS_NORMAL ) {
			$content       = $this->handleUrlCall( $url, $postData, CURL_METHODS::METHOD_POST, $postAs );
			$ResponseArray = $this->ParseResponse( $content );

			return $ResponseArray;
		}

		/**
		 * @param string $url
		 * @param array $postData
		 * @param int $postAs
		 *
		 * @return array
		 */
		public function doPut( $url = '', $postData = array(), $postAs = CURL_POST_AS::POST_AS_NORMAL ) {
			$content       = $this->handleUrlCall( $url, $postData, CURL_METHODS::METHOD_PUT, $postAs );
			$ResponseArray = $this->ParseResponse( $content );

			return $ResponseArray;
		}

		/**
		 * @param string $url
		 * @param array $postData
		 * @param int $postAs
		 *
		 * @return array
		 */
		public function doDelete( $url = '', $postData = array(), $postAs = CURL_POST_AS::POST_AS_NORMAL ) {
			$content       = $this->handleUrlCall( $url, $postData, CURL_METHODS::METHOD_DELETE, $postAs );
			$ResponseArray = $this->ParseResponse( $content );

			return $ResponseArray;
		}

		/**
		 * Call cUrl with a GET
		 *
		 * @param string $url
		 * @param int $postAs
		 *
		 * @return array
		 */
		public function doGet( $url = '', $postAs = CURL_POST_AS::POST_AS_NORMAL ) {
			$content       = $this->handleUrlCall( $url, array(), CURL_METHODS::METHOD_GET, $postAs );
			$ResponseArray = $this->ParseResponse( $content );

			return $ResponseArray;
		}

		/**
		 * Enable authentication with cURL.
		 *
		 * @param null $Username
		 * @param null $Password
		 * @param int $AuthType Falls back on CURLAUTH_ANY if none are given. CURL_AUTH_TYPES are minimalistic since it follows the standards of CURLAUTH_*
		 */
		public function setAuthentication( $Username = null, $Password = null, $AuthType = CURL_AUTH_TYPES::AUTHTYPE_BASIC ) {
			$this->AuthData['Username'] = $Username;
			$this->AuthData['Password'] = $Password;
			$this->AuthData['Type']     = $AuthType;
		}

		public function setProxy( $ProxyAddr, $ProxyType = CURLPROXY_HTTP ) {
			$this->CurlProxy     = $ProxyAddr;
			$this->CurlProxyType = $ProxyType;
		}

		/**
		 * Fix problematic header data by converting them to proper outputs.
		 *
		 * @param array $headerList
		 */
		private function fixHttpHeaders( $headerList = array() ) {
			if ( is_array( $headerList ) && count( $headerList ) ) {
				foreach ( $headerList as $headerKey => $headerValue ) {
					$testHead = explode( ":", $headerValue, 2 );
					if ( isset( $testHead[1] ) ) {
						$this->CurlHeaders[] = $headerValue;
					} else {
						if ( ! is_numeric( $headerKey ) ) {
							$this->CurlHeaders[] = $headerKey . ": " . $headerValue;
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
		 */
		public function setCurlHeader( $key = '', $value = '' ) {
			if ( ! empty( $key ) ) {
				$this->CurlHeadersUserDefined[ $key ] = $value;
			}
		}

		/**
		 * cURL data handler, sets up cURL in what it believes is the correct set for you.
		 *
		 * @param string $url
		 * @param array $postData
		 * @param int $CurlMethod
		 * @param int $postAs
		 *
		 * @return mixed
		 * @throws \Exception
		 */
		private function handleUrlCall( $url = '', $postData = array(), $CurlMethod = CURL_METHODS::METHOD_GET, $postAs = CURL_POST_AS::POST_AS_NORMAL ) {
			if ( ! empty( $url ) ) {
				$this->CurlURL = $url;
			}

			$this->init();
			$this->CurlHeaders = array();

			// Find out if CURLOPT_FOLLOWLOCATION can be set by user/developer or not.
			//
			// Make sure the safety control occurs even when the enforcing parameter is false.
			// This should prevent problems when $this->>followLocationSet is set to anything else than false
			// and security settings are higher for PHP. From v6.0.2, the in this logic has been simplified
			// to only set any flags if the security levels of PHP allows it, and only if the follow flag is enabled.
			//
			// Refers to http://php.net/manual/en/ini.sect.safe-mode.php
			if ( ini_get( 'open_basedir' ) == '' && ! filter_var( ini_get( 'safe_mode' ), FILTER_VALIDATE_BOOLEAN ) ) {
				// To disable the default behaviour of this function, use setEnforceFollowLocation([bool]).
				if ( $this->followLocationSet ) {
					curl_setopt( $this->CurlSession, CURLOPT_FOLLOWLOCATION, $this->followLocationSet );
					$this->curlopt[ CURLOPT_FOLLOWLOCATION ] = $this->followLocationSet;
				}
			}

			// Prepare SOAPclient if requested
			if ( preg_match( "/\?wsdl$|\&wsdl$/i", $this->CurlURL ) || $postAs == CURL_POST_AS::POST_AS_SOAP ) {
				$Soap = new Tornevall_SimpleSoap( $this->CurlURL, $this->curlopt );
				$Soap->setCustomUserAgent( $this->CustomUserAgent );
				$Soap->setThrowableState( $this->canThrow );
				$Soap->setSoapAuthentication( $this->AuthData );
				$Soap->SoapTryOnce = $this->SoapTryOnce;
				return $Soap->getSoap();
			}

			// Picking up externally select outgoing ip if any
			$this->handleIpList();
			curl_setopt( $this->CurlSession, CURLOPT_URL, $this->CurlURL );

			if ( is_array( $postData ) || is_object( $postData ) ) {
				$postDataContainer = http_build_query( $postData );
			} else {
				$postDataContainer = $postData;
			}

			$domainArray = $this->ExtractDomain( $this->CurlURL );
			$domainName  = null;
			$domainHash  = null;
			if ( isset( $domainArray[0] ) ) {
				$domainName = $domainArray[0];
				$domainHash = md5( $domainName );
			}

			/**** CONDITIONAL SETUP ****/

			// Lazysession: Sets post data if any found and sends it even if the curl-method is GET or any other than POST
			if ( ! empty( $postDataContainer ) ) {
				curl_setopt( $this->CurlSession, CURLOPT_POSTFIELDS, $postDataContainer );
			}
			if ( $CurlMethod == CURL_METHODS::METHOD_POST || $CurlMethod == CURL_METHODS::METHOD_PUT || $CurlMethod == CURL_METHODS::METHOD_DELETE ) {
				if ( $CurlMethod == CURL_METHODS::METHOD_PUT ) {
					curl_setopt( $this->CurlSession, CURLOPT_CUSTOMREQUEST, "PUT" );
				} else if ( $CurlMethod == CURL_METHODS::METHOD_DELETE ) {
					curl_setopt( $this->CurlSession, CURLOPT_CUSTOMREQUEST, "DELETE" );
				} else {
					curl_setopt( $this->CurlSession, CURLOPT_POST, true );
				}

				if ( $postAs == CURL_POST_AS::POST_AS_JSON ) {
					/*
                 * Using $jsonRealData to validate the string
                 */
					$jsonRealData = null;
					if ( ! is_string( $postData ) ) {
						$jsonRealData = json_encode( $postData );
					} else {
						$testJsonData = json_decode( $postData );
						if ( is_object( $testJsonData ) || is_array( $testJsonData ) ) {
							$jsonRealData = $postData;
						}
					}
					$this->CurlHeadersSystem['Content-Type']   = "application/json";
					$this->CurlHeadersSystem['Content-Length'] = strlen( $jsonRealData );
					curl_setopt( $this->CurlSession, CURLOPT_POSTFIELDS, $jsonRealData );
				}
			}

			// Self set timeouts, making sure the timeout set in the public is an integer over 0. Otherwise this falls back to the curldefauls.
			if ( isset( $this->CurlTimeout ) && $this->CurlTimeout > 0 ) {
				curl_setopt( $this->CurlSession, CURLOPT_CONNECTTIMEOUT, ceil( $this->CurlTimeout / 2 ) );
				curl_setopt( $this->CurlSession, CURLOPT_TIMEOUT, $this->CurlTimeout );
			}
			if ( isset( $this->CurlResolve ) && $this->CurlResolve !== CURL_RESOLVER::RESOLVER_DEFAULT ) {
				if ( $this->CurlResolve == CURL_RESOLVER::RESOLVER_IPV4 ) {
					curl_setopt( $this->CurlSession, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
				}
				if ( $this->CurlResolve == CURL_RESOLVER::RESOLVER_IPV6 ) {
					curl_setopt( $this->CurlSession, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V6 );
				}
			}

			// If certificates missing
			if ( ! $this->TestCerts() ) {
				// And we're allowed to run without them
				if ( ! $this->sslVerify && $this->allowSslUnverified ) {
					// Then disable the checking here
					curl_setopt( $this->CurlSession, CURLOPT_SSL_VERIFYHOST, 0 );
					curl_setopt( $this->CurlSession, CURLOPT_SSL_VERIFYPEER, 0 );
				} else {
					// From libcurl 7.28.1 CURLOPT_SSL_VERIFYHOST is deprecated. However, using the value 1 can be used
					// as of PHP 5.4.11, where the deprecation notices was added. The deprecation has started before libcurl
					// 7.28.1 (this was discovered on a server that was running PHP 5.5 and libcurl-7.22). In full debug
					// even libcurl-7.22 was generating this message, so from PHP 5.4.11 we are now enforcing the value 2
					// for CURLOPT_SSL_VERIFYHOST instead. The reason of why we are using the value 1 before this version
					// is actually a lazy thing, as we don't want to break anything that might be unsupported before this version.
					if ( version_compare( PHP_VERSION, '5.4.11', ">=" ) ) {
						curl_setopt( $this->CurlSession, CURLOPT_SSL_VERIFYHOST, 2 );
					} else {
						curl_setopt( $this->CurlSession, CURLOPT_SSL_VERIFYHOST, 1 );
					}
					curl_setopt( $this->CurlSession, CURLOPT_SSL_VERIFYPEER, 1 );
				}
			} else {
				// Silently configure for https-connections, if exists
				if ( $this->useCertFile != "" && file_exists( $this->useCertFile ) ) {
					try {
						curl_setopt( $this->CurlSession, CURLOPT_CAINFO, $this->useCertFile );
						curl_setopt( $this->CurlSession, CURLOPT_CAPATH, dirname( $this->useCertFile ) );
					} catch ( \Exception $e ) {
					}
				}
			}
			curl_setopt( $this->CurlSession, CURLOPT_VERBOSE, false );
			if ( isset( $this->CurlProxy ) && ! empty( $this->CurlProxy ) ) {
				// Run from proxy
				curl_setopt( $this->CurlSession, CURLOPT_PROXY, $this->CurlProxy );
				if ( isset( $this->CurlProxyType ) && ! empty( $this->CurlProxyType ) ) {
					curl_setopt( $this->CurlSession, CURLOPT_PROXYTYPE, $this->CurlProxyType );
				}
				unset( $this->CurlIp );
			}
			if ( isset( $this->CurlTunnel ) && ! empty( $this->CurlTunnel ) ) {
				// Run in tunneling mode
				curl_setopt( $this->CurlSession, CURLOPT_HTTPPROXYTUNNEL, true );
				unset( $this->CurlIp );
			}
			// Another HTTP_REFERER
			if ( isset( $this->CurlReferer ) && ! empty( $this->CurlReferer ) ) {
				curl_setopt( $this->CurlSession, CURLOPT_REFERER, $this->CurlReferer );
			}

			$this->fixHttpHeaders( $this->CurlHeadersUserDefined );
			$this->fixHttpHeaders( $this->CurlHeadersSystem );

			if ( isset( $this->CurlHeaders ) && is_array( $this->CurlHeaders ) && count( $this->CurlHeaders ) ) {
				curl_setopt( $this->CurlSession, CURLOPT_HTTPHEADER, $this->CurlHeaders );
			}
			if ( isset( $this->CurlUserAgent ) && ! empty( $this->CurlUserAgent ) ) {
				curl_setopt( $this->CurlSession, CURLOPT_USERAGENT, $this->CurlUserAgent );
			}
			if ( isset( $this->CurlEncoding ) && ! empty( $this->CurlEncoding ) ) {
				curl_setopt( $this->CurlSession, CURLOPT_ENCODING, $this->CurlEncoding );
			}
			if ( file_exists( $this->CookiePath ) && $this->CurlUseCookies && ! empty( $this->CurlURL ) ) {
				@file_put_contents( $this->CookiePath . "/tmpcookie", "test" );
				if ( ! file_exists( $this->CookiePath . "/tmpcookie" ) ) {
					$this->SaveCookies = true;
					$this->CookieFile  = $domainHash;
					curl_setopt( $this->CurlSession, CURLOPT_COOKIEFILE, $this->CookiePath . "/" . $this->CookieFile );
					curl_setopt( $this->CurlSession, CURLOPT_COOKIEJAR, $this->CookiePath . "/" . $this->CookieFile );
					curl_setopt( $this->CurlSession, CURLOPT_COOKIE, 1 );

				} else {
					if ( file_exists( $this->CookiePath . "/tmpcookie" ) ) {
						unlink( $this->CookiePath . "/tmpcookie" );
					}
					$this->SaveCookies = false;
				}
			} else {
				$this->SaveCookies = false;
			}

			if ( ! empty( $this->AuthData['Username'] ) && $this->AuthData['Type'] != CURL_AUTH_TYPES::AUTHTYPE_NONE ) {
				$useAuth = CURLAUTH_ANY;
				if ( CURL_AUTH_TYPES::AUTHTYPE_BASIC ) {
					$useAuth = CURLAUTH_BASIC;
				}
				curl_setopt( $this->CurlSession, CURLOPT_HTTPAUTH, $useAuth );
				curl_setopt( $this->CurlSession, CURLOPT_USERPWD, $this->AuthData['Username'] . ':' . $this->AuthData['Password'] );
			}

			/**** UNCONDITIONAL SETUP ****/
			curl_setopt( $this->CurlSession, CURLOPT_HEADER, true );
			curl_setopt( $this->CurlSession, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $this->CurlSession, CURLOPT_AUTOREFERER, true );
			curl_setopt( $this->CurlSession, CURLINFO_HEADER_OUT, true );

			$returnContent = curl_exec( $this->CurlSession );

			if ( curl_errno( $this->CurlSession ) ) {
				if ( $this->canStoreSessionException ) {
					$this->sessionsExceptions[] = array(
						'Content'     => $returnContent,
						'SessionInfo' => curl_getinfo( $this->CurlSession )
					);
				}
				$errorCode = curl_errno( $this->CurlSession );
				if ( $this->CurlResolveForced && $this->CurlResolveRetry >= 2 ) {
					throw new \Exception( __FUNCTION__ . ": Could not fetch url after internal retries", 1004 );
				}
				if ( $errorCode == CURLE_COULDNT_RESOLVE_HOST || $errorCode === 45 ) {
					$this->CurlResolveRetry ++;
					unset( $this->CurlIp );
					$this->CurlResolveForced = true;
					if ( $this->CurlIpType == 6 ) {
						$this->CurlResolve = CURL_RESOLVER::RESOLVER_IPV4;
					}
					if ( $this->CurlIpType == 4 ) {
						$this->CurlResolve = CURL_RESOLVER::RESOLVER_IPV6;
					}

					return $this->handleUrlCall( $this->CurlURL, $postData, $CurlMethod );
				}
				throw new \Exception( "PHPException at " . __FUNCTION__ . ": " . curl_error( $this->CurlSession ), curl_errno( $this->CurlSession ) );
			}

			return $returnContent;
		}
	}

	/** @noinspection PhpUndefinedClassInspection */

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
			'trace'      => true,
			'cache_wsdl' => 0       // Replacing WSDL_CACHE_NONE (WSDL_CACHE_BOTH = 3)
		);
		private $soapUrl;
		private $AuthData;
		private $soapRequest;
		private $soapRequestHeaders;
		private $soapResponse;
		private $soapResponseHeaders;
		private $libResponse;
		private $canThrowSoapFaults = true;
		private $CustomUserAgent;

		public $SoapFaultString = null;
		public $SoapFaultCode = 0;
		public $SoapTryOnce = true;

		/**
		 * Tornevall_SimpleSoap constructor.
		 *
		 * @param string $Url
		 * @param array $SoapOptions
		 */
		function __construct( $Url, $SoapOptions = array() ) {
			parent::__construct();
			$this->soapUrl = $Url;
			$this->sslGetOptionsStream();
			if ( ! count( $SoapOptions ) ) {
				$this->soapOptions = $SoapOptions;
			}
			foreach ( $this->addSoapOptions as $soapKey => $soapValue ) {
				if ( ! isset( $this->soapOptions[ $soapKey ] ) ) {
					$this->soapOptions[ $soapKey ] = $soapValue;
				}
			}
		}

		/**
		 * Prepare authentication for SOAP calls
		 *
		 * @param array $AuthData
		 */
		public function setSoapAuthentication( $AuthData = array() ) {
			$this->AuthData = $AuthData;
			if ( ! empty( $this->AuthData['Username'] ) && ! empty( $this->AuthData['Password'] ) && ! isset( $this->soapOptions['login'] ) && ! isset( $this->soapOptions['password'] ) ) {
				$this->soapOptions['login']    = $this->AuthData['Username'];
				$this->soapOptions['password'] = $this->AuthData['Password'];
			}
		}

		public function setCustomUserAgent( $userAgentString ) {
			$this->CustomUserAgent = preg_replace( "/\s+$/", '', $userAgentString );
			$this->setUserAgent( $userAgentString . " +TorneLIB-SimpleSoap" );
			$this->sslGetOptionsStream();
		}

		/**
		 * Set up this class so that it can throw exceptions
		 *
		 * @param bool $throwable Setting this to false, we will suppress some errors
		 */
		public function setThrowableState( $throwable = true ) {
			$this->canThrowSoapFaults = $throwable;
		}

		/**
		 * Generate the SOAP
		 *
		 * @return $this
		 * @throws \Exception
		 */
		public function getSoap() {
			$this->soapClient = null;
			if ( gettype( $this->sslopt['stream_context'] ) == "resource" ) {
				$this->soapOptions['stream_context'] = $this->sslopt['stream_context'];
			}
			if ( $this->SoapTryOnce ) {
				$this->soapClient = new \SoapClient( $this->soapUrl, $this->soapOptions );
			} else {
				try {
					/*
                 * FailoverMethod is active per default, trying to parry SOAP-sites that requires ?wsdl in the urls
                 */
					$this->soapClient = @new \SoapClient( $this->soapUrl, $this->soapOptions );
				} catch ( \Exception $soapException ) {
					if ( isset( $soapException->faultcode ) && $soapException->faultcode == "WSDL" ) {
						/*
                     * If an exception has been invoked, check if the url contains a ?wsdl or &wsdl - if not, it may be the problem.
                     * In that case, retry the call and throw an exception if we fail twice.
                     */
						if ( ! preg_match( "/\?wsdl|\&wsdl/i", $this->soapUrl ) ) {
							/*
                         * Try to determine how the URL is built before trying this.
                         */
							if ( preg_match( "/\?/", $this->soapUrl ) ) {
								$this->soapUrl .= "&wsdl";
							} else {
								$this->soapUrl .= "?wsdl";
							}
							try {
								$this->soapClient = @new \SoapClient( $this->soapUrl, $this->soapOptions );
							} catch ( \Exception $soapException ) {
								throw new \Exception( $soapException->getMessage(), $soapException->getCode() );
							}
						}
					}
				}
			}

			return $this;
		}

		function __call( $name, $arguments ) {
			$returnResponse = array(
				'header' => array( 'info' => null, 'full' => null ),
				'body'   => null,
				'code'   => null
			);

			$SoapClientResponse = null;
			try {
				if ( isset( $arguments[0] ) ) {
					$SoapClientResponse = $this->soapClient->$name( $arguments[0] );
				} else {
					$SoapClientResponse = $this->soapClient->$name();
				}
			} catch ( \SoapFault $e ) {
				/** @noinspection PhpUndefinedMethodInspection */
				$this->soapRequest = $this->soapClient->__getLastRequest();
				/** @noinspection PhpUndefinedMethodInspection */
				$this->soapRequestHeaders = $this->soapClient->__getLastRequestHeaders();
				/** @noinspection PhpUndefinedMethodInspection */
				$this->soapResponse = $this->soapClient->__getLastResponse();
				/** @noinspection PhpUndefinedMethodInspection */
				$this->soapResponseHeaders = $this->soapClient->__getLastResponseHeaders();
				$parsedHeader              = $this->getHeader( $this->soapResponseHeaders );
				$returnResponse['header']  = $parsedHeader['header'];
				$returnResponse['code']    = isset( $parsedHeader['code'] ) ? $parsedHeader['code'] : 0;
				$returnResponse['body']    = $this->soapResponse;
				/*
             * Collect the response received internally, before throwing
             */
				$this->libResponse = $returnResponse;
				if ( $this->canThrowSoapFaults ) {
					throw new \Exception( $e->getMessage(), $e->getCode() );
				}
				$this->SoapFaultString = $e->getMessage();
				$this->SoapFaultCode   = $e->getCode();
			}

			/** @noinspection PhpUndefinedMethodInspection */
			$this->soapRequest = $this->soapClient->__getLastRequest();
			/** @noinspection PhpUndefinedMethodInspection */
			$this->soapRequestHeaders = $this->soapClient->__getLastRequestHeaders();
			/** @noinspection PhpUndefinedMethodInspection */
			$this->soapResponse = $this->soapClient->__getLastResponse();
			/** @noinspection PhpUndefinedMethodInspection */
			$this->soapResponseHeaders = $this->soapClient->__getLastResponseHeaders();
			$parsedHeader              = $this->getHeader( $this->soapResponseHeaders );
			$returnResponse['header']  = $parsedHeader['header'];
			$returnResponse['code']    = isset( $parsedHeader['code'] ) ? $parsedHeader['code'] : 0;
			$returnResponse['body']    = $this->soapResponse;
			$returnResponse['parsed']  = $SoapClientResponse;
			if ( isset( $SoapClientResponse->return ) ) {
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
		public function getLibResponse() {
			return $this->libResponse;
		}
	}

	/** @noinspection PhpUndefinedClassInspection */

	/**
	 * Class CURL_METHODS List of methods available in this library
	 *
	 * @package TorneLIB
	 */
	abstract class CURL_METHODS {
		const METHOD_GET = 0;
		const METHOD_POST = 1;
		const METHOD_PUT = 2;
		const METHOD_DELETE = 3;
	}

	/** @noinspection PhpUndefinedClassInspection */

	/**
	 * Class CURL_RESOLVER Resolver methods that is available when trying to connect
	 *
	 * @package TorneLIB
	 */
	abstract class CURL_RESOLVER {
		const RESOLVER_DEFAULT = 0;
		const RESOLVER_IPV4 = 1;
		const RESOLVER_IPV6 = 2;
	}

	/** @noinspection PhpUndefinedClassInspection */

	/**
	 * Class CURL_POST_AS Prepared formatting for POST-content in this library (Also available from for example PUT)
	 *
	 * @package TorneLIB
	 */
	abstract class CURL_POST_AS {
		const POST_AS_NORMAL = 0;
		const POST_AS_JSON = 1;
		const POST_AS_SOAP = 2;
	}

	/** @noinspection PhpUndefinedClassInspection */

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

	/** @noinspection PhpUndefinedClassInspection */

	/**
	 * Class TORNELIB_CURL_ENVIRONMENT
	 *
	 * The unit testing helper. To not collide with production environments, somet settings should only be available while unit testing.
	 *
	 * @package TorneLIB
	 */
	abstract class TORNELIB_CURL_ENVIRONMENT {
		const ENVIRONMENT_PRODUCTION = 0;
		const ENVIRONMENT_TEST = 1;
	}

	/** @noinspection PhpUndefinedClassInspection */

	/**
	 * Class TORNELIB_CURL_RESPONSETYPE
	 * @package TorneLIB
	 */
	abstract class TORNELIB_CURL_RESPONSETYPE {
		const RESPONSETYPE_ARRAY = 0;
		const RESPONSETYPE_OBJECT = 1;
	}

	/** @noinspection PhpUndefinedClassInspection */

	/**
	 * Class TORNELIB_CURLOBJECT
	 * @package TorneLIB
	 */
	class TORNELIB_CURLOBJECT {
		public $header;
		public $body;
		public $code;
		public $parsed;
		public $url;
		public $ip;
	}
}
