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
 * @package TorneLIB
 * @version 6.0.9
 */

namespace TorneLIB;

if ( ! defined( 'TORNELIB_IO_RELEASE' ) ) {
	define( 'TORNELIB_IO_RELEASE', '6.0.9' );
}
if ( ! defined( 'TORNELIB_IO_MODIFY' ) ) {
	define( 'TORNELIB_IO_MODIFY', '20180426' );
}
if ( ! defined( 'TORNELIB_IO_CLIENTNAME' ) ) {
	define( 'TORNELIB_IO_CLIENTNAME', 'MODULE_IO' );
}

if ( defined( 'TORNELIB_IO_REQUIRE' ) ) {
	if ( ! defined( 'TORNELIB_IO_REQUIRE_OPERATOR' ) ) {
		define( 'TORNELIB_IO_REQUIRE_OPERATOR', '==' );
	}
	define( 'TORNELIB_IO_ALLOW_AUTOLOAD', version_compare( TORNELIB_IO_RELEASE, TORNELIB_IO_REQUIRE, TORNELIB_IO_REQUIRE_OPERATOR ) ? true : false );
} else {
	define( 'TORNELIB_IO_ALLOW_AUTOLOAD', true );
}

if ( ! class_exists( 'MODULE_IO' ) && ! class_exists( 'TorneLIB\MODULE_IO' ) && defined( 'TORNELIB_IO_ALLOW_AUTOLOAD' ) && TORNELIB_IO_ALLOW_AUTOLOAD === true ) {

	/**
	 * Class MODULE_IO
	 * @package TorneLIB
	 */
	class MODULE_IO {

		/** @var TorneLIB_Crypto $CRYPTO */
		private $CRYPTO;
		/** @var bool Enforce usage SimpleXML objects even if XML_Serializer is present */
		private $ENFORCE_SIMPLEXML = false;

		/** @var bool $ENFORCE_SERIALIZER */
		private $ENFORCE_SERIALIZER = false;

		/** @var bool $ENFORCE_CDATA */
		private $ENFORCE_CDATA = false;

		/** @var bool $SOAP_ATTRIBUTES_ENABLED */
		private $SOAP_ATTRIBUTES_ENABLED = false;

		/** @var int $XML_TRANSLATE_ENTITY_RERUN */
		private $XML_TRANSLATE_ENTITY_RERUN = 0;

		public function __construct() {
		}

		function setCrypto() {
			if ( empty( $this->CRYPTO ) ) {
				$this->CRYPTO = new TorneLIB_Crypto();
			}
		}

		/**
		 * Set and override compression level
		 *
		 * @param int $compressionLevel
		 *
		 * @since 6.0.3
		 */
		function setCompressionLevel( $compressionLevel = 9 ) {
			$this->setCrypto();
			$this->CRYPTO->setCompressionLevel( $compressionLevel );
		}

		/**
		 * Get current compressionlevel
		 *
		 * @return mixed
		 * @since 6.0.3
		 */
		public function getCompressionLevel() {
			$this->setCrypto();

			return $this->CRYPTO->getCompressionLevel();
		}

		/**
		 * Force the use of SimpleXML before XML/Serializer
		 *
		 * @param bool $enforceSimpleXml
		 *
		 * @since 6.0.3
		 */
		public function setXmlSimple( $enforceSimpleXml = true ) {
			$this->ENFORCE_SIMPLEXML = $enforceSimpleXml;
		}

		/**
		 * @return bool
		 *
		 * @since 6.0.3
		 */
		public function getXmlSimple() {
			return $this->ENFORCE_SIMPLEXML;
		}

		/**
		 * Enforce use of XML/Unserializer before SimpleXML-decoding
		 *
		 * @param bool $activationBoolean
		 *
		 * @since 6.0.5
		 */
		public function setXmlUnSerializer( $activationBoolean = true ) {
			$this->ENFORCE_SERIALIZER = $activationBoolean;
		}

		/**
		 * Figure out if user has enabled overriding default XML parser (SimpleXML => XML/Unserializer)
		 *
		 * @return bool
		 * @since 6.0.5
		 */
		public function getXmlUnSerializer() {
			return $this->ENFORCE_SERIALIZER;
		}

		/**
		 * Enable the use of CDATA-fields in XML data
		 *
		 * @param bool $activationBoolean
		 *
		 * @since 6.0.5
		 */
		public function setCdataEnabled( $activationBoolean = true ) {
			$this->ENFORCE_CDATA = $activationBoolean;
		}

		/**
		 * Figure out if user has enabled the use of CDATA in XML data
		 *
		 * @return bool
		 * @since 6.0.5
		 */
		public function getCdataEnabled() {
			return $this->ENFORCE_CDATA;
		}

		/**
		 * Figure out whether we can use XML/Unserializer as XML parser or not
		 *
		 * @return bool
		 * @since 6.0.5
		 */
		public function getHasXmlSerializer() {
			$serializerPath = stream_resolve_include_path( 'XML/Unserializer.php' );
			if ( ! empty( $serializerPath ) ) {
				return true;
			}

			return false;
		}

		/**
		 * @param bool $soapAttributes
		 *
		 * @since 6.0.6
		 */
		public function setSoapXml( $soapAttributes = true ) {
			$this->SOAP_ATTRIBUTES_ENABLED = $soapAttributes;
			$this->setXmlSimple( true );
		}

		/**
		 * @return bool
		 * @since 6.0.6
		 */
		public function getSoapXml() {
			return $this->SOAP_ATTRIBUTES_ENABLED;
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
		 * @param array $dataArray
		 * @param SimpleXMLElement $xml
		 *
		 * @return mixed
		 * @since 6.0.3
		 */
		private function array_to_xml( $dataArray = array(), $xml ) {
			foreach ( $dataArray as $key => $value ) {
				$key = is_numeric( $key ) ? 'item' : $key;
				if ( is_array( $value ) ) {
					$this->array_to_xml( $value, $xml->addChild( $key ) );
				} else {
					$xml->addChild( $key, $value );
				}
			}

			return $xml;
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
		 * @param string $contentString
		 * @param int $compression
		 * @param bool $renderAndDie
		 *
		 * @return string
		 * @throws \Exception
		 * @since 6.0.3
		 */
		private function compressString( $contentString = '', $compression = TORNELIB_CRYPTO_TYPES::TYPE_NONE, $renderAndDie = false ) {
			if ( $compression == TORNELIB_CRYPTO_TYPES::TYPE_GZ ) {
				$this->setCrypto();
				$contentString = $this->CRYPTO->base64_gzencode( $contentString );
			} else if ( $compression == TORNELIB_CRYPTO_TYPES::TYPE_BZ2 ) {
				$this->setCrypto();
				$contentString = $this->CRYPTO->base64_bzencode( $contentString );
			}

			if ( $renderAndDie ) {
				if ( $compression == TORNELIB_CRYPTO_TYPES::TYPE_GZ ) {
					$contentString = array( 'gz' => $contentString );
				} else if ( $compression == TORNELIB_CRYPTO_TYPES::TYPE_BZ2 ) {
					$contentString = array( 'bz2' => $contentString );
				}
			}

			return $contentString;
		}

		/**
		 * ServerRenderer: Render JSON data
		 *
		 * @param array $contentData
		 * @param bool $renderAndDie
		 * @param int $compression
		 *
		 * @return string
		 * @throws \Exception
		 * @since 6.0.1
		 */
		public function renderJson( $contentData = array(), $renderAndDie = false, $compression = TORNELIB_CRYPTO_TYPES::TYPE_NONE ) {
			$objectArrayEncoded = $this->getUtf8( $this->objectsIntoArray( $contentData ) );

			if ( is_string( $contentData ) ) {
				$objectArrayEncoded = $this->objectsIntoArray( $this->getFromJson( $contentData ) );
			}

			$contentRendered = $this->compressString( @json_encode( $objectArrayEncoded, JSON_PRETTY_PRINT ), $compression, $renderAndDie );

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
		 * @param int $compression
		 *
		 * @return string
		 * @throws \Exception
		 * @since 6.0.1
		 */
		public function renderPhpSerialize( $contentData = array(), $renderAndDie = false, $compression = TORNELIB_CRYPTO_TYPES::TYPE_NONE ) {
			$contentRendered = $this->compressString( serialize( $contentData ), $compression, $renderAndDie );

			if ( $renderAndDie ) {
				header( "Content-Type: text/plain" );
				echo $contentRendered;
				die;
			}

			return $contentRendered;
		}

		/**
		 * @param string $serialInput
		 *
		 * @return mixed
		 * @since 6.0.5
		 */
		public function getFromSerializerInternal( $serialInput = '', $assoc = false ) {
			if ( ! $assoc ) {
				return @unserialize( $serialInput );
			} else {
				return $this->arrayObjectToStdClass( @unserialize( $serialInput ) );
			}
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
		public function renderYaml( $contentData = array(), $renderAndDie = false, $compression = TORNELIB_CRYPTO_TYPES::TYPE_NONE ) {
			$objectArrayEncoded = $this->getUtf8( $this->objectsIntoArray( $contentData ) );
			if ( function_exists( 'yaml_emit' ) ) {
				$contentRendered = $this->compressString( yaml_emit( $objectArrayEncoded ), $compression, $renderAndDie );
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
		 * @param int $compression
		 *
		 * @return mixed
		 * @throws \Exception
		 * @since 6.0.1
		 */
		public function renderXml( $contentData = array(), $renderAndDie = false, $compression = TORNELIB_CRYPTO_TYPES::TYPE_NONE, $initialTagName = 'item', $rootName = 'XMLResponse' ) {
			$serializerPath = stream_resolve_include_path( 'XML/Serializer.php' );
			if ( ! empty( $serializerPath ) ) {
				require_once( 'XML/Serializer.php' );
			}
			$objectArrayEncoded = $this->getUtf8( $this->objectsIntoArray( $contentData ) );
			$options            = array(
				'indent'         => '    ',
				'linebreak'      => "\n",
				'encoding'       => 'UTF-8',
				'rootName'       => $rootName,
				'defaultTagName' => $initialTagName
			);
			if ( class_exists( 'XML_Serializer' ) && ! $this->ENFORCE_SIMPLEXML ) {
				$xmlSerializer = new \XML_Serializer( $options );
				$xmlSerializer->serialize( $objectArrayEncoded );
				$contentRendered = $xmlSerializer->getSerializedData();
			} else {
				// <data></data>
				if ( $this->SOAP_ATTRIBUTES_ENABLED ) {
					$soapNs = 'http://schemas.xmlsoap.org/soap/envelope/';
					$xml    = new \SimpleXMLElement( '<?xml version="1.0"?>' . '<' . $rootName . '></' . $rootName . '>', 0, false, $soapNs, false );
					$xml->addAttribute( $rootName . ':xmlns', $soapNs );
					$xml->addAttribute( $rootName . ':xsi', 'http://www.w3.org/2001/XMLSchema-instance' );
					$xml->addAttribute( $rootName . ':xsd', 'http://www.w3.org/2001/XMLSchema' );
				} else {
					$xml = new \SimpleXMLElement( '<?xml version="1.0"?>' . '<' . $rootName . '></' . $rootName . '>' );
				}
				$this->array_to_xml( $objectArrayEncoded, $xml );
				$contentRendered = $xml->asXML();
			}

			$contentRendered = $this->compressString( $contentRendered, $compression, $renderAndDie );

			if ( $renderAndDie ) {
				header( "Content-Type: application/xml" );
				echo $contentRendered;
				die;
			}

			return $contentRendered;
		}

		/**
		 * @param string $dataIn
		 *
		 * @return mixed|string
		 * @since 6.0.5
		 */
		public function getFromJson( $dataIn = '' ) {
			if ( is_string( $dataIn ) ) {
				return @json_decode( $dataIn );
			} else if ( is_object( $dataIn ) ) {
				return null;
			} else if ( is_array( $dataIn ) ) {
				return null;
			}
		}

		/**
		 * Convert XML string into an object or array
		 *
		 * @param string $dataIn
		 * @param bool $normalize Normalize objects (convert to stdClass)
		 *
		 * @return \SimpleXMLElement
		 * @since 6.0.5
		 */
		public function getFromXml( $dataIn = '', $normalize = false ) {
			$dataIn = trim( $dataIn );

			if ( preg_match( "/&\b(.*?)+;(.*)/is", $dataIn ) ) {
				$dataEntity = trim( html_entity_decode( $dataIn ) );
				if ( preg_match( "/^\</", $dataEntity ) ) {

					return $this->getFromXml( $dataEntity, $normalize );
				}

				if ( $this->XML_TRANSLATE_ENTITY_RERUN >= 0 ) {
					// Fail on too many loops
					$this->XML_TRANSLATE_ENTITY_RERUN ++;
					if ( $this->XML_TRANSLATE_ENTITY_RERUN >= 2 ) {
						return null;
					}

					return $this->getFromXml( $dataEntity, $normalize );
				}

				return null;
			}

			if ( $this->getXmlUnSerializer() && $this->getHasXmlSerializer() ) {
				if ( is_string( $dataIn ) && preg_match( "/\<(.*?)\>/s", $dataIn ) ) {
					require_once( 'XML/Unserializer.php' );
					$xmlSerializer = new \XML_Unserializer();
					$xmlSerializer->unserialize( $dataIn );

					if ( ! $normalize ) {
						return $xmlSerializer->getUnserializedData();
					} else {
						return $this->arrayObjectToStdClass( $xmlSerializer->getUnserializedData() );
					}
				}
			} else {
				if ( class_exists( 'SimpleXMLElement' ) ) {
					if ( is_string( $dataIn ) && preg_match( "/\<(.*?)\>/s", $dataIn ) ) {
						if ( $this->ENFORCE_CDATA ) {
							$simpleXML = new \SimpleXMLElement( $dataIn, LIBXML_NOCDATA );
						} else {
							$simpleXML = new \SimpleXMLElement( $dataIn );
						}
						if ( isset( $simpleXML ) && ( is_object( $simpleXML ) || is_array( $simpleXML ) ) ) {
							if ( ! $normalize ) {
								/*								$xmlExtractedPath = $this->extractXmlPath( $simpleXML );
																if ( ! is_null( $xmlExtractedPath ) && is_object( $xmlExtractedPath ) ) {
																	return $this->arrayObjectToStdClass($xmlExtractedPath);
																}*/
								return $simpleXML;
							} else {
								$objectClass = $this->arrayObjectToStdClass( $simpleXML );
								if ( ! count( (array) $objectClass ) ) {
									$xmlExtractedPath = $this->extractXmlPath( $simpleXML );
									if ( ! is_null( $xmlExtractedPath ) ) {
										if ( is_object( $xmlExtractedPath ) || ( is_array( $xmlExtractedPath ) && count( $xmlExtractedPath ) ) ) {
											return $xmlExtractedPath;
										}
									}
								}

								return $objectClass;
							}
						}
					}
				}
			}

			return null;
		}

		/**
		 * Check if there is something more than just an empty object hidden behind a SimpleXMLElement
		 *
		 * @param null $simpleXML
		 *
		 * @return array|mixed|null
		 * @since 6.0.8
		 */
		private function extractXmlPath( $simpleXML = null ) {
			$canReturn       = false;
			$xmlXpath        = null;
			$xmlPathReturner = null;
			if ( method_exists( $simpleXML, 'xpath' ) ) {
				try {
					$xmlXpath = $simpleXML->xpath( "*/*" );
				} catch ( \Exception $ignoreErrors ) {

				}
				if ( is_array( $xmlXpath ) ) {
					if ( count( $xmlXpath ) == 1 ) {
						$xmlPathReturner = array_pop( $xmlXpath );
						$canReturn       = true;
					} else if ( count( $xmlXpath ) > 1 ) {
						$xmlPathReturner = $xmlXpath;
						$canReturn       = true;
					}
					if ( isset( $xmlPathReturner->return ) ) {
						return $this->arrayObjectToStdClass( $xmlPathReturner )->return;
					}
				}
			}
			if ( $canReturn ) {
				return $xmlPathReturner;
			}

			return null;
		}

		/**
		 * @param string $yamlString
		 * @param bool $getAssoc
		 *
		 * @return array|mixed|object
		 * @throws \Exception
		 * @since 6.0.5
		 */
		public function getFromYaml( $yamlString = '', $getAssoc = true ) {
			if ( function_exists( 'yaml_parse' ) ) {
				$extractYaml = @yaml_parse( $yamlString );
				if ( $getAssoc ) {
					if ( empty( $extractYaml ) ) {
						return null;
					}

					return $extractYaml;
				} else {
					if ( empty( $extractYaml ) ) {
						return null;
					}

					return $this->arrayObjectToStdClass( $extractYaml );
				}
			} else {
				throw new \Exception( "yaml_parse not supported - ask your admin to install the driver", 404 );
			}
		}

	}
}

if ( ! class_exists( 'TorneLIB_IO' ) && ! class_exists( 'TorneLIB\TorneLIB_IO' ) ) {
	class TorneLIB_IO extends MODULE_IO {
	}
}
