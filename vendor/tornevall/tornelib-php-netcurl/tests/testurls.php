<?php

abstract class TESTURLS {

	private static $Urls = array(
		'simple'      => 'identifier.tornevall.net/',
		'simplejson'  => 'identifier.tornevall.net/?json',
		'tests'       => 'developer.tornevall.net/tests/tornevall_network/',
		'httpcode'    => 'developer.tornevall.net/tests/tornevall_network/http.php',
		'soap'        => 'developer.tornevall.net/tests/tornevall_network/index.wsdl?wsdl',
		'selfsigned'  => 'https://dev-ssl-self.tornevall.nu',
		'mismatching' => 'https://dev-ssl-mismatch.tornevall.nu',
	);

	public static function getUrls() {
		return self::$Urls;
	}

	public static function getUrlSimple() {
		return self::$Urls['simple'];
	}

	public static function getUrlSimpleJson() {
		return self::$Urls['simplejson'];
	}

	public static function getUrlTests() {
		return self::$Urls['tests'];
	}

	public static function getUrlSoap() {
		return self::$Urls['soap'];
	}

	public static function getUrlHttpCode() {
		return self::$Urls['httpcode'];
	}

	public static function getUrlSelfSigned() {
		return self::$Urls['selfsigned'];
	}

	public static function getUrlMismatching() {
		return self::$Urls['mismatching'];
	}


}