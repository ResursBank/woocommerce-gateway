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

if ( ! class_exists( 'NETCURL_DRIVER_WORDPRESS' ) && ! class_exists( 'TorneLIB\NETCURL_DRIVER_WORDPRESS' ) ) {
	/**
	 * Class NETCURL_DRIVERS Network communications driver detection
	 *
	 * @package TorneLIB
	 * @since 6.0.20
	 */
	class NETCURL_DRIVER_WORDPRESS implements NETCURL_DRIVERS_INTERFACE {

		/** @var MODULE_NETWORK $NETWORK */
		private $NETWORK;

		/** @var MODULE_IO */
		private $IO;

		/** @var NETCURL_NETWORK_DRIVERS $DRIVER_ID */
		private $DRIVER_ID = NETCURL_NETWORK_DRIVERS::DRIVER_WORDPRESS;

		/** @var array Inbound parameters in the format array, object or whatever this driver takes */
		private $PARAMETERS = array();

		/** @var \WP_Http $DRIVER */
		private $DRIVER;

		/** @var \stdClass $TRANSPORT Wordpress transport layer */
		private $TRANSPORT;

		/** @var string $POST_CONTENT_TYPE Content type */
		private $POST_CONTENT_TYPE = '';

		/**
		 * @var array $POST_AUTH_DATA
		 */
		private $POST_AUTH_DATA = array();

		/** @var $WORKER_DATA */
		private $WORKER_DATA = array();

		/** @var int $HTTP_STATUS */
		private $HTTP_STATUS = 0;

		/** @var string $HTTP_MESSAGE */
		private $HTTP_MESSAGE = '';

		/** @var string $RESPONSE_RAW */
		private $RESPONSE_RAW = '';

		/** @var string $REQUEST_URL */
		private $REQUEST_URL = '';

		/** @var NETCURL_POST_METHODS */
		private $POST_METHOD = NETCURL_POST_METHODS::METHOD_GET;

		/** @var array $POST_DATA ... or string, or object, etc */
		private $POST_DATA;

		/** @var NETCURL_POST_DATATYPES */
		private $POST_DATA_TYPE = NETCURL_POST_DATATYPES::DATATYPE_NOT_SET;


		public function __construct( $parameters = null ) {
			$this->NETWORK = new MODULE_NETWORK();
			$this->IO      = new MODULE_IO();
		}

		public function setDriverId( $driverId = NETCURL_NETWORK_DRIVERS::DRIVER_NOT_SET ) {
			$this->DRIVER_ID = $driverId;
		}

		public function setParameters( $parameters = array() ) {
			$this->PARAMETERS = $parameters;
		}

		public function setContentType( $setContentTypeString = 'application/json; charset=utf-8' ) {
			$this->POST_CONTENT_TYPE = $setContentTypeString;
		}

		public function getContentType() {
			return $this->POST_CONTENT_TYPE;
		}

		/**
		 * @param null $Username
		 * @param null $Password
		 * @param int $AuthType
		 */
		public function setAuthentication( $Username = null, $Password = null, $AuthType = NETCURL_AUTH_TYPES::AUTHTYPE_BASIC ) {
			$this->POST_AUTH_DATA['Username'] = $Username;
			$this->POST_AUTH_DATA['Password'] = $Password;
			$this->POST_AUTH_DATA['Type']     = $AuthType;
		}

		public function getAuthentication() {
			return $this->POST_AUTH_DATA;
		}

		public function getWorker() {
			return $this->WORKER_DATA;
		}

		public function getRawResponse() {
			return $this->RESPONSE_RAW;
		}

		public function getStatusCode() {
			return $this->HTTP_STATUS;
		}

		public function getStatusMessage() {
			return $this->HTTP_MESSAGE;
		}

		private function initializeClass() {
			$this->DRIVER    = new \WP_Http();
			$this->TRANSPORT = $this->DRIVER->_get_first_available_transport( array() );

			if ( empty( $this->TRANSPORT ) ) {
				throw new \Exception( NETCURL_CURL_CLIENTNAME . " " . __FUNCTION__ . " exception: Could not find any available transport for WordPress Driver", $this->NETWORK->getExceptionCode( 'NETCURL_WP_TRANSPORT_ERROR' ) );
			}
		}

		private function getWp() {
			$postThis = array( 'body' => $this->POST_DATA );
			if ( $this->POST_DATA_TYPE == NETCURL_POST_DATATYPES::DATATYPE_JSON ) {
				$postThis['headers'] = array( "content-type" => "application-json" );
				$postThis['body']    = $this->IO->renderJson( $this->POST_DATA );
			}

			$wpResponse = null;
			if ( $this->POST_METHOD == NETCURL_POST_METHODS::METHOD_HEAD ) {
				$wpResponse = $this->DRIVER->head( $this->REQUEST_URL, $postThis );
			} else if ( $this->POST_METHOD == NETCURL_POST_METHODS::METHOD_POST ) {
				$wpResponse = $this->DRIVER->post( $this->REQUEST_URL, $postThis );
			} else if ( $this->POST_METHOD == NETCURL_POST_METHODS::METHOD_REQUEST ) {
				$wpResponse = $this->DRIVER->request( $this->REQUEST_URL, $postThis );
			} else {
				$wpResponse = $this->DRIVER->get( $this->REQUEST_URL, $postThis );
			}

			/** @var $httpResponse \WP_HTTP_Requests_Response */
			$httpResponse = $wpResponse['http_response'];
			/** @var $httpReponseObject \Requests_Response */
			$httpResponseObject = $httpResponse->get_response_object();
			$this->RESPONSE_RAW = isset($httpResponseObject->raw) ? $httpResponseObject->raw : null;

			return $this;
		}

		public function executeNetcurlRequest( $url = '', $postData = array(), $postMethod = NETCURL_POST_METHODS::METHOD_GET, $postDataType = NETCURL_POST_DATATYPES::DATATYPE_NOT_SET ) {
			$this->REQUEST_URL    = $url;
			$this->POST_DATA      = $postData;
			$this->POST_METHOD    = $postMethod;
			$this->POST_DATA_TYPE = $postDataType;

			$this->initializeClass();

			return $this->getWp();
		}
	}
}