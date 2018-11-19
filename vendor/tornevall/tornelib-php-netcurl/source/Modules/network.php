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
 * Each class in this library has its own version numbering to keep track of where the changes are. However, there is a
 * major version too.
 *
 * @package TorneLIB
 * @version 6.0.6
 */

namespace TorneLIB;

if ( ! class_exists( 'MODULE_NETWORK' ) && ! class_exists( 'TorneLIB\MODULE_NETWORK' ) ) {
	if ( ! defined( 'NETCURL_NETWORK_RELEASE' ) ) {
		define( 'NETCURL_NETWORK_RELEASE', '6.0.6' );
	}
	if ( ! defined( 'NETCURL_NETWORK_MODIFY' ) ) {
		define( 'NETCURL_NETWORK_MODIFY', '20180325' );
	}

	/**
	 * Library for handling network related things (currently not sockets). A conversion of a legacy PHP library called "TorneEngine" and family.
	 *
	 * Class MODULE_NETWORK
	 *
	 * @link    https://phpdoc.tornevall.net/TorneLIBv5/class-TorneLIB.TorneLIB_Network.html PHPDoc/Staging - TorneLIB_Network
	 * @link    https://docs.tornevall.net/x/KQCy TorneLIB (PHP) Landing documentation
	 * @link    https://bitbucket.tornevall.net/projects/LIB/repos/tornelib-php/browse Sources of TorneLIB
	 *
	 * @package TorneLIB
	 */
	class MODULE_NETWORK {
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
			$this->BIT = new MODULE_NETBITS();
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
		 * @param bool   $cleanNonNumerics Normally you do not want to strip anything. This boolean however, decides if we will include non numerical version data in the returned array
		 * @param bool   $sanitizeNumerics If we decide to not include non numeric values from the version tag array (by $cleanNonNumerics), the tags will be sanitized in a preg_replace filter that will the keep numerics in the content only (with $cleanNonNumerics set to false, this boolen will have no effect)
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
			$CURL = new MODULE_CURL();

			/** @noinspection PhpUnusedLocalVariableInspection */
			$code             = 0;
			$exceptionMessage = "";
			try {
				$gitGet  = $CURL->doGet( $gitUrl );
				$code    = intval( $CURL->getCode() );
				$gitBody = $CURL->getBody( $gitGet );
				if ( $code >= 200 && $code <= 299 && ! empty( $gitBody ) ) {
					$fetchFail = false;
					preg_match_all( "/refs\/tags\/(.*?)\n/s", $gitBody, $tagMatches );
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
		 * @throws \Exception
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
		 * @throws \Exception
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
		 * @param string $requestedUrlHost
		 * @param bool   $validateHost Validate that the hostname do exist
		 *
		 * @return array
		 * @throws \Exception
		 */
		public function getUrlDomain( $requestedUrlHost = '', $validateHost = false ) {
			// If the scheme is forgotten, add it to keep normal hosts validatable too.
			if ( ! preg_match( "/\:\/\//", $requestedUrlHost ) ) {
				$requestedUrlHost = "http://" . $requestedUrlHost;
			}
			$urlParsed = parse_url( $requestedUrlHost );
			if ( ! isset( $urlParsed['host'] ) || ! $urlParsed['scheme'] ) {
				return array( null, null, null );
			}
			if ( $validateHost || $this->alwaysResolveHostvalidation === true ) {
				// Make sure that the host is not invalid
				if ( filter_var( $requestedUrlHost, FILTER_VALIDATE_URL ) ) {
					$hostRecord = @dns_get_record( $urlParsed['host'], DNS_ANY );
					if ( ! count( $hostRecord ) ) {
						//return array( null, null, null );
						throw new \Exception( NETCURL_CURL_CLIENTNAME . " " . __FUNCTION__ . " exception: Host validation failed", $this->getExceptionCode( 'NETCURL_HOSTVALIDATION_FAIL' ) );
					}
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
		 * @param       $stringWithUrls
		 * @param int   $offset
		 * @param int   $urlLimit
		 * @param array $protocols
		 * @param bool  $preventDuplicates
		 *
		 * @return array
		 */
		public function getUrlsFromHtml( $stringWithUrls, $offset = - 1, $urlLimit = - 1, $protocols = array( "http" ), $preventDuplicates = true ) {
			$returnArray = array();

			// Pick up all urls by protocol (adding http will include https too)
			foreach ( $protocols as $protocol ) {
				$regex = "@[\"|\']$protocol(.*?)[\"|\']@is";
				preg_match_all( $regex, $stringWithUrls, $matches );
				$urls = array();
				if ( isset( $matches[1] ) && count( $matches[1] ) ) {
					$urls = $matches[1];
				}
				if ( count( $urls ) ) {
					foreach ( $urls as $url ) {
						$trimUrl = trim( $url );
						if ( ! empty( $trimUrl ) ) {
							$prependUrl = $protocol . $url;
							if ( ! $preventDuplicates ) {
								$returnArray[] = $prependUrl;
							} else {
								if ( ! in_array( $prependUrl, $returnArray ) ) {
									$returnArray[] = $prependUrl;
								}
							}
						}
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
		 * @param null   $prefix
		 * @param null   $domain
		 * @param null   $secure
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
		 *
		 * @return bool If successful, this is true
		 */
		private function renderProxyHeaders() {
			if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
				$this->clientAddressList = array( 'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] );
				foreach ( $this->proxyHeaders as $proxyVar ) {
					if ( isset( $_SERVER[ $proxyVar ] ) ) {
						$this->clientAddressList[ $proxyVar ] = $_SERVER[ $proxyVar ];
					}
				}

				return true;
			}

			return false;
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
		 * Make sure we always return a "valid" http-host from HTTP_HOST. If the variable is missing, this will fall back to localhost.
		 *
		 * @return string
		 * @sice 6.0.15
		 */
		public function getHttpHost() {
			$httpHost = ( isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : "" );
			if ( empty( $httpHost ) ) {
				$httpHost = "localhost";
			}

			return $httpHost;
		}

		/**
		 * @param bool $returnProtocol
		 *
		 * @return bool|string
		 * @since 6.0.15
		 */
		public static function getCurrentServerProtocol( $returnProtocol = false ) {
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
		 * @throws \Exception
		 * @since 5.0.0
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
				if ( is_array( $thisdomainArray ) ) {
					$thisdomain = $thisdomainArray[ count( $thisdomainArray ) - 2 ] . "." . $thisdomainArray[ count( $thisdomainArray ) - 1 ];
				}
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
		 * @param bool   $returnIpType
		 *
		 * @return int|string
		 */
		public function getArpaFromAddr( $ipAddr = '', $returnIpType = false ) {
			if ( filter_var( $ipAddr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) !== false ) {
				if ( $returnIpType === true ) {
					$vArpaTest = $this->getArpaFromIpv6( $ipAddr );    // PHP 5.3
					if ( ! empty( $vArpaTest ) ) {
						return NETCURL_IP_PROTOCOLS::PROTOCOL_IPV6;
					} else {
						return NETCURL_IP_PROTOCOLS::PROTOCOL_NONE;
					}
				} else {
					return $this->getArpaFromIpv6( $ipAddr );
				}
			} else if ( filter_var( $ipAddr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) !== false ) {
				if ( $returnIpType ) {
					return NETCURL_IP_PROTOCOLS::PROTOCOL_IPV4;
				} else {
					return $this->getArpaFromIpv4( $ipAddr );
				}
			} else {
				if ( $returnIpType ) {
					return NETCURL_IP_PROTOCOLS::PROTOCOL_NONE;
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

if ( ! class_exists( 'TorneLIB_Network' ) && ! class_exists( 'TorneLIB\TorneLIB_Network' ) ) {
	/**
	 * Class MODULE_CURL
	 *
	 * @package    TorneLIB
	 * @deprecated 6.0.20 Use MODULE_NETWORK
	 */
	class TorneLIB_Network extends MODULE_NETWORK {
		function __construct() {
			parent::__construct();
		}
	}
}