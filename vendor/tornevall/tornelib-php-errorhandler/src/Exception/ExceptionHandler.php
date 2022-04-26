<?php

namespace TorneLIB\Exception;

use Exception;

/**
 * Class ExceptionHandler
 * @package TorneLIB\Exception
 * @version 6.1.21
 */
class ExceptionHandler extends Exception
{
    /**
     * @var null
     */
    private $extendException;

    /**
     * @var string
     */
    private $traceFunction;

    /**
     * @var string
     */
    private $stringifiedCode;

    /**
     * ExceptionHandler constructor.
     * @param string $message
     * @param int $code
     * @param null $previous
     * @param null $stringifiedCode
     * @param string $fromFunction
     * @param null $extendException
     */
    public function __construct(
        $message = 'Unknown exception',
        $code = 0,
        $previous = null,
        $stringifiedCode = null,
        $fromFunction = '',
        $extendException = null
    ) {
        if (!$code) {
            if (!defined('LIB_ERROR_HTTP')) {
                // Use internal error.
                $code = Constants::LIB_UNHANDLED;
            } else {
                // Use bad request when unknown and LIB_ERROR_HTTP is enabled.
                $code = 400;
            }
        } else {
            if (!empty($code) && is_string($code)) {
                $code = $this->getValueConstant($code);
            }
        }

        parent::__construct($message, $code, $previous);
        $this->traceFunction = $fromFunction;
        $this->stringifiedCode = $stringifiedCode;
        $this->setStringifiedCode();
        $this->extendException = $extendException;
    }

    /**
     * @param $code
     * @return int|mixed
     */
    private function getValueConstant($code)
    {
        if (!defined('LIB_ERROR_HTTP_CUSTOM') && !is_numeric($code)) {
            // Make it possible to push a stringified code into this exceptionhandler.
            $constantCode = sprintf('TorneLIB\Exception\Constants::%s', $code);

            /**
             * PHP >= 8.0.0
             * If the constant is not defined, constant() now throws an Error exception; previously an E_WARNING was
             * generated, and null was returned.
             *
             * @see https://www.php.net/manual/en/function.constant.php
             */
            if (defined($constantCode)) {
                $numericConstant = constant($constantCode);
                if ($numericConstant) {
                    $code = $numericConstant;
                } else {
                    $code = !defined('LIB_ERROR_HTTP') ? Constants::LIB_UNHANDLED : 400;
                }
            } else {
                $undefinedCode = !defined('LIB_ERROR_HTTP') ? Constants::LIB_UNHANDLED : 400;
                $code = empty($this->stringifiedCode) ? $undefinedCode : $this->stringifiedCode;
            }
        }
        $return = $code;

        $codeType = gettype($code);
        if ($codeType === 'string' && (int)$code > 0) {
            // Make sure badly formatted integers are really handled as integers.
            $return = (int)$code;
        }

        return $return;
    }

    /**
     * @return $this
     */
    private function setStringifiedCode()
    {
        if ((empty($this->code) || $this->code === Constants::LIB_UNHANDLED) && !empty($this->stringifiedCode)) {
            $constantStringify = sprintf('CONSTANTS::%s', $this->stringifiedCode);
            try {
                if (defined($constantStringify)) {
                    $constant = constant($constantStringify);
                }
            } catch (Exception $regularConstantException) {
                // Ignore this.
            }
            if (!empty($constant)) {
                $this->code = constant('CONSTANTS::' . $this->stringifiedCode);
            } else {
                $this->code = $this->stringifiedCode;
            }
        }

        return $this;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        if (empty($this->traceFunction)) {
            return "Exception: [{$this->code}]: {$this->message}";
        } else {
            return "{$this->traceFunction}Exception {$this->code}: {$this->message}";
        }
    }

    /**
     * @return string
     */
    public function getStringifiedCode()
    {
        return $this->stringifiedCode;
    }

    /**
     * @return string
     */
    public function getTraceFunction()
    {
        return $this->traceFunction;
    }

    /**
     * @param mixed $extendException
     */
    public function setExtendException($extendException)
    {
        $this->extendException = $extendException;
    }

    /**
     * @return mixed
     */
    public function getExtendException()
    {
        return $this->extendException;
    }
}
