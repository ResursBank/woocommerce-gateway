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

if ( ! class_exists( 'NETCURL_HTTP_OBJECT' ) && ! class_exists( 'TorneLIB\NETCURL_HTTP_OBJECT' ) ) {
	/**
	 * Class NETCURL_CURLOBJECT
	 * @package TorneLIB
	 * @since 6.0.20
	 */
	class NETCURL_HTTP_OBJECT {

		private $NETCURL_HEADER;
		private $NETCURL_BODY;
		private $NETCURL_CODE;
		private $NETCURL_PARSED;
		private $NETCURL_URL;
		private $NETCURL_IP;

		public function __construct( $header = array(), $body = '', $code = 0, $parsed = '', $url = '', $ip = '' ) {
			$this->NETCURL_HEADER = $header;
			$this->NETCURL_BODY   = $body;
			$this->NETCURL_CODE   = $code;
			$this->NETCURL_PARSED = $parsed;
			$this->NETCURL_URL    = $url;
			$this->NETCURL_IP     = $ip;
		}

		public function getHeader() {
			return $this->NETCURL_HEADER;
		}

		public function getBody() {
			return $this->NETCURL_BODY;
		}

		public function getCode() {
			return $this->NETCURL_CODE;
		}

		public function getParsed() {
			return $this->NETCURL_PARSED;
		}

		public function getUrl() {
			$this->NETCURL_URL;
		}

		public function getIp() {
			return $this->NETCURL_IP;
		}

	}
}
if ( ! class_exists( 'TORNELIB_CURLOBJECT' ) && ! class_exists( 'TorneLIB\TORNELIB_CURLOBJECT' ) ) {
	/**
	 * Class TORNELIB_CURLOBJECT
	 * @package TorneLIB
	 * @deprecated 6.0.20 Use NETCURL_HTTP_OBJECT
	 */
	class TORNELIB_CURLOBJECT extends NETCURL_HTTP_OBJECT {
		public $header;
		public $body;
		public $code;
		public $parsed;
		public $url;
		public $ip;
	}
}