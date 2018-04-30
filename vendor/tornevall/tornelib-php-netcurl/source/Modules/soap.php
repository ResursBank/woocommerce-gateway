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
 * @version 6.0.6
 */

namespace TorneLIB;

if ( ! class_exists( 'MODULE_SOAP' ) && ! class_exists( 'TorneLIB\MODULE_SOAP' ) ) {

	if ( ! defined( 'NETCURL_SIMPLESOAP_RELEASE' ) ) {
		define( 'NETCURL_SIMPLESOAP_RELEASE', '6.0.6' );
	}
	if ( ! defined( 'NETCURL_SIMPLESOAP_MODIFY' ) ) {
		define( 'NETCURL_SIMPLESOAP_MODIFY', '20180325' );
	}
	if ( ! defined( 'NETCURL_SIMPLESOAP_CLIENTNAME' ) ) {
		define( 'NETCURL_SIMPLESOAP_CLIENTNAME', 'SimpleSOAP' );
	}

	/**
	 * Class TorneLIB_SimpleSoap Simple SOAP client.
	 *
	 * Masking no difference of a SOAP call and a regular GET/POST
	 *
	 * @package TorneLIB
	 */
	class MODULE_SOAP extends MODULE_CURL {
		protected $soapClient;
		protected $soapOptions = array();
		protected $addSoapOptions = array(
			'exceptions' => true,
			'trace'      => true,
			'cache_wsdl' => 0       // Replacing WSDL_CACHE_NONE (WSDL_CACHE_BOTH = 3)
		);
		private $simpleSoapVersion = NETCURL_SIMPLESOAP_RELEASE;
		private $soapUrl;
		private $AuthData;
		private $soapRequest;
		private $soapRequestHeaders;
		private $soapResponse;
		private $soapResponseHeaders;
		private $libResponse;
		private $canThrowSoapFaults = true;
		private $CUSTOM_USER_AGENT;
		private $soapFaultExceptionObject;
		/** @var MODULE_CURL */
		private $PARENT;

		private $sslopt = array();
		private $SoapFaultString = null;
		private $SoapFaultCode = 0;
		private $SoapTryOnce = true;

		private $soapInitException = array( 'faultstring' => '', 'code' => 0 );

		/**
		 * MODULE_SOAP constructor.
		 *
		 * @param $Url
		 * @param null $that
		 *
		 * @throws \Exception
		 */
		function __construct( $Url, $that = null ) {
			// Inherit parent
			parent::__construct();

			/** @var MODULE_CURL */
			$this->PARENT      = $that;      // Get the parent instance from parent, when parent gives wrong information
			$this->soapUrl     = $Url;
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
		 *
		 * @throws \Exception
		 */
		public function setCustomUserAgent( $userAgentString ) {
			$this->setUserAgent( NETCURL_SIMPLESOAP_CLIENTNAME . "-" . NETCURL_SIMPLESOAP_RELEASE, $userAgentString );
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
			//$optionsStream    = $this->sslGetOptionsStream();
			$optionsStream = $this->PARENT->sslGetOptionsStream();

			if ( is_array( $optionsStream ) && count( $optionsStream ) ) {
				foreach ( $optionsStream as $optionKey => $optionValue ) {
					$this->soapOptions[ $optionKey ] = $optionValue;
				}
			}

			if ( isset( $sslOpt['stream_context'] ) ) {
				if ( gettype( $sslOpt['stream_context'] ) == "resource" ) {
					$this->soapOptions['stream_context'] = $sslOpt['stream_context'];
				}
			}

			$this->soapOptions['exceptions'] = true;
			$this->soapOptions['trace']      = true;

			$throwErrorMessage = null;
			$throwErrorCode    = null;
			$throwBackCurrent  = null;
			//$throwPrevious     = null;
			$soapFaultOnInit = false;

			$parentFlags = $this->PARENT->getFlags();
			foreach ( $parentFlags as $flagKey => $flagValue ) {
				$this->setFlag( $flagKey, $flagValue );
			}

			if ( $this->SoapTryOnce ) {
				try {
					$this->soapClient = @new \SoapClient( $this->soapUrl, $this->soapOptions );
				} catch ( \Exception $soapException ) {
					$soapCode = $soapException->getCode();
					if ( ! $soapCode ) {
						$soapCode = 500;
					}
					$throwErrorMessage = NETCURL_CURL_CLIENTNAME . " exception from soapClient: " . $soapException->getMessage();
					$throwErrorCode    = $soapCode;
					$throwBackCurrent  = $soapException;
					//$throwPrevious     = $soapException->getPrevious();
					if ( isset( $parentFlags['SOAPWARNINGS'] ) && $parentFlags['SOAPWARNINGS'] === true ) {
						$soapFaultOnInit = true;
					}
				}

				// If we get an error immediately on the first call, lets find out if there are any warnings we need to know about...
				if ( $soapFaultOnInit ) {
					set_error_handler( function ( $errNo, $errStr ) {
						$throwErrorMessage = $errStr;
						$throwErrorCode    = $errNo;
						if ( empty( $this->soapInitException['faultstring'] ) ) {
							$this->soapInitException['faultstring'] = $throwErrorMessage;
						}
						if ( empty( $this->soapInitException['code'] ) ) {
							$this->soapInitException['code'] = $throwErrorCode;
						}
					}, E_ALL );
					try {
						$this->soapClient = @new \SoapClient( $this->soapUrl, $this->soapOptions );
					} catch ( \Exception $e ) {
						if ( $this->soapInitException['faultstring'] !== $e->getMessage() ) {
							$throwErrorMessage = $this->soapInitException['faultstring'] . "\n" . $e->getMessage();
							$throwErrorCode    = $this->soapInitException['code'];
							if ( preg_match( "/http request failed/i", $throwErrorMessage ) && preg_match( "/http\/(.*?) \d+ (.*?)/i", $throwErrorMessage ) ) {
								preg_match_all( "/! (http\/\d+\.\d+ \d+ (.*?))\n/is", $throwErrorMessage, $outInfo );
								if ( isset( $outInfo[1] ) && isset( $outInfo[1][0] ) && preg_match( "/^HTTP\//", $outInfo[1][0] ) ) {
									$httpError      = $outInfo[1][0];
									$httpSplitError = explode( " ", $httpError );
									if ( isset( $httpSplitError[1] ) && intval( $httpSplitError[1] ) > 0 ) {
										$throwErrorCode = $httpSplitError[1];
										if ( isset( $httpSplitError[2] ) && is_string( $httpSplitError[2] ) && ! empty( $httpSplitError[2] ) ) {
											if ( ! isset( $parentFlags['SOAPWARNINGS_EXTEND'] ) ) {
												unset( $throwErrorMessage );
											}
											$throwErrorMessage = "HTTP-Request exception (" . $throwErrorCode . "): " . $httpSplitError[1] . " " . trim( $httpSplitError[2] ) . "\n" . $throwErrorMessage;
										}
									}
								}
							}
						}
					}
					restore_error_handler();
				}

				if ( ! is_object( $this->soapClient ) && is_null( $throwErrorCode ) ) {
					$throwErrorMessage = NETCURL_CURL_CLIENTNAME . " exception from SimpleSoap->getSoap(): Could not create SoapClient. Make sure that all settings and URLs are correctly configured.";
					$throwErrorCode    = 500;
				}
				if ( ! is_null( $throwErrorMessage ) || ! is_null( $throwErrorCode ) ) {
					throw new \Exception( $throwErrorMessage, $throwErrorCode, $throwBackCurrent );
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
							$this->SoapTryOnce = true;
							$this->getSoap();
						}
					}
				}
				if ( ! is_object( $this->soapClient ) ) {
					// NETCURL_SIMPLESOAP_GETSOAP_CREATE_FAIL
					throw new \Exception( NETCURL_CURL_CLIENTNAME . " exception from SimpleSoap->getSoap(): Could not create SoapClient. Make sure that all settings and URLs are correctly configured.", 1008 );
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
				//$parsedHeader              = $this->getHeader( $this->soapResponseHeaders );
				$this->netcurl_split_raw( $this->soapResponseHeaders );
				$returnResponse['header']       = $this->getHeader();
				$returnResponse['code']         = $this->getCode();
				$returnResponse['body']         = $this->soapResponse;
				$returnResponse['parsed']       = $SoapClientResponse;
				$this->libResponse              = $returnResponse;
				$this->soapFaultExceptionObject = $e;
				if ( $this->canThrowSoapFaults ) {
					$exceptionCode = $e->getCode();
					if ( ! $exceptionCode && $this->getCode() > 0 ) {
						$exceptionCode = $this->getCode();
					}
					throw new \Exception( NETCURL_CURL_CLIENTNAME . " exception from soapClient: " . $e->getMessage(), $exceptionCode, $e );
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
			$headerAndBody             = $this->soapResponseHeaders . "\r\n" . $this->soapResponse; // Own row for debugging

			$this->getHeader( $headerAndBody );
			$returnResponse['parsed'] = $SoapClientResponse;
			if ( isset( $SoapClientResponse->return ) ) {
				$returnResponse['parsed'] = $SoapClientResponse->return;
			}
			$returnResponse['header'] = $this->getHeader();
			$returnResponse['code']   = $this->getCode();
			$returnResponse['body']   = $this->getBody();
			$this->libResponse        = $returnResponse;

			$this->NETCURL_RESPONSE_RAW              = $headerAndBody;
			$this->NETCURL_RESPONSE_CONTAINER_PARSED = $returnResponse['parsed'];
			$this->NETCURL_RESPONSE_CONTAINER_CODE   = $this->getCode();
			$this->NETCURL_RESPONSE_CONTAINER_BODY   = $this->getBody();
			$this->NETCURL_RESPONSE_CONTAINER_HEADER = $this->getHeader();
			$this->NETCURL_RESPONSE_CONTAINER        = $returnResponse;
			$this->NETCURL_REQUEST_HEADERS           = $this->soapRequestHeaders;
			$this->NETCURL_REQUEST_BODY              = $this->soapRequest;

			if ( ! is_null( $this->PARENT ) ) {
				$this->PARENT->NETCURL_RESPONSE_RAW              = $this->NETCURL_RESPONSE_RAW;
				$this->PARENT->NETCURL_RESPONSE_CONTAINER_PARSED = $this->NETCURL_RESPONSE_CONTAINER_PARSED;
				$this->PARENT->NETCURL_RESPONSE_CONTAINER_CODE   = $this->NETCURL_RESPONSE_CONTAINER_CODE;
				$this->PARENT->NETCURL_RESPONSE_CONTAINER_BODY   = $this->NETCURL_RESPONSE_CONTAINER_BODY;
				$this->PARENT->NETCURL_RESPONSE_CONTAINER_HEADER = $this->NETCURL_RESPONSE_CONTAINER_HTTPMESSAGE;
				$this->PARENT->NETCURL_RESPONSE_CONTAINER        = $this->NETCURL_RESPONSE_CONTAINER;
				$this->PARENT->NETCURL_REQUEST_HEADERS           = $this->soapRequestHeaders;
				$this->PARENT->NETCURL_REQUEST_BODY              = $this->soapRequest;
			}

			// HTTPMESSAGE is not applicable for this section
			//$this->NETCURL_RESPONSE_CONTAINER_HTTPMESSAGE = trim( $httpMessage );

			if ( $this->isFlag( 'SOAPCHAIN' ) && isset( $returnResponse['parsed'] ) && ! empty( $returnResponse['parsed'] ) ) {
				return $returnResponse['parsed'];
			}

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

	if ( ! class_exists( 'Tornevall_SimpleSoap' ) && ! class_exists( 'TorneLIB\Tornevall_SimpleSoap' ) ) {
		/**
		 * Class MODULE_CURL
		 * @package TorneLIB
		 */
		class Tornevall_SimpleSoap extends MODULE_SOAP {
			function __construct( string $Url, $that = null ) {
				parent::__construct( $Url, $that );
			}
		}
	}
}