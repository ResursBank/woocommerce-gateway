<?php

namespace TorneLIB\Data;

use TorneLIB\Config\Flag;
use TorneLIB\Exception\Constants;
use TorneLIB\Exception\ExceptionHandler;
use TorneLIB\IO\Data\Strings;
use TorneLIB\Utils\Security;

/**
 * Class Aes OpenSSL encryption library with mcrypt failover for obsolete systems.
 *
 * @package TorneLIB\Data
 * @since 6.1.0
 */
class Aes
{
    /** @var int Available driver: None. */
    const CRYPTO_UNAVAILABLE = 0;

    /** @var int Available driver: OpenSSL. */
    const CRYPTO_SSL = 1;

    /** @var int Available driver: mcrypt (deprecated driver from PHP 7.1). */
    const CRYPTO_MCRYPT = 2;

    /**
     * @var string
     * @since 6.1.0
     */
    private $aesKey;

    /**
     * @var string
     * @since 6.1.1
     */
    private $aesKeyRaw;

    /**
     * @var string
     * @since 6.1.0
     */
    private $aesIv;

    /**
     * @var string
     * @since 6.1.1
     */
    private $aesIvRaw;

    /**
     * @var int
     * @since 6.1.0
     */
    private $aesIvLength;

    /**
     * @var
     */
    private $cryptoLib;

    /**
     * @var bool $canMcrypt
     */
    private $canMcrypt = false;

    /**
     * @var
     */
    private $sslCipherType;

    /**
     * @var bool
     */
    private $mcryptOverSsl = false;

    /**
     * Aes constructor.
     * @since 6.1.0
     */
    public function __construct()
    {
        $this->setCryptoLib();

        return $this;
    }

    /**
     * openssl has higher priority.
     *
     * @return $this
     * @throws ExceptionHandler
     */
    private function setCryptoLib()
    {
        $this->cryptoLib = Aes::CRYPTO_UNAVAILABLE;

        if (Security::getCurrentFunctionState('mcrypt_encrypt', false)) {
            $this->canMcrypt = true;
        }

        // Depend on openssl. If not there, fallback to mcrypt.
        if (Security::getCurrentFunctionState('openssl_encrypt', false) &&
            !$this->getMcryptOverSsl()
        ) {
            $this->cryptoLib = Aes::CRYPTO_SSL;
            $this->setCipher();
        } elseif (Security::getCurrentFunctionState('mcrypt_encrypt', false)) {
            // If mcrypt is present but platform is PHP 7.1+ we won't proceed as there are
            // no proper encryption available.
            if (version_compare(PHP_VERSION, '7.1', '<=')) {
                $this->cryptoLib = Aes::CRYPTO_MCRYPT;
                $this->canMcrypt = true;
            } else {
                throw new ExceptionHandler(
                    'OpenSSL is unavailable in your platform and mcrypt is deprecated in PHP releases above 7.1.',
                    Constants::LIB_METHOD_OR_LIBRARY_UNAVAILABLE
                );
            }
        }

        return $this;
    }

    /**
     * @return int
     */
    public function getCryptoLib()
    {
        return $this->cryptoLib;
    }

    /**
     * @param $key
     * @param $iv
     * @param string $method
     * @return Aes
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    public function setAesKeys($key, $iv, $method = 'sha1')
    {
        $this->aesKeyRaw = $key;
        $this->aesIvRaw = $iv;

        // Check if a testflag is available, to switch over to mcrypt.
        // If openssl is absent, switch over to mcrypt keying (md5 instead of sha1) automatically.
        if (
            (
                Flag::getFlag('mcrypt') ||
                !Security::getCurrentFunctionState('openssl_encrypt', false) ||
                $this->getMcryptOverSsl()
            ) &&
            $method === 'sha1') {
            $method = 'md5';
        }

        if ($method === 'sha1') {
            $this->aesKey = sha1($key);
            $this->aesIv = sha1($iv);
        } elseif ($method === 'md5') {
            $this->aesKey = md5($key);
            $this->aesIv = md5($iv);
        } else {
            $this->aesKey = $key;
            $this->aesIv = $iv;
        }

        return $this;
    }

    /**
     * @param bool $adjustLength
     * @return mixed
     * @throws ExceptionHandler
     * @since 6.0.15
     */
    public function getAesIv($adjustLength = true)
    {
        if (Security::getCurrentFunctionState('openssl_cipher_iv_length', false)) {
            if ($adjustLength) {
                $this->aesIvLength = openssl_cipher_iv_length($this->getSslCipherType());
                if ((int)$this->aesIvLength >= 0) {
                    if (strlen($this->aesIv) > $this->aesIvLength) {
                        $this->aesIv = substr($this->aesIv, 0, $this->aesIvLength);
                    }
                }
            }
        }

        return $this->aesIv;
    }

    /**
     * @return mixed
     * @since 6.0.15
     */
    public function getAesKey()
    {
        return $this->aesKey;
    }

    /**
     * @param int $compressionLevel
     * @return Aes
     * @since 6.1.0
     */
    public function setCompressionLevel($compressionLevel = 9)
    {
        $this->compressionLevel = $compressionLevel;

        return $this;
    }

    /**
     * @return int
     */
    public function getAesIvLength()
    {
        return $this->aesIvLength;
    }

    /**
     * @param string $cipherConstant
     * @return Aes
     */
    public function setCipher($cipherConstant = 'AES-256-CBC')
    {
        $cipherMethods = openssl_get_cipher_methods();

        if (
            is_array($cipherMethods) &&
            in_array(
                strtolower($cipherConstant),
                array_map('strtolower', $cipherMethods)
            )
        ) {
            $this->sslCipherType = $cipherConstant;

            return $this;
        }

        throw new Exception(
            'Cipher does not exists in this openssl module',
            Constants::LIB_SSL_CIPHER_UNAVAILABLE
        );
    }

    public function aesEncrypt($dataToEncrypt = '', $asBase64 = true, $forceUtf8 = true)
    {
        if (
            (
                $this->getCryptoLib() === self::CRYPTO_MCRYPT ||
                Flag::getFlag('mcrypt')
            ) &&
            $this->canMcrypt
        ) {
            $return = $this->getEncryptedMcrypt(
                $dataToEncrypt,
                $asBase64,
                $forceUtf8
            );
        } else {
            $return = $this->getEncryptedSsl(
                $dataToEncrypt,
                $asBase64,
                $forceUtf8
            );
        }

        return $return;
    }

    /**
     * @param string $dataToEncrypt
     * @param bool $asBase64
     * @param bool $forceUtf8
     * @return false|string
     * @throws ExceptionHandler
     * since 6.1.0
     */
    private function getEncryptedSsl($dataToEncrypt = '', $asBase64 = true, $forceUtf8 = true)
    {
        if (empty($this->aesKey) || empty($this->aesIv)) {
            throw new ExceptionHandler(
                'You need to set KEY and IV to encrypt content.',
                Constants::LIB_SSL_CIPHER_NO_KEYS
            );
        }

        $return = openssl_encrypt(
            $forceUtf8 ? utf8_encode($dataToEncrypt) : $dataToEncrypt,
            $this->getSslCipherType(),
            $this->getAesKey(),
            OPENSSL_RAW_DATA,
            $this->getAesIv(true)
        );

        if ($asBase64) {
            $return = (new Strings())->base64urlEncode($return);
        }

        return $return;
    }

    /**
     * Statically encrypting with RIJNDAEL_256.
     *
     * @param string $dataToEncrypt
     * @param bool $asBase64
     * @param bool $forceUtf8
     * @return string
     * @throws ExceptionHandler
     */
    private function getEncryptedMcrypt(
        $dataToEncrypt = '',
        $asBase64 = true,
        $forceUtf8 = true
    ) {
        $return = mcrypt_encrypt(
            MCRYPT_RIJNDAEL_256,
            $this->getAesKey(),
            $forceUtf8 ? utf8_encode($dataToEncrypt) : $dataToEncrypt,
            MCRYPT_MODE_CBC,
            $this->getAesIv(false)
        );

        if ($asBase64) {
            $return = (new Strings())->base64urlEncode($return);
        }

        return $return;
    }

    /**
     * @param $dataToDecrypt
     * @param bool $asBase64
     * @return false|string
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    public function aesDecrypt($dataToDecrypt, $asBase64 = true)
    {
        if (empty($this->aesKey) || empty($this->aesIv)) {
            throw new ExceptionHandler(
                'You need to set KEY and IV to encrypt content.',
                Constants::LIB_SSL_CIPHER_NO_KEYS
            );
        }

        return $this->getDecryptedSsl($dataToDecrypt, $asBase64);
    }

    /**
     * @param $dataToDecrypt
     * @param $asBase64
     * @return false|string
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    public function getDecryptedSsl($dataToDecrypt, $asBase64)
    {
        if ($asBase64) {
            $dataToDecrypt = (new Strings())->base64urlDecode($dataToDecrypt);
        }

        return openssl_decrypt(
            $dataToDecrypt,
            $this->getSslCipherType(),
            $this->getAesKey(),
            OPENSSL_RAW_DATA,
            $this->getAesIv(true)
        );
    }

    /**
     * @return mixed
     */
    public function getSslCipherType()
    {
        return $this->sslCipherType;
    }

    /**
     * @param $encrypted
     * @param $decrypted
     * @return string
     * @throws ExceptionHandler
     */
    public function getCipherTypeByString($encrypted, $decrypted)
    {
        $return = '';
        $originalKey = $this->getAesKey();
        $originalIv = $this->getAesIv(false);
        if ($this->getCryptoLib() === self::CRYPTO_SSL) {
            $cipherTypes = openssl_get_cipher_methods();
            foreach ($cipherTypes as $type) {
                try {
                    $this->setCipher($type);
                    $this->setAesKeys($originalKey, $originalIv, 'plain');
                    $result = $this->getEncryptedSsl($decrypted);
                    if (!empty($result) && $result === $encrypted) {
                        $return = $type;
                        break;
                    }
                } catch (Exception $e) {
                }
            }
        }

        return (string)$return;
    }

    /**
     * @return bool
     * @since 6.1.1
     */
    public function getMcryptOverSsl()
    {
        return $this->mcryptOverSsl;
    }

    /**
     * @param bool $mcryptOverSsl
     * @return Aes
     * @throws ExceptionHandler
     * @since 6.1.1
     */
    public function setMcryptOverSsl($mcryptOverSsl = false)
    {
        $this->mcryptOverSsl = $mcryptOverSsl;

        // Rerun procedure.
        $this->setCryptoLib();
        $this->setAesKeys($this->aesKeyRaw, $this->aesIvRaw);

        return $this;
    }
}
