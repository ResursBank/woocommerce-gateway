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

if ( ! class_exists( 'NETCURL_POST_METHODS' ) && ! class_exists( 'TorneLIB\NETCURL_POST_METHODS' ) ) {
	/**
	 * Class NETCURL_POST_METHODS List of methods available in this library
	 *
	 * @package TorneLIB
	 * @since 6.0.20
	 */
	abstract class NETCURL_POST_METHODS {
		const METHOD_GET = 0;
		const METHOD_POST = 1;
		const METHOD_PUT = 2;
		const METHOD_DELETE = 3;
		const METHOD_HEAD = 4;
		const METHOD_REQUEST = 5;
	}
}

if ( ! class_exists( 'CURL_METHODS' ) && ! class_exists( 'TorneLIB\CURL_METHODS' ) ) {
	/**
	 * @package TorneLIB
	 * @deprecated Use NETCURL_POST_METHODS
	 * @since 6.0.20
	 */
	abstract class CURL_METHODS extends NETCURL_POST_METHODS {
	}
}