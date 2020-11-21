<?php

namespace TorneLIB\IO;

use TorneLIB\Exception\Constants;
use TorneLIB\Exception\ExceptionHandler;
use TorneLIB\IO\Data\Arrays;
use TorneLIB\IO\Data\Content;
use TorneLIB\IO\Data\Strings;

/**
 * Class Bridge IO-bridge.
 *
 * @package TorneLIB\IO
 * @deprecated If you ever thinking of using this, please don't. Use direct calls instead.
 * @version 6.1.4
 */
class Bridge
{
    /**
     * @var Strings
     */
    private $strings;

    /**
     * @var Content
     */
    private $content;

    /**
     * @var Arrays
     */
    private $arrays;

    /**
     * Bridge constructor.
     */
    public function __construct()
    {
        $this->strings = new Strings();
        $this->content = new Content();
        $this->arrays = new Arrays();
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     * @throws ExceptionHandler
     */
    public function __call($name, $arguments)
    {
        $return = null;
        $requestClass = null;

        if (method_exists($this->content, $name)) {
            $requestClass = $this->content;
        }
        if (method_exists($this->strings, $name)) {
            $requestClass = $this->content;
        }
        if (method_exists($this->arrays, $name)) {
            $requestClass = $this->content;
        }

        if (!is_null($requestClass)) {
            $return = call_user_func_array(
                [
                    $requestClass,
                    $name,
                ],
                $arguments
            );
        }

        if (is_null($return)) {
            throw new ExceptionHandler(
                sprintf(
                    'There is no method named "%s" in class %s',
                    $name,
                    __CLASS__
                ),
                Constants::LIB_METHOD_OR_LIBRARY_UNAVAILABLE
            );
        }

        return $return;
    }
}