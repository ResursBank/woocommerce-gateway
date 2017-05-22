<?php

/**
 * Resurs Bank Exception Extender
 * @package RBEcomPHP
 * @author Resurs Bank Ecommrece <ecommerce.support@resurs.se>
 */

class ResursException extends \Exception {
    private $fromFunction = null;
    public function __construct($message = 'Unknown exception', $code = 0, $fromFunction = '', \Exception $previous = null) {
        $this->fromFunction = $fromFunction;
        parent::__construct($message, $code, $previous);
    }
    public function __toString() {
        if (null === $this->fromFunction) {
            return "RBEcomPHP Exception: [{$this->code}]: {$this->message}";
        } else {
            return "RBEcomPHP {$this->fromFunction}Exception {$this->code}: {$this->message}";
        }
    }
    public function getFromFunction()
    {
        if (empty($this->fromFunction)) {
            return "NaN";
        }
        return $this->fromFunction;
    }
}
