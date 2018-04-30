<?php

namespace TorneLIB;

interface NETCURL_DRIVERS_INTERFACE {

	public function __construct( $parameters = null );

	public function setDriverId( $driverId = NETCURL_NETWORK_DRIVERS::DRIVER_NOT_SET );

	public function setParameters( $parameters = array() );

	public function setContentType( $setContentTypeString = 'application/json; charset=utf-8' );

	public function getContentType();

	public function setAuthentication( $Username = null, $Password = null, $AuthType = NETCURL_AUTH_TYPES::AUTHTYPE_BASIC );

	public function getAuthentication();

	public function getWorker();

	public function getRawResponse();

	public function getStatusCode();

	public function getStatusMessage();

	public function executeNetcurlRequest( $url = '', $postData = array(), $postMethod = NETCURL_POST_METHODS::METHOD_GET, $postDataType = NETCURL_POST_DATATYPES::DATATYPE_NOT_SET );

}