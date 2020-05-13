<?php

namespace TorneLIB\Exception;

/**
 * Class ExceptionHandler
 * @package TorneLIB\Exception
 * @version 6.1.7
 */
class ExceptionHandler extends \Exception
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
                $code = $this->getConstantedValue($code);
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
    private function getConstantedValue($code)
    {
        // Make it possible to push a stringified code into this exceptionhandler.
        $numericConstant = @constant(sprintf('TorneLIB\Exception\Constants::%s', $code));
        if ($numericConstant) {
            $code = $numericConstant;
        } else {
            if (!defined('LIB_ERROR_HTTP')) {
                // Use internal error.
                $code = Constants::LIB_UNHANDLED;
            } else {
                // Use bad request when unknown and LIB_ERROR_HTTP is enabled.
                $code = 400;
            }
        }

        return $code;
    }

    /**
     * @return $this
     */
    private function setStringifiedCode()
    {
        if (empty($this->code) && !empty($this->stringifiedCode)) {
            try {
                $constant = constant('CONSTANTS::' . $this->stringifiedCode);
            } catch (\Exception $regularConstantException) {
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
