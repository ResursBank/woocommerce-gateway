<?php

/**
 * Copyright 2017 Tomas Tornevall & Tornevall Networks
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
 * Each class in this library has its own version numbering to keep track of where the changes are. However, there is a major version too.
 * @package TorneLIB
 * @version 6.0.15
 */

namespace Resursbank\RBEcomPHP;

if ( ! defined( 'TORNELIB_NETCURL_RELEASE' ) ) {
	define( 'TORNELIB_NETCURL_RELEASE', '6.0.14' );
}

if ( file_exists( '../vendor/autoload.php' ) ) {
	require_once( '../vendor/autoload.php' );
}

if ( ! class_exists( 'TorneLIB_Network' ) && ! class_exists( 'TorneLIB\TorneLIB_Network' ) ) {
	/**
	 * Library for handling network related things (currently not sockets). A conversion of a legacy PHP library called "TorneEngine" and family.
	 *
	 * Class TorneLIB_Network
	 * @version 6.0.5
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
		private $alwaysResolveHostvalidation = false;

		/** @var TorneLIB_NetBits BitMask handler with 8 bits as default */
		public $BIT;

		/**
		 * TorneLIB_Network constructor.
		 */
		function __construct() {
			// Initiate and get client headers.
			$this->renderProxyHeaders();
			$this->BIT = new TorneLIB_NetBits();
		}

		/**
		 * Get an exception code from internal abstract
		 *
		 * If the exception constant name does not exist, or the abstract class is not included in this package, a generic unknown error, based on internal server error, will be returned (500)
		 *
		 * @param string $exceptionConstantName Constant name (make sure it exists before use)
		 *
		 * @return int
		 */
		public function getExceptionCode( $exceptionConstantName = 'NETCURL_NO_ERROR' ) {
			// Make sure that nothing goes wrong here.
			try {
				if ( empty( $exceptionConstantName ) ) {
					$exceptionConstantName = 'NETCURL_NO_ERROR';
				}
				if ( ! class_exists( 'TorneLIB\TORNELIB_NETCURL_EXCEPTIONS' ) ) {
					if ( $exceptionConstantName == 'NETCURL_NO_ERROR' ) {
						return 0;
					} else {
						return 500;
					}
				} else {
					$exceptionCode = @constant( 'TorneLIB\TORNELIB_NETCURL_EXCEPTIONS::' . $exceptionConstantName );
					if ( empty( $exceptionCode ) || ! is_numeric( $exceptionCode ) ) {
						return 500;
					} else {
						return (int) $exceptionCode;
					}
				}
			} catch ( \Exception $e ) {
				// If anything goes wrong in this internal handler, return with 501 instead
				return 501;
			}
		}

		/**
		 * Try to fetch git tags from git URLS
		 *
		 * @param string $gitUrl
		 * @param bool $cleanNonNumerics Normally you do not want to strip anything. This boolean however, decides if we will include non numerical version data in the returned array
		 * @param bool $sanitizeNumerics If we decide to not include non numeric values from the version tag array (by $cleanNonNumerics), the tags will be sanitized in a preg_replace filter that will the keep numerics in the content only (with $cleanNonNumerics set to false, this boolen will have no effect)
		 *
		 * @return array
		 * @throws \Exception
		 * @since 6.0.4
		 */
		public function getGitTagsByUrl( $gitUrl = '', $cleanNonNumerics = false, $sanitizeNumerics = false ) {
			$fetchFail = true;
			$tagArray  = array();
			$gitUrl    .= "/info/refs?service=git-upload-pack";
			// Clean up all user auth data in URL if exists
			$gitUrl = preg_replace( "/\/\/(.*?)@/", '//', $gitUrl );
			/** @var $CURL Tornevall_cURL */
			$CURL = new Tornevall_cURL();

			$code             = 0;
			$exceptionMessage = "";
			try {
				$gitGet = $CURL->doGet( $gitUrl );
				$code   = intval( $CURL->getResponseCode() );
				if ( $code >= 200 && $code <= 299 && ! empty( $gitGet['body'] ) ) {
					$fetchFail = false;
					preg_match_all( "/refs\/tags\/(.*?)\n/s", $gitGet['body'], $tagMatches );
					if ( isset( $tagMatches[1] ) && is_array( $tagMatches[1] ) ) {
						$tagList = $tagMatches[1];
						foreach ( $tagList as $tag ) {
							if ( ! preg_match( "/\^/", $tag ) ) {
								if ( $cleanNonNumerics ) {
									$exTag              = explode( ".", $tag );
									$tagArrayUncombined = array();
									foreach ( $exTag as $val ) {
										if ( is_numeric( $val ) ) {
											$tagArrayUncombined[] = $val;
										} else {
											if ( $sanitizeNumerics ) {
												$vNum                 = preg_replace( "/[^0-9$]/is", '', $val );
												$tagArrayUncombined[] = $vNum;
											}
										}
									}
									$tag = implode( ".", $tagArrayUncombined );
								}
								// Fill the list here,if it has not already been added
								if ( ! isset( $tagArray[ $tag ] ) ) {
									$tagArray[ $tag ] = $tag;
								}
							}
						}
					}
				} else {
					$exceptionMessage = "Request failure, got $code from URL";
				}
				if ( count( $tagArray ) ) {
					asort( $tagArray, SORT_NATURAL );
					$newArray = array();
					foreach ( $tagArray as $arrayKey => $arrayValue ) {
						$newArray[] = $arrayValue;
					}
					$tagArray = $newArray;
				}
			} catch ( \Exception $gitGetException ) {
				$exceptionMessage = $gitGetException->getMessage();
				$code             = $gitGetException->getCode();
			}
			if ( $fetchFail ) {
				throw new \Exception( $exceptionMessage, $code );
			}

			return $tagArray;
		}

		/**
		 * @param string $myVersion
		 * @param string $gitUrl
		 *
		 * @return array
		 * @since 6.0.4
		 */
		public function getMyVersionByGitTag( $myVersion = '', $gitUrl = '' ) {
			$versionArray   = $this->getGitTagsByUrl( $gitUrl, true, true );
			$versionsHigher = array();
			foreach ( $versionArray as $tagVersion ) {
				if ( version_compare( $tagVersion, $myVersion, ">" ) ) {
					$versionsHigher[] = $tagVersion;
				}
			}

			return $versionsHigher;
		}

		/**
		 * Find out if your internal version is older than the tag releases in a git repo
		 *
		 * @param string $myVersion
		 * @param string $gitUrl
		 *
		 * @return bool
		 * @since 6.0.4
		 */
		public function getVersionTooOld( $myVersion = '', $gitUrl = '' ) {
			if ( count( $this->getMyVersionByGitTag( $myVersion, $gitUrl ) ) ) {
				return true;
			}

			return false;
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
			if ( $validateHost || $this->alwaysResolveHostvalidation === true ) {
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
		 * Return correct data on https-detection
		 *
		 * @param bool $returnProtocol
		 *
		 * @return bool|string
		 * @since 6.0.3
		 */
		public function getProtocol( $returnProtocol = false ) {
			if ( isset( $_SERVER['HTTPS'] ) ) {
				if ( $_SERVER['HTTPS'] == "on" ) {
					if ( ! $returnProtocol ) {
						return true;
					} else {
						return "https";
					}
				} else {
					if ( ! $returnProtocol ) {
						return false;
					} else {
						return "http";
					}
				}
			}
			if ( ! $returnProtocol ) {
				return false;
			} else {
				return "http";
			}
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
						return TorneLIB_Network_IP_Protocols::PROTOCOL_IPV6;
					} else {
						return TorneLIB_Network_IP_Protocols::PROTOCOL_NONE;
					}
				} else {
					return $this->getArpaFromIpv6( $ipAddr );
				}
			} else if ( filter_var( $ipAddr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) !== false ) {
				if ( $returnIpType ) {
					return TorneLIB_Network_IP_Protocols::PROTOCOL_IPV4;
				} else {
					return $this->getArpaFromIpv4( $ipAddr );
				}
			} else {
				if ( $returnIpType ) {
					return TorneLIB_Network_IP_Protocols::PROTOCOL_NONE;
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

		/**
		 * When active: Force this libray to always validate hosts with a DNS resolve during a getUrlDomain()-call.
		 *
		 * @param bool $activate
		 */
		public function setAlwaysResolveHostvalidation( $activate = false ) {
			$this->alwaysResolveHostvalidation = $activate;
		}

		/**
		 * Return the current boolean value for alwaysResolveHostvalidation.
		 */
		public function getAlwaysResolveHostvalidation() {
			$this->alwaysResolveHostvalidation;
		}

	}
}
if ( ! class_exists( 'Tornevall_cURL' ) && ! class_exists( 'TorneLIB\Tornevall_cURL' ) ) {
	/**
	 * Class Tornevall_cURL
	 *
	 * @package TorneLIB
	 * @version 6.0.13
	 * @link https://docs.tornevall.net/x/KQCy TorneLIBv5
	 * @link https://bitbucket.tornevall.net/projects/LIB/repos/tornelib-php-netcurl/browse Sources of TorneLIB
	 * @link https://docs.tornevall.net/x/KwCy Network & Curl v5 and v6 Library usage
	 * @link https://docs.tornevall.net/x/FoBU TorneLIB Full documentation
	 */
	class Tornevall_cURL {

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

		//// PUBLIC CONFIG THAT SHOULD GO PRIVATE
		/** @var array Default paths to the certificates we are looking for */
		private $sslPemLocations = array( '/etc/ssl/certs/cacert.pem', '/etc/ssl/certs/ca-certificates.crt' );
		/** @var array Interfaces to use */
		public $IpAddr = array();
		/** @var bool If more than one ip is set in the interfaces to use, this will make the interface go random */
		public $IpAddrRandom = true;
		/** @var null Sets a HTTP_REFERER to the http call */
		private $CurlReferer;

		/**
		 * Die on use of proxy/tunnel on first try (Incomplete).
		 *
		 * This function is supposed to stop if the proxy fails on connection, so the library won't continue looking for a preferred exit point, since that will reveal the current unproxified address.
		 *
		 * @var bool
		 */
		private $CurlProxyDeath = true;

		//// PRIVATE AND PROTECTED VARIABLES VARIABLES
		/** @var string This modules name (inherited to some exceptions amongst others) */
		protected $ModuleName = "NetCurl";
		/** @var string Internal version that is being used to find out if we are running the latest version of this library */
		private $TorneCurlVersion = "6.0.11";
		/** @var null Curl Version */
		private $CurlVersion = null;
		/** @var string Internal release snapshot that is being used to find out if we are running the latest version of this library */
		private $TorneCurlReleaseDate = "20171013";
		/**
		 * Prepare TorneLIB_Network class if it exists (as of the november 2016 it does).
		 *
		 * @var TorneLIB_Network
		 */
		private $NETWORK;

		/**
		 * Target environment (if target is production some debugging values will be skipped)
		 *
		 * @since 5.0.0
		 * @var int
		 */
		private $TargetEnvironment = TORNELIB_CURL_ENVIRONMENT::ENVIRONMENT_PRODUCTION;
		/** @var null Our communication channel */
		private $CurlSession = null;
		/** @var null URL that was set to communicate with */
		private $CurlURL = null;
		/** @var array Flags controller to change behaviour on internal function */
		private $internalFlags = array();
		private $debugData = array(
			'data'     => array(
				'info' => array()
			),
			'soapData' => array(
				'info' => array()
			),
			'calls'    => 0
		);


		//// SSL AUTODETECTION CAPABILITIES
		/// DEFAULT: Most of the settings are set to be disabled, so that the system handles this automatically with defaults
		/// If there are problems reaching wsdl or connecting to https-based URLs, try set $testssl to true

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
		/** @var array Error messages from SSL loading */
		private $sslDriverError = array();
		/** @var bool If SSL has been compiled in CURL, this will transform to true */
		private $sslCurlDriver = false;
		/** @var array Storage of invisible errors */
		private $hasErrorsStore = array();
		/**
		 * Allow https calls to unverified peers/hosts
		 *
		 * @since 5.0.0
		 * @var bool
		 */
		private $allowSslUnverified = false;
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

		//// IP AND PROXY CONFIG
		private $CurlIp = null;
		private $CurlIpType = null;
		/** @var null CurlProxy, if set, we will try to proxify the traffic */
		private $CurlProxy = null;
		/** @var null, if not set, but CurlProxy is, we will use HTTP as proxy (See CURLPROXY_* for more information) */
		private $CurlProxyType = null;
		/** @var bool Enable tunneling mode */
		private $CurlTunnel = false;

		//// URL REDIRECT
		/** @var bool Decide whether the curl library should follow an url redirect or not */
		private $followLocationSet = true;
		/** @var array List of redirections during curl calls */
		private $redirectedUrls = array();

		//// POST-GET-RESPONSE
		/** @var null A tempoary set of the response from the url called */
		private $TemporaryResponse = null;
		/** @var What post type to use when using POST (Enforced) */
		private $forcePostType = null;
		/** @var string Sets an encoding to the http call */
		public $CurlEncoding = null;
		/** @var array Run-twice-in-handler (replaces CurlResolveRetry, etc) */
		private $CurlRetryTypes = array( 'resolve' => 0, 'sslunverified' => 0 );
		/** @var string Custom User-Agent sent in the HTTP-HEADER */
		private $CurlUserAgent;
		/** @var string Custom User-Agent Memory */
		private $CustomUserAgent;
		/** @var bool Try to automatically parse the retrieved body content. Supports, amongst others json, serialization, etc */
		public $CurlAutoParse = true;
		/** @var bool Allow parsing of content bodies (tags) */
		private $allowParseHtml = false;
		private $ResponseType = TORNELIB_CURL_RESPONSETYPE::RESPONSETYPE_ARRAY;
		/** @var array Authentication */
		private $AuthData = array( 'Username' => null, 'Password' => null, 'Type' => CURL_AUTH_TYPES::AUTHTYPE_NONE );
		/** @var array Adding own headers to the HTTP-request here */
		private $CurlHeaders = array();
		private $CurlHeadersSystem = array();
		private $CurlHeadersUserDefined = array();
		private $allowCdata = false;
		private $useXmlSerializer = false;
		/** @var bool Store information about the URL call and if the SSL was unsafe (disabled) */
		protected $unsafeSslCall = false;

		//// COOKIE CONFIGS
		private $useLocalCookies = false;
		private $CookiePath = null;
		private $SaveCookies = false;
		private $CookieFile = null;
		private $CookiePathCreated = false;
		private $UseCookieExceptions = false;
		public $AllowTempAsCookiePath = false;
		/** @var bool Use cookies and save them if needed (Normally not needed, but enabled by default) */
		public $CurlUseCookies = true;

		//// RESOLVING AND TIMEOUTS

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
		private $CurlTimeout;
		private $CurlResolveForced = false;

		//// EXCEPTION HANDLING
		/** @var Throwable http codes */
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
		 */
		public $canThrow = true;

		/**
		 * Tornevall_cURL constructor.
		 *
		 * @param string $PreferredURL
		 * @param array $PreparedPostData
		 * @param int $PreferredMethod
		 * @param array $flags
		 *
		 * @throws \Exception
		 */
		public function __construct( $PreferredURL = '', $PreparedPostData = array(), $PreferredMethod = CURL_METHODS::METHOD_POST, $flags = array() ) {
			register_shutdown_function( array( $this, 'tornecurl_terminate' ) );

			if ( is_array( $flags ) && count( $flags ) ) {
				$this->setFlags( $flags );
			}
			$this->NETWORK = new TorneLIB_Network();

			// Store constants of curl errors and curlOptions
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
			if ( ! function_exists( 'curl_init' ) ) {
				throw new \Exception( $this->ModuleName . " curl init exception: curl library not found", $this->NETWORK->getExceptionCode( 'NETCURL_CURL_MISSING' ) );
			}
			// Common ssl checkers (if they fail, there is a sslDriverError to recall
			if ( ! in_array( 'https', @stream_get_wrappers() ) ) {
				$this->sslDriverError[] = "SSL Failure: HTTPS wrapper can not be found";
			}
			if ( ! extension_loaded( 'openssl' ) ) {
				$this->sslDriverError[] = "SSL Failure: HTTPS extension can not be found";
			}
			// Initial setup
			$this->CurlUserAgent = 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 1.0.3705; .NET CLR 1.1.4322; Media Center PC 4.0; +TorneLIB-NetCurl-' . TORNELIB_NETCURL_RELEASE . " +TorneLIB+cUrl-" . $this->TorneCurlVersion . ')';
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
			$this->openssl_guess();
			$this->throwableHttpCodes = array();

			if ( ! empty( $PreferredURL ) ) {
				$this->CurlURL   = $PreferredURL;
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

		public function getDebugData() {
			return $this->debugData;
		}

		/**
		 * Termination Controller - Used amongst others, to make sure that empty cookiepaths created by this library gets removed if they are being used.
		 */
		function tornecurl_terminate() {
			// If this indicates that we created the path, make sure it's removed if empty after session completion
			if ( ! count( glob( $this->CookiePath . "/*" ) ) && $this->CookiePathCreated ) {
				@rmdir( $this->CookiePath );
			}
		}

		/**
		 * @param array $arrayData
		 *
		 * @return bool
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
			if ( $this->isFlag( "NOCHAIN" ) ) {
				$this->unsetFlag( "CHAIN" );
			}
		}

		/**
		 * Return all flags
		 *
		 * @return array
		 */
		public function getFlags() {
			return $this->internalFlags;
		}

		/**
		 * cUrl initializer, if needed faster
		 *
		 * @return resource
		 * @since 5.0.0
		 */
		public function init() {
			$this->initCookiePath();
			$this->CurlSession = curl_init( $this->CurlURL );

			return $this->CurlSession;
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
			$this->CurlTimeout = $timeout;
		}

		/**
		 * Get current timeout setting
		 * @return string
		 * @since 6.0.13
		 */
		public function getTimeout() {
			$returnTimeouts = array(
				'connecttimeout' => ceil( $this->CurlTimeout / 2 ),
				'requesttimeout' => ceil( $this->CurlTimeout )
			);
			if ( empty( $this->CurlTimeout ) ) {
				$returnTimeouts = array(
					'connecttimeout' => 300,
					'requesttimeout' => 0
				);
			}

			return $returnTimeouts;
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
						throw new \Exception( $this->ModuleName . " " . __FUNCTION__ . " exception: Could not set up a proper cookiepath [To override this, use AllowTempAsCookiePath (not recommended)]", $this->NETWORK->getExceptionCode( 'NETCURL_COOKIEPATH_SETUP_FAIL' ) );
					}
				}
			}
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
					throw new \Exception( $this->ModuleName . " HTTP Response Exception: " . $message, $code );
				}
			}
		}

		//// SESSION

		/**
		 * Returns an ongoing cUrl session - Normally you may get this from initSession (and normally you don't need this at all)
		 *
		 * @return null
		 */
		public function getCurlSession() {
			return $this->CurlSession;
		}


		//// PUBLIC SETTERS & GETTERS

		/**
		 * Allow fallback tests in SOAP mode
		 *
		 * Defines whether, when there is a SOAP-call, we should try to make the SOAP initialization twice.
		 * This is a kind of fallback when users forget to add ?wsdl or &wsdl in urls that requires this to call for SOAP.
		 * It may happen when setting CURL_POST_AS to a SOAP-call but, the URL is not defined as one.
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
		 * Set the curl libraray to die, if no proxy has been successfully set up (TODO: not implemented)
		 *
		 * @param bool $dieEnabled
		 *
		 * @since 6.0.9
		 */
		public function setDieOnNoProxy( $dieEnabled = true ) {
			$this->CurlProxyDeath = $dieEnabled;
		}

		/**
		 * Get the state of whether the library should bail out if no proxy has been successfully set
		 *
		 * @return bool
		 * @since 6.0.9
		 */
		public function getDieOnNoProxy() {
			return $this->CurlProxyDeath;
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
		 * @return array|Throwable
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
		 * Enforce a response type if you're not happy with the default returned array.
		 *
		 * @param int $ResponseType
		 *
		 * @since 5.0.0
		 */
		public function setResponseType( $ResponseType = TORNELIB_CURL_RESPONSETYPE::RESPONSETYPE_ARRAY ) {
			$this->ResponseType = $ResponseType;
		}

		/**
		 * Return the value of how the responses are returned
		 *
		 * @return int
		 * @since 6.0.6
		 */
		public function getResponseType() {
			return $this->ResponseType;
		}

		/**
		 * Enforce a specific type of post method
		 *
		 * To always send PostData, even if it is not set in the doXXX-method, you can use this setting to enforce - for example - JSON posts
		 * $myLib->setPostTypeDefault(CURL_POST_AS::POST_AS_JSON)
		 *
		 * @param int $postType
		 *
		 * @since 6.0.6
		 */
		public function setPostTypeDefault( $postType = CURL_POST_AS::POST_AS_NORMAL ) {
			$this->forcePostType = $postType;
		}

		/**
		 * Returns what to use as post method (CURL_POST_AS) on default. Returns null if none are set (= no overrides will be made)
		 * @return CURL_POST_AS
		 * @since 6.0.6
		 */
		public function getPostTypeDefault() {
			return $this->forcePostType;
		}

		/**
		 * Enforces CURLOPT_FOLLOWLOCATION to act different if not matching with the internal rules
		 *
		 * @param bool $setEnabledState
		 *
		 * @since 5.0.0/2017.4
		 */
		public function setEnforceFollowLocation( $setEnabledState = true ) {
			$this->followLocationSet = $setEnabledState;
		}

		/**
		 * Returns the boolean value of followLocationSet (see setEnforceFollowLocation)
		 * @return bool
		 * @since 6.0.6
		 */
		public function getEnforceFollowLocation() {
			return $this->followLocationSet;
		}

		/**
		 * Switch over to forced debugging
		 *
		 * To not break production environments by setting for example _DEBUG_TCURL_UNSET_LOCAL_PEM_LOCATION, switching over to test mode is required
		 * to use those variables.
		 *
		 * @since 5.0.0
		 */
		public function setTestEnabled() {
			$this->TargetEnvironment = TORNELIB_CURL_ENVIRONMENT::ENVIRONMENT_TEST;
		}

		/**
		 * Returns current target environment
		 * @return int
		 * @since 6.0.6
		 */
		public function getTestEnabled() {
			return $this->TargetEnvironment;
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

		/**
		 * Returns the boolean value set (eventually) from setCookieException
		 * @return bool
		 * @since 6.0.6
		 */
		public function getCookieExceptions() {
			return $this->UseCookieExceptions;
		}

		/**
		 * Set up whether we should allow html parsing or not
		 *
		 * @param bool $enabled
		 */
		public function setParseHtml( $enabled = false ) {
			$this->allowParseHtml = $enabled;
		}

		/**
		 * Return the boolean of the setParseHtml
		 * @return bool
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
		 */
		public function setUserAgent( $CustomUserAgent = "" ) {
			if ( ! empty( $CustomUserAgent ) ) {
				$this->CustomUserAgent .= preg_replace( "/\s+$/", '', $CustomUserAgent );
				$this->CurlUserAgent   = $this->CustomUserAgent . " +TorneLIB-NetCurl-" . TORNELIB_NETCURL_RELEASE . " +TorneLIB+cUrl-" . $this->TorneCurlVersion;
			} else {
				$this->CurlUserAgent = 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 1.0.3705; .NET CLR 1.1.4322; Media Center PC 4.0; +TorneLIB-NetCurl-' . TORNELIB_NETCURL_RELEASE . " +TorneLIB+cUrl-" . $this->TorneCurlVersion . ')';
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

		/**
		 * Get the value of customized user agent
		 *
		 * @return string
		 * @since 6.0.6
		 */
		public function getCustomUserAgent() {
			return $this->CustomUserAgent;
		}

		/**
		 * @param string $refererString
		 *
		 * @since 6.0.9
		 */
		public function setReferer( $refererString = "" ) {
			$this->CurlReferer = $refererString;
		}

		/**
		 * @return null
		 * @since 6.0.9
		 */
		public function getReferer() {
			return $this->CurlReferer;
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
		 * @param array $curlOptArrayOrKey If arrayed, there will be multiple options at once
		 * @param null $curlOptValue If not null, and the first parameter is not an array, this is taken as a single update value
		 */
		public function setCurlOpt( $curlOptArrayOrKey = array(), $curlOptValue = null ) {
			if ( is_null( $this->CurlSession ) ) {
				$this->init();
			}
			if ( is_array( $curlOptArrayOrKey ) ) {
				foreach ( $curlOptArrayOrKey as $key => $val ) {
					$this->curlopt[ $key ] = $val;
					curl_setopt( $this->CurlSession, $key, $val );
				}
			}
			if ( ! is_array( $curlOptArrayOrKey ) && ! empty( $curlOptArrayOrKey ) && ! is_null( $curlOptValue ) ) {
				$this->curlopt[ $curlOptArrayOrKey ] = $curlOptValue;
				curl_setopt( $this->CurlSession, $curlOptArrayOrKey, $curlOptValue );
			}
		}

		/**
		 * curlops that can be overridden
		 *
		 * @param array $curlOptArrayOrKey
		 * @param null $curlOptValue
		 */
		private function setCurlOptInternal( $curlOptArrayOrKey = array(), $curlOptValue = null ) {
			if ( is_null( $this->CurlSession ) ) {
				$this->init();
			}
			if ( ! is_array( $curlOptArrayOrKey ) && ! empty( $curlOptArrayOrKey ) && ! is_null( $curlOptValue ) ) {
				if ( ! isset( $this->curlopt[ $curlOptArrayOrKey ] ) ) {
					$this->curlopt[ $curlOptArrayOrKey ] = $curlOptValue;
					curl_setopt( $this->CurlSession, $curlOptArrayOrKey, $curlOptValue );
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
		 * @since 5.0.0
		 */
		public function getVersion( $fullRelease = false ) {
			if ( ! $fullRelease ) {
				return $this->TorneCurlVersion;
			} else {
				return $this->TorneCurlVersion . "-" . $this->TorneCurlReleaseDate;
			}
		}

		/**
		 * Get this internal release version
		 *
		 * Requires the constant TORNELIB_ALLOW_VERSION_REQUESTS to return any information.
		 *
		 * @return string
		 * @throws \Exception
		 * @deprecated Use tag control
		 */
		public function getInternalRelease() {
			if ( defined( 'TORNELIB_ALLOW_VERSION_REQUESTS' ) && TORNELIB_ALLOW_VERSION_REQUESTS === true ) {
				return $this->TorneCurlVersion . "," . $this->TorneCurlReleaseDate;
			}
			throw new \Exception( $this->ModuleName . " internalReleaseException [" . __CLASS__ . "]: Version requests are not allowed in current state (permissions required)", 403 );
		}

		/**
		 * Get store exceptions
		 * @return array
		 */
		public function getStoredExceptionInformation() {
			return $this->sessionsExceptions;
		}

		/// SPECIAL FEATURES

		/**
		 * @return bool
		 */
		public function hasErrors() {
			if ( ! count( $this->hasErrorsStore ) ) {
				return false;
			}

			return true;
		}

		/**
		 * @return array
		 */
		public function getErrors() {
			return $this->hasErrorsStore;
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
		 * @param string $libName
		 *
		 * @return string
		 */
		private function getHasUpdateState( $libName = 'tornelib_curl' ) {
			// Currently only supporting this internal module (through $myRelease).
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
		 * Set and/or append certificate bundle locations to current configuration
		 *
		 * @param array $locationArray
		 * @param bool $resetArray Make the location array go reset on customized list
		 *
		 */
		public function setSslPemLocations(
			$locationArray = array(
				'/etc/ssl/certs/cacert.pem',
				'/etc/ssl/certs/ca-certificates.crt'
			), $resetArray = false
		) {
			$newPem = array();
			if ( count( $this->sslPemLocations ) ) {
				foreach ( $this->sslPemLocations as $filePathAndName ) {
					if ( ! in_array( $filePathAndName, $newPem ) ) {
						$newPem[] = $filePathAndName;
					}
				}
			}
			if ( count( $locationArray ) ) {
				if ( $resetArray ) {
					$newPem = array();
				}
				foreach ( $locationArray as $filePathAndName ) {
					if ( ! in_array( $filePathAndName, $newPem ) ) {
						$newPem[] = $filePathAndName;
					}
				}
			}
			$this->sslPemLocations = $newPem;
		}

		/**
		 * Get current certificate bundle locations
		 *
		 * @return array
		 */
		public function getSslPemLocations() {
			return $this->sslPemLocations;
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
			// The certificate location here will be set up for the curl engine later on, during preparation of the connection.
			// NOTE: ini_set() does not work for setting up the cafile, this has to be done through php.ini, .htaccess, httpd.conf or .user.ini
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
								if ( $this->hasFlag( '_DEBUG_TCURL_UNSET_LOCAL_PEM_LOCATION' ) && $this->isFlag( '_DEBUG_TCURL_UNSET_LOCAL_PEM_LOCATION' ) ) {
									if ( $this->TargetEnvironment == TORNELIB_CURL_ENVIRONMENT::ENVIRONMENT_TEST ) {
										// Enforce wrong certificate location
										$this->hasCertFile = false;
										$this->useCertFile = null;
									}
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
								if ( $this->TargetEnvironment == TORNELIB_CURL_ENVIRONMENT::ENVIRONMENT_TEST && $this->hasFlag( '_DEBUG_TCURL_UNSET_LOCAL_PEM_LOCATION' ) && $this->isFlag( '_DEBUG_TCURL_UNSET_LOCAL_PEM_LOCATION' ) ) {
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
		 * @return bool
		 * @throws \Exception
		 */
		public function setSslVerify( $enabledFlag = true ) {
			// allowSslUnverified is set to true, the enabledFlag is also allowed to be set to false
			if ( $this->allowSslUnverified ) {
				$this->sslVerify = $enabledFlag;
			} else {
				// If the enabledFlag is false and the allowance is not set, we will not be allowed to disabled SSL verification either
				if ( ! $enabledFlag ) {
					throw new \Exception( $this->ModuleName . " setSslVerify exception: setSslUnverified(true) has not been set", $this->NETWORK->getExceptionCode( 'NETCURL_SETSSLVERIFY_UNVERIFIED_NOT_SET' ) );
				} else {
					// However, if we force the verify flag to be on, we won't care about the allowance override, as the security
					// will be enhanced anyway.
					$this->sslVerify = $enabledFlag;
				}
			}

			return true;
		}

		/**
		 * Return the boolean value set in setSslVerify
		 *
		 * @return bool
		 * @since 6.0.6
		 */
		public function getSslVerify() {
			return $this->sslVerify;
		}

		/**
		 * While doing SSL calls, and SSL certificate verifications is failing, enable the ability to skip SSL verifications.
		 *
		 * Normally, we want a valid SSL certificate while doing https-requests, but sometimes the verifications must be disabled. One reason of this is
		 * in cases, when crt-files are missing and PHP can not under very specific circumstances verify the peer. To allow this behaviour, the client
		 * MUST use this function.
		 *
		 * @since 5.0.0
		 *
		 * @param bool $enabledFlag
		 */
		public function setSslUnverified( $enabledFlag = false ) {
			$this->allowSslUnverified = $enabledFlag;
		}

		/**
		 * Return the boolean value set from setSslUnverified
		 * @return bool
		 * @since 6.0.6
		 */
		public function getSslUnverified() {
			return $this->allowSslUnverified;
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



		//// DEPRECATION (POSSIBLY EXTRACTABLE FROM NETWORK-LIBRARY)

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



		//// IP SETUP

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
					throw new \Exception( $this->ModuleName . " " . __FUNCTION__ . " exception: " . $UseIp . " is not a valid ip-address", $this->NETWORK->getExceptionCode( 'NETCURL_IPCONFIG_NOT_VALID' ) );
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
		 * Set up a proxy
		 *
		 * @param $ProxyAddr
		 * @param int $ProxyType
		 */
		public function setProxy( $ProxyAddr, $ProxyType = CURLPROXY_HTTP ) {
			$this->CurlProxy     = $ProxyAddr;
			$this->CurlProxyType = $ProxyType;
			// Run from proxy on request
			$this->setCurlOptInternal( CURLOPT_PROXY, $this->CurlProxy );
			if ( isset( $this->CurlProxyType ) && ! empty( $this->CurlProxyType ) ) {
				$this->setCurlOptInternal( CURLOPT_PROXYTYPE, $this->CurlProxyType );
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
				'curlProxy'     => $this->CurlProxy,
				'curlProxyType' => $this->CurlProxyType
			);
		}

		/**
		 * Enable curl tunneling
		 *
		 * @param bool $curlTunnelEnable
		 *
		 * @since 6.0.11
		 */
		public function setTunnel( $curlTunnelEnable = true ) {
			// Run in tunneling mode
			$this->CurlTunnel = $curlTunnelEnable;
			$this->setCurlOptInternal( CURLOPT_HTTPPROXYTUNNEL, $curlTunnelEnable );
		}

		/**
		 * Return state of curltunneling
		 *
		 * @return bool
		 */
		public function getTunnel() {
			return $this->CurlTunnel;
		}


		//// PARSING

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
				$trimmedContent        = trim( $content ); // PHP 5.3: Can't use function return value in write context
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
					// Returns empty class if the SimpleXMLElement is missing.
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
					throw new \Exception( $this->ModuleName . " HtmlParse exception: Can not parse DOMDocuments without the DOMDocuments class", $this->NETWORK->getExceptionCode( "NETCURL_DOMDOCUMENT_CLASS_MISSING" ) );
				}
			}

			return $parsedContent;
		}

		/**
		 * Parse response, in case of there is any followed traces from the curl engine, so we'll always land on the right ending stream
		 *
		 * @param string $content
		 *
		 * @return array|string|TORNELIB_CURLOBJECT
		 */
		private function ParseResponse( $content = '' ) {
			// Kill the chaining (for future releases, when we eventually raise chaining mode as default)
			if ( $this->isFlag( "NOCHAIN" ) ) {
				$this->unsetFlag( "CHAIN" );
			}

			if ( ! is_string( $content ) ) {
				return $content;
			}
			list( $header, $body ) = explode( "\r\n\r\n", $content, 2 );
			$rows              = explode( "\n", $header );
			$response          = explode( " ", isset( $rows[0] ) ? $rows[0] : null );
			$shortCodeResponse = explode( " ", isset( $rows[0] ) ? $rows[0] : null, 3 );
			$httpMessage       = isset( $shortCodeResponse[2] ) ? $shortCodeResponse[2] : null;
			$code              = isset( $response[1] ) ? $response[1] : null;
			// If the first row of the body contains a HTTP/-string, we'll try to reparse it
			if ( preg_match( "/^HTTP\//", $body ) ) {
				$newBody = $this->ParseResponse( $body );
				$header  = $newBody['header'];
				$body    = $newBody['body'];
			}

			// If response code starts with 3xx, this is probably a redirect
			if ( preg_match( "/^3/", $code ) ) {
				$this->redirectedUrls[] = $this->CurlURL;
				$redirectArray[]        = array(
					'header' => $header,
					'body'   => $body,
					'code'   => $code
				);
				if ( $this->isFlag( 'FOLLOWLOCATION_INTERNAL' ) ) {
					// For future coding only: Add internal follow function, eventually.
				}
			}
			$headerInfo     = $this->GetHeaderKeyArray( $rows );
			$returnResponse = array(
				'header' => array( 'info' => $headerInfo, 'full' => $header ),
				'body'   => $body,
				'code'   => $code
			);

			$this->throwCodeException( $httpMessage, $code );
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
			if ( $this->isFlag( "CHAIN" ) ) {
				return $this;
			}

			return $returnResponse;
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
		 * @throws \Exception
		 */
		public function getParsedResponse( $ResponseContent = null ) {
			if ( isset( $ResponseContent['code'] ) && $ResponseContent['code'] >= 400 ) {
				throw new \Exception( $this->ModuleName . " parseResponse exception - Unexpected response code from server: " . $ResponseContent['code'], $ResponseContent['code'] );
			}
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
			if ( is_null( $ResponseContent ) && ! empty( $this->TemporaryResponse ) && isset( $this->TemporaryResponse['code'] ) ) {
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
			if ( is_null( $ResponseContent ) && ! empty( $this->TemporaryResponse ) && isset( $this->TemporaryResponse['body'] ) ) {
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
						throw new \Exception( $this->ModuleName . " getParsedValue exception: Requested key was not found in parsed response", $this->NETWORK->getExceptionCode( 'NETCURL_GETPARSEDVALUE_KEY_NOT_FOUND' ) );
					}
				}
			}

			return null;
		}

		public function getRedirectedUrls() {
			return $this->redirectedUrls;
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
		 * Check if SOAP exists in system
		 *
		 * @param bool $extendedSearch Extend search for SOAP (unsafe method, looking for constants defined as SOAP_*)
		 *
		 * @return bool
		 */
		public function hasSoap( $extendedSearch = false ) {
			$soapClassBoolean = false;
			if ( ( class_exists( 'SoapClient' ) || class_exists( '\SoapClient' ) ) ) {
				$soapClassBoolean = true;
			}
			$sysConst = get_defined_constants();
			if ( in_array( 'SOAP_1_1', $sysConst ) || in_array( 'SOAP_1_2', $sysConst ) ) {
				$soapClassBoolean = true;
			} else {
				if ( $extendedSearch ) {
					foreach ( $sysConst as $constantKey => $constantValue ) {
						if ( preg_match( '/^SOAP_/', $constantKey ) ) {
							$soapClassBoolean = true;
						}
					}
				}
			}

			return $soapClassBoolean;
		}

		/**
		 * Return number of tries, arrayed, that different parts of netcurl has been trying to make a call
		 *
		 * @return array
		 * @since 6.0.8
		 */
		public function getRetries() {
			return $this->CurlRetryTypes;
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
		 * Returns the boolean value of whether exceptions can be stored in memory during calls
		 *
		 * @return bool
		 * @since 6.0.6
		 */
		public function getStoreSessionExceptions() {
			return $this->canStoreSessionException;
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
			$response = null;
			if ( ! empty( $url ) ) {
				$content  = $this->executeCurl( $url, $postData, CURL_METHODS::METHOD_POST, $postAs );
				$response = $this->ParseResponse( $content );
			}

			return $response;
		}

		/**
		 * @param string $url
		 * @param array $postData
		 * @param int $postAs
		 *
		 * @return array
		 */
		public function doPut( $url = '', $postData = array(), $postAs = CURL_POST_AS::POST_AS_NORMAL ) {
			$response = null;
			if ( ! empty( $url ) ) {
				$content  = $this->executeCurl( $url, $postData, CURL_METHODS::METHOD_PUT, $postAs );
				$response = $this->ParseResponse( $content );
			}

			return $response;
		}

		/**
		 * @param string $url
		 * @param array $postData
		 * @param int $postAs
		 *
		 * @return array
		 */
		public function doDelete( $url = '', $postData = array(), $postAs = CURL_POST_AS::POST_AS_NORMAL ) {
			$response = null;
			if ( ! empty( $url ) ) {
				$content  = $this->executeCurl( $url, $postData, CURL_METHODS::METHOD_DELETE, $postAs );
				$response = $this->ParseResponse( $content );
			}

			return $response;
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
			$response = null;
			if ( ! empty( $url ) ) {
				$content  = $this->executeCurl( $url, array(), CURL_METHODS::METHOD_GET, $postAs );
				$response = $this->ParseResponse( $content );
			}

			return $response;
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
		 * Return user defined headers
		 *
		 * @return array
		 * @since 6.0.6
		 */
		public function getCurlHeader() {
			return $this->CurlHeadersUserDefined;
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
		private function executeCurl( $url = '', $postData = array(), $CurlMethod = CURL_METHODS::METHOD_GET, $postAs = CURL_POST_AS::POST_AS_NORMAL ) {
			if ( ! empty( $url ) ) {
				$this->CurlURL = $url;
			}

			$this->debugData['calls'] ++;

			if ( is_null( $this->CurlSession ) ) {
				$this->init();
			}
			$this->CurlHeaders = array();

			// Enforce postAs
			// If you'd like to force everything to use json you can for example use: $myLib->setPostTypeDefault(CURL_POST_AS::POST_AS_JSON)
			if ( ! is_null( $this->forcePostType ) ) {
				$postAs = $this->forcePostType;
			}

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
					// Since setCurlOptInternal is not an overrider, using the overrider here, will have no effect on the curlopt setting
					// as it has already been set from our top defaults. This has to be pushed in, by force.
					$this->setCurlOpt( CURLOPT_FOLLOWLOCATION, $this->followLocationSet );
				}
			}

			// If certificates missing (place above the wsdl, as it has to be inheritaged down to the soapclient
			if ( ! $this->TestCerts() ) {
				// And we're allowed to run without them
				if ( ! $this->sslVerify && $this->allowSslUnverified ) {
					// Then disable the checking here (overriders should always be enforced)
					$this->setCurlOpt( CURLOPT_SSL_VERIFYHOST, 0 );
					$this->setCurlOpt( CURLOPT_SSL_VERIFYPEER, 0 );
					$this->unsafeSslCall = true;
				} else {
					// From libcurl 7.28.1 CURLOPT_SSL_VERIFYHOST is deprecated. However, using the value 1 can be used
					// as of PHP 5.4.11, where the deprecation notices was added. The deprecation has started before libcurl
					// 7.28.1 (this was discovered on a server that was running PHP 5.5 and libcurl-7.22). In full debug
					// even libcurl-7.22 was generating this message, so from PHP 5.4.11 we are now enforcing the value 2
					// for CURLOPT_SSL_VERIFYHOST instead. The reason of why we are using the value 1 before this version
					// is actually a lazy thing, as we don't want to break anything that might be unsupported before this version.
					if ( version_compare( PHP_VERSION, '5.4.11', ">=" ) ) {
						$this->setCurlOptInternal( CURLOPT_SSL_VERIFYHOST, 2 );
					} else {
						$this->setCurlOptInternal( CURLOPT_SSL_VERIFYHOST, 1 );
					}
					$this->setCurlOptInternal( CURLOPT_SSL_VERIFYPEER, 1 );
				}
			} else {
				// Silently configure for https-connections, if exists
				if ( $this->useCertFile != "" && file_exists( $this->useCertFile ) ) {
					if ( ! $this->sslVerify && $this->allowSslUnverified ) {
						// Then disable the checking here
						$this->setCurlOpt( CURLOPT_SSL_VERIFYHOST, 0 );
						$this->setCurlOpt( CURLOPT_SSL_VERIFYPEER, 0 );
						$this->unsafeSslCall = true;
					} else {
						try {
							$this->setCurlOptInternal( CURLOPT_CAINFO, $this->useCertFile );
							$this->setCurlOptInternal( CURLOPT_CAPATH, dirname( $this->useCertFile ) );
						} catch ( \Exception $e ) {
						}
					}
				}
			}

			// Picking up externally select outgoing ip if any
			$this->handleIpList();

			// This curlopt makes it possible to make a call to a specific ip address and still use the HTTP_HOST (Must override)
			$this->setCurlOpt( CURLOPT_URL, $this->CurlURL );

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
			// The postdata section must overwrite others, since the variables are set more than once depending on how the data
			// changes or gets converted. The internal curlOpt setter don't overwrite variables if they are alread set.
			if ( ! empty( $postDataContainer ) ) {
				$this->setCurlOpt( CURLOPT_POSTFIELDS, $postDataContainer );
			}
			if ( $CurlMethod == CURL_METHODS::METHOD_POST || $CurlMethod == CURL_METHODS::METHOD_PUT || $CurlMethod == CURL_METHODS::METHOD_DELETE ) {
				if ( $CurlMethod == CURL_METHODS::METHOD_PUT ) {
					$this->setCurlOpt( CURLOPT_CUSTOMREQUEST, "PUT" );
				} else if ( $CurlMethod == CURL_METHODS::METHOD_DELETE ) {
					$this->setCurlOpt( CURLOPT_CUSTOMREQUEST, "DELETE" );
				} else {
					$this->setCurlOpt( CURLOPT_POST, true );
				}

				if ( $postAs == CURL_POST_AS::POST_AS_JSON ) {
					// Using $jsonRealData to validate the string
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
					$this->setCurlOpt( CURLOPT_POSTFIELDS, $jsonRealData );  // overwrite old
				}
			}

			// Self set timeouts, making sure the timeout set in the public is an integer over 0. Otherwise this falls back to the curldefauls.
			if ( isset( $this->CurlTimeout ) && $this->CurlTimeout > 0 ) {
				$this->setCurlOptInternal( CURLOPT_CONNECTTIMEOUT, ceil( $this->CurlTimeout / 2 ) );
				$this->setCurlOptInternal( CURLOPT_TIMEOUT, ceil( $this->CurlTimeout ) );
			}
			if ( isset( $this->CurlResolve ) && $this->CurlResolve !== CURL_RESOLVER::RESOLVER_DEFAULT ) {
				if ( $this->CurlResolve == CURL_RESOLVER::RESOLVER_IPV4 ) {
					$this->setCurlOptInternal( CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
				}
				if ( $this->CurlResolve == CURL_RESOLVER::RESOLVER_IPV6 ) {
					$this->setCurlOptInternal( CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V6 );
				}
			}

			$this->setCurlOptInternal( CURLOPT_VERBOSE, false );
			// Tunnel and proxy setup. If this is set, make sure the default IP setup gets cleared out.
			if ( ! empty( $this->CurlProxy ) && ! empty( $this->CurlProxyType ) ) {
				unset( $this->CurlIp );
			}
			if ( $this->getTunnel() ) {
				unset( $this->CurlIp );
			}

			// Another HTTP_REFERER
			if ( isset( $this->CurlReferer ) && ! empty( $this->CurlReferer ) ) {
				$this->setCurlOptInternal( CURLOPT_REFERER, $this->CurlReferer );
			}

			$this->fixHttpHeaders( $this->CurlHeadersUserDefined );
			$this->fixHttpHeaders( $this->CurlHeadersSystem );

			if ( isset( $this->CurlHeaders ) && is_array( $this->CurlHeaders ) && count( $this->CurlHeaders ) ) {
				$this->setCurlOpt( CURLOPT_HTTPHEADER, $this->CurlHeaders ); // overwrite old
			}
			if ( isset( $this->CurlUserAgent ) && ! empty( $this->CurlUserAgent ) ) {
				$this->setCurlOpt( CURLOPT_USERAGENT, $this->CurlUserAgent ); // overwrite old
			}
			if ( isset( $this->CurlEncoding ) && ! empty( $this->CurlEncoding ) ) {
				$this->setCurlOpt( CURLOPT_ENCODING, $this->CurlEncoding ); // overwrite old
			}
			if ( file_exists( $this->CookiePath ) && $this->CurlUseCookies && ! empty( $this->CurlURL ) ) {
				@file_put_contents( $this->CookiePath . "/tmpcookie", "test" );
				if ( ! file_exists( $this->CookiePath . "/tmpcookie" ) ) {
					$this->SaveCookies = true;
					$this->CookieFile  = $domainHash;
					$this->setCurlOptInternal( CURLOPT_COOKIEFILE, $this->CookiePath . "/" . $this->CookieFile );
					$this->setCurlOptInternal( CURLOPT_COOKIEJAR, $this->CookiePath . "/" . $this->CookieFile );
					$this->setCurlOptInternal( CURLOPT_COOKIE, 1 );
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
				$this->setCurlOptInternal( CURLOPT_HTTPAUTH, $useAuth );
				$this->setCurlOptInternal( CURLOPT_USERPWD, $this->AuthData['Username'] . ':' . $this->AuthData['Password'] );
			}

			// UNCONDITIONAL SETUP
			// Things that should be overwritten if set by someone else
			$this->setCurlOpt( CURLOPT_HEADER, true );
			$this->setCurlOpt( CURLOPT_RETURNTRANSFER, true );
			$this->setCurlOpt( CURLOPT_AUTOREFERER, true );
			$this->setCurlOpt( CURLINFO_HEADER_OUT, true );

			// Override with SoapClient just before the real curl_exec is the most proper way to handle inheritages
			if ( preg_match( "/\?wsdl$|\&wsdl$/i", $this->CurlURL ) || $postAs == CURL_POST_AS::POST_AS_SOAP ) {
				if ( ! $this->hasSoap() ) {
					throw new \Exception( $this->ModuleName . " " . __FUNCTION__ . " exception: SoapClient is not available in this system", $this->NETWORK->getExceptionCode( 'NETCURL_SOAPCLIENT_CLASS_MISSING' ) );
				}
				$Soap = new Tornevall_SimpleSoap( $this->CurlURL, $this );
				$Soap->setCustomUserAgent( $this->CustomUserAgent );
				$Soap->setThrowableState( $this->canThrow );
				$Soap->setSoapAuthentication( $this->AuthData );
				$Soap->setSoapTryOnce( $this->SoapTryOnce );
				try {
					$getSoapResponse                      = $Soap->getSoap();
					$this->debugData['soapdata']['url'][] = array(
						'url'       => $this->CurlURL,
						'opt'       => $this->getCurlOptByKeys(),
						'success'   => true,
						'exception' => null
					);
				} catch ( \Exception $getSoapResponseException ) {
					$this->debugData['soapdata']['url'][] = array(
						'url'       => $this->CurlURL,
						'opt'       => $this->getCurlOptByKeys(),
						'success'   => false,
						'exception' => $getSoapResponseException
					);
					throw new \Exception( $this->ModuleName . " exception from soapClient: " . $getSoapResponseException->getMessage(), $getSoapResponseException->getCode() );
				}

				return $getSoapResponse;
			}

			$returnContent = curl_exec( $this->CurlSession );

			if ( curl_errno( $this->CurlSession ) ) {

				$this->debugData['data']['url'][] = array(
					'url'       => $this->CurlURL,
					'opt'       => $this->getCurlOptByKeys(),
					'success'   => false,
					'exception' => curl_error( $this->CurlSession )
				);

				if ( $this->canStoreSessionException ) {
					$this->sessionsExceptions[] = array(
						'Content'     => $returnContent,
						'SessionInfo' => curl_getinfo( $this->CurlSession )
					);
				}
				$errorCode    = curl_errno( $this->CurlSession );
				$errorMessage = curl_error( $this->CurlSession );
				if ( $this->CurlResolveForced && $this->CurlRetryTypes['resolve'] >= 2 ) {
					throw new \Exception( $this->ModuleName . " exception in " . __FUNCTION__ . ": The maximum tries of curl_exec() for " . $this->CurlURL . " has been reached without any successful response. Normally, this happens after " . $this->CurlRetryTypes['resolve'] . " CurlResolveRetries and might be connected with a bad URL or similar that can not resolve properly.\nCurl error message follows: " . $errorMessage, $errorCode );
				}
				if ( $errorCode == CURLE_SSL_CACERT || $errorCode === 60 && $this->allowSslUnverified ) {
					if ( $this->CurlRetryTypes['sslunverified'] >= 2 ) {
						throw new \Exception( $this->ModuleName . " exception in " . __FUNCTION__ . ": The maximum tries of curl_exec() for " . $this->CurlURL . ", during a try to make a SSL connection to work, has been reached without any successful response. This normally happens when allowSslUnverified is activated in the library and " . $this->CurlRetryTypes['resolve'] . " tries to fix the problem has been made, but failed.\nCurl error message follows: " . $errorMessage, $errorCode );
					} else {
						$this->hasErrorsStore[] = array( 'code' => $errorCode, 'message' => $errorMessage );
						$this->setSslVerify( false );
						$this->setSslUnverified( true );
						$this->unsafeSslCall = true;
						$this->CurlRetryTypes['sslunverified'] ++;

						return $this->executeCurl( $this->CurlURL, $postData, $CurlMethod );
					}
				}
				if ( $errorCode == CURLE_COULDNT_RESOLVE_HOST || $errorCode === 45 ) {
					$this->hasErrorsStore[] = array( 'code' => $errorCode, 'message' => $errorMessage );
					$this->CurlRetryTypes['resolve'] ++;
					unset( $this->CurlIp );
					$this->CurlResolveForced = true;
					if ( $this->CurlIpType == 6 ) {
						$this->CurlResolve = CURL_RESOLVER::RESOLVER_IPV4;
					}
					if ( $this->CurlIpType == 4 ) {
						$this->CurlResolve = CURL_RESOLVER::RESOLVER_IPV6;
					}

					return $this->executeCurl( $this->CurlURL, $postData, $CurlMethod );
				}
				throw new \Exception( $this->ModuleName . " exception from PHP/CURL at " . __FUNCTION__ . ": " . curl_error( $this->CurlSession ), curl_errno( $this->CurlSession ) );
			} else {
				$this->debugData['data']['url'][] = array(
					'url'       => $this->CurlURL,
					'opt'       => $this->getCurlOptByKeys(),
					'success'   => true,
					'exception' => null
				);
			}

			return $returnContent;
		}
	}
}
if ( ! class_exists( 'TorneLIB_NetBits' ) && ! class_exists( 'TorneLIB\TorneLIB_NetBits' ) ) {
	/**
	 * Class TorneLIB_NetBits Netbits Library for calculations with bitmasks
	 *
	 * @package TorneLIB
	 * @version 6.0.1
	 */
	class TorneLIB_NetBits {
		/** @var array Standard bitmask setup */
		private $BIT_SETUP;
		private $maxBits = 8;

		function __construct( $bitStructure = array() ) {
			$this->BIT_SETUP = array(
				'OFF'     => 0,
				'BIT_1'   => 1,
				'BIT_2'   => 2,
				'BIT_4'   => 4,
				'BIT_8'   => 8,
				'BIT_16'  => 16,
				'BIT_32'  => 32,
				'BIT_64'  => 64,
				'BIT_128' => 128
			);
			if ( count( $bitStructure ) ) {
				$this->BIT_SETUP = $this->validateBitStructure( $bitStructure );
			}
		}

		public function setMaxBits( $maxBits = 8 ) {
			$this->maxBits = $maxBits;
			$this->validateBitStructure( $maxBits );
		}

		public function getMaxBits() {
			return $this->maxBits;
		}

		private function getRequiredBits( $maxBits = 8 ) {
			$requireArray = array();
			if ( $this->maxBits != $maxBits ) {
				$maxBits = $this->maxBits;
			}
			for ( $curBit = 0; $curBit <= $maxBits; $curBit ++ ) {
				$requireArray[] = (int) pow( 2, $curBit );
			}

			return $requireArray;
		}

		private function validateBitStructure( $bitStructure = array() ) {
			if ( is_numeric( $bitStructure ) ) {
				$newBitStructure = array(
					'OFF' => 0
				);
				for ( $bitIndex = 0; $bitIndex <= $bitStructure; $bitIndex ++ ) {
					$powIndex                              = pow( 2, $bitIndex );
					$newBitStructure[ "BIT_" . $powIndex ] = $powIndex;
				}
				$bitStructure    = $newBitStructure;
				$this->BIT_SETUP = $bitStructure;
			}
			$require                  = $this->getRequiredBits( count( $bitStructure ) );
			$validated                = array();
			$newValidatedBitStructure = array();
			$valueKeys                = array();
			foreach ( $bitStructure as $key => $value ) {
				if ( in_array( $value, $require ) ) {
					$newValidatedBitStructure[ $key ] = $value;
					$valueKeys[ $value ]              = $key;
					$validated[]                      = $value;
				}
			}
			foreach ( $require as $bitIndex ) {
				if ( ! in_array( $bitIndex, $validated ) ) {
					if ( $bitIndex == "0" ) {
						$newValidatedBitStructure["OFF"] = $bitIndex;
					} else {
						$bitIdentificationName                              = "BIT_" . $bitIndex;
						$newValidatedBitStructure[ $bitIdentificationName ] = $bitIndex;
					}
				} else {
					if ( isset( $valueKeys[ $bitIndex ] ) && ! empty( $valueKeys[ $bitIndex ] ) ) {
						$bitIdentificationName                              = $valueKeys[ $bitIndex ];
						$newValidatedBitStructure[ $bitIdentificationName ] = $bitIndex;
					}
				}
			}
			asort( $newValidatedBitStructure );
			$this->BIT_SETUP = $newValidatedBitStructure;

			return $newValidatedBitStructure;
		}

		public function setBitStructure( $bitStructure = array() ) {
			$this->validateBitStructure( $bitStructure );
		}

		public function getBitStructure() {
			return $this->BIT_SETUP;
		}

		/**
		 * Finds out if a bitmasked value is located in a bitarray
		 *
		 * @param int $requestedExistingBit
		 * @param int $requestedBitSum
		 *
		 * @return bool
		 */
		public function isBit( $requestedExistingBit = 0, $requestedBitSum = 0 ) {
			$return = false;
			if ( is_array( $requestedExistingBit ) ) {
				foreach ( $requestedExistingBit as $bitKey ) {
					if ( ! $this->isBit( $bitKey, $requestedBitSum ) ) {
						return false;
					}
				}

				return true;
			}

			// Solution that works with unlimited bits
			for ( $bitCount = 0; $bitCount < count( $this->getBitStructure() ); $bitCount ++ ) {
				if ( $requestedBitSum & pow( 2, $bitCount ) ) {
					if ( $requestedExistingBit == pow( 2, $bitCount ) ) {
						$return = true;
					}
				}
			}

			// Solution that works with bits up to 8
			/*
			$sum = 0;
			preg_match_all("/\d/", sprintf("%08d", decbin( $requestedBitSum)), $bitArray);
			for ($bitCount = count($bitArray[0]); $bitCount >= 0; $bitCount--) {
				if (isset($bitArray[0][$bitCount])) {
					if ( $requestedBitSum & pow(2, $bitCount)) {
						if ( $requestedExistingBit == pow(2, $bitCount)) {
							$return = true;
						}
					}
				}
			}
			*/

			return $return;
		}

		/**
		 * Get active bits in an array
		 *
		 * @param int $bitValue
		 *
		 * @return array
		 */
		public function getBitArray( $bitValue = 0 ) {
			$returnBitList = array();
			foreach ( $this->BIT_SETUP as $key => $value ) {
				if ( $this->isBit( $value, $bitValue ) ) {
					$returnBitList[] = $key;
				}
			}

			return $returnBitList;
		}

	}
}
if ( ! class_exists( 'TorneLIB_Network_IP_Protocols' ) && ! class_exists( 'TorneLIB\TorneLIB_Network_IP_Protocols' ) ) {
	/**
	 * Class TorneLIB_Network_IP IP Address Types class
	 * @package TorneLIB
	 */
	abstract class TorneLIB_Network_IP_Protocols {
		const PROTOCOL_NONE = 0;
		const PROTOCOL_IPV4 = 4;
		const PROTOCOL_IPV6 = 6;
	}

	if ( ! class_exists( 'TorneLIB_Network_IP' ) && ! class_exists( 'TorneLIB\TorneLIB_Network_IP' ) ) {
		/**
		 * Class TorneLIB_Network_IP
		 * @package TorneLIB
		 * @deprecated Use TorneLIB_Network_IP_Protocols
		 */
		abstract class TorneLIB_Network_IP extends TorneLIB_Network_IP_Protocols {
			const IPTYPE_NONE = 0;
			const IPTYPE_V4 = 4;
			const IPTYPE_V6 = 6;
		}
	}
}
if ( ! class_exists( 'Tornevall_SimpleSoap' ) && ! class_exists( 'TorneLIB\Tornevall_SimpleSoap' ) ) {
	/**
	 * Class TorneLIB_SimpleSoap Simple SOAP client.
	 *
	 * Masking no difference of a SOAP call and a regular GET/POST
	 *
	 * @package TorneLIB
	 * @version 6.0.3
	 */
	class Tornevall_SimpleSoap extends Tornevall_cURL {
		protected $soapClient;
		protected $soapOptions = array();
		protected $addSoapOptions = array(
			'exceptions' => true,
			'trace'      => true,
			'cache_wsdl' => 0       // Replacing WSDL_CACHE_NONE (WSDL_CACHE_BOTH = 3)
		);
		private $simpleSoapVersion = "6.0.4";
		private $soapUrl;
		private $AuthData;
		private $soapRequest;
		private $soapRequestHeaders;
		private $soapResponse;
		private $soapResponseHeaders;
		private $libResponse;
		private $canThrowSoapFaults = true;
		private $CustomUserAgent;
		private $soapFaultExceptionObject;
		/** @var Tornevall_cURL */
		private $PARENT;

		private $SoapFaultString = null;
		private $SoapFaultCode = 0;
		private $SoapTryOnce = true;

		/**
		 * Tornevall_SimpleSoap constructor.
		 *
		 * @param string $Url
		 * @param \TorneLIB\Tornevall_cURL
		 */
		function __construct( $Url, $that = null ) {
			// Inherit parent
			parent::__construct();

			/** @var Tornevall_cURL */
			$this->PARENT  = $that;      // Get the parent instance from parent, when parent gives wrong information
			$this->soapUrl = $Url;
			$this->sslGetOptionsStream();
			$this->soapOptions = $this->PARENT->getCurlOpt();
			foreach ( $this->addSoapOptions as $soapKey => $soapValue ) {
				if ( ! isset( $this->soapOptions[ $soapKey ] ) ) {
					$this->soapOptions[ $soapKey ] = $soapValue;
				}
			}
			$this->configureInternals();
		}

		/**
		 * Configure internal data
		 *
		 * @since 6.0.3
		 */
		private function configureInternals() {
			$proxySettings = $this->PARENT->getProxy();

			// SOCKS is currently unsupported by SoapClient
			if ( ! empty( $proxySettings['curlProxy'] ) ) {
				$proxyConfig = explode( ":", $proxySettings['curlProxy'] );
				if ( isset( $proxyConfig[1] ) && ! empty( $proxyConfig[0] ) && $proxyConfig[1] > 0 ) {
					$this->soapOptions['proxy_host'] = $proxyConfig[0];
					$this->soapOptions['proxy_port'] = $proxyConfig[1];
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

		/**
		 * @param $userAgentString
		 */
		public function setCustomUserAgent( $userAgentString ) {
			$this->CustomUserAgent = preg_replace( "/\s+$/", '', $userAgentString );
			$this->setUserAgent( $userAgentString . " +TorneLIB-SimpleSoap/" . $this->simpleSoapVersion );
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
			$sslOpt           = $this->getSslOpt();
			if ( gettype( $sslOpt['stream_context'] ) == "resource" ) {
				$this->soapOptions['stream_context'] = $sslOpt['stream_context'];
			}
			if ( $this->SoapTryOnce ) {
				try {
					$this->soapClient = @new \SoapClient( $this->soapUrl, $this->soapOptions );
				} catch ( \Exception $soapException ) {
					$soapCode = $soapException->getCode();
					if ( ! $soapCode ) {
						$soapCode = 500;
					}
					throw new \Exception( $this->ModuleName . " exception from soapClient: " . $soapException->getMessage(), $soapCode, $soapException );
				}
				if ( ! is_object( $this->soapClient ) ) {
					throw new \Exception( $this->ModuleName . " exception from SimpleSoap->getSoap(): Could not create SoapClient. Make sure that all settings and URLs are correctly configured.", 500 );
				}
			} else {
				try {
					// FailoverMethod is active per default, trying to parry SOAP-sites that requires ?wsdl in the urls
					$this->soapClient = @new \SoapClient( $this->soapUrl, $this->soapOptions );
				} catch ( \Exception $soapException ) {
					if ( isset( $soapException->faultcode ) && $soapException->faultcode == "WSDL" ) {
						// If an exception has been invoked, check if the url contains a ?wsdl or &wsdl - if not, it may be the problem. In that case, retry the call and throw an exception if we fail twice.
						if ( ! preg_match( "/\?wsdl|\&wsdl/i", $this->soapUrl ) ) {
							// Try to determine how the URL is built before trying this.
							if ( preg_match( "/\?/", $this->soapUrl ) ) {
								$this->soapUrl .= "&wsdl";
							} else {
								$this->soapUrl .= "?wsdl";
							}
							try {
								$this->soapClient = @new \SoapClient( $this->soapUrl, $this->soapOptions );
							} catch ( \Exception $soapException ) {
								throw new \Exception( $this->ModuleName . " exception from soapClient: " . $soapException->getMessage(), $soapException->getCode(), $soapException );
							}
						}
					}
				}
				if ( ! is_object( $this->soapClient ) ) {
					// NETCURL_SIMPLESOAP_GETSOAP_CREATE_FAIL
					throw new \Exception( $this->ModuleName . " exception from SimpleSoap->getSoap(): Could not create SoapClient. Make sure that all settings and URLs are correctly configured.", 1008 );
				}
			}

			return $this;
		}

		/**
		 * @param bool $enabledState
		 */
		public function setSoapTryOnce( $enabledState = true ) {
			$this->SoapTryOnce = $enabledState;
		}

		/**
		 * @return bool
		 */
		public function getSoapTryOnce() {
			return $this->SoapTryOnce;
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
				// Collect the response received internally, before throwing
				$this->libResponse              = $returnResponse;
				$this->soapFaultExceptionObject = $e;
				if ( $this->canThrowSoapFaults ) {
					throw new \Exception( $this->ModuleName . " exception from soapClient: " . $e->getMessage(), $e->getCode(), $e );
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
		 * @since 5.0.0
		 * @deprecated 6.0.5 Use getSoapResponse()
		 */
		public function getLibResponse() {
			return $this->libResponse;
		}

		public function getSoapFaultString() {
			return $this->SoapFaultString;
		}

		public function getSoapFaultCode() {
			return $this->SoapFaultCode;
		}


		/**
		 * Get the SOAP response independently on exceptions or successes
		 *
		 * @return mixed
		 * @since 6.0.5
		 */
		public function getSoapResponse() {
			return $this->libResponse;
		}

		/**
		 * Get the last thrown soapfault object
		 *
		 * @return mixed
		 * @since 6.0.5
		 */
		public function getSoapFault() {
			return $this->soapFaultExceptionObject;
		}
	}
}
if ( ! class_exists( 'CURL_METHODS' ) && ! class_exists( 'TorneLIB\CURL_METHODS' ) ) {
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
}
if ( ! class_exists( 'CURL_RESOLVER' ) && ! class_exists( 'TorneLIB\CURL_RESOLVER' ) ) {
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
}
if ( ! class_exists( 'CURL_POST_AS' ) && ! class_exists( 'TorneLIB\CURL_POST_AS' ) ) {
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
}
if ( ! class_exists( 'CURL_AUTH_TYPES' ) && ! class_exists( 'TorneLIB\CURL_AUTH_TYPES' ) ) {
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
}
if ( ! class_exists( 'TORNELIB_CURL_ENVIRONMENT' ) && ! class_exists( 'TorneLIB\TORNELIB_CURL_ENVIRONMENT' ) ) {
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
}
if ( ! class_exists( 'TORNELIB_CURL_RESPONSETYPE' ) && ! class_exists( 'TorneLIB\TORNELIB_CURL_RESPONSETYPE' ) ) {
	/**
	 * Class TORNELIB_CURL_RESPONSETYPE
	 * @package TorneLIB
	 */
	abstract class TORNELIB_CURL_RESPONSETYPE {
		const RESPONSETYPE_ARRAY = 0;
		const RESPONSETYPE_OBJECT = 1;
	}
}
if ( ! class_exists( 'TORNELIB_CURLOBJECT' ) && ! class_exists( 'TorneLIB\TORNELIB_CURLOBJECT' ) ) {
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
