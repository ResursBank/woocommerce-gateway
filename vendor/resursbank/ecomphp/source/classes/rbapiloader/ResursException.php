<?php

/**
 * Class ResursExceptions Exception handling for EComPHP
 */
abstract class RESURS_EXCEPTIONS
{
    const ECOMMERCEERROR_ILLEGAL_ARGUMENT = 1;
    const ECOMMERCEERROR_INTERNAL_ERROR = 3;
    const ECOMMERCEERROR_NOT_ALLOWED = 4;
    const ECOMMERCEERROR_REFERENCED_DATA_DONT_EXISTS = 8;
    const ECOMMERCEERROR_NOT_ALLOWED_IN_ORDER_STATE = 9;
    const ECOMMERCEERROR_CREDITAPPLICATION_FAILED = 10;
    const ECOMMERCEERROR_NOT_IMPLEMENTED = 11;
    const ECOMMERCEERROR_INVALID_CREDITAPPLICATION_SUBMISSION = 14;
    const ECOMMERCEERROR_SIGNING_REQUIRED = 15;
    const ECOMMERCEERROR_AUTHORIZATION_FAILED = 17;
    const ECOMMERCEERROR_APPLICATION_VALIDATION_ERROR = 18;
    const ECOMMERCEERROR_OBJECT_WITH_ID_ALREADY_EXIST = 19;
    const ECOMMERCEERROR_NOT_ALLOWED_IN_PAYMENT_STATE = 20;
    const ECOMMERCEERROR_CUSTOMER_CONFIGURATION_EXCEPTION = 21;
    const ECOMMERCEERROR_SERVICE_CONFIGURATION_EXCEPTION = 22;
    const ECOMMERCEERROR_INVALID_CREDITING = 23;
    const ECOMMERCEERROR_LIMIT_PER_TIME_EXCEEDED = 24;
    const ECOMMERCEERROR_NOT_ALLOWED_IN_CURRENT_STATE = 25;
    const ECOMMERCEERROR_INVALID_FINALIZATION = 26;
    const ECOMMERCEERROR_FORM_PARSING = 27;
    const ECOMMERCEERROR_NOT_ALLOWED_INVOICE_ID = 28;
    const ECOMMERCEERROR_ALREADY_EXISTS_INVOICE_ID = 29;
    const ECOMMERCEERROR_INVALID_IDENTIFICATION = 30;
    const ECOMMERCEERROR_TO_MANY_TOKENS = 31;
    const ECOMMERCEERROR_TOO_MANY_TOKENS = 31; // EComPHP typo fix
    const ECOMMERCEERROR_CUSTOMER_ALREADY_HAVE_VALID_CARD = 32;
    const ECOMMERCEERROR_CUSTOMER_HAS_NO_VALID_CARD = 33;
    const ECOMMERCEERROR_CUSTOMER_HAS_MORE_THAN_ONE_VALID_CARD = 34;
    const ECOMMERCEERROR_INVALID_AUTHENTICATION = 35;
    const ECOMMERCEERROR_ANNUL_FAILED = 36;
    const ECOMMERCEERROR_CUSTOMER_HAS_NO_VALID_ACCOUNT = 37;
    const ECOMMERCEERROR_LEGACY_EXCEPTION = 99; // V3LegacyModeException
    const ECOMMERCEERROR_WEAK_PASSWORD = 502;
    const ECOMMERCEERROR_NOT_AUTHORIZED = 503;

    const PAYMENT_SESSION_NOT_FOUND = 700;

    /**
     * Miscellaneous exceptions
     */
    const NOT_IMPLEMENTED = 1000;
    const CLASS_REFLECTION_MISSING = 1001;
    const WSDL_APILOAD_EXCEPTION = 1002;
    const WSDL_PASSTHROUGH_EXCEPTION = 1003;
    const FORMFIELD_CANHIDE_EXCEPTION = 1004;
    const TEST_ERROR_CODE_AS_STRING = 1005;
    const UNKOWN_SOAP_EXCEPTION_CODE_ZERO = 1006;
    const INTERNAL_QUANTITY_EXCEPTION = 1007;
    const REGEX_COUNTRYCODE_MISSING = 1008;
    const REGEX_CUSTOMERTYPE_MISSING = 1009;

    /*
     * SSL/HTTP Exceptions
     */
    const SSL_PRODUCTION_CERTIFICATE_MISSING = 1500;
    const SSL_WRAPPER_MISSING = 1501;

    /*
     * Services related
     */
    const NO_SERVICE_CLASSES_LOADED = 2000;
    const NO_SERVICE_API_HANDLED = 2001;

    /*
     * API and callbacks
     */
    const CALLBACK_INSUFFICIENT_DATA = 6000;
    const CALLBACK_TYPE_UNSUPPORTED = 6001;
    const CALLBACK_URL_MISMATCH = 6002;
    const CALLBACK_SALTDIGEST_MISSING = 6003;

    /*
     * API and bookings
     */
    const BOOKPAYMENT_NO_BOOKDATA = 7000;
    const PAYMENTSPEC_EMPTY = 7001;
    const BOOKPAYMENT_NO_BOOKPAYMENT_CLASS = 7002;
    const PAYMENT_METHODS_CACHE_DISABLED = 7003;
    const ANNUITY_FACTORS_CACHE_DISABLED = 7004;
    const ANNUITY_FACTORS_METHOD_UNAVAILABLE = 7005;
    const UPDATECART_NOCLASS_EXCEPTION = 7006;
    const UPDATECARD_DOUBLE_DATA_EXCEPTION = 7007;
    const PREPARECARD_NUMERIC_EXCEPTION = 7008;
    const BOOK_CUSTOMERTYPE_MISSING = 7009;
    const EXSHOP_PROHIBITED = 7010;
    const CREATEPAYMENT_NO_ID_SET = 7011;
    const CREATEPAYMENT_TOO_FAST = 7012;
    const CALLBACK_REGISTRATION_ERROR = 7013;
    const PAYMENT_METHODS_ERROR = 7014;
}

/**
 * Class RESURS_EXCEPTION_CLASS
 */
class ResursException extends \Exception
{
    private $traceFunction;
    private $stringifiedCode;

    public function __construct(
        $message = 'Unknown exception',
        $code = 0,
        \Exception $previous = null,
        $stringifiedCode = null,
        $fromFunction = ''
    ) {
        parent::__construct($message, $code, $previous);
        $this->traceFunction = $fromFunction;
        $this->stringifiedCode = $stringifiedCode;
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
            } catch (\Exception $regularConstantException) {
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
}
