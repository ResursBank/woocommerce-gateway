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
 */

namespace TorneLIB;

if ( ! class_exists( 'NETCURL_DRIVER_CONTROLLER' ) && ! class_exists( 'TorneLIB\NETCURL_DRIVER_CONTROLLER' ) ) {
	/**
	 * Class NETCURL_DRIVERS Network communications driver detection
	 *
	 * @package TorneLIB
	 * @since 6.0.20
	 */
	class NETCURL_DRIVER_CONTROLLER {

		function __construct() {
			$this->NETWORK = new MODULE_NETWORK();
			$this->getDisabledFunctions();
			$this->getInternalDriver();
			$this->getAvailableClasses();
		}

		/**
		 * Class drivers supported by NETCURL
		 * @var array
		 */
		private $DRIVERS_SUPPORTED = array(
			'GuzzleHttp\Client'                => NETCURL_NETWORK_DRIVERS::DRIVER_GUZZLEHTTP,
			'GuzzleHttp\Handler\StreamHandler' => NETCURL_NETWORK_DRIVERS::DRIVER_GUZZLEHTTP_STREAM,
			'WP_Http'                          => NETCURL_NETWORK_DRIVERS::DRIVER_WORDPRESS
		);

		private $DRIVERS_BRIDGED = array(
			'GuzzleHttp\Client'                => 'NETCURL_DRIVER_GUZZLEHTTP',
			'GuzzleHttp\Handler\StreamHandler' => 'NETCURL_DRIVER_GUZZLEHTTP',
			'WP_Http'                          => 'NETCURL_DRIVER_WORDPRESS'
		);

		private $DRIVERS_STREAMABLE = array(
			'GuzzleHttp\Handler\StreamHandler' => 'NETCURL_DRIVER_GUZZLEHTTP'
		);

		/** @var array $DRIVERS_AVAILABLE */
		private $DRIVERS_AVAILABLE = array();

		/** @var array $FUNCTIONS_DISABLED List of functions disabled via php.ini, arrayed */
		private $FUNCTIONS_DISABLED = array();

		/** @var NETCURL_DRIVERS_INTERFACE $DRIVER Preloaded driver when setDriver is used */
		private $DRIVER = null;

		/** @var int $DRIVER_ID */
		private $DRIVER_ID = 0;

		private $URL = array(
			'drivers' => 'https://docs.tornevall.net/x/CYBiAQ#Module:NetCurl-Internaldrivers'
		);

		/**
		 * @var MODULE_NETWORK $NETWORK Handles exceptions
		 */
		private $NETWORK;

		/**
		 * @return string
		 */
		public function getDisabledFunctions() {
			$disabledFunctions        = @ini_get( 'disable_functions' );
			$disabledArray            = array_map( "trim", explode( ",", $disabledFunctions ) );
			$this->FUNCTIONS_DISABLED = is_array( $disabledArray ) ? $disabledArray : array();

			return $this->FUNCTIONS_DISABLED;
		}

		/**
		 * @return bool
		 */
		public function hasCurl() {
			if ( isset( $this->DRIVERS_AVAILABLE[ NETCURL_NETWORK_DRIVERS::DRIVER_CURL ] ) ) {
				return true;
			}

			return false;
		}

		/**
		 * @return NETCURL_DRIVER_CONTROLLER
		 */
		private static function getStatic() {
			return new NETCURL_DRIVER_CONTROLLER();
		}

		/**
		 * @return bool
		 */
		public static function getCurl() {
			return self::getStatic()->hasCurl();
		}


		/**
		 * Checks if it is possible to use the standard setup
		 *
		 * @return bool
		 */
		private function getInternalDriver() {
			if ( function_exists( 'curl_init' ) && function_exists( 'curl_exec' ) ) {
				$this->DRIVERS_AVAILABLE[ NETCURL_NETWORK_DRIVERS::DRIVER_CURL ] = NETCURL_NETWORK_DRIVERS::DRIVER_CURL;

				return true;
			}

			return false;
		}

		private function getAvailableClasses() {
			$DRIVERS_AVAILABLE = array();
			foreach ( $this->DRIVERS_SUPPORTED as $driverClass => $driverClassId ) {
				if ( class_exists( $driverClass ) ) {
					$DRIVERS_AVAILABLE[ $driverClassId ] = $driverClass;
					// Guzzle supports both curl and stream so include it here
					if ( $driverClassId == NETCURL_NETWORK_DRIVERS::DRIVER_GUZZLEHTTP ) {
						if ( ! $this->hasCurl() ) {
							unset( $DRIVERS_AVAILABLE[ NETCURL_NETWORK_DRIVERS::DRIVER_GUZZLEHTTP ] );
						}
						$DRIVERS_AVAILABLE [ NETCURL_NETWORK_DRIVERS::DRIVER_GUZZLEHTTP_STREAM ] = $driverClass;
					}
				}
			}
			$this->DRIVERS_AVAILABLE += $DRIVERS_AVAILABLE;

			return $DRIVERS_AVAILABLE;
		}

		/**
		 * @return int|NETCURL_DRIVERS_INTERFACE
		 * @throws \Exception
		 */
		public function getAutodetectedDriver() {
			if ( $this->hasCurl() ) {
				$this->DRIVER = NETCURL_NETWORK_DRIVERS::DRIVER_CURL;

				return $this->DRIVER;
			} else {
				if ( is_array( $this->DRIVERS_AVAILABLE ) && count( $this->DRIVERS_AVAILABLE ) ) {
					$availableDriverIds = array_keys( $this->DRIVERS_AVAILABLE );
					$nextDriver         = array_pop( $availableDriverIds );
					$this->setDriver( $nextDriver );

					return $this->DRIVER;
				} else {
					throw new \Exception( NETCURL_CURL_CLIENTNAME . " NetCurlDriverException: No communication drivers are currently available (not even curl).", $this->NETWORK->getExceptionCode( 'NETCURL_NO_DRIVER_AVAILABLE' ) );
				}
			}
		}

		public static function setAutoDetect() {
			return self::getStatic()->getAutodetectedDriver();
		}

		/**
		 * Get list of available drivers
		 *
		 * @return array
		 */
		public function getSystemWideDrivers() {
			return $this->DRIVERS_AVAILABLE;
		}

		/**
		 * Get status of disabled function
		 *
		 * @param string $functionName
		 *
		 * @return bool
		 */
		public function getIsDisabled( $functionName = '' ) {
			if ( is_string( $functionName ) ) {
				if ( preg_match( "/,/", $functionName ) ) {
					$findMultiple = array_map( "trim", explode( ",", $functionName ) );

					return $this->getIsDisabled( $findMultiple );
				}
				if ( in_array( $functionName, $this->FUNCTIONS_DISABLED ) ) {
					return true;
				}
			} else if ( is_array( $functionName ) ) {
				foreach ( array_map( "strtolower", $functionName ) as $findFunction ) {
					if ( in_array( $findFunction, $this->FUNCTIONS_DISABLED ) ) {
						return true;
					}
				}
			}

			return false;
		}

		/**
		 * Set up driver by class name
		 *
		 * @param int $driverId
		 * @param array $parameters
		 *
		 * @return NETCURL_DRIVERS_INTERFACE
		 */
		private function getDriverByClass( $driverId = NETCURL_NETWORK_DRIVERS::DRIVER_NOT_SET, $parameters = null, $ownClass = null ) {
			$driverClass = isset( $this->DRIVERS_AVAILABLE[ $driverId ] ) ? $this->DRIVERS_AVAILABLE[ $driverId ] : null;
			/** @var NETCURL_DRIVERS_INTERFACE $newDriver */
			$newDriver       = null;
			$bridgeClassName = "";

			// Guzzle primary driver is based on curl, so we'll check if curl is available
			if ( $driverId == NETCURL_NETWORK_DRIVERS::DRIVER_GUZZLEHTTP && ! $this->hasCurl() ) {
				// If curl is unavailable, we'll fall  back to guzzleStream
				$driverId = NETCURL_NETWORK_DRIVERS::DRIVER_GUZZLEHTTP_STREAM;
			}

			if ( ! is_null( $ownClass ) && class_exists( $ownClass ) ) {
				if ( is_null( $parameters ) ) {
					$newDriver = new $ownClass();
				} else {
					$newDriver = new $ownClass( $parameters );
				}

				return $newDriver;
			}

			if ( class_exists( $driverClass ) ) {
				if ( isset( $this->DRIVERS_BRIDGED[ $driverClass ] ) ) {
					if ( class_exists( $this->DRIVERS_BRIDGED[ $driverClass ] ) ) {
						$bridgeClassName = $this->DRIVERS_BRIDGED[ $driverClass ];
					} else if ( class_exists( '\\TorneLIB\\' . $this->DRIVERS_BRIDGED[ $driverClass ] ) ) {
						$bridgeClassName = '\\TorneLIB\\' . $this->DRIVERS_BRIDGED[ $driverClass ];
					}
					if ( is_null( $parameters ) ) {
						$newDriver = new $bridgeClassName();
					} else {
						$newDriver = new $bridgeClassName( $parameters );
					}
				} else {
					if ( is_null( $parameters ) ) {
						$newDriver = new $driverClass();
					} else {
						$newDriver = new $driverClass( $parameters );
					}
				}
				// Follow standards for internal bridges if method exists, otherwise skip this part. By doing this, we'd be able to import and directly use external drivers.
				if ( ! is_null( $newDriver ) && method_exists( $newDriver, 'setDriverId' ) ) {
					$newDriver->setDriverId( $driverId );
				}
			}

			$this->DRIVER = $newDriver;

			return $newDriver;
		}

		/**
		 * @param int $driverNameConstans
		 *
		 * @return bool
		 */
		public function getIsDriver( $driverNameConstans = NETCURL_NETWORK_DRIVERS::DRIVER_CURL ) {
			if ( isset( $this->DRIVERS_AVAILABLE[ $driverNameConstans ] ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Initialize driver
		 *
		 * @param int $netDriver
		 * @param null $parameters
		 * @param null $ownClass
		 *
		 * @return int|NETCURL_DRIVERS_INTERFACE
		 * @throws \Exception
		 */
		public function setDriver( $netDriver = NETCURL_NETWORK_DRIVERS::DRIVER_CURL, $parameters = null, $ownClass = null ) {
			$this->DRIVER = null;

			return $this->getDriver( $netDriver, $parameters, $ownClass );
		}

		/**
		 * @param int $netDriver
		 * @param null $parameters
		 * @param null $ownClass
		 *
		 * @return int|NETCURL_DRIVERS_INTERFACE
		 * @throws \Exception
		 */
		public function getDriver( $netDriver = NETCURL_NETWORK_DRIVERS::DRIVER_CURL, $parameters = null, $ownClass = null ) {

			if ( is_object( $this->DRIVER ) ) {
				return $this->DRIVER;
			}

			if ( ! is_null( $ownClass ) && class_exists( $ownClass ) ) {
				$this->DRIVER    = $this->getDriverByClass( $netDriver, $parameters, $ownClass );
				$this->DRIVER_ID = $netDriver;

				return $this->DRIVER;
			}

			if ( $this->getIsDriver( $netDriver ) ) {
				if ( is_string( $this->DRIVERS_AVAILABLE[ $netDriver ] ) && ! is_numeric( $this->DRIVERS_AVAILABLE[ $netDriver ] ) ) {
					/** @var NETCURL_DRIVERS_INTERFACE DRIVER */
					$this->DRIVER = $this->getDriverByClass( $netDriver, $parameters, $ownClass );
				} else if ( is_numeric( $this->DRIVERS_AVAILABLE[ $netDriver ] ) && $this->DRIVERS_AVAILABLE[ $netDriver ] == $netDriver ) {
					$this->DRIVER = $netDriver;
				}
				$this->DRIVER_ID = $netDriver;

			} else {
				if ( $this->hasCurl() ) {
					$this->DRIVER    = NETCURL_NETWORK_DRIVERS::DRIVER_CURL;
					$this->DRIVER_ID = NETCURL_NETWORK_DRIVERS::DRIVER_CURL;
				} else {
					// Last resort: Check if there is any other driver available if this fails
					$testDriverAvailability = $this->getAutodetectedDriver();
					if ( is_object( $testDriverAvailability ) ) {
						$this->DRIVER = $testDriverAvailability;
					} else {
						throw new \Exception( NETCURL_CURL_CLIENTNAME . " NetCurlDriverException: No communication drivers are currently available (not even curl).", $this->NETWORK->getExceptionCode( 'NETCURL_NO_DRIVER_AVAILABLE' ) );
					}
				}
			}

			return $this->DRIVER;
		}

		public function getDriverById() {
			return $this->DRIVER_ID;
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


	}
}