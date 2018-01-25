<?php

/**
 * Copyright 2017 Tomas Tornevall & Tornevall Networks
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
 * @package TorneLIB
 * @version 6.0.2
 */

namespace TorneLIB;

if ( ! class_exists( 'TorneLIB_IO' ) && ! class_exists( 'TorneLIB\TorneLIB_IO' ) ) {
	class TorneLIB_IO {

		public function __construct() {
		}

		/**
		 * Convert object to a data object (used for repairing __PHP_Incomplete_Class objects)
		 *
		 * This function are written to work with WSDL2PHPGenerator, where serialization of some objects sometimes generates, as described, __PHP_Incomplete_Class objects.
		 * The upgraded version are also supposed to work with protected values.
		 *
		 * @param array $objectArray
		 * @param bool $useJsonFunction
		 *
		 * @return object
		 * @since 6.0.0
		 */
		public function arrayObjectToStdClass( $objectArray = array(), $useJsonFunction = false ) {
			/**
			 * If json_decode and json_encode exists as function, do it the simple way.
			 * http://php.net/manual/en/function.json-encode.php
			 */
			if ( ( function_exists( 'json_decode' ) && function_exists( 'json_encode' ) ) || $useJsonFunction ) {
				return json_decode( json_encode( $objectArray ) );
			}
			$newArray = array();
			if ( is_array( $objectArray ) || is_object( $objectArray ) ) {
				foreach ( $objectArray as $itemKey => $itemValue ) {
					if ( is_array( $itemValue ) ) {
						$newArray[ $itemKey ] = (array) $this->arrayObjectToStdClass( $itemValue );
					} elseif ( is_object( $itemValue ) ) {
						$newArray[ $itemKey ] = (object) (array) $this->arrayObjectToStdClass( $itemValue );
					} else {
						$newArray[ $itemKey ] = $itemValue;
					}
				}
			}

			return $newArray;
		}

		/**
		 * Convert objects to arrays
		 *
		 * @param $arrObjData
		 * @param array $arrSkipIndices
		 *
		 * @return array
		 * @since 6.0.0
		 */
		public function objectsIntoArray( $arrObjData, $arrSkipIndices = array() ) {
			$arrData = array();
			// if input is object, convert into array
			if ( is_object( $arrObjData ) ) {
				$arrObjData = get_object_vars( $arrObjData );
			}
			if ( is_array( $arrObjData ) ) {
				foreach ( $arrObjData as $index => $value ) {
					if ( is_object( $value ) || is_array( $value ) ) {
						$value = $this->objectsIntoArray( $value, $arrSkipIndices ); // recursive call
					}
					if ( @in_array( $index, $arrSkipIndices ) ) {
						continue;
					}
					$arrData[ $index ] = $value;
				}
			}

			return $arrData;
		}

		/**
		 * Convert all data to utf8
		 *
		 * @param array $dataArray
		 *
		 * @return array
		 * @since 6.0.0
		 */
		private function getUtf8( $dataArray = array() ) {
			$newArray = array();
			if ( is_array( $dataArray ) ) {
				foreach ( $dataArray as $p => $v ) {
					if ( is_array( $v ) || is_object( $v ) ) {
						$v              = $this->getUtf8( $v );
						$newArray[ $p ] = $v;
					} else {
						$v              = utf8_encode( $v );
						$newArray[ $p ] = $v;
					}

				}
			}

			return $newArray;
		}

		/**
		 * @param array $arrayData
		 *
		 * @return bool
		 * @since 6.0.2
		 */
		function isAssoc( array $arrayData ) {
			if ( array() === $arrayData ) {
				return false;
			}

			return array_keys( $arrayData ) !== range( 0, count( $arrayData ) - 1 );
		}

		/**
		 * ServerRenderer: Render JSON data
		 *
		 * @param array $contentData
		 * @param bool $renderAndDie
		 *
		 * @return string
		 * @since 6.0.1
		 */
		public function renderJson( $contentData = array(), $renderAndDie = false ) {
			$objectArrayEncoded = $this->getUtf8( $this->objectsIntoArray( $contentData ) );
			$contentRendered    = json_encode( $objectArrayEncoded, JSON_PRETTY_PRINT );

			if ( $renderAndDie ) {
				header( "Content-type: application/json; charset=utf-8" );
				echo $contentRendered;
				die;
			}

			return $contentRendered;
		}

		/**
		 * ServerRenderer: PHP serialized
		 *
		 * @param array $contentData
		 * @param bool $renderAndDie
		 *
		 * @return string
		 * @since 6.0.1
		 */
		public function renderPhpSerialize( $contentData = array(), $renderAndDie = false ) {
			$contentRendered = serialize( $contentData );
			if ( $renderAndDie ) {
				header( "Content-Type: text/plain" );
				echo $contentRendered;
				die;
			}

			return $contentRendered;
		}

		/**
		 * ServerRenderer: Render yaml data
		 *
		 * Install:
		 *  apt-get install libyaml-dev
		 *  pecl install yaml
		 *
		 * @param array $contentData
		 * @param bool $renderAndDie
		 *
		 * @return string
		 * @throws \Exception
		 * @since 6.0.1
		 */
		public function renderYaml( $contentData = array(), $renderAndDie = false ) {
			$objectArrayEncoded = $this->getUtf8( $this->objectsIntoArray( $contentData ) );
			if ( function_exists( 'yaml_emit' ) ) {
				$contentRendered = yaml_emit( $objectArrayEncoded );
				if ( $renderAndDie ) {
					header( "Content-Type: text/plain" );
					echo $contentRendered;
					die;
				}

				return $contentRendered;
			} else {
				throw new \Exception( "yaml_emit not supported - ask your admin to install the driver", 404 );
			}
		}

		/**
		 * @param array $contentData
		 * @param bool $renderAndDie
		 *
		 * @return mixed
		 * @since 6.0.1
		 */
		public function renderXml( $contentData = array(), $renderAndDie = false ) {
			$serializerPath = stream_resolve_include_path( 'XML/Serializer.php' );
			if ( ! empty( $serializerPath ) ) {
				require_once( 'XML/Serializer.php' );
			}
			$objectArrayEncoded = $this->getUtf8( $this->objectsIntoArray( $contentData ) );
			$options            = array(
				'indent'         => '    ',
				'linebreak'      => "\n",
				'encoding'       => 'UTF-8',
				'rootName'       => 'TorneAPIXMLResponse',
				'defaultTagName' => 'item'
			);
			if ( class_exists( 'XML_Serializer' ) ) {
				$xmlSerializer = new \XML_Serializer( $options );
				$xmlSerializer->serialize( $objectArrayEncoded );
				$contentRendered = $xmlSerializer->getSerializedData();
			} else {
				$xml = new \SimpleXMLElement( '<?xml version="1.0"?><data></data>' );
				$this->array_to_xml( $objectArrayEncoded, $xml );
				$contentRendered = $xml->asXML();
			}

			if ( $renderAndDie ) {
				header( "Content-Type: application/xml" );
				echo $contentRendered;
				die;
			}

			return $contentRendered;
		}

	}
}