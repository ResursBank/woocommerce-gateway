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

use Exception;

if (!class_exists('NETCURL_DRIVER_WORDPRESS',
        NETCURL_CLASS_EXISTS_AUTOLOAD) && !class_exists('TorneLIB\NETCURL_DRIVER_WORDPRESS',
        NETCURL_CLASS_EXISTS_AUTOLOAD)
) {
    /**
     * Class NETCURL_DRIVERS Network communications driver detection
     *
     * @package TorneLIB
     * @since 6.0.20
     */
    class NETCURL_DRIVER_WORDPRESS implements NETCURL_DRIVERS_INTERFACE
    {

        /** @var MODULE_NETWORK $NETWORK */
        private $NETWORK;

        /** @var MODULE_IO */
        private $IO;

        /** @var NETCURL_NETWORK_DRIVERS $DRIVER_ID */
        private $DRIVER_ID = NETCURL_NETWORK_DRIVERS::DRIVER_WORDPRESS;

        /** @var array Inbound parameters in the format array, object or whatever this driver takes */
        private $PARAMETERS = [];

        /** @noinspection PhpUndefinedClassInspection */
        /** @var \WP_Http $DRIVER When this class exists, it should be referred to WP_Http */
        private $DRIVER;

        /** @var \stdClass $TRANSPORT Wordpress transport layer */
        private $TRANSPORT;

        /** @var string $POST_CONTENT_TYPE Content type */
        private $POST_CONTENT_TYPE = '';

        /**
         * @var array $POST_AUTH_DATA
         */
        private $POST_AUTH_DATA = [];

        /** @var $WORKER_DATA */
        private $WORKER_DATA = [];

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

        /** @var */
        private $WP_PARAMS;

        /**
         * NETCURL_DRIVER_WORDPRESS constructor.
         * @param null $parameters
         */
        public function __construct($parameters = null)
        {
            $this->WP_PARAMS = $parameters;
            $this->NETWORK = new MODULE_NETWORK();
            $this->IO = new MODULE_IO();
        }

        public function setDriverId($driverId = NETCURL_NETWORK_DRIVERS::DRIVER_NOT_SET)
        {
            $this->DRIVER_ID = $driverId;
        }

        public function setParameters($parameters = [])
        {
            $this->PARAMETERS = $parameters;
        }

        public function setContentType($setContentTypeString = 'application/json; charset=utf-8')
        {
            $this->POST_CONTENT_TYPE = $setContentTypeString;
        }

        public function getContentType()
        {
            return $this->POST_CONTENT_TYPE;
        }

        /**
         * @param null $Username
         * @param null $Password
         * @param int $AuthType
         */
        public function setAuthentication(
            $Username = null,
            $Password = null,
            $AuthType = NETCURL_AUTH_TYPES::AUTHTYPE_BASIC
        ) {
            $this->POST_AUTH_DATA['Username'] = $Username;
            $this->POST_AUTH_DATA['Password'] = $Password;
            $this->POST_AUTH_DATA['Type'] = $AuthType;
        }

        public function getAuthentication()
        {
            return $this->POST_AUTH_DATA;
        }

        public function getWorker()
        {
            return $this->WORKER_DATA;
        }

        public function getRawResponse()
        {
            return $this->RESPONSE_RAW;
        }

        public function getStatusCode()
        {
            return $this->HTTP_STATUS;
        }

        public function getStatusMessage()
        {
            return $this->HTTP_MESSAGE;
        }

        /**
         * @throws \Exception
         */
        private function initializeClass()
        {
            /** @noinspection PhpUndefinedClassInspection */
            $this->DRIVER = new \WP_Http();
            if (method_exists($this->DRIVER, '_get_first_available_transport')) {
                $this->TRANSPORT = $this->DRIVER->_get_first_available_transport([]);
            }
            if (empty($this->TRANSPORT)) {
                throw new Exception(
                    sprintf(
                        '%s %s exception: Could not find any available transport for WordPress Driver',
                        NETCURL_CURL_CLIENTNAME,
                        __FUNCTION__
                    ),
                    $this->NETWORK->getExceptionCode('NETCURL_WP_TRANSPORT_ERROR'));
            }
        }

        /**
         * @return $this
         * @throws \Exception
         */
        private function getWp()
        {
            $postThis = ['body' => $this->POST_DATA];
            $postThis['headers'] = [];
            $authData = $this->getAuthentication();
            $hasAuthentication = false;
            if (isset($authData['Password']) && !empty($authData['Password'])) {
                $hasAuthentication = true;
                $wpAuthHeader = [
                    'Authorization' => sprintf(
                        'Basic %s',
                        base64_encode(sprintf('%s:%s', $authData['Username'], $authData['Password']))
                    ),
                ];

                $postThis['headers'] = array_merge($postThis['headers'], $wpAuthHeader);
            }

            if ($this->POST_DATA_TYPE == NETCURL_POST_DATATYPES::DATATYPE_JSON) {
                $postThis['headers']['content-type'] = 'application-json';
                $postThis['body'] = $this->IO->renderJson($this->POST_DATA);
            }

            $wpResponse = $this->getWpResponse($postThis);
            /** @noinspection PhpUndefinedClassInspection */

            /** @var $httpResponse \WP_HTTP_Requests_Response */
            $httpResponse = $wpResponse['http_response'];

            if (method_exists($httpResponse, 'get_response_object')) {
                /** @noinspection PhpUndefinedClassInspection */
                /** @var $httpReponseObject \Requests_Response */
                $httpResponseObject = $httpResponse->get_response_object();
                $this->RESPONSE_RAW = isset($httpResponseObject->raw) ? $httpResponseObject->raw : null;
            } else {
                throw new Exception(
                    sprintf(
                        '%s %s exception: Wordpress driver seem to miss get_response_object',
                        NETCURL_CURL_CLIENTNAME,
                        __FUNCTION__
                    ),
                    $this->NETWORK->getExceptionCode('NETCURL_WP_REQUEST_ERROR')
                );
            }

            return $this;
        }

        /**
         * @param $postData
         *
         * @return null
         */
        private function getWpResponse($postData)
        {
            $wpResponse = null;
            if ($this->POST_METHOD == NETCURL_POST_METHODS::METHOD_HEAD) {
                if (method_exists($this->DRIVER, 'head')) {
                    $wpResponse = $this->DRIVER->head($this->REQUEST_URL, $postData);
                }
            } elseif ($this->POST_METHOD == NETCURL_POST_METHODS::METHOD_POST) {
                if (method_exists($this->DRIVER, 'post')) {
                    $wpResponse = $this->DRIVER->post($this->REQUEST_URL, $postData);
                }
            } elseif ($this->POST_METHOD == NETCURL_POST_METHODS::METHOD_REQUEST) {
                if (method_exists($this->DRIVER, 'request')) {
                    $wpResponse = $this->DRIVER->request($this->REQUEST_URL, $postData);
                }
            } else {
                if (method_exists($this->DRIVER, 'get')) {
                    $wpResponse = $this->DRIVER->get($this->REQUEST_URL, $postData);
                }
            }

            return $wpResponse;
        }

        /**
         * @param string $url
         * @param array $postData
         * @param int $postMethod
         * @param int $postDataType
         *
         * @return NETCURL_DRIVER_WORDPRESS
         * @throws \Exception
         */
        public function executeNetcurlRequest(
            $url = '',
            $postData = [],
            $postMethod = NETCURL_POST_METHODS::METHOD_GET,
            $postDataType = NETCURL_POST_DATATYPES::DATATYPE_NOT_SET
        ) {
            $this->REQUEST_URL = $url;
            $this->POST_DATA = $postData;
            $this->POST_METHOD = $postMethod;
            $this->POST_DATA_TYPE = $postDataType;

            $this->initializeClass();

            return $this->getWp();
        }
    }
}