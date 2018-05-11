<?php
/**
 * Resurs Bank Passthrough API - A pretty silent ShopFlowSimplifier for Resurs Bank.
 * Compatible with simplifiedFlow, hostedFlow and Resurs Checkout.
 * Pipelines.
 *
 * @package RBEcomPHP
 * @author Resurs Bank Ecommerce <ecommerce.support@resurs.se>
 * @branch 1.3
 * @version 1.3.9
 * @link https://test.resurs.com/docs/x/KYM0 Get started - PHP Section
 * @link https://test.resurs.com/docs/x/TYNM EComPHP Usage
 * @license Apache License
 */

namespace Resursbank\RBEcomPHP;

// Prevent duplicate loading
if ( class_exists( 'ResursBank' ) && class_exists( 'Resursbank\RBEcomPHP\ResursBank' ) ) {return;}

require_once( __DIR__ . '/rbapiloader/ResursForms.php' );
require_once( __DIR__ . '/rbapiloader/ResursTypeClasses.php' );
require_once( __DIR__ . '/rbapiloader/ResursException.php' );

if ( file_exists( __DIR__ . "/../../vendor/autoload.php" ) ) {
	require_once( __DIR__ . '/../../vendor/autoload.php' );
}

use \TorneLIB\TorneLIB_Crypto;
use \TorneLIB\TorneLIB_NetBits;
use \TorneLIB\TorneLIB_Network;
use \TorneLIB\Tornevall_cURL;
use \TorneLIB\CURL_POST_AS;

// Globals starts here
if ( ! defined( 'ECOMPHP_VERSION' ) ) {
	define( 'ECOMPHP_VERSION', '1.3.9' );
}
if ( ! defined( 'ECOMPHP_MODIFY_DATE' ) ) {
	define( 'ECOMPHP_MODIFY_DATE', '20180509' );
}

/**
 * Class ResursBank
 * Works with dynamic data arrays. By default, the API-gateway will connect to Resurs Bank test environment, so to use production mode this must be configured at runtime.
 * @package Resursbank\RBEcomPHP
 */
class ResursBank {
	////////// Constants
	/**
	 * Constant variable for using ecommerce production mode
	 */
	const ENVIRONMENT_PRODUCTION = 0;
	/**
	 * Constant variable for using ecommerce in test mode
	 */
	const ENVIRONMENT_TEST = 1;
	////////// Public variables

	///// Debugging, helpers and development
	/** @var bool Activation of debug mode */
	private $debug = false;

	///// Environment and API
	/** @var int Current targeted environment - default is always test, as we don't like that mistakes are going production */
	public $current_environment = self::ENVIRONMENT_TEST;
	/** @var null The username used with the webservices */
	public $username = null;
	/** @var null The password used with the webservices */
	public $password = null;

	/// Web Services (WSDL) available in case of needs to call services directly

	/**
	 * Which services we do support (picked up automatically from $ServiceRequestList)
	 *
	 * @var array
	 */
	private $wsdlServices = array();

	///// Shop related
	/** @var bool Always append amount data and ending urls (cost examples) */
	public $alwaysAppendPriceLast = false;
	/** @var bool If set to true, EComPHP will ignore totalVatAmounts in specrows, and recalculate the rows by itself */
	public $bookPaymentInternalCalculate = true;
	/** @var int How many decimals to use with round */
	public $bookPaymentRoundDecimals = 2;
	/** @var string Customer id used at afterShopFlow */
	private $customerId = "";
	/** @var bool If the merchant has PSP methods available in the simplified and hosted flow where it is normally not supported, this should be set to true via setSimplifiedPsp(true) */
	private $paymentMethodsHasPsp = false;
	/** @var bool If the strict control of payment methods vs PSP is set, we will never show any payment method that is based on PAYMENT_PROVIDER - this might be good to use in mixed environments */
	private $paymentMethodsIsStrictPsp = false;
	/** @var bool Setting this to true should help developers have their payment method ids returned in a consistent format */
	private $paymentMethodIdSanitizing = false;
	/**
	 * If a choice of payment method are discovered during the flow, this is set here
	 * @var $desiredPaymentMethod
	 * @since 1.0.26
	 * @since 1.1.26
	 * @since 1.2.0
	 */
	private $desiredPaymentMethod;

	/** @var bool Enable the possibility to push over User-Agent from customer into header (debugging related) */
	private $customerUserAgentPush = false;

	////////// Private variables
	///// Client Specific Settings
	/** @var string The version of this gateway */
	private $version = ECOMPHP_VERSION;
	/** @var string Identify current version release (as long as we are located in v1.0.0beta this is necessary */
	private $lastUpdate = ECOMPHP_MODIFY_DATE;
	/** @var string URL to git storage */
	private $gitUrl = "https://bitbucket.org/resursbankplugins/resurs-ecomphp";
	/** @var string This. */
	private $clientName = "EComPHP";
	/** @var string Replacing $clientName on usage of setClientNAme */
	private $realClientName = "EComPHP";

	/** @var bool $metaDataHashEnabled When enabled, ECom uses Resurs metadata to add a sha1-encoded hash string, based on parts of the payload to secure the data transport */
	private $metaDataHashEnabled = false;
	/** @var bool $metaDataHashEncrypted When enabled, ECom will try to pack and encrypt metadata strings instead of hashing it */
	private $metaDataHashEncrypted = false;
	/** @var string $metaDataIv For encryption */
	private $metaDataIv = null;
	/** @var string $metaDataKey For encryption */
	private $metaDataKey = null;

	///// Package related
	/** @var bool Internal "handshake" control that defines if the module has been initiated or not */
	private $hasServicesInitialization = false;
	/** @var bool Future functionality to backtrace customer ip address to something else than REMOTE_ADDR (if proxified) */
	private $preferCustomerProxy = false;

	///// Communication
	/**
	 * Primary class for handling all HTTP calls
	 * @var Tornevall_cURL
	 * @since 1.0.1
	 * @since 1.1.1
	 */
	private $CURL;
	private $CURL_HANDLE_COLLECTOR = array();
	/**
	 * Info and statistics from the CURL-client
	 * @var array
	 */
	private $curlStats = array();
	/**
	 * @var TorneLIB_Network Class for handling Network related checks
	 * @since 1.0.1
	 * @since 1.1.1
	 */
	private $NETWORK;
	/**
	 * @var TorneLIB_NetBits Class for handling bitmasks
	 */
	private $BIT;
	/**
	 * @var TorneLIB_Crypto Class for handling data encoding/encryption
	 * @since 1.0.13
	 * @since 1.1.13
	 * @since 1.2.0
	 */
	private $T_CRYPTO;

	/** @var RESURS_DEPRECATED_FLOW $E_DEPRECATED */
	private $E_DEPRECATED;

	/**
	 * The payload rendered out from CreatePayment()
	 * @var array
	 * @since 1.0.1
	 * @since 1.1.1
	 */
	private $Payload = array();
	/**
	 * @var array
	 * @since 1.0.31
	 * @since 1.1.31
	 * @since 1.2.4
	 * @since 1.3.4
	 */
	private $PayloadHistory = array();
	/**
	 * If there is a chosen payment method, the information about it (received from Resurs Ecommerce) will be stored here
	 * @var array $PaymentMethod
	 * @since 1.0.13
	 * @since 1.1.13
	 * @since 1.2.0
	 */
	private $PaymentMethod;
	/**
	 * Payment spec (orderlines)
	 * @var array
	 * @since 1.0.2
	 * @since 1.1.2
	 */
	private $SpecLines = array();

	/// Environment URLs
	/** @var null The chosen environment */
	private $environment = null;
	/** @var string Default URL to test environment */
	private $env_test = "https://test.resurs.com/ecommerce-test/ws/V4/";
	/** @var string Default URL to production environment */
	private $env_prod = "https://ecommerce.resurs.com/ws/V4/";
	/** @var string Default URL to hostedflow test */
	private $env_hosted_test = "https://test.resurs.com/ecommerce-test/hostedflow/back-channel";
	/** @var string Default URL to hosted flow production */
	private $env_hosted_prod = "https://ecommerce-hosted.resurs.com/back-channel";
	/** @var string Default URL to omnicheckout test */
	private $env_omni_test = "https://omnitest.resurs.com";
	/** @var string Default URL to omnicheckout production */
	private $env_omni_prod = "https://checkout.resurs.com";
	/** @var string Default URL to omnicheckout test */
	private $env_omni_pos_test = "https://postest.resurs.com";
	/** @var string Default URL to omnicheckout production */
	private $env_omni_pos_prod = "https://poscheckout.resurs.com";
	/** @var string Country of choice */
	private $envCountry;
	/** @var bool Defined if the environment will point at Resurs Checkout POS */
	private $env_omni_pos = false;
	/** @var string ShopUrl to use with the checkout */
	private $checkoutShopUrl = "";
	/** @var bool Set to true via setValidateCheckoutShopUrl() if you require validation of a proper shopUrl */
	private $validateCheckoutShopUrl = false;
	/** @var int Default current environment. Always set to test (security reasons) */
	private $current_environment_updated = false;
	/** @var Store ID */
	private $storeId;
	/** @var $ecomSession */
	private $ecomSession;

	/** @var string How EcomPHP should identify with the web services */
	private $myUserAgent = null;
	/** @var array Internally set flags */
	private $internalFlags = array();

	/**
	 * @var RESURS_FLOW_TYPES
	 */
	private $enforceService = null;
	/**
	 * @var URLS pointing to direct access of Resurs Bank, instead of WSDL-stubs.
	 * @since 1.0.1
	 * @since 1.1.1
	 */
	private $URLS;
	/**
	 * @var array An index of where to find each service for webservices
	 * @since 1.0.1
	 * @since 1.1.1
	 */
	private $ServiceRequestList = array(
		'getPaymentMethods'          => 'SimplifiedShopFlowService',
		'getAddress'                 => 'SimplifiedShopFlowService',
		'getAnnuityFactors'          => 'SimplifiedShopFlowService',
		'getCostOfPurchaseHtml'      => 'SimplifiedShopFlowService',
		'bookPayment'                => 'SimplifiedShopFlowService',
		'bookSignedPayment'          => 'SimplifiedShopFlowService',
		'getPayment'                 => 'AfterShopFlowService',
		'findPayments'               => 'AfterShopFlowService',
		'addMetaData'                => 'AfterShopFlowService',
		'annulPayment'               => 'AfterShopFlowService',
		'creditPayment'              => 'AfterShopFlowService',
		'additionalDebitOfPayment'   => 'AfterShopFlowService',
		'finalizePayment'            => 'AfterShopFlowService',
		'registerEventCallback'      => 'ConfigurationService',
		'unregisterEventCallback'    => 'ConfigurationService',
		'getRegisteredEventCallback' => 'ConfigurationService',
		'peekInvoiceSequence'        => 'ConfigurationService',
		'setInvoiceSequence'         => 'ConfigurationService'
	);

	/**
	 * If there is another method than the GET method, it is told here, which services that requires this. Most of the WSDL data are fetched by GET.
	 *
	 * @var array
	 * @since 1.0.1
	 * @since 1.1.1
	 */
	private $ServiceRequestMethods = array();
	/** @var string Validating URLs are made through a third party API and is disabled by default (Used for checking reachability of an URL) */
	private $externalApiAddress = "https://api.tornevall.net/3.0/";
	/** @var array An array that defines an url to test and which response codes (OK-200, and errors when for example a digest fails) from the webserver that is expected */
	private $validateExternalUrl = null;

	/** @var Prepared variable for execution */
	private $createPaymentExecuteCommand;
	/** @var bool Enforce the Execute() */
	private $forceExecute = false;
	/**
	 * Defines which way we are actually communicating - if the WSDL stubs are left out of the pacakge, this will remain false.
	 * If the package do contain the full release packages, this will be switched over to true.
	 * @var bool
	 */
	private $skipCallbackValidation = true;

	/** @var bool The choice of using rest instead of a soapclient when registering callbacks */
	private $registerCallbacksViaRest = true;

	/// SOAP and WSDL
	/**
	 * @var array Standard options for the SOAP-interface.
	 */
	var $soapOptions = array(
		'exceptions'         => 1,
		'connection_timeout' => 60,
		'login'              => '',
		'password'           => '',
		'trace'              => 1
	);
	private $curlSslDisable = false;

	///// ShopRelated
	/// Customizable
	/** @var string Eventually a logged in user on the platform using EComPHP (used in aftershopFlow) */
	private $loggedInuser = "";
	/** @var null Get Cost of Purchase Custom HTML - Before html code received from webservices */
	private $getcost_html_before;
	/** @var null Get Cost of Purchase Custom HTML - AFter html code received from webservices */
	private $getcost_html_after;

	/// Callback handling
	/** @var array Callback related variables */
	private $digestKey = array();
	/** @var string Globally set digestive key */
	private $globalDigestKey = "";

	/// Shopflow
	/** @var bool Defines wheter we have detected a hosted flow request or not */
	private $isHostedFlow = false;
	/** @var bool Defines wheter we have detected a ResursCheckout flow request or not */
	private $isOmniFlow = false;
	/** @var null Omnicheckout payment data container */
	private $omniFrame = null;
	/** @var null The preferred payment order reference, set in a shopflow. Reachable through getPreferredPaymentId() */
	private $preferredId = null;
	private $paymentSessionId;
	/** @var array List of available payment method names (for use with getPaymentMethodNames()) */
	private $paymentMethodNames = array();
	/** @var bool Defines if the checkout should honor the customer field array */
	private $checkoutCustomerFieldSupport = false;

	/// AfterShop Flow
	/** @var string Preferred transaction id for aftershop */
	private $afterShopPreferredTransactionId = "";
	/** @var string Order id for aftershop */
	private $afterShopOrderId = "";
	/** @var string Invoice id (Optional) for aftershop */
	private $afterShopInvoiceId = "";
	/** @var string Invoice external reference for aftershop */
	private $afterShopInvoiceExtRef = "";

	/** @var string Default unit measure. "st" or styck for Sweden. If your plugin is not used for Sweden, use the proper unit for your country. */
	private $defaultUnitMeasure = "st";

	/// Resurs Checkout
	/** @var null When using clearOcShop(), the Resurs Checkout tailing script (resizer) will be stored here */
	private $ocShopScript = null;

	///// Template rules (Which is a clone from Resurs Bank deprecated shopflow that defines what customer data fields that is required while running simplified flow)
	///// In the deprecated shopFlow, this was the default behaviour.
	/** @var array Form template rules handled */
	private $formTemplateRuleArray = array();
	/**
	 * Array rules set, baed by getTemplateFieldsByMethodType()
	 * @var array
	 */
	private $templateFieldsByMethodResponse = array();


	/////////// INITIALIZERS

	/**
	 * Constructor method for Resurs Bank WorkFlows
	 *
	 * This method prepares initial variables for the workflow. No connections are being made from this point.
	 *
	 * @param string $login
	 * @param string $password
	 * @param int $targetEnvironment
	 * @param null $debug Activate debugging immediately on initialization
	 */
	function __construct( $login = '', $password = '', $targetEnvironment = RESURS_ENVIRONMENTS::ENVIRONMENT_NOT_SET, $debug = null ) {
		if ( isset( $_SERVER['HTTP_HOST'] ) ) {
			$theHost = $_SERVER['HTTP_HOST'];
		} else {
			$theHost = "nohost.localhost";
		}

		if (!is_null($debug) && is_bool($debug)) {
			$this->debug = $debug;
		}

		$this->checkoutShopUrl           = $this->hasHttps( true ) . "://" . $theHost;
		$this->soapOptions['cache_wsdl'] = ( defined( 'WSDL_CACHE_BOTH' ) ? WSDL_CACHE_BOTH : true );
		$this->soapOptions['ssl_method'] = ( defined( 'SOAP_SSL_METHOD_TLS' ) ? SOAP_SSL_METHOD_TLS : false );

		$this->setAuthentication( $login, $password );
		if ( $targetEnvironment != RESURS_ENVIRONMENTS::ENVIRONMENT_NOT_SET ) {
			$this->setEnvironment( $targetEnvironment );
		}
		$this->setUserAgent();
		$this->E_DEPRECATED = new RESURS_DEPRECATED_FLOW();

	}

	/**
	 * @param $eventName
	 *
	 * @return mixed|null
	 * @since 1.0.36
	 * @since 1.1.36
	 * @since 1.3.9
	 */
	private function event($eventName) {
		$args = func_get_args();
		$value = null;

		if (function_exists('ecom_event_run')) {
			$value = ecom_event_run($eventName, $args);
		}

		return $value;
	}

	/**
	 * Session usage
	 * @return bool
	 * @since 1.0.29
	 * @since 1.1.29
	 * @since 1.2.2
	 * @since 1.3.2
	 */
	private function sessionActivate() {
		try {
			if ( ! session_id() ) {
				@session_start();
				$this->ecomSession = session_id();
				if ( ! empty( $this->ecomSession ) ) {
					return true;
				}
			} else {
				$this->ecomSession = session_id();
			}
		} catch ( \Exception $sessionActivationException ) {

		}

		return false;
	}

	/**
	 * Push variable into customer session
	 *
	 * @param string $key
	 * @param string $keyValue
	 *
	 * @return bool
	 * @since 1.0.29
	 * @since 1.1.29
	 * @since 1.2.2
	 * @since 1.3.2
	 */
	public function setSessionVar( $key = '', $keyValue = '' ) {
		$this->sessionActivate();
		if ( isset( $_SESSION ) ) {
			$_SESSION[ $key ] = $keyValue;

			return true;
		}

		return false;
	}

	/**
	 * Get current stored variable from customer session
	 *
	 * @param string $key
	 *
	 * @return null
	 * @since 1.0.29
	 * @since 1.1.29
	 * @since 1.2.2
	 * @since 1.3.2
	 */
	public function getSessionVar( $key = '' ) {
		$this->sessionActivate();
		$returnVar = null;
		if ( isset( $_SESSION ) && isset( $_SESSION[ $key ] ) ) {
			$returnVar = $_SESSION[ $key ];
		}

		return $returnVar;
	}

	/**
	 * Remove current stored variable from customer session
	 * @return bool
	 * @since 1.0.29
	 * @since 1.1.29
	 * @since 1.2.2
	 * @since 1.3.2
	 */
	public function deleteSessionVar( $key = '' ) {
		$this->sessionActivate();
		if ( isset( $_SESSION ) && isset( $_SESSION[ $key ] ) ) {
			unset( $_SESSION[ $key ] );

			return true;
		}

		return false;
	}

	/**
	 * Check HTTPS-requirements, if they pass.
	 *
	 * Resurs Bank requires secure connection to the webservices, so your PHP-version must support SSL. Normally this is not a problem, but since there are server- and hosting providers that is actually having this disabled, the decision has been made to do this check.
	 * @throws \Exception
	 */
	private function testWrappers() {
		// suddenly, in some system, this data returns null without any reason
		$streamWrappers = @stream_get_wrappers();
		if ( ! is_array( $streamWrappers ) ) {
			$streamWrappers = array();
		}
		if ( ! in_array( 'https', array_map( "strtolower", $streamWrappers ) ) ) {
			throw new \Exception( __FUNCTION__ . ": HTTPS wrapper can not be found", \RESURS_EXCEPTIONS::SSL_WRAPPER_MISSING );
		}
	}

	/**
	 * Everything that communicates with Resurs Bank should go here, wheter is is web services or curl/json data. The former name of this
	 * function is InitializeWsdl, but since we are handling nonWsdl-calls differently, but still needs some kind of compatibility in dirty
	 * code structures, everything needs to be done from here. For now. In future version, this is probably deprecated too, as it is an
	 * obsolete way of getting things done as Resurs Bank has more than one way to pick things up in the API suite.
	 *
	 * @param bool $reInitializeCurl
	 * @return bool
	 * @throws \Exception
	 * @since 1.0.1
	 * @since 1.1.1
	 */
	private function InitializeServices( $reInitializeCurl = true ) {

		$inheritExtendedSoapWarnings = false;
		if ( is_null( $this->CURL ) ) {
			$reInitializeCurl = true;
		} else {
			if ($this->CURL->hasFlag('SOAPWARNINGS_EXTEND')) {
				$inheritExtendedSoapWarnings = $this->CURL->getFlag('SOAPWARNINGS_EXTEND');
			}
		}
		if ( ! $reInitializeCurl ) {
			return;
		}

		$this->sessionActivate();
		$this->hasServicesInitialization = true;
		$this->testWrappers();
		if ( $this->current_environment == self::ENVIRONMENT_TEST ) {
			$this->environment = $this->env_test;
		} else {
			$this->environment = $this->env_prod;
		}
		if ( class_exists( '\Resursbank\RBEcomPHP\Tornevall_cURL' ) || class_exists( '\TorneLIB\Tornevall_cURL' ) ) {
			$this->CURL = new Tornevall_cURL();
			$this->CURL->setChain( false );
			if ($inheritExtendedSoapWarnings) {
				$this->CURL->setFlag('SOAPWARNINGS_EXTEND', true);
			}
			$this->CURL->setFlag( 'SOAPCHAIN', false );
			$this->CURL->setStoreSessionExceptions( true );
			$this->CURL->setAuthentication( $this->soapOptions['login'], $this->soapOptions['password'] );
			$this->CURL->setUserAgent( $this->myUserAgent );
			//$this->CURL->setThrowableHttpCodes();
			$this->NETWORK = new TorneLIB_Network();
			$this->BIT     = $this->NETWORK->BIT;
		}
		$this->wsdlServices = array();
		foreach ( $this->ServiceRequestList as $reqType => $reqService ) {
			$this->wsdlServices[ $reqService ] = true;
		}
		foreach ( $this->wsdlServices as $ServiceName => $isAvailableBoolean ) {
			$this->URLS[ $ServiceName ] = $this->environment . $ServiceName . "?wsdl";
		}
		$this->getSslValidation();

		return true;
	}

	/**
	 * @param bool $debugModeState
	 *
	 * @throws \Exception
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	public function setDebug( $debugModeState = false ) {
		$this->InitializeServices(false);
		$this->debug = $debugModeState;
	}

	/**
	 * Get debugging information
	 * @return array
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	public function getDebug() {
		$this->curlStats['debug'] = $this->debug;

		return $this->curlStats;
	}

	/**
	 * Get curl mode version without the debugging requirement
	 * @param bool $fullRelease
	 *
	 * @return string
	 */
	public function getCurlVersion( $fullRelease = false ) {
		if ( ! is_null( $this->CURL ) ) {
			return $this->CURL->getVersion( $fullRelease );
		}
	}

	/**
	 * Return the CURL communication handle to the client, when in debug mode (Read only)
	 *
	 * @param bool $bulk
	 * @return Tornevall_cURL
	 * @throws \Exception
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	public function getCurlHandle($bulk = false) {
		$this->InitializeServices(false);
		if ( $this->debug ) {
			if ($bulk) {
				if (count($this->CURL_HANDLE_COLLECTOR)) {
					return array_pop($this->CURL_HANDLE_COLLECTOR);
				}
				return $this->CURL_HANDLE_COLLECTOR;
			}
			return $this->CURL;
		} else {
			throw new \Exception( "Can't return handle. The module is in wrong state (non-debug mode)", 403 );
		}
	}

	/**
	 *
	 * Make it possible, in test mode, to replace the old curl handle with a new reconfigured one
	 *
	 * @param $newCurlHandle
	 *
	 * @throws \Exception
	 * @since 1.0.23
	 * @since 1.1.23
	 * @since 1.2.0
	 */
	public function setCurlHandle( $newCurlHandle ) {
		$this->InitializeServices();
		if ( $this->debug ) {
			$this->CURL = $newCurlHandle;
		} else {
			throw new \Exception( "Can't return handle. The module is in wrong state (non-debug mode)", 403 );
		}
	}

	/**
	 * Make EComPHP go through the POS endpoint rather than the standard Checkout endpoint
	 *
	 * @param bool $activatePos
	 * @since 1.0.36
	 * @since 1.1.36
	 * @since 1.3.9
	 * @since 2.0.0
	 */
	public function setPos($activatePos = true) {
		$this->env_omni_pos = $activatePos;
	}

	/**
	 * Returns true if Resurs Checkout is pointing at the POS endpoint
	 *
	 * @return bool
	 * @since 1.0.36
	 * @since 1.1.36
	 * @since 1.3.9
	 * @since 2.0.0
	 */
	public function getPos() {
		return $this->env_omni_pos;
	}

	/**
	 * Put SSL Validation into relaxed mode (Test and debug only) - this disables SSL certificate validation off
	 *
	 * @param bool $validationEnabled
	 *
	 * @throws \Exception
	 * @since 1.0.23
	 * @since 1.1.23
	 * @since 1.2.0
	 */
	public function setSslValidation( $validationEnabled = false ) {
		$this->InitializeServices();
		if ( $this->debug && $this->current_environment == RESURS_ENVIRONMENTS::ENVIRONMENT_TEST ) {
			$this->curlSslDisable = true;
		} else {
			throw new \Exception( "Can't set SSL validation in relaxed mode. Debug mode is disabled and/or test environment are not set", 403 );
		}
	}

	/**
	 * @since 1.0.23
	 * @since 1.1.23
	 * @since 1.2.0
	 */
	private function getSslValidation() {
		return $this->curlSslDisable;
	}

	/**
	 * Returns true if the URL call was set to be unsafe (disabled)
	 *
	 * @return bool
	 * @since 1.0.23
	 * @since 1.1.23
	 * @since 1.2.0
	 */
	public function getSslIsUnsafe() {
		return $this->CURL->getSslIsUnsafe();
	}

	/**
	 * Initialize networking functions
	 *
	 * @since 1.0.35
	 * @since 1.1.35
	 * @since 1.2.8
	 * @since 1.3.8
	 */
	private function isNetWork() {
		// When no initialization of this library has been done yet
		if (is_null($this->NETWORK)) {
			$this->NETWORK = new TorneLIB_Network();
		}
	}

	/**
	 * Returns true if your version of EComPHP is the current (based on git tags)
	 *
	 * @param null $testVersion
	 *
	 * @return bool
	 * @since 1.0.26
	 * @since 1.1.26
	 * @since 1.2.0
	 */
	public function getIsCurrent( $testVersion = null ) {
		$this->isNetWork();
		if ( is_null( $testVersion ) ) {
			return ! $this->NETWORK->getVersionTooOld( $this->getVersionNumber( false ), $this->gitUrl );
		} else {
			return ! $this->NETWORK->getVersionTooOld( $testVersion, $this->gitUrl );
		}
	}

	/**
	 * @return mixed
	 * @throws \Exception
	 * @since 1.0.35
	 * @since 1.1.35
	 * @since 1.2.8
	 * @since 1.3.8
	 */
	public function getCurrentRelease() {
		$tags = $this->getVersionsByGitTag();
		return array_pop($tags);
	}

	/**
	 * Try to fetch a list of versions for EComPHP by its git tags
	 *
	 * @return array
	 * @throws \Exception
	 * @since 1.0.26
	 * @since 1.1.26
	 * @since 1.2.0
	 */
	public function getVersionsByGitTag() {
		$this->isNetWork();
		return $this->NETWORK->getGitTagsByUrl( $this->gitUrl );
	}

	/**
	 * Set up a user-agent to identify with webservices.
	 *
	 * @param string $MyUserAgent
	 *
	 * @since 1.0.2
	 * @since 1.1.2
	 */
	public function setUserAgent( $MyUserAgent = '' ) {
		if ( ! empty( $MyUserAgent ) ) {
			$this->myUserAgent = $MyUserAgent . " +" . $this->getVersionFull() . (defined('PHP_VERSION') ? "/PHP-" . PHP_VERSION : "");
		} else {
			$this->myUserAgent = $this->getVersionFull() . (defined('PHP_VERSION') ? "/PHP-" . PHP_VERSION : "");
		}
		if ( $this->customerUserAgentPush && isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			$this->myUserAgent .= " +CLI-" . $this->T_CRYPTO->base64_compress( $_SERVER['HTTP_USER_AGENT'] );
		}
	}

	/**
	 * Get current user agent info IF has been forced to set (returns null if we are using default)
	 *
	 * @return string
	 * @since 1.0.26
	 * @since 1.1.26
	 * @since 1.2.0
	 */
	public function getUserAgent() {
		return $this->myUserAgent;
	}

	/**
	 * Set internal flag parameter
	 *
	 * @param string $flagKey
	 * @param string $flagValue Will be boolean==true if empty
	 *
	 * @return bool If successful
	 * @throws \Exception
	 * @since 1.0.23
	 * @since 1.1.23
	 * @since 1.2.0
	 */
	public function setFlag( $flagKey = '', $flagValue = null ) {
		if ( is_null( $this->CURL ) ) {
			$this->InitializeServices();
		}
		if ( is_null( $flagValue ) ) {
			$flagValue = true;
		}

		if ( ! empty( $flagKey ) ) {
			// CURL passthrough
			$this->CURL->setFlag( $flagKey, $flagValue );
			$this->internalFlags[ $flagKey ] = $flagValue;

			return true;
		}
		throw new \Exception( "Flags can not be empty", 500 );
	}

	/**
	 * Get internal flag
	 *
	 * @param string $flagKey
	 *
	 * @return mixed|null
	 * @since 1.0.23
	 * @since 1.1.23
	 * @since 1.2.0
	 */
	public function getFlag( $flagKey = '' ) {
		if ( isset( $this->internalFlags[ $flagKey ] ) ) {
			return $this->internalFlags[ $flagKey ];
		}

		return null;
	}

	/**
	 * Clean up internal flags
	 * @since 1.0.25
	 * @since 1.1.25
	 * @since 1.2.0
	 */
	public function clearAllFlags() {
		$this->internalFlags = array();
	}

	/**
	 * Remove flag
	 *
	 * @param $flagKey
	 *
	 * @since 1.0.25
	 * @since 1.1.25
	 * @since 1.2.0
	 */
	public function deleteFlag( $flagKey ) {
		if ( $this->hasFlag( $flagKey ) ) {
			unset( $this->internalFlags[ $flagKey ] );
		}
	}

	/**
	 * Check if flag is set and true
	 *
	 * @param string $flagKey
	 *
	 * @return bool
	 * @since 1.0.23
	 * @since 1.1.23
	 * @since 1.2.0
	 */
	public function isFlag( $flagKey = '' ) {
		if ( $this->hasFlag( $flagKey ) ) {
			return ( $this->getFlag( $flagKey ) == 1 || $this->getFlag( $flagKey ) == true ? true : false );
		}

		return false;
	}

	/**
	 * Check if there is an internal flag set with current key
	 *
	 * @param string $flagKey
	 *
	 * @return bool
	 * @since 1.0.23
	 * @since 1.1.23
	 * @since 1.2.0
	 */
	public function hasFlag( $flagKey = '' ) {
		if ( ! is_null( $this->getFlag( $flagKey ) ) ) {
			return true;
		}

		return false;
	}


	/////////// Standard getters and setters

	/**
	 * Define current environment
	 *
	 * @param int $environmentType
	 */
	public function setEnvironment( $environmentType = RESURS_ENVIRONMENTS::ENVIRONMENT_TEST ) {
		$this->current_environment         = $environmentType;
		$this->current_environment_updated = true;
	}

	/**
	 * Returns target environment (production or test)
	 *
	 * @return int
	 * @since 1.0.2
	 * @since 1.1.2
	 */
	public function getEnvironment() {
		return $this->current_environment;
	}

	/**
	 * Set up authentication for ecommerce
	 *
	 * @param string $username
	 * @param string $password
	 *
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	public function setAuthentication( $username = '', $password = '' ) {
		$this->username = $username;
		$this->password = $password;
		if ( ! is_null( $username ) ) {
			$this->soapOptions['login'] = $username;
			$this->username             = $username; // For use with initwsdl
		}
		if ( ! is_null( $password ) ) {
			$this->soapOptions['password'] = $password;
			$this->password                = $password; // For use with initwsdl
		}
	}

	/**
	 * Set a new url to the chosen test flow (this is prohibited in production sets)
	 *
	 * @param string $newUrl
	 * @param int $FlowType
	 *
	 * @return string
	 */
	public function setTestUrl( $newUrl = '', $FlowType = RESURS_FLOW_TYPES::FLOW_NOT_SET ) {
		if ( ! preg_match( "/^http/i", $newUrl ) ) {
			/*
             * Automatically base64-decode if encoded
             */
			$testDecoded = $this->base64url_decode( $newUrl );
			if ( preg_match( "/^http/i", $testDecoded ) ) {
				$newUrl = $testDecoded;
			} else {
				$newUrl = "https://" . $newUrl;
			}
		}
		if ( $FlowType == RESURS_FLOW_TYPES::FLOW_SIMPLIFIED_FLOW ) {
			$this->env_test = $newUrl;
		} else if ( $FlowType == RESURS_FLOW_TYPES::FLOW_HOSTED_FLOW ) {
			$this->env_hosted_test = $newUrl;
		} else if ( $FlowType == RESURS_FLOW_TYPES::FLOW_RESURS_CHECKOUT ) {
			$this->env_omni_test = $newUrl;
		} else {
			/*
             * If this developer wasn't sure of what to change, we'd change all.
             */
			$this->env_test        = $newUrl;
			$this->env_hosted_test = $newUrl;
			$this->env_omni_test   = $newUrl;
		}

		return $newUrl;
	}



	/// DEBUGGING AND DEVELOPMENT

	/**
	 * Set up this to automatically validate a destination url.
	 *
	 * @param null $url
	 * @param string $expectedHttpAcceptCode What response code from the web server we expect when we are successful
	 * @param string $expectedHttpErrorCode What response code from the web server we expect when we (normally) fails on digest failures
	 */
	public function setValidateExternalCallbackUrl( $url = null, $expectedHttpAcceptCode = "200", $expectedHttpErrorCode = "403" ) {
		$this->validateExternalUrl = array(
			'url'         => $url,
			'http_accept' => $expectedHttpAcceptCode,
			'http_error'  => $expectedHttpErrorCode
		);
	}





	/////////// STRING BEHAVIOUR

	/**
	 * base64_encode for urls
	 *
	 * @param $data
	 *
	 * @return string
	 */
	private function base64url_encode( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * base64_decode for urls
	 *
	 * @param $data
	 *
	 * @return string
	 */
	private function base64url_decode( $data ) {
		return base64_decode( str_pad( strtr( $data, '-_', '+/' ), strlen( $data ) % 4, '=', STR_PAD_RIGHT ) );
	}


	/////////// CALLBACK BEHAVIOUR HELPERS

	/**
	 * Generate salt key. Beware of html encoding.
	 *
	 * Complexity levels:
	 *  Level 1 - Simple (uppercase string only)
	 *  Level 2 - Simple vary (uppercase-lowercase mixed string)
	 *  Level 3 - Level 2 with numerics
	 *  Level 4 - Level 3 with extra characters
	 *
	 * @param int $complexity
	 * @param null $setmax
	 *
	 * @return string
	 */
	public function getSaltKey( $complexity = 1, $setmax = null ) {

		$retp               = null;
		$characterListArray = array(
			'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
			'abcdefghijklmnopqrstuvwxyz',
			'0123456789',
			'!@#$%*?'
		);

		// Set complexity to no limit if type 5 is requested
		if ( $complexity == 5 ) {
			$characterListArray = array();
			for ( $unlim = 0; $unlim <= 255; $unlim ++ ) {
				$characterListArray[0] .= chr( $unlim );
			}
			if ( $setmax == null ) {
				$setmax = 15;
			}
		}

		// Backward-compatibility in the complexity will still give us captcha-capabilities for simpler users
		$max = 8;    // Longest complexity
		if ( $complexity == 1 ) {
			unset( $characterListArray[1], $characterListArray[2], $characterListArray[3] );
			$max = 6;
		}
		if ( $complexity == 2 ) {
			unset( $characterListArray[2], $characterListArray[3] );
			$max = 10;
		}
		if ( $complexity == 3 ) {
			unset( $characterListArray[3] );
			$max = 10;
		}
		if ( $setmax > 0 ) {
			$max = $setmax;
		}
		$chars    = array();
		$numchars = array();
		for ( $i = 0; $i < $max; $i ++ ) {
			$charListId = rand( 0, count( $characterListArray ) - 1 );
			// Set $numchars[ $charListId ] to a zero a value if not set before. This might render ugly notices about undefined offsets in some cases.
			if ( ! isset( $numchars[ $charListId ] ) ) {
				$numchars[ $charListId ] = 0;
			}
			$numchars[ $charListId ] ++;
			$chars[] = $characterListArray[ $charListId ]{mt_rand( 0, ( strlen( $characterListArray[ $charListId ] ) - 1 ) )};
		}
		shuffle( $chars );
		$retp = implode( "", $chars );

		return $retp;
	}

	/**
	 * Get salt by crypto library
	 *
	 * @param int $complexity
	 * @param int $totalLength
	 *
	 * @return string
	 * @since 1.3.4
	 */
	public function getSaltByCrypto( $complexity = 3, $totalLength = 24 ) {
		$this->T_CRYPTO = new TorneLIB_Crypto();

		return $this->T_CRYPTO->mkpass( $complexity, $totalLength );
	}

	/**
	 * Convert callback types to string names
	 *
	 * @param int $callbackType
	 *
	 * @return null|string
	 */
	private function getCallbackTypeString( $callbackType = RESURS_CALLBACK_TYPES::CALLBACK_TYPE_NOT_SET ) {
		if ( $callbackType == RESURS_CALLBACK_TYPES::CALLBACK_TYPE_NOT_SET ) {
			return null;
		}
		if ( $callbackType == RESURS_CALLBACK_TYPES::CALLBACK_TYPE_ANNULMENT ) {
			return "ANNULMENT";
		}
		if ( $callbackType == RESURS_CALLBACK_TYPES::CALLBACK_TYPE_AUTOMATIC_FRAUD_CONTROL ) {
			return "AUTOMATIC_FRAUD_CONTROL";
		}
		if ( $callbackType == RESURS_CALLBACK_TYPES::CALLBACK_TYPE_FINALIZATION ) {
			return "FINALIZATION";
		}
		if ( $callbackType == RESURS_CALLBACK_TYPES::CALLBACK_TYPE_TEST ) {
			return "TEST";
		}
		if ( $callbackType == RESURS_CALLBACK_TYPES::CALLBACK_TYPE_UNFREEZE ) {
			return "UNFREEZE";
		}
		if ( $callbackType == RESURS_CALLBACK_TYPES::CALLBACK_TYPE_UPDATE ) {
			return "UPDATE";
		}
		if ( $callbackType == RESURS_CALLBACK_TYPES::CALLBACK_TYPE_BOOKED ) {
			return "BOOKED";
		}

		return null;
	}

	/**
	 * @param string $callbackTypeString
	 *
	 * @return int
	 */
	public function getCallbackTypeByString( $callbackTypeString = "" ) {
		if ( strtoupper( $callbackTypeString ) == "ANNULMENT" ) {
			return RESURS_CALLBACK_TYPES::CALLBACK_TYPE_ANNULMENT;
		}
		if ( strtoupper( $callbackTypeString ) == "UPDATE" ) {
			return RESURS_CALLBACK_TYPES::CALLBACK_TYPE_UPDATE;
		}
		if ( strtoupper( $callbackTypeString ) == "TEST" ) {
			return RESURS_CALLBACK_TYPES::CALLBACK_TYPE_TEST;
		}
		if ( strtoupper( $callbackTypeString ) == "FINALIZATION" ) {
			return RESURS_CALLBACK_TYPES::CALLBACK_TYPE_FINALIZATION;
		}
		if ( strtoupper( $callbackTypeString ) == "AUTOMATIC_FRAUD_CONTROL" ) {
			return RESURS_CALLBACK_TYPES::CALLBACK_TYPE_AUTOMATIC_FRAUD_CONTROL;
		}
		if ( strtoupper( $callbackTypeString ) == "UNFREEZE" ) {
			return RESURS_CALLBACK_TYPES::CALLBACK_TYPE_UNFREEZE;
		}
		if ( strtoupper( $callbackTypeString ) == "BOOKED" ) {
			return RESURS_CALLBACK_TYPES::CALLBACK_TYPE_BOOKED;
		}

		return RESURS_CALLBACK_TYPES::CALLBACK_TYPE_NOT_SET;
	}

	/**
	 * Set up digestive parameters baed on requested callback type
	 *
	 * @param int $callbackType
	 *
	 * @return array
	 */
	private function getCallbackTypeParameters( $callbackType = RESURS_CALLBACK_TYPES::CALLBACK_TYPE_NOT_SET ) {
		if ( $callbackType == RESURS_CALLBACK_TYPES::CALLBACK_TYPE_ANNULMENT ) {
			return array( 'paymentId' );
		}
		if ( $callbackType == RESURS_CALLBACK_TYPES::CALLBACK_TYPE_AUTOMATIC_FRAUD_CONTROL ) {
			return array( 'paymentId', 'result' );
		}
		if ( $callbackType == RESURS_CALLBACK_TYPES::CALLBACK_TYPE_FINALIZATION ) {
			return array( 'paymentId' );
		}
		if ( $callbackType == RESURS_CALLBACK_TYPES::CALLBACK_TYPE_TEST ) {
			return array( 'param1', 'param2', 'param3', 'param4', 'param5' );
		}
		if ( $callbackType == RESURS_CALLBACK_TYPES::CALLBACK_TYPE_UNFREEZE ) {
			return array( 'paymentId' );
		}
		if ( $callbackType == RESURS_CALLBACK_TYPES::CALLBACK_TYPE_UPDATE ) {
			return array( 'paymentId' );
		}
		if ( $callbackType == RESURS_CALLBACK_TYPES::CALLBACK_TYPE_BOOKED ) {
			return array( 'paymentId' );
		}

		return array();
	}

	/**
	 * Callback digest helper - sets a simple digest key before calling setCallback
	 *
	 * @param string $digestSaltString If empty, $digestSaltString is randomized
	 * @param int $callbackType
	 *
	 * @return string
	 * @since 1.0.0
	 * @since 1.1.0
	 */
	public function setCallbackDigest( $digestSaltString = '', $callbackType = RESURS_CALLBACK_TYPES::CALLBACK_TYPE_NOT_SET ) {
		return $this->setCallbackDigestSalt( $digestSaltString, $callbackType );
	}

	/**
	 * Callback digest helper - sets a simple digest key before calling setCallback
	 *
	 * @param string $digestSaltString If empty, $digestSaltString is randomized
	 * @param int $callbackType
	 *
	 * @return string
	 * @since 1.0.1
	 * @since 1.1.1
	 */
	public function setCallbackDigestSalt( $digestSaltString = '', $callbackType = RESURS_CALLBACK_TYPES::CALLBACK_TYPE_NOT_SET ) {
		// Make sure the digestSaltString is never empty
		if ( ! empty( $digestSaltString ) ) {
			$currentDigest = $digestSaltString;
		} else {
			$currentDigest = $this->getSaltKey( 4, 10 );
		}
		if ( $callbackType !== RESURS_CALLBACK_TYPES::CALLBACK_TYPE_NOT_SET ) {
			$callbackTypeString                     = $this->getCallbackTypeString( ! is_null( $callbackType ) ? $callbackType : RESURS_CALLBACK_TYPES::CALLBACK_TYPE_NOT_SET );
			$this->digestKey[ $callbackTypeString ] = $currentDigest;
		} else {
			$this->globalDigestKey = $currentDigest;
		}

		// Confirm the set up
		return $currentDigest;
	}

	/**
	 * Retreive a full list of, by merchant, registered callbacks
	 * 
	 * The callback list will return only existing eventTypes, so if no event types exists, the returned array or object will be empty.
	 * Developer note: Changing this behaviour so all event types is always returned even if they don't exist (meaning ecomphp fills in what's missing) might
	 * break plugins that is already in production.
	 *
	 * The callback list will return only existing eventTypes, so if no event types exists, the returned array or object will be empty.
	 * Developer note: Changing this behaviour so all event types is always returned even if they don't exist (meaning ecomphp fills in what's missing) might
	 * break plugins that is already in production.
	 *
	 * @param bool $ReturnAsArray
	 *
	 * @return array
	 * @throws \Exception
	 * @link https://test.resurs.com/docs/display/ecom/ECommerce+PHP+Library#ECommercePHPLibrary-getCallbacksByRest
	 * @since 1.0.1
	 */
	public function getCallBacksByRest( $ReturnAsArray = false ) {
		$this->InitializeServices();
		try {
			$ResursResponse = $this->CURL->getParsedResponse( $this->CURL->doGet( $this->getCheckoutUrl() . "/callbacks" ) );
		} catch ( \Exception $restException ) {
			throw new \Exception( $restException->getMessage(), $restException->getCode() );
		}
		if ( $ReturnAsArray ) {
			$ResursResponseArray = array();
			if ( is_array( $ResursResponse ) && count( $ResursResponse ) ) {
				foreach ( $ResursResponse as $object ) {
					if ( isset( $object->eventType ) ) {
						$ResursResponseArray[ $object->eventType ] = isset( $object->uriTemplate ) ? $object->uriTemplate : "";
					}
				}
			}
			// Redmine #78124 workaround
			if ( ! isset( $ResursResponseArray['UPDATE'] ) ) {
				$updateResponse = $this->getRegisteredEventCallback( RESURS_CALLBACK_TYPES::CALLBACK_TYPE_UPDATE );
				if ( is_object( $updateResponse ) && isset( $updateResponse->uriTemplate ) ) {
					$ResursResponseArray['UPDATE'] = $updateResponse->uriTemplate;
				}
			}

			return $ResursResponseArray;
		}
		$hasUpdate = false;
		foreach ( $ResursResponse as $responseObject ) {
			if ( isset( $responseObject->eventType ) && $responseObject->eventType == "UPDATE" ) {
				$hasUpdate = true;
			}
		}
		if ( ! $hasUpdate ) {
			$updateResponse = $this->getRegisteredEventCallback( RESURS_CALLBACK_TYPES::CALLBACK_TYPE_UPDATE );
			if ( isset( $updateResponse->uriTemplate ) && ! empty( $updateResponse->uriTemplate ) ) {
				if ( ! isset( $updateResponse->eventType ) ) {
					$updateResponse->eventType = "UPDATE";
				}
				$ResursResponse[] = $updateResponse;
			}
		}

		return $ResursResponse;
	}

	/**
	 * Reimplementation of getRegisteredEventCallback due to #78124
	 *
	 * @param int $callbackType
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function getRegisteredEventCallback( $callbackType = RESURS_CALLBACK_TYPES::CALLBACK_TYPE_NOT_SET ) {
		$this->InitializeServices();
		$fetchThisCallback        = $this->getCallbackTypeString( $callbackType );
		$getRegisteredCallbackUrl = $this->getServiceUrl( "getRegisteredEventCallback" );
		// We are not using postService here, since we are dependent on the response code rather than the response itself
		$renderedResponse = $this->CURL->doPost( $getRegisteredCallbackUrl )->getRegisteredEventCallback( array( 'eventType' => $fetchThisCallback ) );
		$parsedResponse   = $this->CURL->getParsedResponse( $renderedResponse );

		return $parsedResponse;
	}

	/**
	 * Setting this to false enables URI validation controls while registering callbacks
	 *
	 * @param bool $callbackValidationDisable
	 */
	public function setSkipCallbackValidation( $callbackValidationDisable = true ) {
		$this->skipCallbackValidation = $callbackValidationDisable;
	}

	/**
	 * If you want to register callbacks through the rest API instead of SOAP, set this to true
	 *
	 * @param bool $useRest
	 */
	public function setRegisterCallbacksViaRest( $useRest = true ) {
		$this->registerCallbacksViaRest = $useRest;
	}

	/**
	 * Register a callback URL with Resurs Bank
	 *
	 * @param int $callbackType
	 * @param string $callbackUriTemplate
	 * @param array $digestData
	 * @param null $basicAuthUserName
	 * @param null $basicAuthPassword
	 *
	 * @return bool
	 * @throws \Exception
	 * @since 1.0.1
	 * @since 1.1.1
	 */
	public function setRegisterCallback( $callbackType = RESURS_CALLBACK_TYPES::CALLBACK_TYPE_NOT_SET, $callbackUriTemplate = "", $digestData = array(), $basicAuthUserName = null, $basicAuthPassword = null ) {
		$this->InitializeServices();
		if ( is_array( $this->validateExternalUrl ) && count( $this->validateExternalUrl ) ) {
			$isValidAddress = $this->validateExternalAddress();
			if ( $isValidAddress == RESURS_CALLBACK_REACHABILITY::IS_NOT_REACHABLE ) {
				throw new \Exception( "Reachability Response: Your site might not be available to our callbacks" );
			} else if ( $isValidAddress == RESURS_CALLBACK_REACHABILITY::IS_REACHABLE_WITH_PROBLEMS ) {
				throw new \Exception( "Reachability Response: Your site is availble from the outide. However, problems occured during tests, that indicates that your site is not available to our callbacks" );
			}
		}
		// The final array
		$renderCallback = array();

		// DEFAULT SETUP
		$renderCallback['eventType'] = $this->getCallbackTypeString( $callbackType );
		if ( empty( $renderCallback['eventType'] ) ) {
			throw new \Exception( __FUNCTION__ . ": The callback type you are trying to register is not supported by EComPHP", \RESURS_EXCEPTIONS::CALLBACK_TYPE_UNSUPPORTED );
		}
		$renderCallback['uriTemplate'] = $callbackUriTemplate;

		// BASIC AUTH CONTROL
		if ( ! empty( $basicAuthUserName ) && ! empty( $basicAuthPassword ) ) {
			$renderCallback['basicAuthUserName'] = $basicAuthUserName;
			$renderCallback['basicAuthPassword'] = $basicAuthPassword;
		}

		////// DIGEST CONFIGURATION BEGIN
		$renderCallback['digestConfiguration'] = array(
			'digestParameters' => $this->getCallbackTypeParameters( $callbackType )
		);
		if ( isset( $digestData['digestAlgorithm'] ) && strtolower( $digestData['digestAlgorithm'] ) != "sha1" && strtolower( $digestData['digestAlgorithm'] ) != "md5" ) {
			$renderCallback['digestConfiguration']['digestAlgorithm'] = "sha1";
		} elseif ( ! isset( $callbackDigest['digestAlgorithm'] ) ) {
			$renderCallback['digestConfiguration']['digestAlgorithm'] = "sha1";
		}
		$renderCallback['digestConfiguration']['digestAlgorithm'] = strtoupper( $renderCallback['digestConfiguration']['digestAlgorithm'] );
		if ( ! empty( $callbackDigest['digestSalt'] ) ) {
			if ( $digestData['digestSalt'] ) {
				$renderCallback['digestConfiguration']['digestSalt'] = $digestData['digestSalt'];
			}
		}
		// Overriders - if the globalDigestKey or the digestKey (specific type required) is set, it means that setCallbackDigest has been used.
		if ( ! empty( $this->globalDigestKey ) ) {
			$renderCallback['digestConfiguration']['digestSalt'] = $this->globalDigestKey;
		}
		if ( isset( $this->digestKey[ $renderCallback['eventType'] ] ) && ! empty( $this->digestKey[ $renderCallback['eventType'] ] ) ) {
			$renderCallback['digestConfiguration']['digestSalt'] = $this->digestKey['eventType'];
		}
		if ( empty( $renderCallback['digestConfiguration']['digestSalt'] ) ) {
			throw new \Exception( "Can not continue without a digest salt key", \RESURS_EXCEPTIONS::CALLBACK_SALTDIGEST_MISSING );
		}
		////// DIGEST CONFIGURATION FINISH
		if ( $this->registerCallbacksViaRest && $callbackType !== RESURS_CALLBACK_TYPES::CALLBACK_TYPE_UPDATE ) {
			$serviceUrl        = $this->getCheckoutUrl() . "/callbacks";
			$renderCallbackUrl = $serviceUrl . "/" . $renderCallback['eventType'];
			if ( isset( $renderCallback['eventType'] ) ) {
				unset( $renderCallback['eventType'] );
			}
			$renderedResponse = $this->CURL->doPost( $renderCallbackUrl, $renderCallback, CURL_POST_AS::POST_AS_JSON );
			$code             = $this->CURL->getResponseCode($renderedResponse);
		} else {
			$renderCallbackUrl = $this->getServiceUrl( "registerEventCallback" );
			// We are not using postService here, since we are dependent on the response code rather than the response itself
			$renderedResponse = $this->CURL->doPost( $renderCallbackUrl )->registerEventCallback( $renderCallback );
			$code             = $this->CURL->getResponseCode( $renderedResponse );
		}
		if ( $code >= 200 && $code <= 250 ) {
			if ( isset( $this->skipCallbackValidation ) && $this->skipCallbackValidation === false ) {
				$callbackUriControl = $this->CURL->getParsedResponse( $this->CURL->doGet( $renderCallbackUrl ) );
				if ( isset( $callbackUriControl->uriTemplate ) && is_string( $callbackUriControl->uriTemplate ) && strtolower( $callbackUriControl->uriTemplate ) == strtolower( $callbackUriTemplate ) ) {
					return true;
				}
			}

			return true;
		}

		throw new \Exception("setRegisterCallbackException ($code): Could not register callback event " . $renderCallback['eventType'] . ' (service: '.$registerBy.')', $code);
	}

	/**
	 * Simplifies removal of callbacks even when they does not exist at first.
	 *
	 * @param int $callbackType
	 *
	 * @return bool
	 * @throws \Exception
	 * @since 1.0.1
	 * @since 1.1.1
	 */
	public function unregisterEventCallback( $callbackType = RESURS_CALLBACK_TYPES::CALLBACK_TYPE_NOT_SET ) {
		$callbackType = $this->getCallbackTypeString( $callbackType );

		if ( ! empty( $callbackType ) ) {
			if ( $this->registerCallbacksViaRest ) {
				$this->InitializeServices();
				$serviceUrl        = $this->getCheckoutUrl() . "/callbacks";
				$renderCallbackUrl = $serviceUrl . "/" . $callbackType;
				$curlResponse      = $this->CURL->doDelete( $renderCallbackUrl );
				$curlCode          = $this->CURL->getResponseCode( $curlResponse );
				if ( $curlCode >= 200 && $curlCode <= 250 ) {
					return true;
				}
			} else {
				$this->InitializeServices();
				// Not using postService here, since we're
				$curlResponse = $this->CURL->doGet( $this->getServiceUrl( 'unregisterEventCallback' ) )->unregisterEventCallback( array( 'eventType' => $callbackType ) );
				$curlCode     = $this->CURL->getResponseCode( $curlResponse );
				if ( $curlCode >= 200 && $curlCode <= 250 ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Trigger the registered callback event TEST if set. Returns true if trigger call was successful, otherwise false (Observe that this not necessarily is a successful completion of the callback)
	 *
	 * @return bool
	 * @throws \Exception
	 * @since 1.0.2
	 * @since 1.1.2
	 */
	public function triggerCallback() {
		$this->InitializeServices();
		$envUrl = $this->env_test;
		$curEnv = $this->getEnvironment();
		if ( $curEnv == RESURS_ENVIRONMENTS::ENVIRONMENT_PRODUCTION ) {
			$envUrl = $this->env_prod;
		}
		$serviceUrl      = $envUrl . "DeveloperWebService?wsdl";
		$eventRequest    = $this->CURL->doGet( $serviceUrl );
		$eventParameters = array(
			'eventType' => 'TEST',
			'param'     => array(
				rand( 10000, 30000 ),
				rand( 10000, 30000 ),
				rand( 10000, 30000 ),
				rand( 10000, 30000 ),
				rand( 10000, 30000 )
			)
		);
		try {
			$eventRequest->triggerEvent( $eventParameters );
		} catch ( \Exception $e ) {
			return false;
		}

		return true;
	}

	/////////// SERVICE HELPERS AND INTERNAL FLOW FUNCTIONS

	/**
	 * Internal function to get the correct service URL for a specific call.
	 *
	 * @param string $ServiceName
	 *
	 * @return string
	 * @throws \Exception
	 * @since 1.0.1
	 * @since 1.1.1
	 */
	public function getServiceUrl( $ServiceName = '' ) {
		$this->InitializeServices();
		$properService = "";
		if ( isset( $this->ServiceRequestList[ $ServiceName ] ) && isset( $this->URLS[ $this->ServiceRequestList[ $ServiceName ] ] ) ) {
			$properService = $this->URLS[ $this->ServiceRequestList[ $ServiceName ] ];
		}

		return $properService;
	}

	/**
	 * Get the preferred method for a service (GET or POST baed)
	 *
	 * @param string $ServiceName
	 *
	 * @return string
	 * @since 1.0.2
	 * @since 1.1.2
	 */
	private function getServiceMethod( $ServiceName = '' ) {
		$ReturnMethod = "GET";
		if ( isset( $this->ServiceRequestMethods[ $ServiceName ] ) ) {
			$ReturnMethod = $this->ServiceRequestMethods[ $ServiceName ];
		}

		return strtolower( $ReturnMethod );
	}

	/**
	 * Enforce another flow than the simplified flow
	 *
	 * @param int $flowType
	 *
	 * @since 1.0.0
	 * @since 1.1.0
	 * @deprecated 1.0.26 Use setPreferredPaymentFlowService
	 * @deprecated 1.1.26 Use setPreferredPaymentFlowService
	 */
	public function setPreferredPaymentService( $flowType = RESURS_FLOW_TYPES::FLOW_NOT_SET ) {
		$this->setPreferredPaymentFlowService( $flowType );
	}

	/**
	 * Return the current set "preferred payment service" (hosted, checkout, simplified)
	 * @return RESURS_FLOW_TYPES
	 * @since 1.0.0
	 * @since 1.1.0
	 * @deprecated 1.0.26 Use getPreferredPaymentFlowService
	 * @deprecated 1.1.26 Use getPreferredPaymentFlowService
	 */
	public function getPreferredPaymentService() {
		return $this->getPreferredPaymentFlowService();
	}

	/**
	 * @param bool $setSoapChainBoolean
	 *
	 * @throws \Exception
	 * @since 1.0.36
	 * @since 1.1.36
	 * @since 1.3.9
	 * @since 2.0.0
	 */
	public function setSoapChain($setSoapChainBoolean = true) {
		$this->InitializeServices();
		$this->CURL->setFlag('SOAPCHAIN', $setSoapChainBoolean);
	}

	/**
	 * @return mixed|null
	 * @throws \Exception
	 * @since 1.0.36
	 * @since 1.1.36
	 * @since 1.3.9
	 * @since 2.0.0
	 */
	public function getSoapChain() {
		$this->InitializeServices();
		return $this->CURL->getFlag('SOAPCHAIN');
	}

	/**
	 * Configure EComPHP to use a specific flow
	 *
	 * @param int $flowType
	 *
	 * @since 1.0.26
	 * @since 1.1.26
	 * @since 1.2.0
	 */
	public function setPreferredPaymentFlowService( $flowType = RESURS_FLOW_TYPES::FLOW_NOT_SET ) {
		$this->enforceService = $flowType;
		if ( $flowType == RESURS_FLOW_TYPES::FLOW_HOSTED_FLOW ) {
			$this->isHostedFlow = true;
			$this->isOmniFlow   = false;
		} elseif ( $flowType == RESURS_FLOW_TYPES::FLOW_RESURS_CHECKOUT ) {
			$this->isHostedFlow = false;
			$this->isOmniFlow   = true;
		} elseif ( $flowType == RESURS_FLOW_TYPES::FLOW_SIMPLIFIED_FLOW ) {
			$this->isHostedFlow = false;
			$this->isOmniFlow   = false;
		} else {
			$this->isHostedFlow = false;
			$this->isOmniFlow   = false;
		}
	}

	/**
	 * Return the current set by user preferred payment flow service
	 * @return RESURS_FLOW_TYPES
	 * @since 1.0.26
	 * @since 1.1.26
	 * @since 1.2.0
	 */
	public function getPreferredPaymentFlowService() {
		return $this->enforceService;
	}

	/**
	 * Speak with webservices
	 *
	 * @param string $serviceName
	 * @param array $resursParameters
	 * @param bool $getResponseCode
	 *
	 * @return array
	 * @throws \Exception
	 * @since 1.0.2
	 * @since 1.1.2
	 * @since 1.2.0
	 */
	private function postService( $serviceName = "", $resursParameters = array(), $getResponseCode = false ) {
		$this->InitializeServices();
		$serviceNameUrl = $this->getServiceUrl( $serviceName );
		$soapBody       = null;
		if ( ! empty( $serviceNameUrl ) && ! is_null( $this->CURL ) ) {
			$this->CURL->setFlag( "SOAPWARNINGS", true );
			$Service = $this->CURL->doGet( $serviceNameUrl );
			try {
				$RequestService = $Service->$serviceName( $resursParameters );
			} catch ( \Exception $serviceRequestException ) {
				// Try to fetch previous exception (This is what we actually want)
				$previousException = $serviceRequestException->getPrevious();
				if ( ! empty( $previousException ) ) {
					$previousExceptionMessage = $previousException->getMessage();
					$previousExceptionCode    = $previousException->getCode();
				}
				if ( ! empty( $previousExceptionMessage ) ) {
					$exceptionMessage = $previousExceptionMessage;
					$exceptionCode    = $previousExceptionCode;
				} else {
					$exceptionCode    = $serviceRequestException->getCode();
					$exceptionMessage = $serviceRequestException->getMessage();
				}
				if ( isset( $previousException->detail ) && is_object( $previousException->detail ) && isset( $previousException->detail->ECommerceError ) && is_object( $previousException->detail->ECommerceError ) ) {
					$objectDetails = $previousException->detail->ECommerceError;
					if ( isset( $objectDetails->errorTypeId ) && intval( $objectDetails->errorTypeId ) > 0 ) {
						$exceptionCode = $objectDetails->errorTypeId;
					}
					if ( isset( $objectDetails->userErrorMessage ) ) {
						$errorTypeDescription = isset( $objectDetails->errorTypeDescription ) ? "[" . $objectDetails->errorTypeDescription . "] " : "";
						$exceptionMessage     = $errorTypeDescription . $objectDetails->userErrorMessage;
						$fixableByYou         = isset( $objectDetails->fixableByYou ) ? $objectDetails->fixableByYou : null;
						if ( $fixableByYou == "false" ) {
							$fixableByYou = " (Not fixable by you)";
						} else {
							$fixableByYou = " (Fixable by you)";
						}
						$exceptionMessage .= $fixableByYou;

					}
				}
				if ( empty( $exceptionCode ) || $exceptionCode == "0" ) {
					$exceptionCode = \RESURS_EXCEPTIONS::UNKOWN_SOAP_EXCEPTION_CODE_ZERO;
				}
				// Cast internal soap errors into a new, since the exception code is lost
				throw new \Exception( $exceptionMessage, $exceptionCode, $serviceRequestException );
			}
			$ParsedResponse = $Service->getParsedResponse( $RequestService );
			$ResponseCode   = $Service->getResponseCode();
			if ( $this->debug ) {
				if ( ! isset( $this->curlStats['calls'] ) ) {
					$this->curlStats['calls'] = 1;
				}
				$this->curlStats['calls'] ++;
				$this->curlStats['internals'] = $this->CURL->getDebugData();
			}
			$this->CURL_HANDLE_COLLECTOR[] = $Service;

			if ( ! $getResponseCode ) {
				return $ParsedResponse;
			} else {
				return $ResponseCode;
			}
		}

		return null;
	}

	/**
	 * When something from CURL threw an exception and you really need to get detailed information about those exceptions
	 *
	 * @return array
	 * @since 1.0.1
	 * @since 1.1.1
	 */
	public function getStoredCurlExceptionInformation() {
		return $this->CURL->getStoredExceptionInformation();
	}

	/**
	 * Special function for pushing user-agent from customer into our ecommerce communication. This must be enabled before setUserAgent.
	 *
	 * @param bool $enableCustomerUserAgent
	 *
	 * @since 1.0.13
	 * @since 1.1.13
	 * @since 1.2.0
	 */
	public function setPushCustomerUserAgent( $enableCustomerUserAgent = false ) {
		$this->T_CRYPTO = new TorneLIB_Crypto();
		if ( ! empty( $this->T_CRYPTO ) ) {
			$this->customerUserAgentPush = $enableCustomerUserAgent;
		}
	}

	/**
	 * Get next invoice number - and initialize if not set.
	 *
	 * @param bool $initInvoice Allow to set a new invoice number if not set (if not set, this is set to 1 if nothing else is set)
	 * @param int $firstInvoiceNumber Initializes invoice number sequence with this value if not set and requested
	 *
	 * @return int Returns If 0, the set up might have failed
	 * @throws \Exception
	 * @since 1.0.0
	 * @since 1.1.0
	 */
	public function getNextInvoiceNumber( $initInvoice = true, $firstInvoiceNumber = null ) {
		$this->InitializeServices();
		// Initial invoice number
		$currentInvoiceNumber = 0;
		$invoiceInvokation    = false;

		// Get the current from e-commerce
		try {
			$peekSequence = $this->postService( "peekInvoiceSequence" );
			// Check if nextInvoiceNumber is missing
			if ( isset( $peekSequence->nextInvoiceNumber ) ) {
				$currentInvoiceNumber = $peekSequence->nextInvoiceNumber;
			} else {
				$firstInvoiceNumber = 1;
			}
		} catch ( \Exception $e ) {
			if ( is_null( $firstInvoiceNumber ) && $initInvoice ) {
				$firstInvoiceNumber = 1;
			}
		}

		// Continue look at initinvoice, but this time take a look at the requested $firstInvoiceNumber
		if ( $initInvoice ) {
			// If the requested invoice number is a numeric and over 0, set it as next invoice number
			if ( ! is_null( $firstInvoiceNumber ) && is_numeric( $firstInvoiceNumber ) && $firstInvoiceNumber > 0 ) {
				$this->postService( "setInvoiceSequence", array( 'nextInvoiceNumber' => $firstInvoiceNumber ) );
				$invoiceInvokation = true;
			}
		}

		// If $invoiceInvokation is true, we'll know that something happened under this run
		if ( $invoiceInvokation ) {
			// So in that case, request it again
			try {
				$currentInvoiceNumber = $this->postService( "peekInvoiceSequence" )->nextInvoiceNumber;
			} catch ( \Exception $e ) {

			}
		}

		return $currentInvoiceNumber;
	}

	/**
	 * Nullify invoice sequence
	 *
	 * @return array
	 * @throws \Exception
	 * @since 1.0.27
	 * @since 1.1.27
	 * @since 1.2.0
	 */
	public function resetInvoiceNumber() {
		$this->InitializeServices();

		return $this->postService( "setInvoiceSequence" );
	}

	/**
	 * Returns all invoice numbers for a specific payment
	 *
	 * @param string $paymentIdOrPaymentObject
	 *
	 * @return array
	 * @throws \Exception
	 * @since 1.0.11
	 * @since 1.1.11
	 * @since 1.2.0
	 */
	public function getPaymentInvoices( $paymentIdOrPaymentObject = '' ) {
		$invoices = array();
		if ( is_string( $paymentIdOrPaymentObject ) ) {
			$paymentData = $this->getPayment( $paymentIdOrPaymentObject );
		} else if ( is_object( $paymentIdOrPaymentObject ) ) {
			$paymentData = $paymentIdOrPaymentObject;
		} else {
			return array();
		}
		if ( ! empty( $paymentData ) && isset( $paymentData->paymentDiffs ) ) {
			foreach ( $paymentData->paymentDiffs as $paymentRow ) {
				if ( isset( $paymentRow->type ) && isset( $paymentRow->invoiceId ) ) {
					$invoices[] = $paymentRow->invoiceId;
				}
			}
		}

		return $invoices;
	}

	/**
	 * Invoice sequence number rescuer/scanner (This function replaces old sequence numbers if there is a higher value found in the last X payments)
	 *
	 * @param $scanDebitCount
	 *
	 * @return int
	 * @throws \Exception
	 * @since 1.0.27
	 * @since 1.1.27
	 */
	public function getNextInvoiceNumberByDebits( $scanDebitCount = 10 ) {
		/**
		 * @since 1.3.7
		 */
		$currentInvoiceTest = null;
		// Check if there is a "current" invoice ID before searching for them. This prevents errors like "Setting a invoice number lower than last number is not allowed (1)"
		try {
			$currentInvoiceTest = $this->getNextInvoiceNumber();
		} catch ( \Exception $e ) {
		}
		$paymentScanTypes = array('IS_DEBITED', 'IS_CREDITED', 'IS_ANNULLED');

		$lastHighestInvoice = 0;
		foreach ( $paymentScanTypes as $paymentType ) {
			$paymentScanList    = $this->findPayments( array( 'statusSet' => array( $paymentType ) ), 1, $scanDebitCount, array(
				'ascending'   => false,
				'sortColumns' => array( 'FINALIZED_TIME', 'MODIFIED_TIME', 'BOOKED_TIME' )
			) );
			$lastHighestInvoice = $this->getHighestValueFromPaymentList( $paymentScanList, $lastHighestInvoice );
		}

		$properInvoiceNumber = intval( $lastHighestInvoice ) + 1;
		if ( intval( $currentInvoiceTest ) > 0 && $currentInvoiceTest > $properInvoiceNumber ) {
			$properInvoiceNumber = $currentInvoiceTest;
		}
		$this->getNextInvoiceNumber( true, $properInvoiceNumber );

		return $properInvoiceNumber;
	}

	/**
	 * Get the highest invoice value from a list of payments
	 *
	 * @param array $paymentList
	 * @param int $lastHighestInvoice
	 *
	 * @return int|mixed
	 * @throws \Exception
	 */
	private function getHighestValueFromPaymentList( $paymentList = array(), $lastHighestInvoice = 0 ) {
		if (is_object($paymentList)) {
			$paymentList = array($paymentList);
		}
		if ( is_array( $paymentList ) ) {
			foreach ( $paymentList as $payments ) {
				if (isset($payments->paymentId)) {
					$id       = $payments->paymentId;
					$invoices = $this->getPaymentInvoices( $id );
					foreach ( $invoices as $multipleDebitCheck ) {
						if ( $multipleDebitCheck > $lastHighestInvoice ) {
							$lastHighestInvoice = $multipleDebitCheck;
						}
					}
				}
			}
		}

		return $lastHighestInvoice;

	}

	/**
	 * List payment methods
	 *
	 * Retrieves detailed information on the payment methods available to the representative. Parameters (customerType, language and purchaseAmount) are optional.
	 * @link https://test.resurs.com/docs/display/ecom/Get+Payment+Methods
	 *
	 * @param array $parameters
	 *
	 * @return mixed
	 * @throws \Exception
	 * @since 1.0.0
	 * @since 1.1.0
	 */
	public function getPaymentMethods( $parameters = array() ) {
		$this->InitializeServices();

		$paymentMethods = $this->postService( "getPaymentMethods", array(
			'customerType'   => isset( $parameters['customerType'] ) ? $parameters['customerType'] : null,
			'language'       => isset( $parameters['language'] ) ? $parameters['language'] : null,
			'purchaseAmount' => isset( $parameters['purchaseAmount'] ) ? $parameters['purchaseAmount'] : null
		) );
		// Make sure this method always returns an array even if it is only one method. Ecommerce will, in case of only one available method
		// return an object instead of an array.
		if ( is_object( $paymentMethods ) ) {
			$paymentMethods = array( $paymentMethods );
		}
		$realPaymentMethods = $this->sanitizePaymentMethods( $paymentMethods );

		return $realPaymentMethods;
	}

	/**
	 * Get list of payment methods (payment method objects), that support annuity factors
	 *
	 * @param bool $namesOnly
	 *
	 * @return array
	 * @throws \Exception
	 * @since 1.0.36
	 * @since 1.1.36
	 * @since 1.3.9
	 * @since 2.0.0
	 */
	public function getPaymentMethodsByAnnuity($namesOnly = false) {
		$allMethods = $this->getPaymentMethods();
		$annuitySupported = array('REVOLVING_CREDIT');
		$annuityMethods = array();
		foreach ($allMethods as $methodIndex => $methodObject) {
			$t = isset($methodObject->type) ? $methodObject->type : null;
			$s = isset($methodObject->specificType) ? $methodObject->specificType : null;
			if (in_array($t, $annuitySupported) || in_array($s, $annuitySupported)) {
				if (!$namesOnly) {
					$annuityMethods[] = $methodObject;
				} else {
					if (isset($methodObject->id)) {
						$annuityMethods[] = $methodObject->id;
					}
				}
			}
		}
		return $annuityMethods;
	}

	/**
	 * Sanitize payment methods locally: make sure, amongst others that also cached payment methods is handled correctly on request, when for example PAYMENT_PROVIDER needs to be cleaned up
	 *
	 * @param array $paymentMethods
	 *
	 * @return array
	 * @since 1.0.24
	 * @since 1.1.24
	 * @since 1.2.0
	 */
	public function sanitizePaymentMethods( $paymentMethods = array() ) {
		$realPaymentMethods = array();
		$paymentSevice      = $this->getPreferredPaymentService();
		if ( is_array( $paymentMethods ) && count( $paymentMethods ) ) {
			foreach ( $paymentMethods as $paymentMethodIndex => $paymentMethodData ) {
				$type      = $paymentMethodData->type;
				$addMethod = true;

				if ( $this->paymentMethodIdSanitizing && isset( $paymentMethods[ $paymentMethodIndex ]->id ) ) {
					$paymentMethods[ $paymentMethodIndex ]->id = preg_replace( "/[^a-z0-9$]/i", '', $paymentMethods[ $paymentMethodIndex ]->id );
				}

				if ( $this->paymentMethodsIsStrictPsp ) {
					if ( $type == "PAYMENT_PROVIDER" ) {
						$addMethod = false;
					}
				} else if ( $paymentSevice != RESURS_FLOW_TYPES::FLOW_RESURS_CHECKOUT ) {
					if ( $type == "PAYMENT_PROVIDER" ) {
						$addMethod = false;
					}
					if ( $this->paymentMethodsHasPsp ) {
						$addMethod = true;
					}
				}

				if ( $addMethod ) {
					$realPaymentMethods[] = $paymentMethodData;
				}
			}
		}

		return $realPaymentMethods;
	}

	/**
	 * Setting this to true should help developers have their payment method ids returned in a consistent format (a-z, 0-9, will be the only accepted characters)
	 *
	 * @param bool $doSanitize
	 *
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	public function setPaymentMethodIdSanitizing( $doSanitize = false ) {
		$this->paymentMethodIdSanitizing = $doSanitize;
	}

	/**
	 * Return the payment method id sanitizer status (active=true)
	 *
	 * @return bool
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	public function getPaymentMethodIdSanitizing() {
		return $this->paymentMethodIdSanitizing;
	}

	/**
	 * If the merchant has PSP methods available in the simplified and hosted flow where it is normally not supported, this should be set to true. setStrictPsp() overrides this setting.
	 *
	 * @param bool $allowed
	 *
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	public function setSimplifiedPsp( $allowed = false ) {
		$this->paymentMethodsHasPsp = $allowed;
	}

	/**
	 * Return a boolean of paymentMethodsHasPsp (if they are allowed in simplified/hosted)
	 *
	 * @return bool
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	public function getSimplifiedPsp() {
		return $this->paymentMethodsHasPsp;
	}

	/**
	 * If the strict control of payment methods vs PSP is set, we will never show any payment method that is based on PAYMENT_PROVIDER.
	 *
	 * This might be good to use in mixed environments and payment methods are listed regardless of the requested flow. This setting overrides setSimplifiedPsp()
	 *
	 * @param bool $isStrict
	 *
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	public function setStrictPsp( $isStrict = false ) {
		$this->paymentMethodsIsStrictPsp = $isStrict;
	}

	/**
	 * Returns the value set with setStrictPsp()
	 *
	 * @param bool $isStrict
	 *
	 * @return bool
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	public function getStrictPsp( $isStrict = false ) {
		return $this->paymentMethodsIsStrictPsp;
	}

	/**
	 * @param string $governmentId
	 * @param string $customerType
	 * @param string $customerIpAddress
	 *
	 * @return array|mixed|null
	 * @throws \Exception
	 * @since 1.0.1
	 * @since 1.1.1
	 */
	public function getAddress( $governmentId = '', $customerType = 'NATURAL', $customerIpAddress = "" ) {
		if ( ! empty( $customerIpAddress ) && isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$customerIpAddress = $_SERVER['REMOTE_ADDR'];
		}

		return $this->postService( "getAddress", array(
			'governmentId'      => $governmentId,
			'customerType'      => $customerType,
			'customerIpAddress' => $customerIpAddress
		) );
	}

	/**
	 * AnnuityFactorsLight - Replacement of the former annuityfactor call, simplified.
	 *
	 * To use the former method, look for getAnnuityFactorsDeprecated. This function might however disappear in the future.
	 *
	 * @param string $paymentMethodId
	 *
	 * @return array|mixed|null
	 * @throws \Exception
	 * @since 1.0.1
	 * @since 1.1.1
	 * @link https://test.resurs.com/docs/x/JQBH getAnnuityFactors() documentation
	 */
	public function getAnnuityFactors( $paymentMethodId = '' ) {
		return $this->postService( "getAnnuityFactors", array( 'paymentMethodId' => $paymentMethodId ) );
	}

	/**
	 * Get annuity factor by duration
	 *
	 * @param $paymentMethodIdOrFactorObject
	 * @param $duration
	 *
	 * @return float
	 * @throws \Exception
	 * @since 1.1.24
	 */
	public function getAnnuityFactorByDuration( $paymentMethodIdOrFactorObject, $duration ) {
		$returnFactor = 0;
		$factorObject = $paymentMethodIdOrFactorObject;
		if ( is_string( $paymentMethodIdOrFactorObject ) && ! empty( $paymentMethodIdOrFactorObject ) ) {
			$factorObject = $this->getAnnuityFactors( $paymentMethodIdOrFactorObject );
		}
		if ( is_array( $factorObject ) ) {
			foreach ( $factorObject as $factorObjectData ) {
				if ( $factorObjectData->duration == $duration && isset( $factorObjectData->factor ) ) {
					return (float) $factorObjectData->factor;
				}
			}
		}

		return $returnFactor;
	}

	/**
	 * Get annuity factor rounded sum by the total price
	 *
	 * @param $totalAmount
	 * @param $paymentMethodIdOrFactorObject
	 * @param $duration
	 *
	 * @return float
	 * @since 1.1.24
	 * @throws \Exception
	 */
	public function getAnnuityPriceByDuration( $totalAmount, $paymentMethodIdOrFactorObject, $duration ) {
		$durationFactor = $this->getAnnuityFactorByDuration( $paymentMethodIdOrFactorObject, $duration );
		if ( $durationFactor > 0 ) {
			return round( $durationFactor * $totalAmount );
		}
	}

	/**
	 * Retrieves detailed information about a payment.
	 *
	 * @param string $paymentId
	 *
	 * @return array|mixed|null
	 * @throws \Exception
	 * @link https://test.resurs.com/docs/x/moEW getPayment() documentation
	 * @since 1.0.31
	 * @since 1.1.31
	 * @since 1.2.4
	 * @since 1.3.4
	 */
	public function getPaymentBySoap( $paymentId = '' ) {
		return $this->postService( "getPayment", array( 'paymentId' => $paymentId ) );
	}

	/**
	 * getPayment - Retrieves detailed information about a payment (rewritten to primarily use rest instead of SOAP, to get more soap independence)
	 *
	 * @param string $paymentId
	 * @param bool $useSoap
	 *
	 * @return array|mixed|null
	 * @throws \Exception
	 * @since 1.0.1
	 * @since 1.1.1
	 * @since 1.0.31 Refactored from this version
	 * @since 1.1.31 Refactored from this version
	 * @since 1.2.4 Refactored from this version
	 * @since 1.3.4 Refactored from this version
	 */
	public function getPayment( $paymentId = '', $useSoap = false ) {
		$this->InitializeServices();
		if ( $this->isFlag( 'GET_PAYMENT_BY_SOAP' ) || $useSoap ) {
			return $this->getPaymentBySoap( $paymentId );
		}

		try {
			return $this->CURL->getParsedResponse( $this->CURL->doGet( $this->getCheckoutUrl() . "/checkout/payments/" . $paymentId ) );
		} catch (\Exception $e) {
			// Get internal exceptions before http responses
			$exceptionTestBody = @json_decode($this->CURL->getResponseBody());
			if (isset($exceptionTestBody->errorCode) && isset($exceptionTestBody->description)) {
				throw new \Exception($exceptionTestBody->errorMessage, $exceptionTestBody->errorCode, $e);
			}
			throw new \Exception($e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * @param string $paymentId
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function getMetaData( $paymentId = '' ) {
		$metaDataResponse = array();
		if ( is_string( $paymentId ) ) {
			$payment = $this->getPayment( $paymentId );
		} else if ( is_object( $paymentId ) ) {
			$payment = $paymentId;
		} else {
			throw new \Exception( "getMetaDataException: PaymentID is neither and id nor object", 500 );
		}
		if ( isset( $payment ) && isset( $payment->metaData ) ) {
			foreach ( $payment->metaData as $metaIndexArray ) {
				if ( isset( $metaIndexArray->key ) && ! empty( $metaIndexArray->key ) ) {
					if ( ! isset( $metaDataResponse[ $metaIndexArray->key ] ) ) {
						$metaDataResponse[ $metaIndexArray->key ] = $metaIndexArray->value;
					} else {
						$metaDataResponse[ $metaIndexArray->key ][] = $metaIndexArray->value;
					}
				}
			}
		}

		return $metaDataResponse;
	}

	/**
	 * Make sure a payment will always be returned correctly. If string, getPayment will run first. If array/object, it will continue to look like one.
	 *
	 * @param array $paymentArrayOrPaymentId
	 *
	 * @return array|mixed|null
	 * @throws \Exception
	 * @since 1.0.2
	 * @since 1.1.2
	 */
	private function getCorrectPaymentContent( $paymentArrayOrPaymentId = array() ) {
		if ( is_string( $paymentArrayOrPaymentId ) && ! empty( $paymentArrayOrPaymentId ) ) {
			return $this->getPayment( $paymentArrayOrPaymentId );
		} else if ( is_object( $paymentArrayOrPaymentId ) ) {
			return $paymentArrayOrPaymentId;
		} else if ( is_array( $paymentArrayOrPaymentId ) ) {
			// This is wrong, but we'll return it anyway.
			return $paymentArrayOrPaymentId;
		}

		return null;
	}

	/**
	 * Get the correct key value from a payment (or a paymentobject directly)
	 *
	 * @param array $paymentArrayOrPaymentId
	 * @param string $paymentKey
	 *
	 * @return null
	 * @throws \Exception
	 * @since 1.0.2
	 * @since 1.1.2
	 */
	private function getPaymentContent( $paymentArrayOrPaymentId = array(), $paymentKey = "" ) {
		$Payment = $this->getCorrectPaymentContent( $paymentArrayOrPaymentId );
		if ( isset( $Payment->$paymentKey ) ) {
			return $Payment->$paymentKey;
		}

		return null;
	}

	/**
	 * Find/search payments
	 *
	 * @param array $searchCriteria
	 * @param int $pageNumber
	 * @param int $itemsPerPage
	 * @param null $sortBy
	 *
	 * @return array|mixed|null
	 * @throws \Exception
	 * @link https://test.resurs.com/docs/x/loEW
	 * @since 1.0.1
	 * @since 1.1.1
	 */
	public function findPayments( $searchCriteria = array(), $pageNumber = 1, $itemsPerPage = 10, $sortBy = null ) {
		$searchCriterias = array(
			'searchCriteria' => $searchCriteria,
			'pageNumber'     => $pageNumber,
			'itemsPerPage'   => $itemsPerPage
		);
		if ( ! empty( $sortBy ) ) {
			$searchCriterias['sortBy'] = $sortBy;
		}

		return $this->postService( 'findPayments', $searchCriterias );
	}

	/**
	 * Get a list of current available payment methods, in the form of an arraylist with id's
	 *
	 * @return array
	 * @throws \Exception
	 * @since 1.0.0
	 * @since 1.1.0
	 */
	public function getPaymentMethodNames() {
		$methods = $this->getPaymentMethods();
		if ( is_array( $methods ) ) {
			$this->paymentMethodNames = array();
			foreach ( $methods as $objectMethod ) {
				if ( isset( $objectMethod->id ) && ! empty( $objectMethod->id ) && ! in_array( $objectMethod->id, $this->paymentMethodNames ) ) {
					$this->paymentMethodNames[ $objectMethod->id ] = $objectMethod->id;
				}
			}
		}

		return $this->paymentMethodNames;
	}

	/**
	 * Fetch one specific payment method only, from Resurs Bank
	 *
	 * @param string $specificMethodName
	 *
	 * @return array If not found, array will be empty
	 * @throws \Exception
	 * @since 1.0.0
	 * @since 1.1.0
	 */
	public function getPaymentMethodSpecific( $specificMethodName = '' ) {
		$methods     = $this->getPaymentMethods();
		$methodArray = array();
		if ( is_array( $methods ) ) {
			foreach ( $methods as $objectMethod ) {
				if ( isset( $objectMethod->id ) && strtolower( $objectMethod->id ) == strtolower( $specificMethodName ) ) {
					$methodArray = $objectMethod;
				}
			}
		}

		return $methodArray;
	}

	/**
	 * @param  string $paymentId The current paymentId
	 * @param  string $to What it should be updated to
	 *
	 * @return bool
	 * @throws \Exception
	 * @since 1.0.1
	 * @since 1.1.1
	 */
	public function updatePaymentReference( $paymentId, $to ) {
		if ( empty( $paymentId ) || empty( $to ) ) {
			throw new \Exception( "Payment id and to must be set" );
		}
		$this->InitializeServices();
		$url          = $this->getCheckoutUrl() . '/checkout/payments/' . $paymentId . '/updatePaymentReference';
		$result       = $this->CURL->doPut( $url, array( 'paymentReference' => $to ), CURL_POST_AS::POST_AS_JSON );
		$ResponseCode = $this->CURL->getResponseCode( $result );
		if ( $ResponseCode >= 200 && $ResponseCode <= 250 ) {
			return true;
		}
		if ( $ResponseCode >= 400 ) {
			throw new \Exception( "Payment reference could not be updated", $ResponseCode );
		}

		return false;
	}

	/**
	 * Set store id for the payload
	 *
	 * @param null $storeId
	 *
	 * @since 1.0.7
	 * @since 1.1.7
	 */
	public function setStoreId( $storeId = null ) {
		if ( ! empty( $storeId ) ) {
			$this->storeId = $storeId;
		}
	}

	/**
	 * Get the configured store id
	 *
	 * @return mixed
	 * @since 1.0.7
	 * @since 1.1.7
	 */
	public function getStoreId() {
		return $this->storeId;
	}

	/**
	 * Adds metadata to an already created order
	 *
	 * This should not by mistake be mixed up with the payload, that are created before a payment.
	 *
	 * @param string $paymentId
	 * @param string $metaDataKey
	 * @param string $metaDataValue
	 *
	 * @return bool
	 * @throws \Exception
	 * @since 1.0.1
	 * @since 1.1.1
	 */
	public function addMetaData( $paymentId = '', $metaDataKey = '', $metaDataValue = '' ) {
		if ( empty( $paymentId ) ) {
			throw new \Exception( "Payment id is not set" );
		}
		if ( empty( $metaDataKey ) || empty( $metaDataValue ) ) {
			throw new \Exception( "Can't have empty meta information" );
		}

		$customErrorMessage = "";
		try {
			$checkPayment = $this->getPayment( $paymentId );
		} catch ( \Exception $e ) {
			$customErrorMessage = $e->getMessage();
		}
		if ( ! isset( $checkPayment->id ) && ! empty( $customErrorMessage ) ) {
			throw new \Exception( $customErrorMessage );
		}
		$metaDataArray    = array(
			'paymentId' => $paymentId,
			'key'       => $metaDataKey,
			'value'     => $metaDataValue
		);
		$metaDataResponse = $this->CURL->doGet( $this->getServiceUrl( "addMetaData" ) )->addMetaData( $metaDataArray );
		$curlCode         = $this->CURL->getResponseCode( $metaDataResponse );
		if ( $curlCode >= 200 && $curlCode <= 250 ) {
			return true;
		}

		return false;
	}

	/**
	 * Make sure that the amount are properly appended to an URL.
	 *
	 * @param string $URL
	 * @param int $Amount
	 * @param string $Parameter
	 *
	 * @return string
	 * @since 1.0.0
	 * @since 1.1.0
	 */
	private function priceAppender( $URL = '', $Amount = 0, $Parameter = 'amount' ) {
		if ( isset( $this->priceAppenderParameter ) && ! empty( $this->priceAppenderParameter ) ) {
			$Parameter = $this->priceAppenderParameter;
		}
		if ( preg_match( "/=$/", $URL ) ) {
			return $URL . $Amount;
		} else {
			return $URL . "&" . $Parameter . "=" . $Amount;
		}
	}

	/**
	 * Update URL's for a payment method to SEKKI-prepared content, if appendPriceLast is set for the method.
	 *
	 * The request can be sent in three ways (examples):
	 *
	 *  - Where you have the total amount and a method (Slow, since we need to fetch payment methods live each call, unless caching is enabled)
	 *      getSekkiUrls("789.90", "INVOICE")
	 *  - Where you have a pre-cached legalInfoLinks (from for example your website). In that case, we're only appending the amount to the info links
	 *      getSekkiUrls("789.90", $cachedLegalInfoLinks);
	 *  - Where you have a prepared URL. Then we practically do nothing, and we will trust that your URL is correct when appending the amount.
	 *
	 * @param int $totalAmount
	 * @param array|string $paymentMethodID If paymentMethodID is set as string, we'll try to look up the links
	 * @param string $URL
	 *
	 * @return array|string Returns an array if the whole method are requested, returns a string if the URL is already prepared as last parameter in
	 * @throws \Exception
	 * @since 1.0.0
	 * @since 1.1.0
	 */
	public function getSekkiUrls( $totalAmount = 0, $paymentMethodID = array(), $URL = '' ) {
		if ( ! empty( $URL ) ) {
			return $this->priceAppender( $URL, $totalAmount );
		}
		$currentLegalUrls = array();
		// If not an array (string) or array but empty
		if ( ( ! is_array( $paymentMethodID ) ) || ( is_array( $paymentMethodID ) && ! count( $paymentMethodID ) ) ) {
			$methods = $this->getPaymentMethods();
			foreach ( $methods as $methodArray ) {
				if ( isset( $methodArray->id ) ) {
					$methodId = $methodArray->id;
					if ( isset( $methodArray->legalInfoLinks ) ) {
						$linkCount = 0;
						foreach ( $methodArray->legalInfoLinks as $legalInfoLinkId => $legalInfoArray ) {
							if ( isset( $legalInfoArray->appendPriceLast ) && ( $legalInfoArray->appendPriceLast === true ) ) {
								$appendPriceLast = true;
							} else {
								$appendPriceLast = false;
							}
							if ( isset( $this->alwaysAppendPriceLast ) && $this->alwaysAppendPriceLast ) {
								$appendPriceLast = true;
							}
							$currentLegalUrls[ $methodId ][ $linkCount ] = $legalInfoArray;
							if ( $appendPriceLast ) {
								/* Append only amounts higher than 0 */
								$currentLegalUrls[ $methodId ][ $linkCount ]->url = $this->priceAppender( $currentLegalUrls[ $methodId ][ $linkCount ]->url, ( $totalAmount > 0 ? $totalAmount : "" ) );
							}
							$linkCount ++;
						}
					}
				}
			}
			if ( ! empty( $paymentMethodID ) ) {
				return $currentLegalUrls[ $paymentMethodID ];
			} else {
				return $currentLegalUrls;
			}
		} else {
			$linkCount = 0;
			foreach ( $paymentMethodID as $legalInfoLinkId => $legalInfoArray ) {
				if ( isset( $legalInfoArray->appendPriceLast ) && ( $legalInfoArray->appendPriceLast === true ) ) {
					$appendPriceLast = true;
				} else {
					$appendPriceLast = false;
				}
				$currentLegalUrls[ $linkCount ] = $legalInfoArray;
				if ( $appendPriceLast ) {
					$currentLegalUrls[ $linkCount ]->url = $this->priceAppender( $currentLegalUrls[ $linkCount ]->url, ( $totalAmount > 0 ? $totalAmount : "" ) );
				}
				$linkCount ++;
			}
		}

		return $currentLegalUrls;
	}

	/**
	 * Automated function for getCostOfPurchaseHtml() - Returning content in UTF-8 formatted display if a body are requested
	 *
	 * @param string $paymentMethod
	 * @param int $amount
	 * @param bool $returnBody Make this function return a full body with css
	 * @param string $callCss Your own css url
	 * @param string $hrefTarget Point opening target somewhere else (i.e. _blank opens in a new window)
	 *
	 * @return string
	 * @throws \Exception
	 * @link https://test.resurs.com/docs/x/_QBV
	 * @since 1.0.0
	 * @since 1.1.0
	 */
	public function getCostOfPurchase( $paymentMethod = '', $amount = 0, $returnBody = false, $callCss = 'costofpurchase.css', $hrefTarget = "_blank" ) {
		$returnHtml = $this->postService( "getCostOfPurchaseHtml", array(
			'paymentMethodId' => $paymentMethod,
			'amount'          => $amount
		) );
		// Try to make the target open as a different target, if set. This will not invoke, if not set.
		if ( ! empty( $hrefTarget ) ) {
			// Check if there are any target set, somewhere in the returned html. If true, we'll consider this already done somewhere else.
			if ( ! preg_match( "/target=/is", $returnHtml ) ) {
				$returnHtml = preg_replace( "/href=/is", 'target="' . $hrefTarget . '" href=', $returnHtml );
			}
		}
		if ( $returnBody ) {
			$specific          = $this->getPaymentMethodSpecific( $paymentMethod );
			$methodDescription = htmlentities( isset( $specific->description ) && ! empty( $specific->description ) ? $specific->description : "Payment information" );
			$returnBodyHtml    = '
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>' . $methodDescription . '</title>
            ';
			if ( is_null( $callCss ) ) {
				$callCss = "costofpurchase.css";
			}
			if ( ! empty( $callCss ) ) {
				if ( ! is_array( $callCss ) ) {
					$returnBodyHtml .= '<link rel="stylesheet" media="all" type="text/css" href="' . $callCss . '">' . "\n";
				} else {
					foreach ( $callCss as $cssLink ) {
						$returnBodyHtml .= '<link rel="stylesheet" media="all" type="text/css" href="' . $cssLink . '">' . "\n";
					}
				}
			}
			$returnBodyHtml .= '
                </head>
                <body>

                ' . $this->getcost_html_before . '
                ' . $returnHtml . '
                ' . $this->getcost_html_after . '

                </body>
                </html>
            ';
			$returnHtml     = $returnBodyHtml;
		}

		return $returnHtml;
	}

	/**
	 * While generating a getCostOfPurchase where $returnBody is true, this function adds custom html before the returned html-code from Resurs Bank
	 *
	 * @param string $htmlData
	 *
	 * @since 1.0.0
	 * @since 1.1.0
	 */
	public function setCostOfPurcaseHtmlBefore( $htmlData = '' ) {
		$this->getcost_html_before = $htmlData;
	}

	/**
	 * While generating a getCostOfPurchase where $returnBody is true, this function adds custom html after the returned html-code from Resurs Bank
	 *
	 * @param string $htmlData
	 *
	 * @since 1.0.0
	 * @since 1.1.0
	 */
	public function setCostOfPurcaseHtmlAfter( $htmlData = '' ) {
		$this->getcost_html_after = $htmlData;
	}



	/////////// OTHER BEHAVIOUR (AS HELPERS, MISCELLANEOUS)

	/**
	 * Run external URL validator and see whether an URL is really reachable or not (unsupported)
	 *
	 * @return int Returns a value from the class RESURS_CALLBACK_REACHABILITY
	 * @throws \Exception
	 * @since 1.0.3
	 * @since 1.1.3
	 */
	public function validateExternalAddress() {
		$this->isNetWork();
		if ( is_array( $this->validateExternalUrl ) && count( $this->validateExternalUrl ) ) {
			$this->InitializeServices();
			$ExternalAPI = $this->externalApiAddress . "urltest/isavailable/";
			$UrlDomain   = $this->NETWORK->getUrlDomain( $this->validateExternalUrl['url'] );
			if ( ! preg_match( "/^http/i", $UrlDomain[1] ) ) {
				return RESURS_CALLBACK_REACHABILITY::IS_REACHABLE_NOT_AVAILABLE;
			}
			$Expect           = $this->validateExternalUrl['http_accept'];
			$UnExpect         = $this->validateExternalUrl['http_error'];
			$useUrl           = $this->validateExternalUrl['url'];
			$base64url        = $this->base64url_encode( $useUrl );
			$ExternalPostData = array( 'link' => $useUrl, "returnEncoded" => true );
			try {
				$this->CURL->doPost( $ExternalAPI, $ExternalPostData, CURL_POST_AS::POST_AS_JSON );
				$WebResponse = $this->CURL->getParsedResponse();
			} catch ( \Exception $e ) {
				return RESURS_CALLBACK_REACHABILITY::IS_REACHABLE_NOT_KNOWN;
			}
			if ( isset( $WebResponse->response->isAvailableResponse ) ) {
				$ParsedResponse = $WebResponse->response->isAvailableResponse;
			} else {
				if ( isset( $WebResponse->errors ) && ! empty( $WebResponse->errors->faultstring ) ) {
					throw new \Exception( $WebResponse->errors->faultstring, $WebResponse->errors->code );
				} else {
					throw new \Exception( "No response returned from API", 500 );
				}
			}
			if ( isset( $ParsedResponse->{$base64url} ) && isset( $ParsedResponse->{$base64url}->exceptiondata->errorcode ) && ! empty( $ParsedResponse->{$base64url}->exceptiondata->errorcode ) ) {
				return RESURS_CALLBACK_REACHABILITY::IS_NOT_REACHABLE;
			}
			$UrlResult         = $ParsedResponse->{$base64url}->result;
			$totalResults      = 0;
			$expectedResults   = 0;
			$unExpectedResults = 0;
			$neitherResults    = 0;
			foreach ( $UrlResult as $BrowserName => $BrowserResponse ) {
				$totalResults ++;
				if ( $BrowserResponse == $Expect ) {
					$expectedResults ++;
				} else if ( $BrowserResponse == $UnExpect ) {
					$unExpectedResults ++;
				} else {
					$neitherResults ++;
				}
			}
			if ( $totalResults == $expectedResults ) {
				return RESURS_CALLBACK_REACHABILITY::IS_FULLY_REACHABLE;
			}
			if ( $expectedResults > 0 && $unExpectedResults > 0 ) {
				return RESURS_CALLBACK_REACHABILITY::IS_REACHABLE_WITH_PROBLEMS;
			}
			if ( $neitherResults > 0 ) {
				return RESURS_CALLBACK_REACHABILITY::IS_REACHABLE_NOT_KNOWN;
			}
			if ( $expectedResults === 0 ) {
				return RESURS_CALLBACK_REACHABILITY::IS_NOT_REACHABLE;
			}
		}

		return RESURS_CALLBACK_REACHABILITY::IS_REACHABLE_NOT_KNOWN;
	}

	/**
	 * Primary method of determining customer ip address
	 *
	 * @return string
	 * @since 1.0.3
	 * @since 1.1.3
	 */
	private function getCustomerIp() {
		$this->isNetWork();

		$primaryAddress = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : "127.0.0.1";
		// Warning: This is untested and currently returns an array instead of a string, which may break ecommerce
		if ( $this->preferCustomerProxy && ! empty( $this->NETWORK ) && is_array( $this->NETWORK->getProxyHeaders() ) && count( $this->NETWORK->getProxyHeaders() ) ) {
			$primaryAddress = $this->NETWORK->getProxyHeaders();
		}

		return $primaryAddress;
	}

	/**
	 * If you prefer to fetch anything that looks like a proxy if it mismatches to the REMOTE_ADDR, activate this (EXPERIMENTAL!!)
	 *
	 * @param bool $activated
	 *
	 * @since 1.0.2
	 * @since 1.1.2
	 */
	public function setCustomerIpProxy( $activated = false ) {
		$this->preferCustomerProxy = $activated;
	}

	/**
	 * Convert objects to array data
	 *
	 * @param $arrObjData
	 * @param array $arrSkipIndices
	 *
	 * @return array
	 */
	private function objectsIntoArray( $arrObjData, $arrSkipIndices = array() ) {
		$arrData = array();
		// if input is object, convert into array
		if ( is_object( $arrObjData ) ) {
			$arrObjData = get_object_vars( $arrObjData );
		}
		if ( is_array( $arrObjData ) ) {
			foreach ( $arrObjData as $index => $value ) {
				if ( is_object( $value ) || is_array( $value ) ) {
					$value = $this->objectsIntoArray( $value, $arrSkipIndices ); // recursive call
				}
				if ( @in_array( $index, $arrSkipIndices ) ) {
					continue;
				}
				$arrData[ $index ] = $value;
			}
		}

		return $arrData;
	}

	/**
	 * Payment spec container cleaner
	 *
	 * TODO: Key on artNo, description, price instead
	 *
	 * @param array $currentArray The current speclineArray
	 * @param array $cleanWith The array with the speclines that should be removed from currentArray
	 * @param bool $keepOpposite Setting this to true, will run the opposite of what the function actually do
	 *
	 * @return array New array
	 * @since 1.0.0
	 * @since 1.1.0
	 */
	private function removeFromArray( $currentArray = array(), $cleanWith = array(), $keepOpposite = false ) {
		$cleanedArray = array();
		foreach ( $currentArray as $currentObject ) {
			if ( is_array( $cleanWith ) ) {
				$foundObject = false;
				foreach ( $cleanWith as $currentCleanObject ) {
					if ( is_object( $currentCleanObject ) ) {
						if ( ! empty( $currentObject->artNo ) ) {
							if ( $currentObject->artNo == $currentCleanObject->artNo ) {    // No longer searching on id, as that is an incremental value rather than a dynamically added.
								$foundObject = true;
								if ( $keepOpposite ) {
									// This little one does the opposite of what this function normally do: Remove everything from the array except the found row.
									$cleanedArray[] = $currentObject;
								}
								break;
							}
						}
					} else if ( is_array( $currentCleanObject ) ) {
						// This is above, but based on incoming array
						if ( ! empty( $currentObject->artNo ) ) {
							if ( $currentObject->artNo == $currentCleanObject['artNo'] ) {    // No longer searching on id, as that is an incremental value rather than a dynamically added.
								$foundObject = true;
								if ( $keepOpposite ) {
									// This little one does the opposite of what this function normally do: Remove everything from the array except the found row.
									$cleanedArray[] = $currentObject;
								}
								break;
							}
						}
					}
				}
				if ( ! $keepOpposite ) {
					if ( ! $foundObject ) {
						$cleanedArray[] = $currentObject;
					}
				}
			} else {
				$cleanedArray[] = $currentObject;
			}
		}

		return $cleanedArray;
	}


	////// Client specific

	/**
	 * Get current client name and version
	 *
	 * @param bool $getDecimals (Get it as decimals, simple mode)
	 *
	 * @return string
	 * @since 1.0.0
	 * @since 1.1.0
	 */
	public function getVersionFull( $getDecimals = false ) {
		if ( ! $getDecimals ) {
			return $this->clientName . " v" . $this->version . "-" . $this->lastUpdate;
		}

		return $this->clientName . "_" . $this->versionToDecimals();
	}

	/**
	 * Get current client version only
	 *
	 * @param bool $getDecimals
	 *
	 * @return string
	 * @since 1.0.0
	 * @since 1.1.0
	 */
	public function getVersionNumber( $getDecimals = false ) {
		if ( ! $getDecimals ) {
			return $this->version; // . "-" . $this->lastUpdate;
		} else {
			return $this->versionToDecimals();
		}
	}

	/**
	 * Get "Created by" if set (used by aftershop)
	 * @return string
	 * @since 1.0.0
	 * @since 1.1.0
	 */
	public function getCreatedBy() {
		$createdBy = $this->realClientName . "_" . $this->getVersionNumber( true );
		if ( ! empty( $this->loggedInuser ) ) {
			$createdBy .= "/" . $this->loggedInuser;
		}

		return $createdBy;
	}

	/**
	 * Convert version number to decimals
	 * @return string
	 * @since 1.0.0
	 * @since 1.1.0
	 */
	private function versionToDecimals() {
		$splitVersion = explode( ".", $this->version );
		$decVersion   = "";
		foreach ( $splitVersion as $ver ) {
			$decVersion .= str_pad( intval( $ver ), 2, "0", STR_PAD_LEFT );
		}

		return $decVersion;
	}

	/**
	 * Set a logged in username (will be merged with the client name at aftershopFlow-level)
	 *
	 * @param string $currentUsername
	 *
	 * @since 1.0.0
	 * @since 1.1.0
	 */
	public function setLoggedInUser( $currentUsername = "" ) {
		$this->loggedInuser = $currentUsername;
	}

	/**
	 * Set an initial shopurl to use with Resurs Checkout
	 *
	 * If this is not set, EComPHP will handle the shopUrl automatically.
	 * It is also possible to handle this through the manual payload as always.
	 *
	 * @param string $shopUrl
	 * @param bool $validateFormat Activate URL validation
	 *
	 * @throws \Exception
	 *
	 * @since 1.0.4
	 * @since 1.1.4
	 */
	public function setShopUrl( $shopUrl = '', $validateFormat = true ) {
		$this->InitializeServices();
		if ( ! empty( $shopUrl ) ) {
			$this->checkoutShopUrl = $shopUrl;
		}
		if ( $validateFormat ) {
			$shopUrlValidate       = $this->NETWORK->getUrlDomain( $this->checkoutShopUrl );
			$this->checkoutShopUrl = $shopUrlValidate[1] . "://" . $shopUrlValidate[0];
		}
	}

	/**
	 * Make sure shopUrl are properly set by enabling this feature
	 *
	 * @param bool $validateEnabled
	 *
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	public function setValidateCheckoutShopUrl( $validateEnabled = true ) {
		$this->validateCheckoutShopUrl = $validateEnabled;
	}

	/////////// LONG LIFE DEPRECATION (Belongs to the deprecated shopFlow emulation, used by the wooCommerce plugin amongst others)

	/**
	 * Override formTemplateFieldsetRules in case of important needs or unexpected changes
	 *
	 * @param $customerType
	 * @param $methodType
	 * @param $fieldArray
	 *
	 * @return array
	 * @deprecated 1.0.8 Build your own integration please
	 * @deprecated 1.1.8 Build your own integration please
	 */
	public function setFormTemplateRules( $customerType, $methodType, $fieldArray ) {
		return $this->E_DEPRECATED->setFormTemplateRules($customerType, $methodType, $fieldArray);
	}

	/**
	 * Retrieve html-form rules for each payment method type, including regular expressions for the form fields, to validate against.
	 *
	 * @return array
	 * @deprecated 1.0.8 Build your own integration please
	 * @deprecated 1.1.8 Build your own integration please
	 */
	private function getFormTemplateRules() {
		return $this->E_DEPRECATED->getFormTemplateRules();
	}

	/**
	 * Get regular expression ruleset for a specific payment formfield
	 *
	 * If no form field name are given, all the fields are returned for a specific payment method.
	 * Parameters are case insensitive.
	 *
	 * @param string $formFieldName
	 * @param $countryCode
	 * @param $customerType
	 *
	 * @return array
	 * @throws \Exception
	 * @deprecated 1.0.8 Build your own integration please
	 * @deprecated 1.1.8 Build your own integration please
	 */
	public function getRegEx( $formFieldName = '', $countryCode, $customerType ) {
		return $this->E_DEPRECATED->getRegEx($formFieldName, $countryCode, $customerType);
	}

	/**
	 * Returns a true/false for a specific form field value depending on the response created by getTemplateFieldsByMethodType.
	 *
	 * This function is a part of Resurs Bank streamline support and actually defines the recommended value whether the field should try propagate it's data from the current store values or not.
	 * Doing this, you may be able to hide form fields that already exists in the store, so the customer does not need to enter the values twice.
	 *
	 * @param string $formField The field you want to test
	 * @param bool $canThrow Make the function throw an exception instead of silently return false if getTemplateFieldsByMethodType has not been run yet
	 *
	 * @return bool Returns false if you should NOT hide the field
	 * @throws \Exception
	 * @deprecated 1.0.8 Build your own integration please
	 * @deprecated 1.1.8 Build your own integration please
	 */
	public function canHideFormField( $formField = "", $canThrow = false ) {
		return $this->E_DEPRECATED->canHideFormField($formField, $canThrow);
	}

	/**
	 * Get field set rules for web-forms
	 *
	 * $paymentMethodType can be both a string or a object. If it is a object, the function will handle the incoming data as it is the complete payment method
	 * configuration (meaning, data may be cached). In this case, it will take care of the types in the method itself. If it is a string, it will handle the data
	 * as the configuration has already been solved out.
	 *
	 * When building forms for a webshop, a specific number of fields are required to show on screen. This function brings the right fields automatically.
	 * The deprecated flow generates form fields and returns them to the shop owner platform, with the form fields that is required for the placing an order.
	 * It also returns a bunch of regular expressions that is used to validate that the fields is correctly filled in. This function partially emulates that flow,
	 * so the only thing a integrating developer needs to take care of is the html code itself.
	 * @link https://test.resurs.com/docs/x/s4A0 Regular expressions
	 *
	 * @param string|array $paymentMethodName
	 * @param string $customerType
	 * @param string $specificType
	 *
	 * @return array
	 * @deprecated 1.0.8 Build your own integration please
	 * @deprecated 1.1.8 Build your own integration please
	 */
	public function getTemplateFieldsByMethodType( $paymentMethodName = "", $customerType = "", $specificType = "" ) {
		return $this->E_DEPRECATED->getTemplateFieldsByMethodType($paymentMethodName, $customerType, $specificType);
	}

	/**
	 * Get template fields by a specific payment method. This function retrieves the payment method in real time.
	 *
	 * @param string $paymentMethodName
	 *
	 * @return array
	 * @throws \Exception
	 * @deprecated 1.0.8 Build your own integration please
	 * @deprecated 1.1.8 Build your own integration please
	 */
	public function getTemplateFieldsByMethod( $paymentMethodName = "" ) {
		return $this->E_DEPRECATED->getTemplateFieldsByMethodType( $this->getPaymentMethodSpecific( $paymentMethodName ) );
	}

	/**
	 * Get form fields by a specific payment method. This function retrieves the payment method in real time.
	 *
	 * @param string $paymentMethodName
	 *
	 * @return array
	 * @throws \Exception
	 * @deprecated 1.0.8 Build your own integration please
	 * @deprecated 1.1.8 Build your own integration please
	 */
	public function getFormFieldsByMethod( $paymentMethodName = "" ) {
		return $this->E_DEPRECATED->getTemplateFieldsByMethod( $paymentMethodName );
	}


	/////////// PRIMARY INTERNAL SHOPFLOW SECTION
	////// HELPERS
	/**
	 * Generates a unique "preferredId" (term from simplified and referes to orderReference) out of a datestamp
	 *
	 * @param int $maxLength The maximum recommended length of a preferred id is currently 25. The order numbers may be shorter (the minimum length is 14, but in that case only the timestamp will be returned)
	 * @param string $prefix Prefix to prepend at unique id level
	 * @param bool $dualUniq Be paranoid and sha1-encrypt the first random uniq id first.
	 * @param bool $force Force a new payment id
	 *
	 * @return string
	 * @since 1.0.2
	 * @since 1.1.2
	 */
	public function getPreferredPaymentId( $maxLength = 25, $prefix = "", $dualUniq = true, $force = false ) {
		if ( ! empty( $this->preferredId ) && ! $force ) {
			return $this->preferredId;
		}
		$timestamp = strftime( "%Y%m%d%H%M%S", time() );
		if ( $dualUniq ) {
			$uniq = uniqid( sha1( uniqid( rand(), true ) ), true );
		} else {
			$uniq = uniqid( rand(), true );
		}
		$uniq       = preg_replace( '/\D/i', '', $uniq );
		$uniqLength = strlen( $uniq );
		if ( ! empty( $prefix ) ) {
			$uniq = substr( $prefix . $uniq, 0, $uniqLength );
		}
		$preferredId       = $timestamp . "-" . $uniq;
		$preferredId       = substr( $preferredId, 0, $maxLength );
		$this->preferredId = $preferredId;

		return $this->preferredId;
	}

	/**
	 * Set your own order reference instead of taking the randomized one
	 *
	 * @param $myPreferredId
	 *
	 * @since 1.0.2
	 * @since 1.1.2
	 */
	public function setPreferredId( $myPreferredId ) {
		$this->preferredId = $myPreferredId;
	}

	/**
	 * Set target country (optional)
	 *
	 * @param int $Country
	 *
	 * @return string Country code is returned
	 * @since 1.0.2
	 * @since 1.1.2
	 */
	public function setCountry( $Country = RESURS_COUNTRY::COUNTRY_UNSET ) {
		if ( $Country === RESURS_COUNTRY::COUNTRY_DK ) {
			$this->envCountry = "DK";
		} else if ( $Country === RESURS_COUNTRY::COUNTRY_NO ) {
			$this->envCountry = "NO";
		} else if ( $Country === RESURS_COUNTRY::COUNTRY_FI ) {
			$this->envCountry = "FI";
		} else if ( $Country === RESURS_COUNTRY::COUNTRY_SE ) {
			$this->envCountry = "SE";
		} else {
			$this->envCountry = null;
		}

		return $this->envCountry;
	}

	/**
	 * Returns current set target country
	 * @return string
	 * @since 1.0.26
	 * @since 1.1.26
	 * @since 1.2.0
	 */
	public function getCountry() {
		return $this->envCountry;
	}

	/**
	 * Set up a country based on a country code string. Supported countries are SE, DK, NO and FI. Anything else than this defaults to SE
	 *
	 * @param string $countryCodeString
	 */
	public function setCountryByCountryCode( $countryCodeString = "" ) {
		if ( strtolower( $countryCodeString ) == "dk" ) {
			$this->setCountry( RESURS_COUNTRY::COUNTRY_DK );
		} else if ( strtolower( $countryCodeString ) == "no" ) {
			$this->setCountry( RESURS_COUNTRY::COUNTRY_NO );
		} else if ( strtolower( $countryCodeString ) == "fi" ) {
			$this->setCountry( RESURS_COUNTRY::COUNTRY_FI );
		} else {
			$this->setCountry( RESURS_COUNTRY::COUNTRY_SE );
		}
	}

	/**
	 * Insert products in "virtual cart"
	 *
	 * @param string $articleNumberOrId
	 * @param string $description
	 * @param int $unitAmountWithoutVat
	 * @param int $vatPct
	 * @param string $unitMeasure
	 * @param string $articleType ORDER_LINE, DISCOUNT, SHIPPING_FEE
	 * @param int $quantity
	 *
	 * @since 1.0.2
	 * @since 1.1.2
	 * @since 1.2.0
	 */
	public function addOrderLine( $articleNumberOrId = '', $description = '', $unitAmountWithoutVat = 0, $vatPct = 0, $unitMeasure = 'st', $articleType = "ORDER_LINE", $quantity = 1 ) {
		if ( ! is_array( $this->SpecLines ) ) {
			$this->SpecLines = array();
		}

		if ( is_null( $articleType ) ) {
			$articleType = "ORDER_LINE";
		}

		// Simplified: id, artNo, description, quantity, unitMeasure, unitAmountWithoutVat, vatPct, totalVatAmount, totalAmount
		// Hosted: artNo, description, quantity, unitMeasure, unitAmountWithoutVat, vatPct, totalVatAmount, totalAmount
		// Checkout: artNo, description, quantity, unitMeasure, unitAmountWithoutVat, vatPct, type

		$duplicateArticle = false;
		foreach ( $this->SpecLines as $specIndex => $specRow ) {
			if ( $specRow['artNo'] == $articleNumberOrId && $specRow['unitAmountWithoutVat'] == $unitAmountWithoutVat ) {
				$duplicateArticle                          = false;
				$this->SpecLines[ $specIndex ]['quantity'] += $quantity;
			}
		}
		if ( ! $duplicateArticle ) {
			$this->SpecLines[] = array(
				'artNo'                => $articleNumberOrId,
				'description'          => $description,
				'quantity'             => $quantity,
				'unitMeasure'          => $unitMeasure,
				'unitAmountWithoutVat' => $unitAmountWithoutVat,
				'vatPct'               => $vatPct,
				'type'                 => ! empty( $articleType ) ? $articleType : ""
			);
		}
		$this->renderPaymentSpec();
	}

	/**
	 * Payment Spec Renderer
	 *
	 * @param int $overrideFlow
	 *
	 * @return mixed
	 * @since 1.0.2
	 * @since 1.1.2
	 */
	private function renderPaymentSpec( $overrideFlow = RESURS_FLOW_TYPES::FLOW_NOT_SET ) {
		$myFlow = $this->getPreferredPaymentFlowService();
		if ( $overrideFlow !== RESURS_FLOW_TYPES::FLOW_NOT_SET ) {
			$myFlow = $overrideFlow;
		}
		$paymentSpec = array();
		if ( is_array( $this->SpecLines ) && count( $this->SpecLines ) ) {
			// Try correctify speclines that have been merged in the wrong way
			if ( isset( $this->SpecLines['artNo'] ) ) {
				$this->SpecLines = array(
					$this->SpecLines
				);
			}
			foreach ( $this->SpecLines as $specIndex => $specRow ) {
				if ( is_array( $specRow ) ) {
					if ( ! isset( $specRow['unitMeasure'] ) || ( isset( $specRow['unitMeasure'] ) && empty( $specRow['unitMeasure'] ) ) ) {
						$this->SpecLines[ $specIndex ]['unitMeasure'] = $this->defaultUnitMeasure;
					}
				}
				if ( $myFlow === RESURS_FLOW_TYPES::FLOW_SIMPLIFIED_FLOW ) {
					$this->SpecLines[ $specIndex ]['id'] = ( $specIndex ) + 1;
				}
				if ( $myFlow === RESURS_FLOW_TYPES::FLOW_HOSTED_FLOW || $myFlow === RESURS_FLOW_TYPES::FLOW_SIMPLIFIED_FLOW ) {
					if ( ! isset( $specRow['totalVatAmount'] ) ) {
						$this->SpecLines[ $specIndex ]['totalVatAmount'] = ( $specRow['unitAmountWithoutVat'] * $specRow['vatPct'] / 100 ) * $specRow['quantity'];
						$this->SpecLines[ $specIndex ]['totalAmount']    = ( $specRow['unitAmountWithoutVat'] + ( $specRow['unitAmountWithoutVat'] * $specRow['vatPct'] / 100 ) ) * $specRow['quantity'];
					}
					if ( ! isset( $paymentSpec['totalAmount'] ) ) {
						$paymentSpec['totalAmount'] = 0;
					}
					if ( ! isset( $paymentSpec['totalVatAmount'] ) ) {
						$paymentSpec['totalVatAmount'] = 0;
					}
					$paymentSpec['totalAmount']    += $this->SpecLines[ $specIndex ]['totalAmount'];
					$paymentSpec['totalVatAmount'] += $this->SpecLines[ $specIndex ]['totalVatAmount'];
				}
			}
			if ( $myFlow === RESURS_FLOW_TYPES::FLOW_SIMPLIFIED_FLOW ) {
				// Do not forget to pass over $myFlow-overriders to sanitizer as it might be sent from additionalDebitOfPayment rather than a regular bookPayment sometimes
				$this->Payload['orderData'] = array(
					'specLines'      => $this->sanitizePaymentSpec( $this->SpecLines, $myFlow ),
					'totalAmount'    => $paymentSpec['totalAmount'],
					'totalVatAmount' => $paymentSpec['totalVatAmount']
				);
			}
			if ( $myFlow === RESURS_FLOW_TYPES::FLOW_HOSTED_FLOW ) {
				// Do not forget to pass over $myFlow-overriders to sanitizer as it might be sent from additionalDebitOfPayment rather than a regular bookPayment sometimes
				$this->Payload['orderData'] = array(
					'orderLines'     => $this->sanitizePaymentSpec( $this->SpecLines, $myFlow ),
					'totalAmount'    => $paymentSpec['totalAmount'],
					'totalVatAmount' => $paymentSpec['totalVatAmount']
				);
			}
			if ( $myFlow == RESURS_FLOW_TYPES::FLOW_RESURS_CHECKOUT ) {
				// Do not forget to pass over $myFlow-overriders to sanitizer as it might be sent from additionalDebitOfPayment rather than a regular bookPayment sometimes
				$this->Payload['orderLines'] = $this->sanitizePaymentSpec( $this->SpecLines, $myFlow );
			}
		} else {
			// If there are no array for the speclines yet, check if we could update one from the payload
			if ( isset( $this->Payload['orderLines'] ) && is_array( $this->Payload['orderLines'] ) ) {
				// Do not forget to pass over $myFlow-overriders to sanitizer as it might be sent from additionalDebitOfPayment rather than a regular bookPayment sometimes
				$this->Payload['orderLines'] = $this->sanitizePaymentSpec( $this->Payload['orderLines'], $myFlow );
				$this->SpecLines             = $this->Payload['orderLines'];
			}
		}

		return $this->Payload;
	}

	////// MASTER SHOPFLOWS - PRIMARY BOOKING FUNCTIONS

	/**
	 * The new payment creation function (replaces bookPayment)
	 *
	 * For EComPHP 1.0.2 there is no need for any object conversion (or external parameters). Most of the parameters is about which preferred payment flow that is used, which should
	 * be set with the function setPreferredPaymentFlowService() instead. If no preferred are set, we will fall back to the simplified flow.
	 *
	 * @param string $payment_id_or_method For ResursCheckout the payment id are preferred before the payment method
	 * @param array $payload If there are any extra (or full) payload for the chosen payment, it should be placed here
	 *
	 * @throws \Exception
	 * @since 1.0.2
	 * @since 1.1.2
	 * @return array
	 */
	public function createPayment( $payment_id_or_method = '', $payload = array() ) {
		if ( ! $this->hasServicesInitialization ) {
			$this->InitializeServices();
		}
		$myFlow = $this->getPreferredPaymentFlowService();
		try {
			if ( $myFlow !== RESURS_FLOW_TYPES::FLOW_RESURS_CHECKOUT ) {
				$this->desiredPaymentMethod = $payment_id_or_method;
				$paymentMethodInfo          = $this->getPaymentMethodSpecific( $payment_id_or_method );
				if ( isset( $paymentMethodInfo->id ) ) {
					$this->PaymentMethod = $paymentMethodInfo;
				}
			}
		} catch ( \Exception $e ) {

		}
		$this->preparePayload( $payment_id_or_method, $payload );
		if ( $this->forceExecute ) {
			$this->createPaymentExecuteCommand = $payment_id_or_method;

			return array( 'status' => 'delayed' );
		} else {
			$bookPaymentResult = $this->createPaymentExecute( $payment_id_or_method, $this->Payload );
		}

		return $bookPaymentResult;
	}

	/**
	 * @param string $payment_id_or_method
	 * @param array $payload
	 *
	 * @return array|mixed
	 * @throws \Exception
	 * @since 1.0.2
	 * @since 1.1.2
	 */
	private function createPaymentExecute( $payment_id_or_method = '', $payload = array() ) {
		/**
		 * @since 1.0.29
		 * @since 1.1.29
		 * @since 1.2.2
		 * @since 1.3.2
		 */
		if ( $this->isFlag( 'PREVENT_EXEC_FLOOD' ) ) {
			$maxTime = intval( $this->getFlag( 'PREVENT_EXEC_FLOOD_TIME' ) );
			if ( ! $maxTime ) {
				$maxTime = 5;
			}
			$lastPaymentExecute = intval( $this->getSessionVar( 'lastPaymentExecute' ) );
			$timeDiff           = time() - $lastPaymentExecute;
			if ( $timeDiff <= $maxTime ) {
				if ( $this->isFlag( 'PREVENT_EXEC_FLOOD_EXCEPTIONS' ) ) {
					throw new \Exception( "You are running createPayemnt too fast", \RESURS_EXCEPTIONS::CREATEPAYMENT_TOO_FAST );
				}

				return false;
			}
			$this->setSessionVar( 'lastPaymentExecute', time() );
		}
		if ( trim( strtolower( $this->username ) ) == "exshop" ) {
			throw new \Exception( "The use of exshop is no longer supported", \RESURS_EXCEPTIONS::EXSHOP_PROHIBITED );
		}
		$error  = array();
		$myFlow = $this->getPreferredPaymentFlowService();

		//$this->addMetaDataHash($payment_id_or_method);

		// Using this function to validate that card data info is properly set up during the deprecation state in >= 1.0.2/1.1.1
		if ( $myFlow == RESURS_FLOW_TYPES::FLOW_SIMPLIFIED_FLOW ) {
			$paymentMethodInfo = $this->getPaymentMethodSpecific( $payment_id_or_method );
			if ( isset( $paymentMethodInfo ) && is_object( $paymentMethodInfo ) ) {
				if ( isset( $paymentMethodInfo->specificType ) && $paymentMethodInfo->specificType == "CARD" || $paymentMethodInfo->specificType == "NEWCARD" || $paymentMethodInfo->specificType == "REVOLVING_CREDIT" ) {
					$this->validateCardData( $paymentMethodInfo->specificType );
				}
			}
			$myFlowResponse = $this->postService( 'bookPayment', $this->Payload );
			$this->resetPayload();

			return $myFlowResponse;
		} else if ( $myFlow == RESURS_FLOW_TYPES::FLOW_RESURS_CHECKOUT ) {
			$checkoutUrl      = $this->getCheckoutUrl() . "/checkout/payments/" . $payment_id_or_method;
			$checkoutResponse = $this->CURL->doPost( $checkoutUrl, $this->Payload, CURL_POST_AS::POST_AS_JSON );
			$parsedResponse   = $this->CURL->getParsedResponse( $checkoutResponse );
			$responseCode     = $this->CURL->getResponseCode( $checkoutResponse );
			// Do not trust response codes!
			if ( isset( $parsedResponse->paymentSessionId ) ) {
				$this->paymentSessionId = $parsedResponse->paymentSessionId;
				$this->SpecLines        = array();

				return $parsedResponse->html;
			} else {
				if ( isset( $parsedResponse->error ) ) {
					$error[] = $parsedResponse->error;
				}
				if ( isset( $parsedResponse->message ) ) {
					$error[] = $parsedResponse->message;
				}
				throw new \Exception( implode( "\n", $error ), $responseCode );
			}

			return $parsedResponse;
		} else if ( $myFlow == RESURS_FLOW_TYPES::FLOW_HOSTED_FLOW ) {
			$hostedUrl      = $this->getHostedUrl();
			$hostedResponse = $this->CURL->doPost( $hostedUrl, $this->Payload, CURL_POST_AS::POST_AS_JSON );
			$parsedResponse = $this->CURL->getParsedResponse( $hostedResponse );
			// Do not trust response codes!
			if ( isset( $parsedResponse->location ) ) {
				$this->resetPayload();

				return $parsedResponse->location;
			} else {
				if ( isset( $parsedResponse->error ) ) {
					$error[] = $parsedResponse->error;
				}
				if ( isset( $parsedResponse->message ) ) {
					$error[] = $parsedResponse->message;
				}
				$responseCode = $this->CURL->getResponseCode( $hostedResponse );
				throw new \Exception( implode( "\n", $error ), $responseCode );
			}
			throw new \Exception( "Could not parse location of hosted flow (missing)", 404 );
		}
	}

	/**
	 * Book signed payment
	 *
	 * @param string $paymentId
	 *
	 * @return array|mixed|null
	 * @throws \Exception
	 * @since 1.0.5
	 * @since 1.1.5
	 */
	public function bookSignedPayment( $paymentId = '' ) {
		return $this->postService( "bookSignedPayment", array( 'paymentId' => $paymentId ) );
	}

	/**
	 * @param $paymentId
	 * @param int $hashLevel
	 *
	 * @throws \Exception
	 */
	public function addMetaDataHash( $paymentId, $hashLevel = RESURS_METAHASH_TYPES::HASH_ORDERLINES ) {
		if ( ! $this->metaDataHashEnabled ) {
			return;
		}

		/** @var string $dataHash Output string */
		$dataHash = null;
		/** @var array $customerData */
		$customerData = array();
		/** @var array $hashes Data to hash or encrypt */
		$hashes = array();

		// Set up the kind of data that can be hashed
		$this->BIT->setBitStructure( array(
			'ORDERLINES' => RESURS_METAHASH_TYPES::HASH_ORDERLINES,
			'CUSTOMER'   => RESURS_METAHASH_TYPES::HASH_CUSTOMER
		) );

		// Fetch the payload and pick up data that can be used in the hashing
		$payload = $this->getPayload();
		if ( isset( $payload['orderData'] ) ) {
			unset( $payload['orderData'] );
		}
		if ( isset( $payload['customer'] ) ) {
			$customerData = $payload['customer'];
		}

		// Sanitize the orderlines with the simplest content available (The "minimalisticflow" gives us artNo, description, price, quantiy)
		$orderData = $this->sanitizePaymentSpec( $this->getOrderLines(), RESURS_FLOW_TYPES::FLOW_MINIMALISTIC );
		if ( $this->BIT->isBit( RESURS_METAHASH_TYPES::HASH_ORDERLINES, $hashLevel ) ) {
			$hashes['orderLines'] = $orderData;
		}
		if ( $this->BIT->isBit( RESURS_METAHASH_TYPES::HASH_CUSTOMER, $hashLevel ) ) {
			$hashes['customer'] = $customerData;
		}

		if ( ! $this->metaDataHashEncrypted ) {
			$dataHash = sha1( json_encode( $hashes ) );
		} else {
			$dataHash = $this->T_CRYPTO->aesEncrypt( json_encode( $hashes ), true );
		}

		if ( ! isset( $this->Payload['metaData'] ) ) {
			$this->Payload['metaData'] = array();
		}
		$this->Payload['metaData'][] = array(
			'key'   => 'ecomHash',
			'value' => $dataHash
		);
	}

	/**
	 * @param bool $enable
	 * @param bool $encryptEnable Requires RIJNDAEL/AES Encryption enabled
	 * @param null $encryptIv
	 * @param null $encryptKey
	 *
	 * @throws \Exception
	 */
	public function setMetaDataHash( $enable = true, $encryptEnable = false, $encryptIv = null, $encryptKey = null ) {
		$this->metaDataHashEnabled   = $enable;
		$this->metaDataHashEncrypted = $encryptEnable;
		if ( $encryptEnable ) {
			if ( is_null( $encryptIv ) || is_null( $encryptKey ) ) {
				throw new \Exception( "To encrypt your metadata, you'll need to set up encryption keys" );
			}
			$this->metaDataIv  = $encryptIv;
			$this->metaDataKey = $encryptKey;
			$this->T_CRYPTO->setAesIv( $this->metaDataIv );
			$this->T_CRYPTO->setAesKey( $this->metaDataKey );
		}
	}

	/**
	 * @return bool
	 */
	public function getMetaDataHash() {
		return $this->metaDataHashEnabled;
	}

	public function getMetaDataVerify() {
		// TODO: Coming soon
	}

	/**
	 * Get the payment session id from Resurs Checkout
	 *
	 * @return string
	 * @since 1.0.2
	 * @since 1.1.2
	 */
	public function getPaymentSessionId() {
		return $this->paymentSessionId;
	}

	/**
	 * @return array|mixed
	 * @throws \Exception
	 * @since 1.0.3
	 * @since 1.1.3
	 */
	public function Execute() {
		if ( ! empty( $this->createPaymentExecuteCommand ) ) {
			return $this->createPaymentExecute( $this->createPaymentExecuteCommand, $this->Payload );
		} else {
			throw new \Exception( "setRequiredExecute() must used before you use this function", 403 );
		}
	}

	/**
	 * Pre-set a default unit measure if it is missing in the payment spec. Defaults to "st" if nothing is set.
	 *
	 * If no unit measure are set but setCountry() have been used, this function will try to set a matching string depending on the country.
	 *
	 * @param null $unitMeasure
	 *
	 * @since 1.0.2
	 * @since 1.1.2
	 */
	public function setDefaultUnitMeasure( $unitMeasure = null ) {
		if ( is_null( $unitMeasure ) ) {
			if ( ! empty( $this->envCountry ) ) {
				if ( $this->envCountry == RESURS_COUNTRY::COUNTRY_DK ) {
					$this->defaultUnitMeasure = "st";
				} else if ( $this->envCountry == RESURS_COUNTRY::COUNTRY_NO ) {
					$this->defaultUnitMeasure = "st";
				} else if ( $this->envCountry == RESURS_COUNTRY::COUNTRY_FI ) {
					$this->defaultUnitMeasure = "kpl";
				} else {
					$this->defaultUnitMeasure = "st";
				}
			} else {
				$this->defaultUnitMeasure = "st";
			}
		} else {
			$this->defaultUnitMeasure = $unitMeasure;
		}
	}

	/**
	 * Returns current set unitmeasure (st, kpl, etc)
	 * @return string
	 * @since 1.0.26
	 * @since 1.1.26
	 * @since 1.2.0
	 */
	public function getDefaultUnitMeasure() {
		return $this->defaultUnitMeasure;
	}

	/**
	 * Prepare the payload
	 *
	 * @param string $payment_id_or_method
	 * @param array $payload
	 *
	 * @throws \Exception
	 * @since 1.0.1
	 * @since 1.1.1
	 */
	private function preparePayload( $payment_id_or_method = '', $payload = array() ) {
		$this->InitializeServices();
		$this->handlePayload( $payload );

		$updateStoreIdEvent = $this->event( 'update_store_id');
		if ( ! is_null( $updateStoreIdEvent ) ) {
			$this->setStoreId( $updateStoreIdEvent );
		}

		if ( empty( $this->defaultUnitMeasure ) ) {
			$this->setDefaultUnitMeasure();
		}
		if ( ! $this->enforceService ) {
			$this->setPreferredPaymentFlowService( RESURS_FLOW_TYPES::FLOW_SIMPLIFIED_FLOW );
		}
		if ( $this->enforceService === RESURS_FLOW_TYPES::FLOW_RESURS_CHECKOUT ) {
			if ( empty( $payment_id_or_method ) && empty( $this->preferredId ) ) {
				throw new \Exception( "A payment method or payment id must be defined", \RESURS_EXCEPTIONS::CREATEPAYMENT_NO_ID_SET );
			}
			$payment_id_or_method = $this->preferredId;
		}
		if ( ! count( $this->Payload ) ) {
			throw new \Exception( "No payload are set for this payment", \RESURS_EXCEPTIONS::BOOKPAYMENT_NO_BOOKDATA );
		}

		// Obsolete way to handle multidimensional specrows
		if ( isset( $this->Payload['specLine'] ) ) {
			if ( isset( $this->Payload['specLine']['artNo'] ) ) {
				$this->SpecLines[] = $this->Payload['specLine'];
			} else {
				if ( is_array( $this->Payload['specLine'] ) ) {
					foreach ( $this->Payload['specLine'] as $specRow ) {
						$this->SpecLines[] = $specRow;
					}
				}
			}
			unset( $this->Payload['specLine'] );
			$this->renderPaymentSpec();
		} else if ( isset( $this->Payload['orderLines'] ) ) {
			$this->renderPaymentSpec();
		} else if ( ! isset( $this->Payload['orderLines'] ) && count( $this->SpecLines ) ) {
			// Fix desynched orderlines
			$this->Payload['orderLines'] = $this->SpecLines;
			$this->renderPaymentSpec();
		}
		if ( $this->enforceService === RESURS_FLOW_TYPES::FLOW_HOSTED_FLOW || $this->enforceService === RESURS_FLOW_TYPES::FLOW_SIMPLIFIED_FLOW ) {
			if ( ! isset( $paymentDataPayload ['paymentData'] ) ) {
				$paymentDataPayload ['paymentData'] = array();
			}

			$paymentDataPayload['paymentData']['paymentMethodId']   = $payment_id_or_method;
			$paymentDataPayload['paymentData']['preferredId']       = $this->getPreferredPaymentId();
			$paymentDataPayload['paymentData']['customerIpAddress'] = $this->getCustomerIp();

			if ( $this->enforceService === RESURS_FLOW_TYPES::FLOW_SIMPLIFIED_FLOW ) {
				if ( ! isset( $this->Payload['storeId'] ) && ! empty( $this->storeId ) ) {
					$this->Payload['storeId'] = $this->storeId;
				}
			} else {
				// The simplified flag control must run to be backward compatible with older services
				if ( isset( $this->Payload['paymentData']['waitForFraudControl'] ) ) {
					$this->Payload['waitForFraudControl'] = $this->Payload['paymentData']['waitForFraudControl'];
					unset( $this->Payload['paymentData']['waitForFraudControl'] );
				}
				if ( isset( $this->Payload['paymentData']['annulIfFrozen'] ) ) {
					$this->Payload['annulIfFrozen'] = $this->Payload['paymentData']['annulIfFrozen'];
					unset( $this->Payload['paymentData']['annulIfFrozen'] );
				}
				if ( isset( $this->Payload['paymentData']['finalizeIfBooked'] ) ) {
					$this->Payload['finalizeIfBooked'] = $this->Payload['paymentData']['finalizeIfBooked'];
					unset( $this->Payload['paymentData']['finalizeIfBooked'] );
				}
			}
			$this->handlePayload( $paymentDataPayload, true );
		}
		if ( ( $this->enforceService == RESURS_FLOW_TYPES::FLOW_RESURS_CHECKOUT || $this->enforceService == RESURS_FLOW_TYPES::FLOW_HOSTED_FLOW ) ) {
			// Convert signing to checkouturls if exists (not receommended as failurl might not always be the backurl)
			// However, those variables will only be replaced in the correct payload if they are not already there.
			if ( isset( $this->Payload['signing'] ) ) {
				if ( $this->enforceService == RESURS_FLOW_TYPES::FLOW_HOSTED_FLOW ) {
					if ( isset( $this->Payload['signing']['forceSigning'] ) ) {
						$this->Payload['forceSigning'] = $this->Payload['signing']['forceSigning'];
					}
					if ( ! isset( $this->Payload['failUrl'] ) && isset( $this->Payload['signing']['failUrl'] ) ) {
						$this->Payload['failUrl'] = $this->Payload['signing']['failUrl'];
					}
					if ( ! isset( $this->Payload['backUrl'] ) && isset( $this->Payload['signing']['backUrl'] ) ) {
						$this->Payload['backUrl'] = $this->Payload['signing']['backUrl'];
					}
				}
				if ( ! isset( $this->Payload['successUrl'] ) && isset( $this->Payload['signing']['successUrl'] ) ) {
					$this->Payload['successUrl'] = $this->Payload['signing']['successUrl'];
				}
				if ( ! isset( $this->Payload['backUrl'] ) && isset( $this->Payload['signing']['failUrl'] ) ) {
					$this->Payload['backUrl'] = $this->Payload['signing']['failUrl'];
				}
				unset( $this->Payload['signing'] );
			}
			// Rules for customer only applies to checkout. As this also involves the hosted flow (see above) this must only specifically occur on the checkout
			if ( $this->enforceService == RESURS_FLOW_TYPES::FLOW_RESURS_CHECKOUT ) {
				if ( ! isset( $this->Payload['storeId'] ) && ! empty( $this->storeId ) ) {
					$this->Payload['storeId'] = $this->storeId;
				}
				if ( isset( $this->Payload['paymentData'] ) ) {
					unset( $this->Payload['paymentData'] );
				}
				if ( isset( $this->Payload['customer']['address'] ) ) {
					unset( $this->Payload['customer']['address'] );
				}
				if ( isset( $this->Payload['customer']['deliveryAddress'] ) ) {
					unset( $this->Payload['customer']['deliveryAddress'] );
				}
				if ( $this->checkoutCustomerFieldSupport === false && isset( $this->Payload['customer'] ) ) {
					unset( $this->Payload['customer'] );
				}
				// Making sure sloppy developers uses shopUrl properly.
				if ( ! isset( $this->Payload['shopUrl'] ) ) {
					if ( $this->validateCheckoutShopUrl ) {
						$shopUrlValidate       = $this->NETWORK->getUrlDomain( $this->checkoutShopUrl );
						$this->checkoutShopUrl = $shopUrlValidate[1] . "://" . $shopUrlValidate[0];
					}
					$this->Payload['shopUrl'] = $this->checkoutShopUrl;
				}
			}
		}
		// If card data has been included in the payload, make sure that the card data is validated if the payload has been sent
		// by manual hands (deprecated mode)
		if ( isset( $this->Payload['card'] ) ) {
			if ( isset( $this->PaymentMethod->specificType ) ) {
				$this->validateCardData( $this->PaymentMethod->specificType );
			}
		}

		$eventReturns = $this->event( 'update_payload', $this->Payload );
		if ( ! is_null( $eventReturns ) ) {
			$this->Payload = $eventReturns;
		}
	}

	private function fixPaymentData() {
		if ( ! isset( $this->Payload['paymentData'] ) ) {
			$this->Payload['paymentData'] = array();
		}
	}

	/**
	 * Set flag annulIfFrozen
	 *
	 * @param bool $setBoolean
	 *
	 * @since 1.0.29
	 * @since 1.1.29
	 * @since 1.2.2
	 * @since 1.3.2
	 */
	public function setAnnulIfFrozen( $setBoolean = true ) {
		$this->fixPaymentData();
		$this->Payload['paymentData']['annulIfFrozen'] = $setBoolean;
	}

	/**
	 * Set flag annulIfFrozen
	 * @return bool
	 * @since 1.0.29
	 * @since 1.1.29
	 * @since 1.2.2
	 * @since 1.3.2
	 */
	public function getAnnulIfFrozen() {
		$this->fixPaymentData();

		return isset( $this->Payload['paymentData']['annulIfFrozen'] ) ? $this->Payload['paymentData']['annulIfFrozen'] : false;
	}

	/**
	 * Set flag waitForFraudControl
	 *
	 * @param bool $setBoolean
	 *
	 * @return bool
	 * @since 1.0.29
	 * @since 1.1.29
	 * @since 1.2.2
	 * @since 1.3.2
	 */
	public function setWaitForFraudControl( $setBoolean = true ) {
		$this->fixPaymentData();
		$this->Payload['paymentData']['waitForFraudControl'] = $setBoolean;

		return isset( $this->Payload['paymentData']['waitForFraudControl'] ) ? $this->Payload['paymentData']['waitForFraudControl'] : false;
	}

	/**
	 * Get flag waitForFraudControl
	 * @return bool
	 * @since 1.0.29
	 * @since 1.1.29
	 * @since 1.2.2
	 * @since 1.3.2
	 */
	public function getWaitForFraudControl() {
		$this->fixPaymentData();

		return isset( $this->Payload['paymentData']['waitForFraudControl'] ) ? $this->Payload['paymentData']['waitForFraudControl'] : false;
	}

	/**
	 * Set flag finalizeIfBooked
	 *
	 * @param bool $setBoolean
	 *
	 * @return bool
	 * @since 1.0.29
	 * @since 1.1.29
	 * @since 1.2.2
	 * @since 1.3.2
	 */
	public function setFinalizeIfBooked( $setBoolean = true ) {
		$this->fixPaymentData();
		$this->Payload['paymentData']['finalizeIfBooked'] = $setBoolean;

		return isset( $this->Payload['paymentData']['finalizeIfBooked'] ) ? $this->Payload['paymentData']['finalizeIfBooked'] : false;
	}

	/**
	 * Get flag finalizeIfBooked
	 * @return bool
	 * @since 1.0.29
	 * @since 1.1.29
	 * @since 1.2.2
	 * @since 1.3.2
	 */
	public function getFinalizeIfBooked() {
		$this->fixPaymentData();

		return isset( $this->Payload['paymentData']['finalizeIfBooked'] ) ? $this->Payload['paymentData']['finalizeIfBooked'] : false;
	}


	/**
	 * Return correct data on https-detection
	 *
	 * @param bool $returnProtocol
	 *
	 * @return bool|string
	 * @since 1.0.3
	 * @since 1.1.3
	 */
	private function hasHttps( $returnProtocol = false ) {
		if ( isset( $_SERVER['HTTPS'] ) ) {
			if ( $_SERVER['HTTPS'] == "on" ) {
				if ( ! $returnProtocol ) {
					return true;
				} else {
					return "https";
				}
			} else {
				if ( ! $returnProtocol ) {
					return false;
				} else {
					return "http";
				}
			}
		}
		if ( ! $returnProtocol ) {
			return false;
		} else {
			return "http";
		}

	}

	/**
	 * Make sure that the payment spec only contains the data that each payment flow needs.
	 *
	 * This function has been created for keeping backwards compatibility from older payment spec renderers. EComPHP is allowing
	 * same content in the payment spec for all flows, so to keep this steady, this part of EComPHP will sanitize each spec
	 * so it only contains data that it really needs when push out the payload to ecommerce.
	 *
	 * @param array $specLines
	 * @param int $myFlowOverrider
	 *
	 * @return array
	 * @since 1.0.4
	 * @since 1.1.4
	 */
	public function sanitizePaymentSpec( $specLines = array(), $myFlowOverrider = RESURS_FLOW_TYPES::FLOW_NOT_SET ) {
		$specRules = array(
			'checkout'     => array(
				'artNo',
				'description',
				'quantity',
				'unitMeasure',
				'unitAmountWithoutVat',
				'vatPct',
				'type'
			),
			'hosted'       => array(
				'artNo',
				'description',
				'quantity',
				'unitMeasure',
				'unitAmountWithoutVat',
				'vatPct',
				'totalVatAmount',
				'totalAmount'
			),
			'simplified'   => array(
				'id',
				'artNo',
				'description',
				'quantity',
				'unitMeasure',
				'unitAmountWithoutVat',
				'vatPct',
				'totalVatAmount',
				'totalAmount'
			),
			'minimalistic' => array(
				'artNo',
				'description',
				'unitAmountWithoutVat',
				'quantity'
			)
		);
		if ( is_array( $specLines ) ) {
			$myFlow = $this->getPreferredPaymentFlowService();
			if ( $myFlowOverrider !== RESURS_FLOW_TYPES::FLOW_NOT_SET ) {
				$myFlow = $myFlowOverrider;
			}
			$mySpecRules = array();
			if ( $myFlow == RESURS_FLOW_TYPES::FLOW_SIMPLIFIED_FLOW ) {
				$mySpecRules = $specRules['simplified'];
			} else if ( $myFlow == RESURS_FLOW_TYPES::FLOW_HOSTED_FLOW ) {
				$mySpecRules = $specRules['hosted'];
			} else if ( $myFlow == RESURS_FLOW_TYPES::FLOW_RESURS_CHECKOUT ) {
				$mySpecRules = $specRules['checkout'];
			} else if ( $myFlow == RESURS_FLOW_TYPES::FLOW_MINIMALISTIC ) {
				$mySpecRules = $specRules['minimalistic'];
			}
			$hasMeasure = false;
			foreach ( $specLines as $specIndex => $specArray ) {
				foreach ( $specArray as $key => $value ) {
					if ( strtolower( $key ) == "unitmeasure" && empty( $value ) ) {
						$hasMeasure = true;
						$specArray[ $key ] = $this->defaultUnitMeasure;
					}
					if ( ! in_array( strtolower( $key ), array_map( "strtolower", $mySpecRules ) ) ) {
						unset( $specArray[ $key ] );
					}
				}
				// Add unit measure if missing
				if ( ! $hasMeasure && $myFlow != RESURS_FLOW_TYPES::FLOW_MINIMALISTIC ) {
					$specArray['unitMeasure'] = $this->defaultUnitMeasure;
				}
				$specLines[ $specIndex ] = $specArray;
			}
		}

		return $specLines;
	}

	/**
	 * Defines if the checkout should honor the customer field array as it is not officially supported by Resurs Bank
	 *
	 * @param bool $isCustomerSupported
	 *
	 * @since 1.0.2
	 * @since 1.1.2
	 */
	public function setCheckoutCustomerSupported( $isCustomerSupported = false ) {
		$this->checkoutCustomerFieldSupport = $isCustomerSupported;
	}

	/**
	 * Enable execute()-mode on data passed through createPayment()
	 *
	 * If you run createPayment() and does not succeed during the primary function, you can enable this function to not fulfill the
	 * whole part of the payment until doing an execute(). In this case EComPHP will only prepare the required parameters for the payment
	 * to run. When this function is enabled you can also, before creating the payment do for example a getPayload() to see how it looks
	 * before completion.
	 *
	 * @param bool $enableExecute
	 *
	 * @since 1.0.3
	 * @since 1.1.3
	 */
	public function setRequiredExecute( $enableExecute = false ) {
		$this->forceExecute = $enableExecute;
	}

	/**
	 * Customer address simplifier. Renders a correct array depending on the flow.
	 *
	 * @param $fullName
	 * @param $firstName
	 * @param $lastName
	 * @param $addressRow1
	 * @param $addressRow2
	 * @param $postalArea
	 * @param $postalCode
	 * @param $country
	 *
	 * @return array
	 * @since 1.0.2
	 * @since 1.1.2
	 */
	private function renderAddress( $fullName, $firstName, $lastName, $addressRow1, $addressRow2, $postalArea, $postalCode, $country ) {
		$ReturnAddress = array(
			'fullName'    => $fullName,
			'firstName'   => $firstName,
			'lastName'    => $lastName,
			'addressRow1' => $addressRow1,
			'postalArea'  => $postalArea,
			'postalCode'  => $postalCode
		);
		$trimAddress   = trim( $addressRow2 ); // PHP Compatibility
		if ( ! empty( $trimAddress ) ) {
			$ReturnAddress['addressRow2'] = $addressRow2;
		}
		$targetCountry = $this->getCountry();
		if ( empty( $country ) && ! empty( $targetCountry ) ) {
			$country = $targetCountry;
		} else if ( ! empty( $country ) && empty( $targetCountry ) ) {
			// Giving internal country data more influence on this method
			$this->setCountryByCountryCode( $targetCountry );
		}
		if ( is_null( $this->enforceService ) ) {
			/**
			 * EComPHP might get a bit confused here, if no preferred flow is set. Normally, we don't have to know this,
			 * but in this case (since EComPHP actually points at the simplified flow by default) we need to tell it
			 * what to use, so correct payload will be used, during automation of the billing.
			 * @link https://resursbankplugins.atlassian.net/browse/ECOMPHP-238
			 */
			$this->setPreferredPaymentFlowService( RESURS_FLOW_TYPES::FLOW_SIMPLIFIED_FLOW );
		}
		if ( $this->enforceService === RESURS_FLOW_TYPES::FLOW_SIMPLIFIED_FLOW) {
			$ReturnAddress['country'] = $country;
		} else {
			$ReturnAddress['countryCode'] = $country;
		}

		return $ReturnAddress;
	}

	/**
	 * Inject a payload with given array, object or string (defaults to array)
	 *
	 * @param $ArrayKey
	 * @param array $ArrayValue
	 *
	 * @since 1.0.2
	 * @since 1.1.2
	 */
	private function setPayloadArray( $ArrayKey, $ArrayValue = array() ) {
		if ( $ArrayKey == "address" || $ArrayKey == "deliveryAddress" ) {
			if ( ! isset( $this->Payload['customer'] ) ) {
				$this->Payload['customer'] = array();
			}
			$this->Payload['customer'][ $ArrayKey ] = $ArrayValue;
		} else {
			$this->Payload[ $ArrayKey ] = $ArrayValue;
		}
	}

	/**
	 * Generate a Payload for customer address, depending on a received getAddress()-object
	 *
	 * @param string $addressKey
	 * @param $addressData
	 *
	 * @since 1.0.2
	 * @since 1.1.2
	 */
	private function setAddressPayload( $addressKey = 'address', $addressData ) {
		if ( is_object( $addressData ) ) {
			$this->setPayloadArray( $addressKey, $this->renderAddress(
				isset( $addressData->fullName ) && ! empty( $addressData->fullName ) ? $addressData->fullName : "",
				isset( $addressData->firstName ) && ! empty( $addressData->firstName ) ? $addressData->firstName : "",
				isset( $addressData->lastName ) && ! empty( $addressData->lastName ) ? $addressData->lastName : "",
				isset( $addressData->addressRow1 ) && ! empty( $addressData->addressRow1 ) ? $addressData->addressRow1 : "",
				isset( $addressData->addressRow2 ) && ! empty( $addressData->addressRow2 ) ? $addressData->addressRow2 : "",
				isset( $addressData->postalArea ) && ! empty( $addressData->postalArea ) ? $addressData->postalArea : "",
				isset( $addressData->postalCode ) && ! empty( $addressData->postalCode ) ? $addressData->postalCode : "",
				isset( $addressData->country ) && ! empty( $addressData->country ) ? $addressData->country : ""
			) );
		} else if ( is_array( $addressData ) ) {
			// If there is an inbound countryCode here, there is a consideration of hosted flow.
			// In this case we need to normalize the address data first as renderAddress() are rerunning also during setBillingAddress()-process.
			// If we don't do this, EComPHP will drop the countryCode and leave the payload empty  - see ECOMPHP-168.
			if ( isset( $addressData['countryCode'] ) && ! empty( $addressData['countryCode'] ) ) {
				$addressData['country'] = $addressData['countryCode'];
				unset( $addressData['countryCode'] );
			}
			$this->setPayloadArray( $addressKey, $this->renderAddress(
				isset( $addressData['fullName'] ) && ! empty( $addressData['fullName'] ) ? $addressData['fullName'] : "",
				isset( $addressData['firstName'] ) && ! empty( $addressData['firstName'] ) ? $addressData['firstName'] : "",
				isset( $addressData['lastName'] ) && ! empty( $addressData['lastName'] ) ? $addressData['lastName'] : "",
				isset( $addressData['addressRow1'] ) && ! empty( $addressData['addressRow1'] ) ? $addressData['addressRow1'] : "",
				isset( $addressData['addressRow2'] ) && ! empty( $addressData['addressRow2'] ) ? $addressData['addressRow2'] : "",
				isset( $addressData['postalArea'] ) && ! empty( $addressData['postalArea'] ) ? $addressData['postalArea'] : "",
				isset( $addressData['postalCode'] ) && ! empty( $addressData['postalCode'] ) ? $addressData['postalCode'] : "",
				isset( $addressData['country'] ) && ! empty( $addressData['country'] ) ? $addressData['country'] : ""
			) );
		}
	}

	/**
	 * Payload simplifier: Having data from getAddress, you want to set as billing address, this can be done from here.
	 *
	 * @param string $getaddressdata_or_governmentid
	 * @param string $customerType
	 *
	 * @return array
	 * @throws \Exception
	 * @since 1.0.2
	 * @since 1.1.2
	 */
	public function setBillingByGetAddress( $getaddressdata_or_governmentid, $customerType = "NATURAL" ) {
		if ( is_object( $getaddressdata_or_governmentid ) ) {
			$this->setAddressPayload( "address", $getaddressdata_or_governmentid );
		} else if ( is_numeric( $getaddressdata_or_governmentid ) ) {
			$this->Payload['customer']['governmentId'] = $getaddressdata_or_governmentid;
			$this->setAddressPayload( "address", $this->getAddress( $getaddressdata_or_governmentid, $customerType, isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : "127.0.0.1" ) );
		}

		return $this->Payload;
	}

	/**
	 * Payload simplifier: Having data from getAddress, you want to set as shipping address, this can be done from here.
	 *
	 * @param $getAddressData
	 *
	 * @since 1.0.2
	 * @since 1.1.2
	 */
	public function setDeliveryByGetAddress( $getAddressData ) {
		$this->setAddressPayload( "deliveryAddress", $getAddressData );
	}

	/**
	 * Generate a Payload for customer address, depending on developer code
	 *
	 * @param $fullName
	 * @param $firstName
	 * @param $lastName
	 * @param $addressRow1
	 * @param $addressRow2
	 * @param $postalArea
	 * @param $postalCode
	 * @param $country
	 *
	 * @since 1.0.2
	 * @since 1.1.2
	 */
	public function setBillingAddress( $fullName, $firstName, $lastName, $addressRow1, $addressRow2, $postalArea, $postalCode, $country ) {
		$this->setAddressPayload( "address", $this->renderAddress( $fullName, $firstName, $lastName, $addressRow1, $addressRow2, $postalArea, $postalCode, $country ) );
	}

	/**
	 * Generate a payload for customer delivery address, depending on developer code
	 *
	 * @param $fullName
	 * @param $firstName
	 * @param $lastName
	 * @param $addressRow1
	 * @param $addressRow2
	 * @param $postalArea
	 * @param $postalCode
	 * @param $country
	 *
	 * @since 1.0.2
	 * @since 1.1.2
	 */
	public function setDeliveryAddress( $fullName, $firstName, $lastName, $addressRow1, $addressRow2, $postalArea, $postalCode, $country ) {
		$this->setAddressPayload( "deliveryAddress", $this->renderAddress( $fullName, $firstName, $lastName, $addressRow1, $addressRow2, $postalArea, $postalCode, $country ) );
	}

	/**
	 * @param string $governmentId
	 * @param string $phone
	 * @param string $cellphone
	 * @param string $email
	 * @param string $customerType NATURAL/LEGAL
	 * @param string $contactgovernmentId
	 *
	 * @throws \Exception
	 * @since 1.0.2
	 * @since 1.1.2
	 */
	public function setCustomer( $governmentId = "", $phone = "", $cellphone = "", $email = "", $customerType = "", $contactgovernmentId = "" ) {
		if ( ! isset( $this->Payload['customer'] ) ) {
			$this->Payload['customer'] = array();
		}
		// Set this if not already set by a getAddress()
		if ( ! isset( $this->Payload['customer']['governmentId'] ) ) {
			$this->Payload['customer']['governmentId'] = ! empty( $governmentId ) ? $governmentId : "";
		}
		$this->Payload['customer']['email'] = $email;
		if ( ! empty( $phone ) ) {
			$this->Payload['customer']['phone'] = $phone;
		}
		if ( ! empty( $cellphone ) ) {
			$this->Payload['customer']['cellPhone'] = $cellphone;
		}
		if ( ! empty( $customerType ) ) {
			$this->Payload['customer']['type'] = ! empty( $customerType ) && ( strtolower( $customerType ) == "natural" || strtolower( $customerType ) == "legal" ) ? strtoupper( $customerType ) : "NATURAL";
		} else {
			// We don't guess on customer types
			throw new \Exception( "No customer type has been set. Use NATURAL or LEGAL to proceed", \RESURS_EXCEPTIONS::BOOK_CUSTOMERTYPE_MISSING );
		}
		if ( ! empty( $contactgovernmentId ) ) {
			$this->Payload['customer']['contactGovernmentId'] = $contactgovernmentId;
		}
	}

	/**
	 * Configure signing data for the payload
	 *
	 * @param string $successUrl Successful payment redirect url
	 * @param string $failUrl Payment failures redirect url
	 * @param bool $forceSigning Always require signing during payment
	 * @param string $backUrl Backurl (optional, for hosted flow) if anything else than failUrl (backUrl is used when customers are clicking "back" rather than failing)
	 *
	 * @throws \Exception
	 * @since 1.0.6
	 * @since 1.1.6
	 */
	public function setSigning( $successUrl = '', $failUrl = '', $forceSigning = false, $backUrl = null ) {
		$SigningPayload['signing'] = array(
			'successUrl'   => $successUrl,
			'failUrl'      => $failUrl,
			'forceSigning' => $forceSigning
		);
		if ( ! is_null( $backUrl ) ) {
			$SigningPayload['backUrl'] = $backUrl;
		}
		$this->handlePayload( $SigningPayload );
	}

	/**
	 * Helper function. This actually does what setSigning do, but with lesser confusion.
	 *
	 * @param string $successUrl
	 * @param string $backUrl
	 *
	 * @throws \Exception
	 */
	public function setCheckoutUrls( $successUrl = '', $backUrl = '' ) {
		$this->setSigning( $successUrl, $backUrl );
	}

	//// PAYLOAD HANDLER!

	/**
	 * Compile user defined payload with payload that may have been pre-set by other calls
	 *
	 * @param array $userDefinedPayload
	 * @param bool $replacePayload Allow replacements of old payload data
	 * @throws \Exception
	 * @since 1.0.2
	 * @since 1.1.2
	 */
	private function handlePayload( $userDefinedPayload = array(), $replacePayload = false ) {
		$myFlow = $this->getPreferredPaymentFlowService();
		if ( is_array( $userDefinedPayload ) && count( $userDefinedPayload ) ) {
			foreach ( $userDefinedPayload as $payloadKey => $payloadContent ) {
				if ( ! isset( $this->Payload[ $payloadKey ] ) && ! $replacePayload ) {
					$this->Payload[ $payloadKey ] = $payloadContent;
				} else {
					// If the payloadkey already exists, there might be something that wants to share information.
					// In this case, append more data to the children
					foreach ( $userDefinedPayload[ $payloadKey ] as $subKey => $subValue ) {
						if ( ! isset( $this->Payload[ $payloadKey ][ $subKey ] ) ) {
							$this->Payload[ $payloadKey ][ $subKey ] = $subValue;
						} else if ($replacePayload) {
							$this->Payload[ $payloadKey ][ $subKey ] = $subValue;
						}
					}
				}
			}
		}
		// Address and deliveryAddress should move to the correct location
		if ( isset( $this->Payload['address'] ) ) {
			$this->Payload['customer']['address'] = $this->Payload['address'];
			if ( $myFlow == RESURS_FLOW_TYPES::FLOW_HOSTED_FLOW && isset( $this->Payload['customer']['address']['country'] ) ) {
				$this->Payload['customer']['address']['countryCode'] = $this->Payload['customer']['address']['country'];
			}
			unset( $this->Payload['address'] );
		}
		if ( isset( $this->Payload['deliveryAddress'] ) ) {
			$this->Payload['customer']['deliveryAddress'] = $this->Payload['deliveryAddress'];
			if ( $myFlow == RESURS_FLOW_TYPES::FLOW_HOSTED_FLOW && isset( $this->Payload['customer']['deliveryAddress']['country'] ) ) {
				$this->Payload['customer']['deliveryAddress']['countryCode'] = $this->Payload['customer']['deliveryAddress']['country'];
			}
			unset( $this->Payload['deliveryAddress'] );
		}
		if ( isset( $this->Payload['customer'] ) ) {
			$noCustomerType = false;
			if ( ( ! isset( $this->Payload['customer']['type'] ) ) || isset( $this->Payload['customer']['type'] ) && empty( $this->Payload['customer']['type'] ) ) {
				$noCustomerType = true;
			}
			if ( $noCustomerType ) {
				if ( ! empty( $this->desiredPaymentMethod ) ) {
					$paymentMethodInfo = $this->getPaymentMethodSpecific( $this->desiredPaymentMethod );
					if ( isset( $paymentMethodInfo->customerType ) ) {
						if ( ! is_array( $paymentMethodInfo->customerType ) && ! empty( $paymentMethodInfo->customerType ) ) {
							$this->Payload['customer']['type'] = $paymentMethodInfo->customerType;
						} else {
							// At this stage, we have no idea of which customer type it is about, so we will fail over to NATURAL
							// when it is not set by the customer itself. We could do a getAddress here, but that may not be safe enough
							// to decide customer types automatically. Also, it is in for example hosted flow not even necessary to
							// enter a government id here.
							// Besides this? It lowers the performance of the actions.
							$this->Payload['customer']['type'] = "NATURAL";
						}
					}
				}
			}
		}
	}

	/**
	 * Returns the final payload
	 *
	 * @param bool $history
	 *
	 * @return array
	 * @throws \Exception
	 * @since 1.0.2
	 * @since 1.1.2
	 * @since 1.2.0
	 */
	public function getPayload( $history = false ) {
		if ( ! $history ) {
			$this->preparePayload();
			// Making sure payloads are returned as they should look
			if ( isset( $this->Payload ) ) {
				if ( ! is_array( $this->Payload ) ) {
					$this->Payload = array();
				}
			} else {
				$this->Payload = array();
			}

			return $this->Payload;
		} else {
			return array_pop( $this->PayloadHistory );
		}
	}

	/**
	 * Return added speclines / Orderlines
	 *
	 * @return array
	 */
	public function getOrderLines() {
		return $this->SpecLines;
	}

	/**
	 * @return array
	 */
	public function getSpecLines() {
		return $this->getOrderLines();
	}

	/**
	 * Return the final payload order data array
	 *
	 * @return array
	 * @throws \Exception
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	public function getOrderData() {
		$this->preparePayload();

		return isset( $this->Payload['orderData'] ) ? $this->Payload['orderData'] : array();
	}


	/**
	 * Remove script from the iframe-source
	 *
	 * Normally, ecommerce in OmniCheckout mode, returns an iframe-tag with a link to the payment handler.
	 * ECommerce also appends a script-tag for which the iframe are resized from. Sometimes, we want to strip
	 * this tag from the iframe and separate them. This is where we do that.
	 *
	 * @param string $htmlString
	 * @param bool $ocShopInternalHandle
	 *
	 * @return mixed|string
	 * @since 1.0.1
	 * @since 1.1.1
	 */
	private function clearOcShop( $htmlString = "", $ocShopInternalHandle = false ) {
		if ( $ocShopInternalHandle ) {
			preg_match_all( "/\<script(.*?)\/script>/", $htmlString, $scriptStringArray );
			if ( is_array( $scriptStringArray ) && isset( $scriptStringArray[0][0] ) && ! empty( $scriptStringArray[0][0] ) ) {
				$scriptString = $scriptStringArray[0][0];
				preg_match_all( "/src=\"(.*?)\"/", $scriptString, $getScriptSrc );
				if ( is_array( $getScriptSrc ) && isset( $getScriptSrc[1][0] ) ) {
					$this->ocShopScript = $getScriptSrc[1][0];
				}
			}
			$htmlString = preg_replace( "/\<script(.*?)\/script>/", '', $htmlString );
		}

		return $htmlString;
	}

	/**
	 * Get the iframe resizer URL if requested from a site
	 *
	 * @return string
	 * @since 1.0.2
	 * @since 1.1.2
	 */
	public function getIframeResizerUrl() {
		if ( ! empty( $this->ocShopScript ) ) {
			return trim( $this->ocShopScript );
		}
	}

	/**
	 * Retrieve the correct omnicheckout url depending chosen environment
	 *
	 * @param int $EnvironmentRequest
	 * @param bool $getCurrentIfSet Always return "current" if it has been set first
	 *
	 * @return string
	 * @since 1.0.1
	 * @since 1.1.1
	 */
	public function getCheckoutUrl( $EnvironmentRequest = RESURS_ENVIRONMENTS::ENVIRONMENT_TEST, $getCurrentIfSet = true ) {
		// If current_environment is set, override incoming variable
		if ( $getCurrentIfSet && $this->current_environment_updated ) {
			if ( $this->current_environment == RESURS_ENVIRONMENTS::ENVIRONMENT_PRODUCTION ) {
				if ($this->getPos()) {
					return $this->env_omni_pos_prod;
				}
				return $this->env_omni_prod;
			} else {
				if ($this->getPos()) {
					return $this->env_omni_pos_test;
				}
				return $this->env_omni_test;
			}
		}
		if ( $EnvironmentRequest == RESURS_ENVIRONMENTS::ENVIRONMENT_PRODUCTION ) {
			return $this->env_omni_prod;
		} else {
			return $this->env_omni_test;
		}
	}

	/**
	 * Update the Checkout iframe
	 *
	 * Backwards compatible so the formatting of the orderLines will be accepted in folllowing formats:
	 *  - $orderLines is accepted as a json string
	 *  - $orderLines can be sent in as array('orderLines' => $yourOrderlines)
	 *  - $orderLines can be sent in as array($yourOrderlines)
	 *
	 * @param string $paymentId
	 * @param array $orderLines
	 *
	 * @return bool
	 * @throws \Exception
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	public function updateCheckoutOrderLines( $paymentId = '', $orderLines = array() ) {
		if ( empty( $paymentId ) ) {
			throw new \Exception( "Payment id not set" );
		}
		if ( ! $this->hasServicesInitialization ) {
			$this->InitializeServices();
		}
		if ( empty( $this->defaultUnitMeasure ) ) {
			$this->setDefaultUnitMeasure();
		}
		if ( is_string( $orderLines ) ) {
			// If this is a string, it might be an json string from older systems. We need, in that case make sure it is returned as an array.
			// This will destroy the content going to the PUT call, if it is not the case. However, sending a string to this point has no effect in the flow whatsoever.
			$orderLines = $this->objectsIntoArray( json_decode( $orderLines ) );
		}
		// Make sure that the payment spec are clean up and set correctly to a non-recursive array
		if ( isset( $orderLines['orderLines'] ) ) {
			$outputOrderLines = $orderLines['orderLines'];
		} else if ( isset( $orderLines['specLines'] ) ) {
			$outputOrderLines = $orderLines['specLines'];
		} else {
			$outputOrderLines = $orderLines;
		}
		$sanitizedOutputOrderLines    = $this->sanitizePaymentSpec( $outputOrderLines, RESURS_FLOW_TYPES::FLOW_RESURS_CHECKOUT );
		$updateOrderLinesResponse     = $this->CURL->doPut( $this->getCheckoutUrl() . "/checkout/payments/" . $paymentId, array( 'orderLines' => $sanitizedOutputOrderLines ), CURL_POST_AS::POST_AS_JSON );
		$updateOrderLinesResponseCode = $this->CURL->getResponseCode( $updateOrderLinesResponse );
		if ( $updateOrderLinesResponseCode >= 400 ) {
			throw new \Exception( "Could not update order lines", $updateOrderLinesResponseCode );
		}
		if ( $updateOrderLinesResponseCode >= 200 && $updateOrderLinesResponseCode < 300 ) {
			return true;
		}

		return false;
	}


	////// HOSTED FLOW

	/**
	 * @return string
	 */
	public function getHostedUrl() {
		if ( $this->current_environment == RESURS_ENVIRONMENTS::ENVIRONMENT_TEST ) {
			return $this->env_hosted_test;
		} else {
			return $this->env_hosted_prod;
		}
	}

	////// MASTER SHOPFLOWS - THE OTHER ONES

	/**
	 * Set up payload with simplified card data.
	 *
	 * Conditions is:
	 *   - Cards: Use card number only
	 *   - New cards: No data needed, but could be set as (null, cardAmount). If no data set the applied amount will be the totalAmount.
	 *
	 * @param null $cardNumber
	 * @param null $cardAmount
	 *
	 * @since 1.0.2
	 * @since 1.1.2
	 */
	public function setCardData( $cardNumber = null, $cardAmount = null ) {
		if ( ! isset( $this->Payload['card'] ) ) {
			$this->Payload['card'] = array();
		}
		if ( ! isset( $this->Payload['card']['cardNumber'] ) ) {
			$this->Payload['card']['cardNumber'] = trim( $cardNumber );
		}
		if ( $cardAmount > 0 ) {
			$this->Payload['card']['amount'] = $cardAmount;
		}
	}

	/**
	 * Payment card validity check for deprecation layer
	 *
	 * @param string $specificType
	 *
	 * @since 1.0.2
	 * @since 1.1.2
	 */
	private function validateCardData( $specificType = "" ) {
		// Keeps compatibility with card data sets
		if ( isset( $this->Payload['orderData']['totalAmount'] ) && $this->getPreferredPaymentFlowService() == RESURS_FLOW_TYPES::FLOW_SIMPLIFIED_FLOW ) {
			$cardInfo = isset( $this->Payload['card'] ) ? $this->Payload['card'] : array();
			if ( ( isset( $cardInfo['cardNumber'] ) && empty( $cardInfo['cardNumber'] ) ) || ! isset( $cardInfo['cardNumber'] ) ) {
				if ( ( isset( $cardInfo['amount'] ) && empty( $cardInfo['amount'] ) ) || ! isset( $cardInfo['amount'] ) ) {
					// Adding the exact total amount as we do not rule of exchange rates. For example, adding 500 extra to the total
					// amount in sweden will work, but will on the other hand be devastating for countries using euro.
					$this->Payload['card']['amount'] = $this->Payload['orderData']['totalAmount'];
				}
			}
		}

		if ( isset( $this->Payload['card']['cardNumber'] ) ) {
			if ( empty( $this->Payload['card']['cardNumber'] ) ) {
				unset( $this->Payload['card']['cardNumber'] );
			}
		}

		if ( isset( $this->Payload['customer'] ) ) {
			// CARD + (NEWCARD, REVOLVING_CREDIT)
			$mandatoryExtendedCustomerFields = array( 'governmentId', 'address', 'phone', 'email', 'type' );
			if ( $specificType == "CARD" ) {
				$mandatoryExtendedCustomerFields = array( 'governmentId' );
			} else if ( ( $specificType == "REVOLVING_CREDIT" || $specificType == "NEWCARD" ) ) {
				$mandatoryExtendedCustomerFields = array( 'governmentId', 'phone', 'email' );
			}
			if ( count( $mandatoryExtendedCustomerFields ) ) {
				foreach ( $this->Payload['customer'] as $customerKey => $customerValue ) {
					// If the key belongs to extendedCustomer, is mandatory for the specificType and is empty,
					// this means we can not deliver this data as a null value to ecommerce. Therefore, we have to remove it.
					// The control being made here will skip the address object as we will only check the non-recursive data strings.
					if ( is_string( $customerValue ) ) {
						$trimmedCustomerValue = trim( $customerValue );
					} else {
						// Do not touch if this is not an array (and consider that something was sent into this part, that did not belong here?)
						$trimmedCustomerValue = $customerValue;
					}
					if ( ! is_array( $customerValue ) && ! in_array( $customerKey, $mandatoryExtendedCustomerFields ) && empty( $trimmedCustomerValue ) ) {
						unset( $this->Payload['customer'][ $customerKey ] );
					}
				}
			}
		}
	}

	/////////// AFTER SHOP ROUTINES

	/**
	 * Find out if a payment is creditable
	 *
	 * @param array $paymentArrayOrPaymentId The current payment if already requested. If this variable is sent as a string, the function will first make a getPayment automatically.
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function canCredit( $paymentArrayOrPaymentId = array() ) {
		$Status = (array) $this->getPaymentContent( $paymentArrayOrPaymentId, "status" );
		// IS_CREDITED - CREDITABLE
		if ( in_array( "CREDITABLE", $Status ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Find out if a payment is debitable
	 *
	 * @param array $paymentArrayOrPaymentId The current payment if already requested. If this variable is sent as a string, the function will first make a getPayment automatically.
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function canDebit( $paymentArrayOrPaymentId = array() ) {
		$Status = (array) $this->getPaymentContent( $paymentArrayOrPaymentId, "status" );
		// IS_DEBITED - DEBITABLE
		if ( in_array( "DEBITABLE", $Status ) ) {
			return true;
		}

		return false;
	}

	/**
	 * A payment is annullable if the payment is debitable
	 *
	 * @param array $paymentArrayOrPaymentId
	 *
	 * @return bool
	 * @throws \Exception
	 * @since 1.0.2
	 * @since 1.1.2
	 */
	public function canAnnul( $paymentArrayOrPaymentId = array() ) {
		return $this->canDebit( $paymentArrayOrPaymentId );
	}

	/**
	 * Return true if order is debited
	 *
	 * @param array $paymentArrayOrPaymentId
	 *
	 * @return bool
	 * @throws \Exception
	 * @since 1.0.13
	 * @since 1.1.13
	 * @since 1.2.0
	 */
	public function getIsDebited( $paymentArrayOrPaymentId = array() ) {
		$Status = (array) $this->getPaymentContent( $paymentArrayOrPaymentId, "status" );
		if ( in_array( "IS_DEBITED", $Status ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Return true if order is credited
	 *
	 * @param array $paymentArrayOrPaymentId
	 *
	 * @return bool
	 * @throws \Exception
	 * @since 1.0.13
	 * @since 1.1.13
	 * @since 1.2.0
	 */
	public function getIsCredited( $paymentArrayOrPaymentId = array() ) {
		$Status = (array) $this->getPaymentContent( $paymentArrayOrPaymentId, "status" );
		if ( in_array( "IS_CREDITED", $Status ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Return true if order is annulled
	 *
	 * @param array $paymentArrayOrPaymentId
	 *
	 * @return bool
	 * @throws \Exception
	 * @since 1.0.13
	 * @since 1.1.13
	 * @since 1.2.0
	 */
	public function getIsAnnulled( $paymentArrayOrPaymentId = array() ) {
		$Status = (array) $this->getPaymentContent( $paymentArrayOrPaymentId, "status" );
		if ( in_array( "IS_ANNULLED", $Status ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get each payment diff content count (mostly used for tests)
	 *
	 * @param $paymentIdOrPaymentObject
	 *
	 * @return array
	 * @throws \Exception
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	public function getPaymentSpecCount( $paymentIdOrPaymentObject ) {
		$countObject         = $this->getPaymentSpecByStatus( $paymentIdOrPaymentObject );
		$returnedCountObject = array();
		foreach ( $countObject as $status => $theArray ) {
			$returnedCountObject[ $status ] = is_array($theArray) ? count( $theArray ) : 0;
		}

		return $returnedCountObject;
	}

	/**
	 * Returns a complete payment spec grouped by status. This function does not merge articles, even if there are multiple rows with the same article number. This normally indicates order modifications, so the are returned raw as is.
	 *
	 * @param $paymentIdOrPaymentObject
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function getPaymentSpecByStatus( $paymentIdOrPaymentObject ) {
		$usePayment = $paymentIdOrPaymentObject;
		// Current specs available: AUTHORIZE, DEBIT, CREDIT, ANNUL
		$orderLinesByStatus = array(
			'AUTHORIZE' => array(),
			'DEBIT'     => array(),
			'CREDIT'    => array(),
			'ANNUL'     => array(),
		);
		if ( is_string( $paymentIdOrPaymentObject ) ) {
			$usePayment = $this->getPayment( $paymentIdOrPaymentObject );
		}
		if ( is_object( $usePayment ) && isset( $usePayment->id ) && isset( $usePayment->paymentDiffs ) ) {
			$paymentDiff = $usePayment->paymentDiffs;
			// If the paymentdiff is an array, we'll know that more than one thing has happened to the payment, and it's probably only an authorization
			if ( is_array( $paymentDiff ) ) {
				foreach ( $paymentDiff as $paymentDiffObject ) {
					// Initially, let's make sure there is a key for the paymentdiff.
					if ( ! isset( $orderLinesByStatus[ $paymentDiffObject->type ] ) ) {
						$orderLinesByStatus[ $paymentDiffObject->type ] = array();
					}
					// Second, make sure that the paymentdiffs are collected as one array per specType (AUTHORIZE,DEBIT,CREDIT,ANNULL)
					if ( is_array( $paymentDiffObject->paymentSpec->specLines ) ) {
						// Note: array_merge won't work if the initial array is empty. Instead we'll append it to the above array.
						// Also note that appending with += may fail when indexes matches each other on both sides - in that case
						// not all objects will be attached properly to this array.
						if ( ! $this->isFlag( 'MERGEBYSTATUS_DEPRECATED_METHOD' ) ) {
							foreach ( $paymentDiffObject->paymentSpec->specLines as $arrayObject ) {
								$orderLinesByStatus[ $paymentDiffObject->type ][] = $arrayObject;
							}
						} else {
							$orderLinesByStatus[ $paymentDiffObject->type ] += $paymentDiffObject->paymentSpec->specLines;
						}
					} else if ( is_object( $paymentDiffObject ) ) {
						$orderLinesByStatus[ $paymentDiffObject->type ][] = $paymentDiffObject->paymentSpec->specLines;
					}
				}
			} else {
				// If the paymentdiff is an object we'd know that only one thing has occured in the order.
				// Keep in mind that, if an order has been debited, there should be rows both for the debiting and the authorization (which shows each orderline
				// separated on which steps it went through).
				if ( ! isset( $orderLinesByStatus[ $paymentDiff->type ] ) ) {
					$orderLinesByStatus[ $paymentDiff->type ] = array();
				}
				if ( is_array( $paymentDiff->paymentSpec->specLines ) ) {
					// Note: array_merge won't work if the initial array is empty. Instead we'll append it to the above array.
					$orderLinesByStatus[ $paymentDiff->type ] += $paymentDiff->paymentSpec->specLines;
				} else if ( is_object( $paymentDiff->paymentSpec->specLines ) ) {
					$orderLinesByStatus[ $paymentDiff->type ][] = $paymentDiff->paymentSpec->specLines;
				}
			}
		}

		return $orderLinesByStatus;
	}

	/**
	 * Sanitize a paymentspec from a payment id or a prepared getPayment object and return filtered depending on the requested aftershop type
	 *
	 * @param string $paymentIdOrPaymentObjectData
	 * @param int $renderType RESURS_AFTERSHOP_RENDER_TYPES as unique type or bitmask
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function sanitizeAfterShopSpec( $paymentIdOrPaymentObjectData = '', $renderType = RESURS_AFTERSHOP_RENDER_TYPES::AFTERSHOP_NO_CHOICE ) {
		$returnSpecObject = null;

		$this->BIT->setBitStructure(
			array(
				'FINALIZE'  => RESURS_AFTERSHOP_RENDER_TYPES::AFTERSHOP_FINALIZE,
				'CREDIT'    => RESURS_AFTERSHOP_RENDER_TYPES::AFTERSHOP_CREDIT,
				'ANNUL'     => RESURS_AFTERSHOP_RENDER_TYPES::AFTERSHOP_ANNUL,
				'AUTHORIZE' => RESURS_AFTERSHOP_RENDER_TYPES::AFTERSHOP_AUTHORIZE,
			)
		);

		// Get payment spec bulked
		$paymentIdOrPaymentObject = $this->getPaymentSpecByStatus( $paymentIdOrPaymentObjectData );

		if ( $this->BIT->isBit( RESURS_AFTERSHOP_RENDER_TYPES::AFTERSHOP_FINALIZE, $renderType ) ) {
			$returnSpecObject = $this->removeFromArray( $paymentIdOrPaymentObject['AUTHORIZE'], array_merge( $paymentIdOrPaymentObject['DEBIT'], $paymentIdOrPaymentObject['ANNUL'], $paymentIdOrPaymentObject['CREDIT'] ) );
		} else if ( $this->BIT->isBit( RESURS_AFTERSHOP_RENDER_TYPES::AFTERSHOP_CREDIT, $renderType ) ) {
			$returnSpecObject = $this->removeFromArray( $paymentIdOrPaymentObject['DEBIT'], array_merge( $paymentIdOrPaymentObject['ANNUL'], $paymentIdOrPaymentObject['CREDIT'] ) );
		} else if ( $this->BIT->isBit( RESURS_AFTERSHOP_RENDER_TYPES::AFTERSHOP_ANNUL, $renderType ) ) {
			$returnSpecObject = $this->removeFromArray( $paymentIdOrPaymentObject['AUTHORIZE'], array_merge( $paymentIdOrPaymentObject['DEBIT'], $paymentIdOrPaymentObject['ANNUL'], $paymentIdOrPaymentObject['CREDIT'] ) );
		} else {
			// If no type is chosen, return all rows
			$returnSpecObject = $this->removeFromArray( $paymentIdOrPaymentObject, array() );
		}

		return $returnSpecObject;
	}

	/**
	 * Sets a preferred transaction id
	 *
	 * @param $preferredTransactionId
	 *
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	public function setAfterShopPreferredTransactionId( $preferredTransactionId ) {
		if ( ! empty( $preferredTransactionId ) ) {
			$this->afterShopPreferredTransactionId = $preferredTransactionId;
		}
	}

	/**
	 * Returns the preferred transaction id if any
	 *
	 * @return string
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	public function getAfterShopPreferredTransactionId() {
		return $this->afterShopPreferredTransactionId;
	}

	/**
	 * Set a order id for the aftershop flow, which will be shown in the invoice
	 *
	 * @param $orderId
	 *
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	public function setAfterShopOrderId( $orderId ) {
		if ( ! empty( $orderId ) ) {
			$this->afterShopOrderId = $orderId;
		}
	}

	/**
	 * Return the set order id for the aftershop flow (invoice)
	 *
	 * @return string
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	public function getAfterShopOrderId() {
		return $this->afterShopOrderId;
	}

	/**
	 * Pre-set a invoice id for aftershop
	 *
	 * @param $invoiceId
	 *
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	public function setAfterShopInvoiceId( $invoiceId ) {
		if ( ! empty( $invoiceId ) ) {
			$this->afterShopInvoiceId = $invoiceId;
		}
	}

	/**
	 * Return pre-set invoice id for aftershop if any
	 *
	 * @return string
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	public function getAfterShopInvoiceId() {
		return $this->afterShopInvoiceId;
	}

	/**
	 * Set invoice external reference
	 *
	 * @param $invoiceExtRef
	 *
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	public function setAfterShopInvoiceExtRef( $invoiceExtRef ) {
		if ( ! empty( $invoiceExtRef ) ) {
			$this->afterShopInvoiceExtRef = $invoiceExtRef;
		}
	}

	/**
	 * Return the invoice external reference
	 *
	 * @return string
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	public function getAfterShopInvoiceExtRef() {
		return $this->afterShopInvoiceExtRef;
	}


	/**
	 * Split function for aftershop: This was included in each of the deprecated function instead of running from a central place
	 *
	 * @param $paymentId
	 *
	 * @return bool
	 */
	private function aftershopPrepareMetaData( $paymentId ) {
		try {
			if ( empty( $this->customerId ) ) {
				$this->customerId = "-";
			}
			$this->addMetaData( $paymentId, "CustomerId", $this->customerId );
		} catch ( \Exception $metaResponseException ) {

		}

		return true;
	}

	/**
	 * Aftershop specific setting to add a customer id to the invoice (can be unset by sending empty value)
	 *
	 * @param $customerId
	 */
	public function setCustomerId( $customerId = "" ) {
		$this->customerId = $customerId;
	}

	/**
	 * Returns the current customer id (for aftershop)
	 *
	 * @return string
	 */
	public function getCustomerId() {
		return $this->customerId;
	}

	/**
	 * Create an afterShopFlow object to use with the afterShop flow
	 *
	 * @param string $paymentId
	 * @param array $customPayloadItemList
	 * @param int $payloadType
	 *
	 * @return array
	 * @throws \Exception
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	private function getAfterShopObjectByPayload( $paymentId = "", $customPayloadItemList = array(), $payloadType = RESURS_AFTERSHOP_RENDER_TYPES::NONE ) {

		$finalAfterShopSpec = array(
			'paymentId' => $paymentId
		);
		if ( ! is_array( $customPayloadItemList ) ) {
			// Make sure this is correct
			$customPayloadItemList = array();
		}

		$storedPayment       = $this->getPayment( $paymentId );
		$paymentMethod       = $storedPayment->paymentMethodId;
		$paymentMethodData   = $this->getPaymentMethodSpecific( $paymentMethod );
		$paymentSpecificType = strtoupper( isset( $paymentMethodData->specificType ) ? $paymentMethodData->specificType : null );
		if ( $paymentSpecificType == "INVOICE" ) {
			$finalAfterShopSpec['orderDate']   = date( 'Y-m-d', time() );
			$finalAfterShopSpec['invoiceDate'] = date( 'Y-m-d', time() );
			if ( empty( $this->afterShopInvoiceId ) ) {
				$finalAfterShopSpec['invoiceId'] = $this->getNextInvoiceNumber();
			}
			$extRef = $this->getAfterShopInvoiceExtRef();
			if ( ! empty( $extRef ) ) {
				$this->addMetaData( $paymentId, 'invoiceExtRef', $extRef );
			}
		}

		// Rendered order spec, use when customPayloadItemList is not set, to handle full orders
		$actualEcommerceOrderSpec = $this->sanitizeAfterShopSpec( $storedPayment, $payloadType );

		$finalAfterShopSpec['createdBy'] = $this->getCreatedBy();
		$this->renderPaymentSpec( RESURS_FLOW_TYPES::FLOW_SIMPLIFIED_FLOW );

		try {
			// Try to fetch internal order data.
			$orderDataArray = $this->getOrderData();
		} catch ( \Exception $getOrderDataException ) {
			// If there is no payload, make sure we'll render this from the current payment
			if ( $getOrderDataException->getCode() == \RESURS_EXCEPTIONS::BOOKPAYMENT_NO_BOOKDATA && ! count( $customPayloadItemList ) ) {
				//array_merge($this->SpecLines, $actualEcommerceOrderSpec);
				$this->SpecLines += $this->objectsIntoArray( $actualEcommerceOrderSpec ); // Convert objects
			}
		}

		if ( count( $customPayloadItemList ) ) {
			// If there is a customized specrowArray injected, no appending should occur.
			//$this->SpecLines += $this->objectsIntoArray($customPayloadItemList);
			$this->SpecLines = $this->objectsIntoArray( $customPayloadItemList );
		}
		$this->renderPaymentSpec( RESURS_FLOW_TYPES::FLOW_SIMPLIFIED_FLOW );
		$orderDataArray = $this->getOrderData();

		if ( isset( $orderDataArray['specLines'] ) ) {
			$orderDataArray['partPaymentSpec'] = $orderDataArray;
		}

		$finalAfterShopSpec += $orderDataArray;

		return $finalAfterShopSpec;
	}

	/**
	 * Identical to paymentFinalize but used for testing errors
	 */
	public function paymentFinalizeTest() {
		if ( defined( 'TEST_OVERRIDE_AFTERSHOP_PAYLOAD' ) && $this->current_environment == RESURS_ENVIRONMENTS::ENVIRONMENT_TEST ) {
			$this->postService( "finalizePayment", unserialize( TEST_OVERRIDE_AFTERSHOP_PAYLOAD ) );
		}
	}

	/**
	 * Clean up payload after usage
	 * @since 1.1.22
	 */
	private function resetPayload() {
		$this->PayloadHistory[] = array(
			'Payload'   => $this->Payload,
			'SpecLines' => $this->SpecLines
		);
		$this->SpecLines        = array();
		$this->Payload          = array();
	}

	/**
	 * Aftershop Payment Finalization (DEBIT)
	 *
	 * Make sure that you are running this with try-catches in cases where failures may occur.
	 *
	 * @param $paymentId
	 * @param array $customPayloadItemList
	 * @param bool $runOnce Only run this once, throw second time
	 *
	 * @return bool
	 * @throws \Exception
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	public function paymentFinalize( $paymentId = "", $customPayloadItemList = array(), $runOnce = false ) {
		$afterShopObject = $this->getAfterShopObjectByPayload( $paymentId, $customPayloadItemList, RESURS_AFTERSHOP_RENDER_TYPES::AFTERSHOP_FINALIZE );
		$this->aftershopPrepareMetaData( $paymentId );
		try {
			$afterShopResponseCode = $this->postService( "finalizePayment", $afterShopObject, true );
			if ( $afterShopResponseCode >= 200 && $afterShopResponseCode < 300 ) {
				$this->resetPayload();

				return true;
			}
		} catch ( \Exception $finalizationException ) {
			if ( $finalizationException->getCode() == 29 && ! $this->isFlag( 'SKIP_AFTERSHOP_INVOICE_CONTROL' ) && ! $runOnce ) {
				$this->getNextInvoiceNumberByDebits( 5 );

				return $this->paymentFinalize( $paymentId, $customPayloadItemList, true );
			}
			throw new \Exception( $finalizationException->getMessage(), $finalizationException->getCode(), $finalizationException );
		}

		return false;
	}

	/**
	 * Shadow function for paymentFinalize
	 *
	 * @param string $paymentId
	 * @param array $customPayloadItemList
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function finalizePayment( $paymentId = "", $customPayloadItemList = array() ) {
		return $this->paymentFinalize( $paymentId, $customPayloadItemList );
	}

	/**
	 * Aftershop Payment Annulling (ANNUL)
	 *
	 * Make sure that you are running this with try-catches in cases where failures may occur.
	 *
	 * @param $paymentId
	 * @param array $customPayloadItemList
	 * @param bool $runOnce Only run this once, throw second time
	 *
	 * @return bool
	 * @throws \Exception
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	public function paymentAnnul( $paymentId = "", $customPayloadItemList = array(), $runOnce = false ) {
		$afterShopObject = $this->getAfterShopObjectByPayload( $paymentId, $customPayloadItemList, RESURS_AFTERSHOP_RENDER_TYPES::AFTERSHOP_ANNUL );
		$this->aftershopPrepareMetaData( $paymentId );
		try {
			$afterShopResponseCode = $this->postService( "annulPayment", $afterShopObject, true );
			if ( $afterShopResponseCode >= 200 && $afterShopResponseCode < 300 ) {
				$this->resetPayload();

				return true;
			}
		} catch ( \Exception $annulException ) {
			if ( $annulException->getCode() == 29 && ! $this->isFlag( 'SKIP_AFTERSHOP_INVOICE_CONTROL' ) && ! $runOnce ) {
				$this->getNextInvoiceNumberByDebits( 5 );

				return $this->paymentFinalize( $paymentId, $customPayloadItemList, true );
			}
			throw new \Exception( $annulException->getMessage(), $annulException->getCode(), $annulException );
		}

		return false;
	}

	/**
	 * Shadow function for paymentAnnul
	 *
	 * @param string $paymentId
	 * @param array $customPayloadItemList
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function annulPayment( $paymentId = "", $customPayloadItemList = array() ) {
		return $this->paymentAnnul( $paymentId, $customPayloadItemList );
	}

	/**
	 * Aftershop Payment Crediting (CREDIT)
	 *
	 * Make sure that you are running this with try-catches in cases where failures may occur.
	 *
	 * @param $paymentId
	 * @param array $customPayloadItemList
	 * @param bool $runOnce Only run this once, throw second time
	 *
	 * @return bool
	 * @throws \Exception
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	public function paymentCredit( $paymentId = "", $customPayloadItemList = array(), $runOnce = false ) {
		$afterShopObject = $this->getAfterShopObjectByPayload( $paymentId, $customPayloadItemList, RESURS_AFTERSHOP_RENDER_TYPES::AFTERSHOP_CREDIT );
		$this->aftershopPrepareMetaData( $paymentId );
		try {
			$afterShopResponseCode = $this->postService( "creditPayment", $afterShopObject, true );
			if ( $afterShopResponseCode >= 200 && $afterShopResponseCode < 300 ) {
				$this->resetPayload();

				return true;
			}
		} catch ( \Exception $creditException ) {
			if ( $creditException->getCode() == 29 && ! $this->isFlag( 'SKIP_AFTERSHOP_INVOICE_CONTROL' ) && ! $runOnce ) {
				$this->getNextInvoiceNumberByDebits( 5 );

				return $this->paymentFinalize( $paymentId, $customPayloadItemList, true );
			}
			throw new \Exception( $creditException->getMessage(), $creditException->getCode(), $creditException );
		}

		return false;
	}

	/**
	 * Shadow function for paymentCredit
	 *
	 * @param string $paymentId
	 * @param array $customPayloadItemList
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function creditPayment( $paymentId = "", $customPayloadItemList = array() ) {
		return $this->paymentCredit( $paymentId, $customPayloadItemList );
	}

	/**
	 * Aftershop Payment Cancellation (ANNUL+CREDIT)
	 *
	 * This function cancels a full order depending on the order content. Payloads MAY be customized but on your own risk!
	 *
	 * @param $paymentId
	 * @param array $customPayloadItemList
	 *
	 * @return bool
	 * @throws \Exception
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	public function paymentCancel( $paymentId = "", $customPayloadItemList = array() ) {
		// Collect the payment
		$currentPayment = $this->getPayment( $paymentId );
		// Collect the payment sorted by status
		$currentPaymentSpec = $this->getPaymentSpecByStatus( $currentPayment );

		// Sanitized paymentspec based on what to CREDIT
		$creditObject = $this->sanitizeAfterShopSpec( $currentPayment, RESURS_AFTERSHOP_RENDER_TYPES::AFTERSHOP_CREDIT );
		// Sanitized paymentspec based on what to ANNUL
		$annulObject = $this->sanitizeAfterShopSpec( $currentPayment, RESURS_AFTERSHOP_RENDER_TYPES::AFTERSHOP_ANNUL );

		if ( is_array( $customPayloadItemList ) && count( $customPayloadItemList ) ) {
			$this->SpecLines = array_merge( $this->SpecLines, $customPayloadItemList );
		}
		$this->renderPaymentSpec( RESURS_FLOW_TYPES::FLOW_SIMPLIFIED_FLOW );

		$this->aftershopPrepareMetaData( $paymentId );
		try {
			// Render and check if this is customized
			$currentOrderLines = $this->getOrderLines();

			if ( is_array( $currentOrderLines ) && count( $currentOrderLines ) ) {
				// If it is customized, we need to render the cancellation differently to specify what's what.

				// Validation object - Contains everything that CAN be credited
				$validatedCreditObject = $this->removeFromArray( $currentPaymentSpec['DEBIT'], array_merge( $currentPaymentSpec['ANNUL'], $currentPaymentSpec['CREDIT'] ) );
				// Validation object - Contains everything that CAN be annulled
				$validatedAnnulmentObject = $this->removeFromArray( $currentPaymentSpec['AUTHORIZE'], array_merge( $currentPaymentSpec['DEBIT'], $currentPaymentSpec['ANNUL'], $currentPaymentSpec['CREDIT'] ) );

				// Clean up selected rows from the credit element and keep those rows than still can be credited and matches the orderRow-request
				$newCreditObject = $this->objectsIntoArray( $this->removeFromArray( $validatedCreditObject, $currentOrderLines, true ) );

				// Clean up selected rows from the credit element and keep those rows than still can be annulled and matches the orderRow-request
				$newAnnulObject = $this->objectsIntoArray( $this->removeFromArray( $validatedAnnulmentObject, $currentOrderLines, true ) );

				if ( is_array( $newCreditObject ) && count( $newCreditObject ) ) {
					$this->paymentCredit( $paymentId, $newCreditObject );
				}
				if ( is_array( $newAnnulObject ) && count( $newAnnulObject ) ) {
					$this->paymentAnnul( $paymentId, $newAnnulObject );
				}
			} else {
				if ( is_array( $creditObject ) && count( $creditObject ) ) {
					$this->paymentCredit( $paymentId, $creditObject );
				}
				if ( is_array( $annulObject ) && count( $annulObject ) ) {
					$this->paymentAnnul( $paymentId, $annulObject );
				}
			}
		} catch ( \Exception $cancelException ) {
			return false;
		}
		$this->resetPayload();

		return true;
	}

	/**
	 * Shadow function for paymentCancel
	 *
	 * @param string $paymentId
	 * @param array $customPayloadItemList
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function cancelPayment( $paymentId = "", $customPayloadItemList = array() ) {
		return $this->paymentCancel( $paymentId, $customPayloadItemList );
	}

	/**
	 * Add an additional orderline to a payment
	 *
	 * With setLoggedInUser() you can also set up a user identification for the createdBy-parameter sent with the additional debig. If not set, EComPHP will use the merchant credentials.
	 *
	 * @param string $paymentId
	 *
	 * @return bool
	 * @throws \Exception
	 * @since 1.0.3
	 * @since 1.1.3
	 */
	public function setAdditionalDebitOfPayment( $paymentId = "" ) {
		$createdBy = $this->username;
		if ( ! empty( $this->loggedInuser ) ) {
			$createdBy = $this->loggedInuser;
		}
		$this->renderPaymentSpec( RESURS_FLOW_TYPES::FLOW_SIMPLIFIED_FLOW );
		$additionalDataArray = array(
			'paymentId'   => $paymentId,
			'paymentSpec' => $this->Payload['orderData'],
			'createdBy'   => $createdBy
		);
		$Result              = $this->postService( "additionalDebitOfPayment", $additionalDataArray, true );
		if ( $Result >= 200 && $Result <= 250 ) {
			// Reset orderData for each addition
			$this->resetPayload();

			return true;
		} else {
			return false;
		}
	}

	/**
	 * Generic orderstatus content information that checks payment statuses instead of callback input and decides what happened to the payment
	 *
	 * @param array $paymentData
	 *
	 * @return int
	 * @throws \Exception
	 * @since 1.0.26
	 * @since 1.1.26
	 * @since 1.2.0
	 */
	private function getOrderStatusByPaymentStatuses( $paymentData = array() ) {
		$resursTotalAmount = $paymentData->totalAmount;
		if ( $this->canDebit( $paymentData ) ) {
			return RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_PROCESSING;
		}
		if ( ! $this->canDebit( $paymentData ) && $this->getIsDebited( $paymentData ) && $resursTotalAmount > 0 ) {
			return RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_COMPLETED;
		}
		if ( $this->getIsAnnulled( $paymentData ) && ! $this->getIsCredited( $paymentData ) && $resursTotalAmount == 0 ) {
			return RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_CANCELLED;
		}
		if ( $this->getIsCredited( $paymentData ) && $resursTotalAmount == 0 ) {
			return RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_REFUND;
		}

		// Return generic
		return RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_STATUS_COULD_NOT_BE_SET;
	}

	/**
	 * Return "best practice"-order statuses for a payment.
	 *
	 * @param string $paymentIdOrPaymentObject
	 * @param int $byCallbackEvent If this variable is set, controls are also being made, compared to what happened on a callback event
	 * @param array|string $callbackEventDataArrayOrString On for example AUTOMATIC_FRAUD_CONTROL, a result based on THAWED or FROZEN are received, which you should add here
	 *
	 * @return int
	 * @throws \Exception
	 * @since 1.0.26
	 * @since 1.1.26
	 * @since 1.2.0
	 */
	public function getOrderStatusByPayment( $paymentIdOrPaymentObject = '', $byCallbackEvent = RESURS_CALLBACK_TYPES::CALLBACK_TYPE_NOT_SET, $callbackEventDataArrayOrString = array() ) {

		if ( is_string( $paymentIdOrPaymentObject ) ) {
			$paymentData = $this->getPayment( $paymentIdOrPaymentObject );
		} else if ( is_object( $paymentIdOrPaymentObject ) ) {
			$paymentData = $paymentIdOrPaymentObject;
		} else {
			throw new \Exception( "Payment data object or id is not valid", 500 );
		}

		// If nothing else suits us, this will be used
		$preAnalyzePayment = $this->getOrderStatusByPaymentStatuses( $paymentData );

		// Analyzed during a callback event, which have higher priority than a regular control
		switch ( $byCallbackEvent ) {
			case RESURS_CALLBACK_TYPES::CALLBACK_TYPE_NOT_SET:
				break;
			case RESURS_CALLBACK_TYPES::CALLBACK_TYPE_ANNULMENT:
				return RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_ANNULLED;
			case RESURS_CALLBACK_TYPES::CALLBACK_TYPE_AUTOMATIC_FRAUD_CONTROL:
				if ( is_string( $callbackEventDataArrayOrString ) ) {
					// Thawed means not frozen
					if ( $callbackEventDataArrayOrString == "THAWED" ) {
						return RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_PROCESSING;
					}
				}

				return RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_PENDING;
			case RESURS_CALLBACK_TYPES::CALLBACK_TYPE_BOOKED:
				// Frozen set, but not true OR frozen not set at all - Go processing
				if ( isset( $paymentData->frozen ) && $paymentData->frozen ) {
					return RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_PENDING;
				}
				// Running in synchronous mode (finalizeIfBooked) might disturb the normal way to handle the booked callback, so we'll continue checking
				// the order by statuses if this order is not frozen
				return $this->getOrderStatusByPaymentStatuses( $paymentData );
				break;
			case RESURS_CALLBACK_TYPES::CALLBACK_TYPE_FINALIZATION:
				return RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_COMPLETED;
			case RESURS_CALLBACK_TYPES::CALLBACK_TYPE_UNFREEZE:
				return RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_PROCESSING;
			case RESURS_CALLBACK_TYPES::CALLBACK_TYPE_UPDATE:
				return $this->getOrderStatusByPaymentStatuses( $paymentData );
			default:    // RESURS_CALLBACK_TYPES::CALLBACK_TYPE_NOT_SET
				break;
		}

		// case RESURS_CALLBACK_TYPES::CALLBACK_TYPE_UPDATE
		$returnThisAfterAll = $preAnalyzePayment;

		return $returnThisAfterAll;
	}

	/**
	 * @param $returnCode
	 *
	 * @return string
	 * @since 1.0.26
	 * @since 1.1.26
	 * @since 1.2.0
	 */
	public function getOrderStatusStringByReturnCode( $returnCode = RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_STATUS_COULD_NOT_BE_SET ) {
		switch ( $returnCode ) {
			case RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_PENDING:
				return "pending";
			case RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_PROCESSING;
				return "processing";
			case RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_COMPLETED;
				return "completed";
			case RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_ANNULLED;
				return "annul";
			case RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_CREDITED;
				return "credit";
			default:
				return "";
		}
	}

	/**
	 * Callback digest validator
	 *
	 * @param string $callbackPaymentId Requested payment id to check
	 * @param string $saltKey Current salt key used for the digest
	 * @param string $inboundDigest Digest reveived from Resurs Bank
	 * @param null $callbackResult Optional for AUTOMATIC_FRAUD_CONTROL
	 *
	 * @return bool
	 * @since 1.0.33
	 * @since 1.1.33
	 * @since 1.2.6
	 * @since 1.3.6
	 */
	public function getValidatedCallbackDigest( $callbackPaymentId = '', $saltKey = '', $inboundDigest = '', $callbackResult = null ) {
		$digestCompiled    = $callbackPaymentId . ( ! is_null( $callbackResult ) ? $callbackResult : null ) . $saltKey;
		$digestMd5         = strtoupper( md5( $digestCompiled ) );
		$digestSha         = strtoupper( sha1( $digestCompiled ) );
		$realInboundDigest = strtoupper( $inboundDigest );
		if ( $realInboundDigest == $digestMd5 || $realInboundDigest == $digestSha ) {
			return true;
		}

		return false;
	}
}
