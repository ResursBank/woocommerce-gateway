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
 * @version 6.0.10
 *
 * Crypto-IO Library. Anything that changes in those folders, will render version increase.
 */

namespace TorneLIB;

if ( ! defined( 'TORNELIB_CRYPTO_RELEASE' ) ) {
	define( 'TORNELIB_CRYPTO_RELEASE', '6.0.10' );
}
if ( ! defined( 'TORNELIB_CRYPTO_MODIFY' ) ) {
	define( 'TORNELIB_CRYPTO_MODIFY', '20180424' );
}
if ( ! defined( 'TORNELIB_CRYPTO_CLIENTNAME' ) ) {
	define( 'TORNELIB_CRYPTO_CLIENTNAME', 'MODULE_CRYPTO' );
}

if ( defined( 'TORNELIB_CRYPTO_REQUIRE' ) ) {
	if ( ! defined( 'TORNELIB_CRYPTO_REQUIRE_OPERATOR' ) ) {
		define( 'TORNELIB_CRYPTO_REQUIRE_OPERATOR', '==' );
	}
	define( 'TORNELIB_CRYPTO_ALLOW_AUTOLOAD', version_compare( TORNELIB_CRYPTO_RELEASE, TORNELIB_CRYPTO_REQUIRE, TORNELIB_CRYPTO_REQUIRE_OPERATOR ) ? true : false );
} else {
	define( 'TORNELIB_CRYPTO_ALLOW_AUTOLOAD', true );
}

if ( ! class_exists( 'MODULE_CRYPTO' ) && ! class_exists( 'TorneLIB\MODULE_CRYPTO' ) && defined( 'TORNELIB_CRYPTO_ALLOW_AUTOLOAD' ) && TORNELIB_CRYPTO_ALLOW_AUTOLOAD === true ) {

	/**
	 * Class TorneLIB_Crypto
	 */
	class MODULE_CRYPTO {

		private $aesKey = "";
		private $aesIv = "";
		private $compressionLevel;

		/**
		 * TorneLIB_Crypto constructor.
		 */
		function __construct() {
			$this->setAesIv( md5( "TorneLIB Default IV - Please Change this" ) );
			$this->setAesKey( md5( "TorneLIB Default KEY - Please Change this" ) );
		}

		/**
		 * Set and override compression level
		 *
		 * @param int $compressionLevel
		 *
		 * @since 6.0.6
		 */
		function setCompressionLevel( $compressionLevel = 9 ) {
			$this->compressionLevel = $compressionLevel;
		}

		/**
		 * Get current compressionlevel
		 *
		 * @return mixed
		 * @since 6.0.6
		 */
		public function getCompressionLevel() {
			return $this->compressionLevel;
		}

		/**
		 * Create a password or salt with different kind of complexity
		 *
		 * 1 = A-Z
		 * 2 = A-Za-z
		 * 3 = A-Za-z0-9
		 * 4 = Full usage
		 * 5 = Full usage and unrestricted $setMax
		 * 6 = Complexity uses full charset of 0-255
		 *
		 * @param int $complexity
		 * @param int $setMax Max string length to use
		 * @param bool $webFriendly Set to true works best with the less complex strings as it only removes characters that could be mistaken by another character (O,0,1,l,I etc)
		 *
		 * @return string
		 * @deprecated 6.0.4 Still here for people who needs it
		 */
		function mkpass_deprecated( $complexity = 4, $setMax = 8, $webFriendly = false ) {
			$returnString       = null;
			$characterListArray = array(
				'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
				'abcdefghijklmnopqrstuvwxyz',
				'0123456789',
				'!@#$%*?'
			);
			// Set complexity to no limit if type 6 is requested
			if ( $complexity == 6 ) {
				$characterListArray = array( '0' => '' );
				for ( $unlim = 0; $unlim <= 255; $unlim ++ ) {
					$characterListArray[0] .= chr( $unlim );
				}
				if ( $setMax == null ) {
					$setMax = 15;
				}
			}
			// Backward-compatibility in the complexity will still give us captcha-capabilities for simpler users
			$max = 8;       // Longest complexity
			if ( $complexity == 1 ) {
				unset( $characterListArray[1], $characterListArray[2], $characterListArray[3] );
				$max = 6;
			}
			if ( $complexity == 2 ) {
				unset( $characterListArray[2], $characterListArray[3] );
				$max = 10;
			}
			if ( $complexity == 3 ) {
				unset( $characterListArray[3] );
				$max = 10;
			}
			if ( $setMax > 0 ) {
				$max = $setMax;
			}
			$chars    = array();
			$numchars = array();
			//$equalityPart = ceil( $max / count( $characterListArray ) );
			for ( $i = 0; $i < $max; $i ++ ) {
				$charListId = rand( 0, count( $characterListArray ) - 1 );
				if ( ! isset( $numchars[ $charListId ] ) ) {
					$numchars[ $charListId ] = 0;
				}
				$numchars[ $charListId ] ++;
				$chars[] = $characterListArray[ $charListId ]{mt_rand( 0, ( strlen( $characterListArray[ $charListId ] ) - 1 ) )};
			}
			shuffle( $chars );
			$returnString = implode( "", $chars );
			if ( $webFriendly ) {
				// The lazyness
				$returnString = preg_replace( "/[+\/=IG0ODQR]/i", "", $returnString );
			}

			return $returnString;
		}

		/**
		 * Returns a string with a chosen character list
		 *
		 * @param string $type
		 *
		 * @return mixed
		 * @since 6.0.4
		 */
		private function getCharacterListArray( $type = 'upper' ) {
			$compiledArray = array(
				'upper'    => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
				'lower'    => 'abcdefghijklmnopqrstuvwxyz',
				'numeric'  => '0123456789',
				'specials' => '!@#$%*?',
				'table'    => ''
			);
			for ( $i = 0; $i <= 255; $i ++ ) {
				$compiledArray['table'] .= chr( $i );
			}

			switch ( $type ) {
				case 'table':
					return $compiledArray['table'];
				case 'specials':
					return $compiledArray['specials'];
				case 'numeric':
					return $compiledArray['numeric'];
				case "lower":
					return $compiledArray['lower'];
				default:
					return $compiledArray['upper'];
			}
		}

		/**
		 * Returns a selected character list array string as a new array
		 *
		 * @param string $type
		 *
		 * @return array|false|string[]
		 * @since 6.0.4
		 */
		private function getCharactersFromList( $type = 'upper' ) {
			return preg_split( "//", $this->getCharacterListArray( $type ), - 1, PREG_SPLIT_NO_EMPTY );
		}

		/**
		 * Returns a random character from a selected character list
		 *
		 * @param array $type
		 * @param bool $ambigous
		 *
		 * @return mixed|string
		 * @since 6.0.4
		 */
		private function getRandomCharacterFromArray( $type = array( 'upper' ), $ambigous = false ) {
			if ( is_string( $type ) ) {
				$type = array( $type );
			}
			$getType         = $type[ rand( 0, count( $type ) - 1 ) ];
			$characterArray  = $this->getCharactersFromList( $getType );
			$characterLength = count( $characterArray ) - 1;
			$chosenCharacter = $characterArray[ rand( 0, $characterLength ) ];
			$ambigousList    = array(
				'+',
				'/',
				'=',
				'I',
				'G',
				'0',
				'O',
				'D',
				'Q',
				'R'
			);
			if ( in_array( $chosenCharacter, $ambigousList ) ) {
				$chosenCharacter = $this->getRandomCharacterFromArray( $type, $ambigous );
			}

			return $chosenCharacter;
		}

		/**
		 * Returns a random character based on complexity selection
		 *
		 * @param int $complexity
		 * @param bool $ambigous
		 *
		 * @return mixed|string
		 * @since 6.0.4
		 */
		private function getCharacterFromComplexity( $complexity = 4, $ambigous = false ) {
			switch ( $complexity ) {
				case 1:
					return $this->getRandomCharacterFromArray( array( 'upper' ), $ambigous );
				case 2:
					return $this->getRandomCharacterFromArray( array( 'upper', 'lower' ), $ambigous );
				case 3:
					return $this->getRandomCharacterFromArray( array( 'upper', 'lower', 'numeric' ), $ambigous );
				case 4:
					return $this->getRandomCharacterFromArray( array(
						'upper',
						'lower',
						'numeric',
						'specials'
					), $ambigous );
				case 5:
					return $this->getRandomCharacterFromArray( array( 'table' ) );
				case 6:
					return $this->getRandomCharacterFromArray( array( 'table' ) );
				default:
					return $this->getRandomCharacterFromArray( 'upper', $ambigous );
			}
		}

		/**
		 * Refactored generator to create a random password or string
		 *
		 * @param int $complexity 1=UPPERCASE, 2=UPPERCASE+lowercase, 3=UPPERCASE+lowercase+numerics, 4=UPPERCASE,lowercase+numerics+specialcharacters, 5/6=Full character set
		 * @param int $totalLength Length of the string
		 * @param bool $ambigous Exclude what we see as ambigous characters (this has no effect in complexity > 4)
		 *
		 * @return string
		 * @since 6.0.4
		 */
		public function mkpass( $complexity = 4, $totalLength = 16, $ambigous = false ) {
			$pwString = "";
			for ( $charIndex = 0; $charIndex < $totalLength; $charIndex ++ ) {
				$pwString .= $this->getCharacterFromComplexity( $complexity, $ambigous );
			}

			return $pwString;
		}

		/**
		 * @param int $complexity
		 * @param int $totalLength
		 * @param bool $ambigous
		 *
		 * @return string
		 * @since 6.0.7
		 */
		public static function getRandomSalt( $complexity = 4, $totalLength = 16, $ambigous = false ) {
			$selfInstance = new TorneLIB_Crypto();

			return $selfInstance->mkpass( $complexity, $totalLength, $ambigous );
		}

		/**
		 * Set up key for aes encryption.
		 *
		 * @param $useKey
		 *
		 * @since 6.0.0
		 */
		public function setAesKey( $useKey ) {
			$this->aesKey = md5( $useKey );
		}

		/**
		 * Set up ip for aes encryption
		 *
		 * @param $useIv
		 *
		 * @since 6.0.0
		 */
		public function setAesIv( $useIv ) {
			$this->aesIv = md5( $useIv );
		}

		/**
		 * Encrypt content to RIJNDAEL/AES-encryption
		 *
		 * @param string $decryptedContent
		 * @param bool $asBase64
		 *
		 * @return string
		 * @throws \Exception
		 * @since 6.0.0
		 */
		public function aesEncrypt( $decryptedContent = "", $asBase64 = true ) {
			$useKey      = $this->aesKey;
			$useIv       = $this->aesIv;
			$contentData = $decryptedContent;
			if ( $useKey == md5( md5( "TorneLIB Default IV - Please Change this" ) ) || $useIv == md5( md5( "TorneLIB Default IV - Please Change this" ) ) ) {
				throw new \Exception( "Current encryption key and iv is not allowed to use." );  // TODO: TORNELIB_EXCEPTIONS::TORNELIB_CRYPTO_KEY_EXCEPTION
			}
			if ( is_string( $decryptedContent ) ) {
				$contentData = utf8_encode( $decryptedContent );
			}
			// TODO: Need better support for PHP7
			$binEnc      = mcrypt_encrypt( MCRYPT_RIJNDAEL_256, $useKey, $contentData, MCRYPT_MODE_CBC, $useIv );
			$baseEncoded = $this->base64url_encode( $binEnc );
			if ( $asBase64 ) {
				return $baseEncoded;
			} else {
				return $binEnc;
			}
		}

		/**
		 * Decrypt content encoded with RIJNDAEL/AES-encryption
		 *
		 * @param string $encryptedContent
		 * @param bool $asBase64
		 *
		 * @return string
		 * @throws \Exception
		 * @since 6.0.0
		 */
		public function aesDecrypt( $encryptedContent = "", $asBase64 = true ) {
			$useKey = $this->aesKey;
			$useIv  = $this->aesIv;
			if ( $useKey == md5( md5( "TorneLIB Default IV - Please Change this" ) ) || $useIv == md5( md5( "TorneLIB Default IV - Please Change this" ) ) ) {
				throw new \Exception( "Current encryption key and iv is not allowed to use." );  // TODO: TORNELIB_EXCEPTIONS::TORNELIB_CRYPTO_KEY_EXCEPTION
			}
			$contentData = $encryptedContent;
			if ( $asBase64 ) {
				$contentData = $this->base64url_decode( $encryptedContent );
			}
			$decryptedOutput = trim( mcrypt_decrypt( MCRYPT_RIJNDAEL_256, $useKey, $contentData, MCRYPT_MODE_CBC, $useIv ) );

			return $decryptedOutput;
		}

		/**
		 * Compress data with gzencode and encode to base64url
		 *
		 * @param string $data
		 * @param int $compressionLevel
		 *
		 * @return string
		 * @throws \Exception
		 * @since 6.0.0
		 */
		public function base64_gzencode( $data = '', $compressionLevel = - 1 ) {

			if ( ! empty( $this->compressionLevel ) ) {
				$compressionLevel = $this->compressionLevel;
			}

			if ( ! function_exists( 'gzencode' ) ) {
				throw new \Exception( "Function gzencode is missing" );
			}
			$gzEncoded = gzencode( $data, $compressionLevel );

			return $this->base64url_encode( $gzEncoded );
		}

		/**
		 * Decompress gzdata that has been encoded with base64url
		 *
		 * @param string $data
		 *
		 * @return string
		 * @throws \Exception
		 * @since 6.0.0
		 */
		public function base64_gzdecode( $data = '' ) {
			$gzDecoded = $this->base64url_decode( $data );

			return $this->gzDecode( $gzDecoded );
		}

		/**
		 * Compress data with bzcompress and base64url-encode it
		 *
		 * @param string $data
		 *
		 * @return string
		 * @throws \Exception
		 * @since 6.0.0
		 */
		public function base64_bzencode( $data = '' ) {
			if ( ! function_exists( 'bzcompress' ) ) {
				throw new \Exception( "bzcompress is missing" );
			}
			$bzEncoded = bzcompress( $data );

			return $this->base64url_encode( $bzEncoded );
		}

		/**
		 * Decompress bzdata that has been encoded with base64url
		 *
		 * @param $data
		 *
		 * @return mixed
		 * @throws \Exception
		 * @since 6.0.0
		 */
		public function base64_bzdecode( $data ) {
			if ( ! function_exists( 'bzdecompress' ) ) {
				throw new \Exception( "bzdecompress is missing" );
			}
			$bzDecoded = $this->base64url_decode( $data );

			return bzdecompress( $bzDecoded );
		}

		/**
		 * Compress and encode data with best encryption
		 *
		 * @param string $data
		 *
		 * @return mixed
		 * @throws \Exception
		 * @since 6.0.0
		 */

		public function base64_compress( $data = '' ) {
			$results         = array();
			$bestCompression = null;
			$lengthArray     = array();
			if ( function_exists( 'gzencode' ) ) {
				$results['gz0'] = $this->base64_gzencode( "gz0:" . $data, 0 );
				$results['gz9'] = $this->base64_gzencode( "gz9:" . $data, 9 );
			}
			if ( function_exists( 'bzcompress' ) ) {
				$results['bz'] = $this->base64_bzencode( "bz:" . $data );
			}
			foreach ( $results as $type => $compressedString ) {
				$lengthArray[ $type ] = strlen( $compressedString );
			}
			asort( $lengthArray );
			foreach ( $lengthArray as $compressionType => $compressionLength ) {
				$bestCompression = $compressionType;
				break;
			}

			return $results[ $bestCompression ];
		}

		/**
		 * Decompress data that has been compressed with base64_compress
		 *
		 * @param string $data
		 * @param bool $getCompressionType
		 *
		 * @return string
		 * @throws \Exception
		 * @since 6.0.0
		 */
		public function base64_decompress( $data = '', $getCompressionType = false ) {
			$results       = array();
			$results['gz'] = $this->base64_gzdecode( $data );
			if ( function_exists( 'bzdecompress' ) ) {
				$results['bz'] = $this->base64_bzdecode( $data );
			}
			$acceptedString = "";
			foreach ( $results as $result ) {
				$resultExploded = explode( ":", $result, 2 );
				if ( isset( $resultExploded[0] ) && isset( $resultExploded[1] ) ) {
					if ( $resultExploded[0] == "gz0" || $resultExploded[0] == "gz9" ) {
						$acceptedString = $resultExploded[1];
						if ( $getCompressionType ) {
							$acceptedString = $resultExploded[0];
						}
						break;
					}
					if ( $resultExploded[0] == "bz" ) {
						$acceptedString = $resultExploded[1];
						if ( $getCompressionType ) {
							$acceptedString = $resultExploded[0];
						}
						break;
					}
				}
			}

			return $acceptedString;
		}

		/**
		 * Decode gzcompressed data. If gzdecode is actually missing (which has happened in early version of PHP), there will be a fallback to gzinflate instead
		 *
		 * @param $data
		 *
		 * @return string
		 * @throws \Exception
		 * @since 6.0.0
		 */
		private function gzDecode( $data ) {
			if ( function_exists( 'gzdecode' ) ) {
				return gzdecode( $data );
			}
			if ( ! function_exists( 'gzinflate' ) ) {
				throw new \Exception( "Function gzinflate and gzdecode is missing" );
			}
			// Inhherited from TorneEngine-Deprecated
			$flags       = ord( substr( $data, 3, 1 ) );
			$headerlen   = 10;
			$extralen    = 0;
			$filenamelen = 0;
			if ( $flags & 4 ) {
				$extralen  = unpack( 'v', substr( $data, 10, 2 ) );
				$extralen  = $extralen[1];
				$headerlen += 2 + $extralen;
			}
			if ( $flags & 8 ) // Filename
			{
				$headerlen = strpos( $data, chr( 0 ), $headerlen ) + 1;
			}
			if ( $flags & 16 ) // Comment
			{
				$headerlen = strpos( $data, chr( 0 ), $headerlen ) + 1;
			}
			if ( $flags & 2 ) // CRC at end of file
			{
				$headerlen += 2;
			}
			$unpacked = gzinflate( substr( $data, $headerlen ) );
			if ( $unpacked === false ) {
				$unpacked = $data;
			}

			return $unpacked;
		}

		/**
		 * URL compatible base64_encode
		 *
		 * @param $data
		 *
		 * @return string
		 * @since 6.0.0
		 */
		public function base64url_encode( $data ) {
			return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
		}

		/**
		 * URL compatible base64_decode
		 *
		 * @param $data
		 *
		 * @return string
		 * @since 6.0.0
		 */
		public function base64url_decode( $data ) {
			return base64_decode( str_pad( strtr( $data, '-_', '+/' ), strlen( $data ) % 4, '=', STR_PAD_RIGHT ) );
		}
	}
}

if ( ! class_exists( 'TORNELIB_CRYPTO_CRYPTOTYPES' ) && ! class_exists( 'TorneLIB\TORNELIB_CRYPTO_CRYPTOTYPES' ) ) {
	abstract class TORNELIB_CRYPTO_TYPES {
		const TYPE_NONE = 0;
		const TYPE_GZ = 1;
		const TYPE_BZ2 = 2;
	}
}

if ( ! class_exists( 'TorneLIB_Crypto' ) && ! class_exists( 'TorneLIB\TorneLIB_Crypto' ) ) {
	class TorneLIB_Crypto extends MODULE_CRYPTO {
	}
}