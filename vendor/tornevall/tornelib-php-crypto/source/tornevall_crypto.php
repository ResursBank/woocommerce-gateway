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
 * @version 6.0.17
 *
 * Crypto-IO Library. Anything that changes in those folders, will render version increase.
 */

namespace TorneLIB;

if ( ! defined('TORNELIB_CRYPTO_RELEASE')) {
    define('TORNELIB_CRYPTO_RELEASE', '6.0.17');
}
if ( ! defined('TORNELIB_CRYPTO_MODIFY')) {
    define('TORNELIB_CRYPTO_MODIFY', '20180624');
}
if ( ! defined('TORNELIB_CRYPTO_CLIENTNAME')) {
    define('TORNELIB_CRYPTO_CLIENTNAME', 'MODULE_CRYPTO');
}

if (defined('TORNELIB_CRYPTO_REQUIRE')) {
    if ( ! defined('TORNELIB_CRYPTO_REQUIRE_OPERATOR')) {
        define('TORNELIB_CRYPTO_REQUIRE_OPERATOR', '==');
    }
    define('TORNELIB_CRYPTO_ALLOW_AUTOLOAD', version_compare(TORNELIB_CRYPTO_RELEASE, TORNELIB_CRYPTO_REQUIRE,
        TORNELIB_CRYPTO_REQUIRE_OPERATOR) ? true : false);
} else {
    if ( ! defined('TORNELIB_CRYPTO_ALLOW_AUTOLOAD')) {
        define('TORNELIB_CRYPTO_ALLOW_AUTOLOAD', true);
    }
}

if ( ! class_exists('MODULE_CRYPTO') && ! class_exists('TorneLIB\MODULE_CRYPTO') && defined('TORNELIB_CRYPTO_ALLOW_AUTOLOAD') && TORNELIB_CRYPTO_ALLOW_AUTOLOAD === true) {

    /**
     * Class TorneLIB_Crypto
     */
    class MODULE_CRYPTO
    {

        private $ENCRYPT_AES_KEY = "";
        private $ENCRYPT_AES_IV = "";

        /**
         * @var $OPENSSL_CIPHER_METHOD
         * @since 6.0.15
         */
        private $OPENSSL_CIPHER_METHOD;

        /**
         * @var $OPENSSL_IV_LENGTH
         * @since 6.0.15
         */
        private $OPENSSL_IV_LENGTH;
        private $COMPRESSION_LEVEL;

        private $USE_MCRYPT = false;

        /**
         * TorneLIB_Crypto constructor.
         */
        function __construct()
        {
            $this->setAesIv(md5("TorneLIB Default IV - Please Change this"));
            $this->setAesKey(md5("TorneLIB Default KEY - Please Change this"));
        }

        /**
         * Set and override compression level
         *
         * @param int $compressionLevel
         *
         * @since 6.0.6
         */
        function setCompressionLevel($compressionLevel = 9)
        {
            $this->COMPRESSION_LEVEL = $compressionLevel;
        }

        /**
         * Get current compressionlevel
         *
         * @return mixed
         * @since 6.0.6
         */
        public function getCompressionLevel()
        {
            return $this->COMPRESSION_LEVEL;
        }

        /**
         * @param bool $enable
         *
         * @since 6.0.15
         */
        public function setMcrypt($enable = false)
        {
            $this->USE_MCRYPT = $enable;
        }

        /**
         * @return bool
         * @since 6.0.15
         */
        public function getMcrypt()
        {
            return $this->USE_MCRYPT;
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
         * @param int  $complexity
         * @param int  $setMax      Max string length to use
         * @param bool $webFriendly Set to true works best with the less complex strings as it only removes characters that could be mistaken by another character (O,0,1,l,I etc)
         *
         * @return string
         * @deprecated 6.0.4 Still here for people who needs it
         */
        function mkpass_deprecated($complexity = 4, $setMax = 8, $webFriendly = false)
        {
            $returnString       = null;
            $characterListArray = array(
                'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
                'abcdefghijklmnopqrstuvwxyz',
                '0123456789',
                '!@#$%*?'
            );
            // Set complexity to no limit if type 6 is requested
            if ($complexity == 6) {
                $characterListArray = array('0' => '');
                for ($unlim = 0; $unlim <= 255; $unlim++) {
                    $characterListArray[0] .= chr($unlim);
                }
                if ($setMax == null) {
                    $setMax = 15;
                }
            }
            // Backward-compatibility in the complexity will still give us captcha-capabilities for simpler users
            $max = 8;       // Longest complexity
            if ($complexity == 1) {
                unset($characterListArray[1], $characterListArray[2], $characterListArray[3]);
                $max = 6;
            }
            if ($complexity == 2) {
                unset($characterListArray[2], $characterListArray[3]);
                $max = 10;
            }
            if ($complexity == 3) {
                unset($characterListArray[3]);
                $max = 10;
            }
            if ($setMax > 0) {
                $max = $setMax;
            }
            $chars    = array();
            $numchars = array();
            //$equalityPart = ceil( $max / count( $characterListArray ) );
            for ($i = 0; $i < $max; $i++) {
                $charListId = rand(0, count($characterListArray) - 1);
                if ( ! isset($numchars[$charListId])) {
                    $numchars[$charListId] = 0;
                }
                $numchars[$charListId]++;
                $chars[] = $characterListArray[$charListId]{mt_rand(0, (strlen($characterListArray[$charListId]) - 1))};
            }
            shuffle($chars);
            $returnString = implode("", $chars);
            if ($webFriendly) {
                // The lazyness
                $returnString = preg_replace("/[+\/=IG0ODQR]/i", "", $returnString);
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
        private function getCharacterListArray($type = 'upper')
        {
            $compiledArray = array(
                'upper'    => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
                'lower'    => 'abcdefghijklmnopqrstuvwxyz',
                'numeric'  => '0123456789',
                'specials' => '!@#$%*?',
                'table'    => ''
            );
            for ($i = 0; $i <= 255; $i++) {
                $compiledArray['table'] .= chr($i);
            }

            switch ($type) {
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
        private function getCharactersFromList($type = 'upper')
        {
            return preg_split("//", $this->getCharacterListArray($type), -1, PREG_SPLIT_NO_EMPTY);
        }

        /**
         * Returns a random character from a selected character list
         *
         * @param array $type
         * @param bool  $ambigous
         *
         * @return mixed|string
         * @since 6.0.4
         */
        private function getRandomCharacterFromArray($type = array('upper'), $ambigous = false)
        {
            if (is_string($type)) {
                $type = array($type);
            }
            $getType         = $type[rand(0, count($type) - 1)];
            $characterArray  = $this->getCharactersFromList($getType);
            $characterLength = count($characterArray) - 1;
            $chosenCharacter = $characterArray[rand(0, $characterLength)];
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
            if (in_array($chosenCharacter, $ambigousList)) {
                $chosenCharacter = $this->getRandomCharacterFromArray($type, $ambigous);
            }

            return $chosenCharacter;
        }

        /**
         * Returns a random character based on complexity selection
         *
         * @param int  $complexity
         * @param bool $ambigous
         *
         * @return mixed|string
         * @since 6.0.4
         */
        private function getCharacterFromComplexity($complexity = 4, $ambigous = false)
        {
            switch ($complexity) {
                case 1:
                    return $this->getRandomCharacterFromArray(array('upper'), $ambigous);
                case 2:
                    return $this->getRandomCharacterFromArray(array('upper', 'lower'), $ambigous);
                case 3:
                    return $this->getRandomCharacterFromArray(array('upper', 'lower', 'numeric'), $ambigous);
                case 4:
                    return $this->getRandomCharacterFromArray(array(
                        'upper',
                        'lower',
                        'numeric',
                        'specials'
                    ), $ambigous);
                case 5:
                    return $this->getRandomCharacterFromArray(array('table'));
                case 6:
                    return $this->getRandomCharacterFromArray(array('table'));
                default:
                    return $this->getRandomCharacterFromArray('upper', $ambigous);
            }
        }

        /**
         * Refactored generator to create a random password or string
         *
         * @param int  $complexity  1=UPPERCASE, 2=UPPERCASE+lowercase, 3=UPPERCASE+lowercase+numerics, 4=UPPERCASE,lowercase+numerics+specialcharacters, 5/6=Full character set
         * @param int  $totalLength Length of the string
         * @param bool $ambigous    Exclude what we see as ambigous characters (this has no effect in complexity > 4)
         *
         * @return string
         * @since 6.0.4
         */
        public function mkpass($complexity = 4, $totalLength = 16, $ambigous = false)
        {
            $pwString = "";
            for ($charIndex = 0; $charIndex < $totalLength; $charIndex++) {
                $pwString .= $this->getCharacterFromComplexity($complexity, $ambigous);
            }

            return $pwString;
        }

        /**
         * @param int  $complexity
         * @param int  $totalLength
         * @param bool $ambigous
         *
         * @return string
         * @since 6.0.7
         */
        public static function getRandomSalt($complexity = 4, $totalLength = 16, $ambigous = false)
        {
            $selfInstance = new TorneLIB_Crypto();

            return $selfInstance->mkpass($complexity, $totalLength, $ambigous);
        }

        /**
         * Set up key for aes encryption.
         *
         * @param      $useKey
         * @param bool $noHash
         *
         * @since 6.0.0
         */
        public function setAesKey($useKey, $noHash = false)
        {
            if ( ! $noHash) {
                $this->ENCRYPT_AES_KEY = md5($useKey);
            } else {
                $this->ENCRYPT_AES_KEY = $useKey;
            }
        }

        /**
         * Set up ip for aes encryption
         *
         * @param      $useIv
         * @param bool $noHash
         *
         * @since 6.0.0
         */
        public function setAesIv($useIv, $noHash = false)
        {
            if ( ! $noHash) {
                $this->ENCRYPT_AES_IV = md5($useIv);
            } else {
                $this->ENCRYPT_AES_IV = $useIv;
            }
        }

        /**
         * @return string
         * @since 6.0.15
         */
        public function getAesKey()
        {
            return $this->ENCRYPT_AES_KEY;
        }

        /**
         * @param bool $adjustLength
         *
         * @return string
         * @since 6.0.15
         */
        public function getAesIv($adjustLength = true)
        {
            if ($adjustLength) {
                if ((int)$this->OPENSSL_IV_LENGTH >= 0) {
                    if (strlen($this->ENCRYPT_AES_IV) > $this->OPENSSL_IV_LENGTH) {
                        $this->ENCRYPT_AES_IV = substr($this->ENCRYPT_AES_IV, 0, $this->OPENSSL_IV_LENGTH);
                    }
                }
            }

            return $this->ENCRYPT_AES_IV;
        }

        /**
         * @param bool $throwOnProblems
         *
         * @return bool
         * @throws \Exception
         * @since 6.0.15
         */
        private function getOpenSslEncrypt($throwOnProblems = true)
        {
            if (function_exists('openssl_encrypt')) {
                return true;
            }
            if ($throwOnProblems) {
                throw new \Exception("openssl_encrypt does not exist in this system. Do you have it installed?");
            }

            return false;
        }

        /**
         * Encrypt content to RIJNDAEL/AES-encryption (Deprecated from PHP 7.1, removed in PHP 7.2)
         *
         * @param string $decryptedContent
         * @param bool   $asBase64
         * @param bool   $forceUtf8
         *
         * @return string
         * @throws \Exception
         * @since 6.0.0
         */
        public function aesEncrypt($decryptedContent = "", $asBase64 = true, $forceUtf8 = true)
        {

            if ( ! $this->USE_MCRYPT) {
                $this->setSslCipher('AES-256-CBC');

                return $this->getEncryptSsl($decryptedContent, $asBase64, $forceUtf8);
            }

            $contentData = $decryptedContent;
            if ( ! function_exists('mcrypt_encrypt')) {
                throw new \Exception("mcrypt does not exist in this system - it has been deprecated since PHP 7.1");
            }
            if ($this->ENCRYPT_AES_KEY == md5(md5("TorneLIB Default IV - Please Change this")) || $this->ENCRYPT_AES_IV == md5(md5("TorneLIB Default IV - Please Change this"))) {
                // TODO: TORNELIB_EXCEPTIONS::TORNELIB_CRYPTO_KEY_EXCEPTION
                throw new \Exception("Current encryption key and iv is not allowed to use.");
            }
            if (is_string($decryptedContent) && $forceUtf8) {
                $contentData = utf8_encode($decryptedContent);
            }
            /** @noinspection PhpDeprecationInspection */
            $binEnc      = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $this->ENCRYPT_AES_KEY, $contentData, MCRYPT_MODE_CBC,
                $this->ENCRYPT_AES_IV);
            $baseEncoded = $this->base64url_encode($binEnc);
            if ($asBase64) {
                return $baseEncoded;
            } else {
                return $binEnc;
            }
        }

        /**
         * @param $cipherConstant
         *
         * @return mixed
         * @throws \Exception
         * @since 6.0.15
         */
        private function setUseCipher($cipherConstant)
        {
            $this->getOpenSslEncrypt();
            if (in_array($cipherConstant, openssl_get_cipher_methods())) {
                $this->OPENSSL_CIPHER_METHOD = $cipherConstant;
                $this->OPENSSL_IV_LENGTH     = $this->getIvLength($cipherConstant);

                return $cipherConstant;
            }
            throw new \Exception("Cipher does not exists in this openssl module");
        }

        /** @noinspection PhpUnusedPrivateMethodInspection */
        /**
         * @return mixed
         * @since 6.0.15
         */
        private function getUseCipher()
        {
            return $this->OPENSSL_CIPHER_METHOD;
        }

        /**
         * @param $cipherConstant
         *
         * @return int
         * @throws \Exception
         * @since 6.0.15
         */
        private function getIvLength($cipherConstant)
        {
            $this->getOpenSslEncrypt();
            if ( ! empty($cipherConstant)) {
                return openssl_cipher_iv_length($cipherConstant);
            }

            return openssl_cipher_iv_length($this->OPENSSL_CIPHER_METHOD);
        }

        /**
         * Get the cipher method name by comparing them (for testing only)
         *
         * @param string $encryptedString
         * @param string $decryptedString
         *
         * @return null
         * @throws \Exception
         * @since 6.0.15
         */
        public function getCipherTypeByString($encryptedString = "", $decryptedString = "")
        {
            $this->getOpenSslEncrypt();
            $cipherMethods  = openssl_get_cipher_methods();
            $skippedMethods = array();
            $originalKey    = $this->ENCRYPT_AES_KEY;
            $originalIv     = $this->ENCRYPT_AES_IV;
            foreach ($cipherMethods as $method) {
                if ( ! in_array($method, $skippedMethods)) {
                    //$skippedMethods[] = strtoupper($method);
                    try {
                        $this->ENCRYPT_AES_KEY = $originalKey;
                        $this->ENCRYPT_AES_IV  = $originalIv;
                        $this->setSslCipher($method);
                        $result = $this->getEncryptSsl($decryptedString);
                        if ( ! empty($result) && $result == $encryptedString) {
                            return $method;
                        }
                    } catch (\Exception $e) {
                    }
                }
            }

            return null;
        }

        /**
         * @param string $decryptedContent
         * @param bool   $asBase64
         * @param bool   $forceUtf8
         *
         * @return string
         * @throws \Exception
         */
        public function getEncryptSsl($decryptedContent = "", $asBase64 = true, $forceUtf8 = true)
        {
            if ($this->ENCRYPT_AES_KEY == md5(md5("TorneLIB Default IV - Please Change this")) || $this->ENCRYPT_AES_IV == md5(md5("TorneLIB Default IV - Please Change this"))) {
                throw new \Exception("Current encryption key and iv is not allowed to use.");
            }

            if ($forceUtf8 && is_string($decryptedContent)) {
                $contentData = utf8_encode($decryptedContent);
            } else {
                $contentData = $decryptedContent;
            }

            if (empty($this->OPENSSL_CIPHER_METHOD)) {
                $this->setSslCipher();
            } else {
                $this->setUseCipher($this->OPENSSL_CIPHER_METHOD);
            }

            // TODO: openssl_random_pseudo_bytes
            $binEnc = openssl_encrypt($contentData, $this->OPENSSL_CIPHER_METHOD, $this->getAesKey(), OPENSSL_RAW_DATA,
                $this->getAesIv(true));

            $baseEncoded = $this->base64url_encode($binEnc);
            if ($asBase64) {
                return $baseEncoded;
            } else {
                return $binEnc;
            }

        }

        /**
         * @param      $encryptedContent
         * @param bool $asBase64
         *
         * @return string
         * @throws \Exception
         * @since 6.0.15
         */
        public function getDecryptSsl($encryptedContent, $asBase64 = true)
        {
            $contentData = $encryptedContent;
            if ($asBase64) {
                $contentData = $this->base64url_decode($encryptedContent);
            }
            if (empty($this->OPENSSL_CIPHER_METHOD)) {
                $this->setSslCipher();
            } else {
                $this->setUseCipher($this->OPENSSL_CIPHER_METHOD);
            }

            // TODO: openssl_random_pseudo_bytes
            return openssl_decrypt($contentData, $this->OPENSSL_CIPHER_METHOD, $this->getAesKey(), OPENSSL_RAW_DATA,
                $this->getAesIv(true));
        }

        /**
         * @param string $cipherConstant
         *
         * @throws \Exception
         * @since 6.0.15
         */
        public function setSslCipher($cipherConstant = 'AES-256-CBC')
        {
            $this->setUseCipher($cipherConstant);
        }

        /**
         * Decrypt content encoded with RIJNDAEL/AES-encryption
         *
         * @param string $encryptedContent
         * @param bool   $asBase64
         *
         * @return string
         * @throws \Exception
         * @since 6.0.0
         */
        public function aesDecrypt($encryptedContent = "", $asBase64 = true)
        {

            if ( ! $this->USE_MCRYPT) {
                return $this->getDecryptSsl($encryptedContent, $asBase64);
            }

            $useKey = $this->ENCRYPT_AES_KEY;
            $useIv  = $this->ENCRYPT_AES_IV;
            if ($useKey == md5(md5("TorneLIB Default IV - Please Change this")) || $useIv == md5(md5("TorneLIB Default IV - Please Change this"))) {
                // TODO: TORNELIB_EXCEPTIONS::TORNELIB_CRYPTO_KEY_EXCEPTION
                throw new \Exception("Current encryption key and iv is not allowed to use.");
            }
            $contentData = $encryptedContent;
            if ($asBase64) {
                $contentData = $this->base64url_decode($encryptedContent);
            }
            /** @noinspection PhpDeprecationInspection */
            $decryptedOutput = trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $useKey, $contentData, MCRYPT_MODE_CBC,
                $useIv));

            return $decryptedOutput;
        }

        /**
         * Compress data with gzencode and encode to base64url
         *
         * @param string $data
         * @param int    $compressionLevel
         *
         * @return string
         * @throws \Exception
         * @since 6.0.0
         */
        public function base64_gzencode($data = '', $compressionLevel = -1)
        {

            if ( ! empty($this->COMPRESSION_LEVEL)) {
                $compressionLevel = $this->COMPRESSION_LEVEL;
            }

            if ( ! function_exists('gzencode')) {
                throw new \Exception("Function gzencode is missing");
            }
            $gzEncoded = gzencode($data, $compressionLevel);

            return $this->base64url_encode($gzEncoded);
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
        public function base64_gzdecode($data = '')
        {
            $gzDecoded = $this->base64url_decode($data);

            return $this->gzDecode($gzDecoded);
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
        public function base64_bzencode($data = '')
        {
            if ( ! function_exists('bzcompress')) {
                throw new \Exception("bzcompress is missing");
            }
            $bzEncoded = bzcompress($data);

            return $this->base64url_encode($bzEncoded);
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
        public function base64_bzdecode($data)
        {
            if ( ! function_exists('bzdecompress')) {
                throw new \Exception("bzdecompress is missing");
            }
            $bzDecoded = $this->base64url_decode($data);

            return bzdecompress($bzDecoded);
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

        public function base64_compress($data = '')
        {
            $results         = array();
            $bestCompression = null;
            $lengthArray     = array();
            if (function_exists('gzencode')) {
                $results['gz0'] = $this->base64_gzencode("gz0:" . $data, 0);
                $results['gz9'] = $this->base64_gzencode("gz9:" . $data, 9);
            }
            if (function_exists('bzcompress')) {
                $results['bz'] = $this->base64_bzencode("bz:" . $data);
            }
            foreach ($results as $type => $compressedString) {
                $lengthArray[$type] = strlen($compressedString);
            }
            asort($lengthArray);
            foreach ($lengthArray as $compressionType => $compressionLength) {
                $bestCompression = $compressionType;
                break;
            }

            return $results[$bestCompression];
        }

        /**
         * Decompress data that has been compressed with base64_compress
         *
         * @param string $data
         * @param bool   $getCompressionType
         *
         * @return string
         * @throws \Exception
         * @since 6.0.0
         */
        public function base64_decompress($data = '', $getCompressionType = false)
        {
            $results       = array();
            $results['gz'] = $this->base64_gzdecode($data);
            if (function_exists('bzdecompress')) {
                $results['bz'] = $this->base64_bzdecode($data);
            }
            $acceptedString = "";
            foreach ($results as $result) {
                $resultExploded = explode(":", $result, 2);
                if (isset($resultExploded[0]) && isset($resultExploded[1])) {
                    if ($resultExploded[0] == "gz0" || $resultExploded[0] == "gz9") {
                        $acceptedString = $resultExploded[1];
                        if ($getCompressionType) {
                            $acceptedString = $resultExploded[0];
                        }
                        break;
                    }
                    if ($resultExploded[0] == "bz") {
                        $acceptedString = $resultExploded[1];
                        if ($getCompressionType) {
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
        private function gzDecode($data)
        {
            if (function_exists('gzdecode')) {
                return gzdecode($data);
            }
            if ( ! function_exists('gzinflate')) {
                throw new \Exception("Function gzinflate and gzdecode is missing");
            }
            // Inhherited from TorneEngine-Deprecated
            $flags     = ord(substr($data, 3, 1));
            $headerlen = 10;
            //$extralen    = 0;
            //$filenamelen = 0;
            if ($flags & 4) {
                $extralen  = unpack('v', substr($data, 10, 2));
                $extralen  = $extralen[1];
                $headerlen += 2 + $extralen;
            }
            if ($flags & 8) // Filename
            {
                $headerlen = strpos($data, chr(0), $headerlen) + 1;
            }
            if ($flags & 16) // Comment
            {
                $headerlen = strpos($data, chr(0), $headerlen) + 1;
            }
            if ($flags & 2) // CRC at end of file
            {
                $headerlen += 2;
            }
            $unpacked = gzinflate(substr($data, $headerlen));
            if ($unpacked === false) {
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
        public function base64url_encode($data)
        {
            return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
        }

        /**
         * URL compatible base64_decode
         *
         * @param $data
         *
         * @return string
         * @since 6.0.0
         */
        public function base64url_decode($data)
        {
            return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
        }
    }
}

if ( ! class_exists('TORNELIB_CRYPTO_TYPES') && ! class_exists('TorneLIB\TORNELIB_CRYPTO_TYPES')) {
    abstract class TORNELIB_CRYPTO_TYPES
    {
        const TYPE_NONE = 0;
        const TYPE_GZ = 1;
        const TYPE_BZ2 = 2;
    }
}

if ( ! class_exists('TorneLIB_Crypto') && ! class_exists('TorneLIB\TorneLIB_Crypto')) {
    class TorneLIB_Crypto extends MODULE_CRYPTO
    {
    }
}