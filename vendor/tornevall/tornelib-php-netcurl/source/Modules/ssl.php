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
 * Each class in this library has its own version numbering to keep track of where the changes are. However, there is a major version too.
 * @package TorneLIB
 * @version 6.0.0
 */

namespace TorneLIB;

if ( ! class_exists( 'MODULE_SSL' ) && ! class_exists( 'TorneLIB\MODULE_SSL' ) ) {

	if ( ! defined( 'NETCURL_SSL_RELEASE' ) ) {
		define( 'NETCURL_SSL_RELEASE', '6.0.0' );
	}
	if ( ! defined( 'NETCURL_SSL_MODIFY' ) ) {
		define( 'NETCURL_SSL_MODIFY', '20180325' );
	}
	if ( ! defined( 'NETCURL_SSL_CLIENTNAME' ) ) {
		define( 'NETCURL_SSL_CLIENTNAME', 'MODULE_SSL' );
	}

	/**
	 * Class MODULE_SSL SSL Helper class
	 * @package TorneLIB
	 */
	class MODULE_SSL {
		/** @var bool Do not test certificates on older PHP-version (< 5.6.0) if this is false */
		private $sslDriverError = array();
		/** @var bool If SSL has been compiled in CURL, this will transform to true */
		private $sslCurlDriver = false;

		/** @var array Default paths to the certificates we are looking for */
		private $sslPemLocations = array( '/etc/ssl/certs' );
		/** @var array Files to look for in sslPemLocations */
		private $sslPemFiles = array( 'cacert.pem', 'ca-certificates.crt' );
		/** @var string Location of the SSL certificate bundle */
		private $sslRealCertLocation;
		/** @var bool Strict verification of the connection (sslVerify) */
		private $SSL_STRICT_VERIFICATION = true;
		/** @var null|bool Allow self signed certificates */
		private $SSL_STRICT_SELF_SIGNED = true;
		/** @var bool Allowing fallback/failover to unstict verification */
		private $SSL_STRICT_FAILOVER = false;

		/** @var MODULE_CURL $PARENT */
		private $PARENT;
		/** @var MODULE_NETWORK $NETWORK */
		private $NETWORK;

		private $sslopt = array();

		/**
		 * MODULE_SSL constructor.
		 *
		 * @param MODULE_CURL $MODULE_CURL
		 */
		function __construct( $MODULE_CURL = null ) {
			if ( is_object( $MODULE_CURL ) ) {
				$this->PARENT = $MODULE_CURL;
			}
			$this->NETWORK = new MODULE_NETWORK();
		}

		/**
		 * @return array
		 * @since 6.0.0
		 */
		public static function getCurlSslAvailable() {
			// Common ssl checkers (if they fail, there is a sslDriverError to recall

			$sslDriverError = array();
			if ( ! in_array( 'https', @stream_get_wrappers() ) ) {
				$sslDriverError[] = "SSL Failure: HTTPS wrapper can not be found";
			}
			if ( ! extension_loaded( 'openssl' ) ) {
				$sslDriverError[] = "SSL Failure: HTTPS extension can not be found";
			}

			if ( function_exists( 'curl_version' ) ) {
				$curlVersionRequest = curl_version();
				$curlVersion        = $curlVersionRequest['version'];
				if ( defined( 'CURL_VERSION_SSL' ) ) {
					if ( isset( $curlVersionRequest['features'] ) ) {
						$CURL_SSL_AVAILABLE = ( $curlVersionRequest['features'] & CURL_VERSION_SSL ? true : false );
						if ( ! $CURL_SSL_AVAILABLE ) {
							$sslDriverError[] = 'SSL Failure: Protocol "https" not supported or disabled in libcurl';
						}
					} else {
						$sslDriverError[] = "SSL Failure: CurlVersionFeaturesList does not return any feature (this should not be happen)";
					}
				}
			}

			return $sslDriverError;
		}

		/**
		 * Returns true if no errors occured in the control
		 * @return bool
		 */
		public static function hasSsl() {
			if (!count(self::getCurlSslAvailable())) {
				return true;
			}
			return false;
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
		 * openssl_guess rewrite
		 *
		 * @return string
		 * @since 6.0.0
		 */
		public function getSslCertificateBundle( $forceChecking = false ) {
			// Assume that sysadmins can handle this, if open_basedir is set as things will fail if we proceed here
			if ( $this->getIsSecure( false ) && ! $forceChecking ) {
				return;
			}

			foreach ( $this->sslPemLocations as $filePath ) {
				if ( is_dir( $filePath ) && ! in_array( $filePath, $this->sslPemLocations ) ) {
					$this->sslPemLocations[] = $filePath;
				}
			}

			// If PHP >= 5.6.0, the OpenSSL module has its own way getting certificate locations
			if ( version_compare( PHP_VERSION, "5.6.0", ">=" ) && function_exists( "openssl_get_cert_locations" ) ) {
				$internalCheck = openssl_get_cert_locations();
				if ( isset( $internalCheck['default_cert_dir'] ) && is_dir( $internalCheck['default_cert_dir'] ) && ! empty( $internalCheck['default_cert_file'] ) ) {
					$certFile = basename( $internalCheck['default_cert_file'] );
					if ( ! in_array( $internalCheck['default_cert_dir'], $this->sslPemLocations ) ) {
						$this->sslPemLocations[] = $internalCheck['default_cert_dir'];
					}
					if ( ! in_array( $certFile, $this->sslPemFiles ) ) {
						$this->sslPemFiles[] = $certFile;
					}
				}
			}

			// get first match
			foreach ( $this->sslPemLocations as $location ) {
				foreach ( $this->sslPemFiles as $file ) {
					$fullCertPath = $location . "/" . $file;
					if ( file_exists( $fullCertPath ) && empty( $this->sslRealCertLocation ) ) {
						$this->sslRealCertLocation = $fullCertPath;
					}
				}
			}

			return $this->sslRealCertLocation;
		}

		/**
		 * @param array $pemLocationData
		 *
		 * @return bool
		 * @throws \Exception
		 * @since 6.0.20
		 */
		public function setPemLocation( $pemLocationData = array() ) {
			$failAdd = false;
			if ( is_string( $pemLocationData ) ) {
				$pemLocationData = array( $pemLocationData );
			}
			if ( is_array( $pemLocationData ) && is_array( $pemLocationData ) ) {
				foreach ( $pemLocationData as $pemDataRow ) {
					$pemDataRow = trim( preg_replace( "/\/$/", '', $pemDataRow ) );
					$pemFile    = $pemDataRow;
					$pemDir     = dirname( $pemDataRow );
					if ( $pemFile != $pemDir && is_file( $pemFile ) ) {
						$this->sslPemFiles[]     = $pemFile;
						$this->sslPemLocations[] = $pemDir;
					} else {
						$failAdd = true;
					}
				}
			}
			if ( $failAdd ) {
				throw new \Exception( NETCURL_CURL_CLIENTNAME . " " . __FUNCTION__ . " exception: The format of pemLocationData is not properly set", $this->NETWORK->getExceptionCode( 'NETCURL_PEMLOCATIONDATA_FORMAT_ERROR' ) );
			}

			return true;
		}

		public function getPemLocations() {
			return $this->sslPemLocations;
		}

		/**
		 * Set the rules of how to verify SSL certificates
		 *
		 * @param bool $strictCertificateVerification
		 * @param bool $prohibitSelfSigned This only covers streams
		 *
		 * @since 6.0.0
		 */
		public function setStrictVerification( $strictCertificateVerification = true, $prohibitSelfSigned = true ) {
			$this->SSL_STRICT_VERIFICATION = $strictCertificateVerification;
			$this->SSL_STRICT_SELF_SIGNED  = $prohibitSelfSigned;
		}

		/**
		 * Returns the mode of strict verification set up. If true, netcurl will be very strict with all certificate verifications.
		 *
		 * @return bool
		 * @since 6.0.0
		 */
		public function getStrictVerification() {
			return $this->SSL_STRICT_VERIFICATION;
		}

		/**
		 *
		 * @return bool|null
		 */
		public function getStrictSelfSignedVerification() {
			// If this is not set, assume we want the value hardened
			return $this->SSL_STRICT_SELF_SIGNED;
		}

		/**
		 * Allow NetCurl to make failover (fallback) to unstrict SSL verification after a strict call has been made
		 *
		 * Replacement for allowSslUnverified setup
		 *
		 * @param bool $sslFailoverEnabled *
		 *
		 * @since 6.0.0
		 */
		public function setStrictFallback( $sslFailoverEnabled = false ) {
			$this->SSL_STRICT_FAILOVER = $sslFailoverEnabled;
		}

		/**
		 * @return bool
		 * @since 6.0.0
		 */
		public function getStrictFallback() {
			return $this->SSL_STRICT_FAILOVER;
		}

		/**
		 * Prepare context stream for SSL
		 *
		 * @return array
		 *
		 * @since 6.0.0
		 */
		public function getSslStreamContext() {
			$sslCaBundle = $this->getSslCertificateBundle();
			/** @var array $contextGenerateArray Default stream context array, does not contain a ca bundle */
			$contextGenerateArray = array(
				'verify_peer'       => $this->SSL_STRICT_VERIFICATION,
				'verify_peer_name'  => $this->SSL_STRICT_VERIFICATION,
				'verify_host'       => $this->SSL_STRICT_VERIFICATION,
				'allow_self_signed' => $this->SSL_STRICT_SELF_SIGNED,
			);
			// During tests, this bundle might disappear depending on what happens in tests. If something fails, that might render
			// strange false alarms, so we'll just add the file into the array if it's set. Many tests in a row can strangely have this effect.
			if (!empty($sslCaBundle)) {
				$contextGenerateArray['cafile'] = $sslCaBundle;
			}
			return $contextGenerateArray;
		}

		/**
		 * Put the context into stream for SSL
		 *
		 * @param array $optionsArray
		 * @param array $addonContextData
		 *
		 * @return array
		 * @since 6.0.0
		 */
		public function getSslStream( $optionsArray = array(), $addonContextData = array() ) {
			$streamContextOptions = array();
			if ( is_object( $this->PARENT ) ) {
				$this->PARENT->setUserAgent(NETCURL_SSL_CLIENTNAME . "-" . NETCURL_SSL_RELEASE);
				$streamContextOptions['http'] = array(
					"user_agent" => $this->PARENT->getUserAgent()
				);
			}
			$sslCorrection = $this->getSslStreamContext();
			if ( count( $sslCorrection ) ) {
				$streamContextOptions['ssl'] = $this->getSslStreamContext();
			}
			if ( is_array( $addonContextData ) && count( $addonContextData ) ) {
				foreach ( $addonContextData as $contextKey => $contextValue ) {
					$streamContextOptions[ $contextKey ] = $contextValue;
				}
			}
			$optionsArray['stream_context'] = stream_context_create( $streamContextOptions );
			$this->sslopt                   = $optionsArray;

			return $optionsArray;

		}
	}
}