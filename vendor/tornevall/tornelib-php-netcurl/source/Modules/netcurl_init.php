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
 * @version 6.0.20
 */

namespace TorneLIB;

// Library Release Information
if ( ! defined( 'NETCURL_RELEASE' ) ) {
	define( 'NETCURL_RELEASE', '6.0.21' );
}
if ( ! defined( 'NETCURL_MODIFY' ) ) {
	define( 'NETCURL_MODIFY', '20180523' );
}
if ( ! defined( 'TORNELIB_NETCURL_RELEASE' ) ) {
	// Compatibility constant
	define( 'TORNELIB_NETCURL_RELEASE', NETCURL_RELEASE );
}

if ( defined( 'NETCURL_REQUIRE' ) ) {
	if ( ! defined( 'NETCURL_REQUIRE_OPERATOR' ) ) {
		define( 'NETCURL_REQUIRE_OPERATOR', '==' );
	}
	define( 'NETCURL_ALLOW_AUTOLOAD', version_compare( NETCURL_RELEASE, NETCURL_REQUIRE, NETCURL_REQUIRE_OPERATOR ) ? true : false );
} else {
	if ( ! defined( 'NETCURL_ALLOW_AUTOLOAD' ) ) {
		define( 'NETCURL_ALLOW_AUTOLOAD', true );
	}
}

if ( file_exists( __DIR__ . '/../vendor/autoload.php' ) && ( defined( 'NETCURL_ALLOW_AUTOLOAD' ) && NETCURL_ALLOW_AUTOLOAD === true ) ) {
	require_once( __DIR__ . '/../vendor/autoload.php' );
}
