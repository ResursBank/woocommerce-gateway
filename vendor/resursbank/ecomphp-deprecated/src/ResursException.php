<?php

/**
 * Class ResursException
 * Resurs Bank exception handler.
 */
class ResursException extends Exception
{
    private $traceFunction;
    private $stringifiedCode;
    private $shortException;
    private $longException;

    public function __construct(
        $message = 'Unknown exception',
        $code = 0,
        Exception $previous = null,
        $stringifiedCode = null,
        $fromFunction = '',
        $shortException = null,
        $longException = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->traceFunction = $fromFunction;
        $this->stringifiedCode = $stringifiedCode;
        $this->shortException = $shortException;
        $this->longException = $longException;
        $this->setStringifiedCode();
    }

    private function setStringifiedCode()
    {
        $constantName = sprintf('\RESURS_EXCEPTIONS::%s', $this->stringifiedCode);
        if (empty($this->code) && !empty($this->stringifiedCode)) {
            try {
                if (defined($constantName)) {
                    $constant = constant($constantName);
                }
            } catch (Exception $regularConstantException) {
                // Ignore this.
            }
            if (!empty($constant)) {
                $this->code = constant($constantName);
            } else {
                $this->code = $this->stringifiedCode;
            }
        }
    }

    public function __toString()
    {
        if (empty($this->traceFunction)) {
            return "RBEcomPHP Exception: [{$this->code}]: {$this->message}";
        } else {
            return "RBEcomPHP {$this->traceFunction}Exception {$this->code}: {$this->message}";
        }
    }

    public function getStringifiedCode()
    {
        return $this->stringifiedCode;
    }

    public function getTraceFunction()
    {
        return $this->traceFunction;
    }

    /**
     * Fetch shorter exception message rather than the original long (fix for some specific soap exceptions).
     * @return mixed|null
     */
    public function getShortException() {
        return $this->shortException;
    }

    /**
     * Fetch longer exception message rather than the original long (fix for some specific soap exceptions).
     * @return mixed|null
     */
    public function getLongException()
    {
        return $this->longException;
    }
}
