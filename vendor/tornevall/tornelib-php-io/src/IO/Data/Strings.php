<?php

namespace TorneLIB\IO\Data;

use Exception;

/**
 * Class Strings
 *
 * @package TorneLIB\IO\Data
 * @version 6.1.4
 */
class Strings
{
    /**
     * @param $string
     * @return string
     * @since 6.1.0
     */
    public function getCamelCase($string)
    {
        $return = @lcfirst(@implode(@array_map("ucfirst", preg_split('/-|_|\s+/', $string))));

        return $return;
    }

    /**
     * @param $string
     * @return string
     * @since 6.1.0
     */
    public static function returnCamelCase($string)
    {
        return (new Strings())->getCamelCase($string);
    }

    /**
     * @param $string
     * @return string
     * @since 6.1.0
     */
    public function getSnakeCase($string)
    {
        $return = preg_split('/(?=[A-Z])/', $string);

        if (is_array($return)) {
            $return = implode('_', array_map('strtolower', $return));
        }

        return (string)$return;
    }

    /**
     * @param $string
     * @return string
     * @since 6.1.0
     */
    public static function returnSnakeCase($string)
    {
        return (new Strings())->getSnakeCase($string);
    }

    /**
     * WP Style byte conversion for memory limits.
     *
     * @param $value
     * @return mixed
     */
    public function getBytes($value)
    {
        $value = strtolower(trim($value));
        $bytes = (int)$value;

        if (false !== strpos($value, 't')) {
            $bytes *= 1024 * 1024 * 1024 * 1024;
        } elseif (false !== strpos($value, 'g')) {
            $bytes *= 1024 * 1024 * 1024;
        } elseif (false !== strpos($value, 'm')) {
            $bytes *= 1024 * 1024;
        } elseif (false !== strpos($value, 'k')) {
            $bytes *= 1024;
        } elseif (false !== strpos($value, 'b')) {
            $bytes *= 1;
        }

        // Deal with large (float) values which run into the maximum integer size.
        return min($bytes, PHP_INT_MAX);
    }

    /**
     * @param $data
     * @return string
     * @since 6.1.0
     */
    public function base64urlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * @param $data
     * @return string
     * @since 6.1.0
     */
    public function base64urlDecode($data)
    {
        return (string)base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }

    /**
     * Convert all data to utf8
     * @param array $dataArray
     * @return array
     * @since 6.0.0
     */
    public function getUtf8($dataArray = [])
    {
        $newArray = [];
        if (is_array($dataArray)) {
            foreach ($dataArray as $p => $v) {
                if (is_array($v) || is_object($v)) {
                    $v = $this->getUtf8($v);
                    $newArray[$p] = $v;
                } else {
                    $v = utf8_encode($v);
                    $newArray[$p] = $v;
                }

            }
        }

        return $newArray;
    }

    /**
     * Obfuscate/anonymize strings.
     *
     * @param $string
     * @param string $replacementCharacter Default: *.
     * @param int $startPosition Default:  2.
     * @param int $stopPosition Default: 2.
     * @param int $rnd Sensitivity: 1-10000. Default is 4000, since we want more stars than real characters.
     * @return string
     * @throws Exception
     * @since 6.1.6
     */
    public function getObfuscatedString(
        $string,
        $replacementCharacter = '*',
        $startPosition = 2,
        $stopPosition = 2,
        $rnd = 4000
    ) {
        $return = $string;
        $startPosition = (int)$startPosition;
        $stopPosition = (int)$stopPosition;
        if (empty($replacementCharacter)) {
            $replacementCharacter = '*';
        }
        preg_match_all('/./', $string, $result);
        if (isset($result[0]) && is_array($result)) {
            $characterCount = count($result[0]);
            if ($characterCount <= 8) {
                $startPosition = 1;
                $stopPosition = 1;
            }
            $startAt = $startPosition ? $startPosition : 2;
            $stopAt = $stopPosition ? $stopPosition : 2;
            $newString = [];
            foreach ($result[0] as $pos => $character) {
                if (function_exists('random_int')) {
                    // Start use secure random if exists.
                    $isTrue = random_int(1, 10000) > ((int)$rnd < 10000 ? $rnd : 4000);
                } else {
                    $isTrue = rand(1, 10000) > ((int)$rnd < 10000 ? $rnd : 4000);
                }
                $wantCharacter = ($isTrue && $pos >= $startAt && $pos <= $characterCount - $stopAt);
                $newString[] = $wantCharacter ? $replacementCharacter : $character;
            }
            $return = implode('', $newString);
        }
        return (string)$return;
    }

    /**
     * Just obfuscate strings between first and last character.
     *
     * @param $string
     * @return string
     * @since 6.1.6
     */
    function getObfuscatedStringFull($string, $startAt = 1, $endAt = 1)
    {
        $stringLength = strlen($string);
        return $stringLength > $startAt - 1 ?
            substr($string, 0, $startAt) . str_repeat('*', $stringLength - 2) . substr(
                $string,
                $stringLength - $endAt,
                $stringLength - $endAt
            ) : $string;
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     * @since 6.1.0
     */
    public function __call($name, $arguments)
    {
        $return = null;

        if (method_exists($this, $this->getCamelCase($name))) {
            $return = call_user_func_array(
                [
                    $this,
                    $this->getCamelCase($name),
                ],
                $arguments
            );
        } elseif (method_exists($this, $this->getSnakeCase($name))) {
            $return = call_user_func_array(
                [
                    $this,
                    $this->getSnakeCase($name),
                ],
                $arguments
            );
        }

        return $return;
    }
}
