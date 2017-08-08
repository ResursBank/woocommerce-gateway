<?php

/**
 * Resurs Bank Passthrough API - A pretty silent ShopFlowSimplifier for Resurs Bank.
 * Compatible with simplifiedFlow, hostedFlow and Resurs Checkout.
 * Requirements: WSDL stubs from WSDL2PHPGenerator (deprecated edition)
 * Important notes: As the WSDL files are generated, it is highly important to run tests before release.
 *
 * Last update: See the lastUpdate variable
 * @package RBEcomPHP
 * @author Resurs Bank Ecommerce <ecommerce.support@resurs.se>
 * @version 1.0-beta
 * @branch 1.0
 * @link https://test.resurs.com/docs/x/KYM0 Get started - PHP Section
 * @link https://test.resurs.com/docs/x/TYNM EComPHP Usage
 * @license Apache License
 */

/**
 * Class ResursExceptions Exception handling for EComPHP
 */
abstract class ResursExceptions
{
	/**
	 * Miscellaneous exceptions
	 */
	const NOT_IMPLEMENTED = 1000;
	const CLASS_REFLECTION_MISSING = 1001;
	const WSDL_APILOAD_EXCEPTION = 1002;
	const WSDL_PASSTHROUGH_EXCEPTION = 1003;
	const REGEX_COUNTRYCODE_MISSING = 1004;
	const REGEX_CUSTOMERTYPE_MISSING = 1004;
	const FORMFIELD_CANHIDE_EXCEPTION = 1005;

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
	const UPDATECARD_DOUBLE_DATA_EXCEPTION = 7006;
	const PREPARECARD_NUMERIC_EXCEPTION = 7007;
	const BOOK_CUSTOMERTYPE_MISSING = 7008;
	const EXSHOP_PROHIBITED = 7009;
	const CREATEPAYMENT_NO_ID_SET = 7008;
}

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
