<?php
/**
 * Resurs Bank Passthrough API - A pretty silent ShopFlowSimplifier for Resurs Bank.
 * Compatible with simplifiedFlow, hostedFlow and Resurs Checkout.
 * Requirements: WSDL stubs from WSDL2PHPGenerator (deprecated edition)
 * Important notes: As the WSDL files are generated, it is highly important to run tests before release.
 * Differences between 1.0 and 1.1 is primarily the namespacing, to be compatible with Magento Marketplace.
 *
 * Last update: See the lastUpdate variable
 * @package RBEcomPHP
 * @author Resurs Bank Ecommerce <ecommerce.support@resurs.se>
 * @branch 1.1
 * @version 1.1.26
 * @link https://test.resurs.com/docs/x/KYM0 Get started - PHP Section
 * @link https://test.resurs.com/docs/x/TYNM EComPHP Usage
 * @license Apache License
 */

namespace Resursbank\RBEcomPHP;

/**
 * Location of RBEcomPHP class files.
 */
if ( ! defined( 'RB_API_PATH' ) ) {
	define( 'RB_API_PATH', __DIR__ );
}
require_once(RB_API_PATH . '/thirdparty/network.php');
require_once(RB_API_PATH . '/thirdparty/crypto.php');
require_once(RB_API_PATH . '/rbapiloader/ResursTypeClasses.php');
require_once(RB_API_PATH . '/rbapiloader/ResursException.php');

if (file_exists(__DIR__ . "/../../vendor/autoload.php")) {
	require_once(__DIR__ . '/../../vendor/autoload.php');
}

use Resursbank\RBEcomPHP\CURL_POST_AS;
use Resursbank\RBEcomPHP\Tornevall_cURL;
use Resursbank\RBEcomPHP\TorneLIB_Network;
use Resursbank\RBEcomPHP\TorneLIB_Crypto;

/**
 * Class ResursBank Primary class for EComPHP
 * Works with dynamic data arrays. By default, the API-gateway will connect to Resurs Bank test environment, so to use production mode this must be configured at runtime.
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
	/**
	 * Last error received
	 * @var
	 * @deprecated 1.0.1
	 * @deprecated 1.0.1
	 */
	public $lastError;
	/**
	 * If set to true, EComPHP will throw on wsdl initialization (default is false, since snapshot 20160405, when Omni got implemented )
	 *
	 * @var bool
	 * @deprecated 1.0.1
	 * @deprecated 1.0.1
	 */
	public $throwOnInit = false;

	/// PHP Support
	/**
	 * User activation flag
	 * @var bool
	 * @deprecated Removed in 1.2
	 */
	private $allowObsoletePHP = false;

	///// Environment and API
	/** @var int Current targeted environment - default is always test, as we don't like that mistakes are going production */
	public $current_environment = self::ENVIRONMENT_TEST;
	/** @var null The username used with the webservices */
	private $username = null;
	/** @var null The password used with the webservices */
	private $password = null;

	/**
	 * If set to true, we're trying to convert received object data to standard object classes so they don't get incomplete on serialization.
	 *
	 * Only a few calls are dependent on this since most of the objects don't need this.
	 * Related to issue #63127
	 *
	 * @var bool
	 * @deprecated 1.0.1
	 * @deprecated 1.0.1
	 */
	public $convertObjects = false;
	/**
	 * Converting objects when a getMethod is used with ecommerce. This is only activated when convertObjects are active
	 *
	 * @var bool
	 * @deprecated 1.0.1
	 * @deprecated 1.0.1
	 */
	public $convertObjectsOnGet = true;


	/// Web Services (WSDL) available in case of needs to call services directly
	/**
	 * Auto configuration loader
	 *
	 * This API was set to primary use Resurs simplified shopflow. By default, this service is loaded automatically, together with the configurationservice which is used for setting up callbacks, etc. If you need other services, like aftershop, you should add it when your API is loading like this for example:<br>
	 * $API->Include[] = 'AfterShopFlowService';
	 *
	 * @var array Simple array with a list of which interfaces that should be automatically loaded on init. Default: ConfigurationService, SimplifiedShopFlowService
	 * @deprecated 1.0.1
	 * @deprecated 1.1.1
	 */
	public $Include = array( 'ConfigurationService', 'SimplifiedShopFlowService', 'AfterShopFlowService' );

	/**
	 * Which services we do support (picked up automatically from $ServiceRequestList)
	 *
	 * @var array
	 */
	private $wsdlServices = array();

	/** @var null Object configurationService */
	public $configurationService = null;
	/** @var null Object developerWebService */
	public $developerWebService = null;
	/** @var null Object simplifiedShopFlowService (this is what is primary used by this gateway) */
	public $simplifiedShopFlowService = null;
	public $afterShopFlowService = null;
	/** @var null Object shopFlowService (Deprecated) */
	public $shopFlowService = null;
	/** @var null What the service has returned (debug) */
	public $serviceReturn;

	///// Shop related
	/** @var bool */
	/**
	 * In tests being made with newer wsdl stubs, extended customer are surprisingly always best practice. It is however settable from here if we need to avoid this.
	 *
	 * @var bool
	 * @deprecated 1.0.2 No longer in use as everything uses extended customer
	 * @deprecated 1.1.2 No longer in use as everything uses extended customer
	 */
	public $alwaysUseExtendedCustomer = true;
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

	/** @var bool Enable the possibility to push over User-Agent from customer into header (debugging related) */
	private $customerUserAgentPush = false;


	///// Public SSL handlers
	/**
	 * Autodetecting of SSL capabilities section
	 *
	 * Default settings: Always disabled, to let the system handle this automatically.
	 * If there are problems reaching wsdl or connecting to https://test.resurs.com, set $testssl to true
	 *
	 * @deprecated 1.0.1 CURL library handles most of it
	 * @deprecated 1.1.1 CURL library handles most of it
	 */
	/**
	 * PHP 5.6.0 or above only: If defined, try to guess if there is valid certificate bundles when using for example https links (used with openssl).
	 * This function tries to detect whether sslVerify should be used or not. The default value of this setting is normally false, since there should be no problems in a correctly installed environment.
	 * @var bool
	 * @deprecated 1.0.1 CURL library handles most of it
	 * @deprecated 1.1.1 CURL library handles most of it
	 */
	public $testssl = false;
	/**
	 * Sets "verify SSL certificate in production required" if true (and if true, unverified SSL certificates will throw an error in production) - for auto-testing certificates only
	 *
	 * @var bool
	 * @deprecated 1.0.1 CURL library handles most of it
	 * @deprecated 1.1.1 CURL library handles most of it
	 */
	public $sslVerifyProduction = true;
	/**
	 * Do not test certificates on older PHP-version (< 5.6.0) if this is false
	 *
	 * @var bool
	 * @deprecated 1.0.1 CURL library handles most of it
	 * @deprecated 1.1.1 CURL library handles most of it
	 */
	public $testssldeprecated = false;
	/**
	 * Default paths to the certificates we are looking for
	 *
	 * @var array
	 * @deprecated 1.0.1 CURL library handles most of it
	 * @deprecated 1.1.1 CURL library handles most of it
	 */
	public $sslPemLocations = array(
		'/etc/ssl/certs/cacert.pem',
		'/etc/ssl/certs/ca-certificates.crt',
		'/usr/local/ssl/certs/cacert.pem'
	);


	////////// Private variables
	///// Client Specific Settings
	/** @var string The version of this gateway */
	private $version = "1.1.26";
	/** @var string Identify current version release (as long as we are located in v1.0.0beta this is necessary */
	private $lastUpdate = "20171031";
	/** @var string URL to git storage */
	private $gitUrl = "https://bitbucket.org/resursbankplugins/resurs-ecomphp";
	/** @var string This. */
	private $clientName = "EComPHP";
	/** @var string Replacing $clientName on usage of setClientNAme */
	private $realClientName = "EComPHP";

	///// Package related

	/**
	 * For backwards compatibility - If this extension are being used in an environment where namespaces are set up, this will be flagged true here
	 * @var bool
	 * @deprecated 1.0.8
	 * @deprecated 1.1.8
	 */
	private $hasNameSpace = false;
	/**
	 * For backwards compatibility - If this extension has the full wsdl package included, this will be flagged true here
	 * @var bool
	 * @deprecated 1.0.8
	 * @deprecated 1.1.8
	 */
	private $hasWsdl = false;
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
	/**
	 * The payload rendered out from CreatePayment()
	 * @var
	 * @since 1.0.1
	 * @since 1.1.1
	 */
	private $Payload;
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
	 * @var
	 * @since 1.0.2
	 * @since 1.1.2
	 */
	private $SpecLines = array();

	/**
	 * Simple web engine built on CURL, used for hosted flow
	 * @var null
	 * @deprecated 1.0.1
	 * @deprecated 1.1.1
	 */
	private $simpleWebEngine;

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
	/** @var string Country of choice */
	private $envCountry;
	/** @var string The current chosen URL for omnicheckout after initiation */
	private $env_omni_current = "";
	/** @var string The current chosen URL for hosted flow after initiation */
	private $env_hosted_current = "";
	/** @var string ShopUrl to use with the checkout */
	private $checkoutShopUrl = "";
	/** @var bool Set to true via setValidateCheckoutShopUrl() if you require validation of a proper shopUrl */
	private $validateCheckoutShopUrl = false;
	/**
	 * JSON string generated by toJsonByType (hosted flow)
	 * @var string
	 * @deprecated 1.0.2
	 * @deprecated 1.1.2
	 */
	private $jsonHosted = "";
	/**
	 * JSON string generated by toJsonByType (Resurs Checkout flow)
	 * @var string
	 * @deprecated 1.0.2
	 * @deprecated 1.1.2
	 */
	// A service that has been enforced, as the client already knows about the flow (It has rather been set through the setPreferredPaymentService()-API instead of in the regular payload)
	private $jsonOmni = "";
	/** @var int Default current environment. Always set to test (security reasons) */
	private $current_environment_updated = false;
	/** @var Store ID */
	private $storeId;

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
	 * @var array An index of where to find each service if no stubs are found
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
	private $externalApiAddress = "https://api.tornevall.net/2.0/";
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

	///// Private SSL handlers
	/**
	 * Marks if ssl controls indicates that we have a valid SSL certificate bundle available
	 * @var bool
	 * @deprecated 1.0.1 CURL library handles most of it
	 * @deprecated 1.1.1 CURL library handles most of it
	 */
	private $hasCertFile = false;
	/**
	 * Marks which file that is used as certificate bundle
	 * @var string
	 * @deprecated 1.0.1 CURL library handles most of it
	 * @deprecated 1.1.1 CURL library handles most of it
	 */
	private $useCertFile = "";
	/**
	 * Marks if the SSL certificates found has been discovered internally or by user
	 * @var bool
	 * @deprecated 1.0.1 CURL library handles most of it
	 * @deprecated 1.1.1 CURL library handles most of it
	 */
	private $hasDefaultCertFile = false;
	/**
	 * Marks if SSL certificate bundles-checker has been runned
	 * @var bool
	 * @deprecated 1.0.1 CURL library handles most of it
	 * @deprecated 1.1.1 CURL library handles most of it
	 */
	private $openSslGuessed = false;
	/**
	 * During tests this will be set to true if certificate directory is found
	 *
	 * @var bool
	 * @deprecated 1.0.1 CURL library handles most of it
	 * @deprecated 1.1.1 CURL library handles most of it
	 */
	private $hasCertDir = false;
	/**
	 * SSL Certificate verification setting. Setting this to false, we will ignore certificate errors
	 *
	 * @var bool
	 * @deprecated 1.0.1 CURL library handles most of it
	 * @deprecated 1.1.1 CURL library handles most of it
	 */
	private $sslVerify = true;

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

	/**
	 * @var string The current directory of RB Classes
	 * @deprecated Removed in 1.2
	 */
	private $classPath = "";

	/**
	 * @var array Files to look for in class directories, to find RB
	 * @deprecated Removed in 1.2
	 */
	private $classPathFiles = array(
		'/simplifiedshopflowservice-client/Resurs_SimplifiedShopFlowService.php',
		'/configurationservice-client/Resurs_ConfigurationService.php',
		'/aftershopflowservice-client/Resurs_AfterShopFlowService.php',
		'/shopflowservice-client/Resurs_ShopFlowService.php'
	);


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

	/**
	 * Stored array for booked payments
	 * @var array
	 * @deprecated 1.0.2
	 * @deprecated 1.1.2
	 */
	private $bookData = array();
	/**
	 * bookedCallbackUrl that may be set in runtime on bookpayments - has to be null or a string with 1 character or more
	 * @var string
	 * @deprecated 1.0.2
	 * @deprecated 1.1.2
	 */
	private $_bookedCallbackUrl = null;
	/**
	 * EComPHP will set this value to true, if the library "interfered" with the cart
	 * @var bool
	 * @deprecated 1.0.2
	 * @deprecated 1.1.2
	 */
	private $bookPaymentCartFixed = false;
	/**
	 * Last booked payment state
	 * @var string
	 * @deprecated 1.0.2
	 * @deprecated 1.1.2
	 */
	private $lastBookPayment = null;
	/**
	 * Payment data object
	 * @var array|object
	 * @deprecated 1.0.2
	 * @deprecated 1.1.2
	 */
	private $_paymentData = null;
	/**
	 * Object for speclines/specrows
	 * @var array|object
	 * @deprecated 1.0.2
	 * @deprecated 1.1.2
	 */
	private $_paymentSpeclines = null;
	/**
	 * Counter for a specline
	 * @var int
	 * @deprecated 1.0.2
	 * @deprecated 1.1.2
	 */
	private $_specLineID = null;
	/**
	 * Order data for the payment
	 * @var array|object
	 * @deprecated 1.0.2
	 * @deprecated 1.1.2
	 */
	private $_paymentOrderData = null;
	/**
	 * Address data for the payment
	 * @var array|object
	 * @deprecated 1.0.2
	 * @deprecated 1.1.2
	 */
	private $_paymentAddress = null;
	/**
	 * Normally used if billing and delivery differs (Sent to the gateway clientside)
	 * @var array|object
	 * @deprecated 1.0.2
	 * @deprecated 1.1.2
	 */
	private $_paymentDeliveryAddress = null;
	/**
	 * Customer data for the payment
	 * @var array|object
	 * @deprecated 1.0.2
	 * @deprecated 1.1.2
	 */
	private $_paymentCustomer = null;
	/**
	 * Customer data, extended, for the payment. For example when delivery address is set
	 * @var array|object
	 * @deprecated 1.0.2
	 * @deprecated 1.1.2
	 */
	private $_paymentExtendedCustomer = null;
	/**
	 * Card data for the payment
	 * @var array|object
	 * @deprecated 1.0.2
	 * @deprecated 1.1.2
	 */
	private $_paymentCardData = null;
	/**
	 * Card data object: Card number
	 * @var array|object
	 * @deprecated 1.0.2
	 * @deprecated 1.1.2
	 */
	private $cardDataCardNumber = null;
	/**
	 * Card data object: The amount applied for the customer
	 * @var bool
	 * @deprecated 1.0.2
	 * @deprecated 1.1.2
	 */
	private $cardDataUseAmount = false;
	/**
	 * Card data object: If set, you can set up your own amount to apply for
	 * @var int
	 * @deprecated 1.0.2
	 * @deprecated 1.1.2
	 */
	private $cardDataOwnAmount = null;


	/// Internal handlers for bookings -- Finish here


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


	///// Configuration system (deprecated)
	/**
	 * Configuration array
	 * @var array
	 * @deprecated 1.0.1
	 * @deprecated 1.1.1
	 */
	private $config;
	/**
	 * Set to true if you want to try to use internally cached data. This is disabled by default since it may, in a addition to performance, also be a security issue since the configuration file needs read- and write permissions
	 * @var bool
	 * @deprecated 1.0.1
	 * @deprecated 1.1.1
	 */
	private $configurationInternal = false;
	/**
	 * For internal handling of cache, etc
	 * @var string
	 * @deprecated 1.0.1
	 * @deprecated 1.1.1
	 */
	private $configurationSystem = "configuration";
	/**
	 * Usage of Configuration file
	 * @var string
	 * @deprecated 1.0.1
	 * @deprecated 1.1.1
	 */
	private $configurationStorage = "";
	/**
	 * Configuration, settings, payment methods etc
	 * @var array
	 * @deprecated 1.0.1
	 * @deprecated 1.1.1
	 */
	private $configurationArray = array();
	/**
	 * Time in seconds when cache should be considered outdated and needs to get updated with new fresh data
	 * @var int
	 * @deprecated 1.0.1
	 * @deprecated 1.1.1
	 */
	private $configurationCacheTimeout = 3600;


	///////////////////////////////////////// REFACTOR SECTION ENDS HERE

	/////////// INITIALIZERS
	/**
	 * Constructor method for Resurs Bank WorkFlows
	 *
	 * This method prepares initial variables for the workflow. No connections are being made from this point.
	 *
	 * @param string $login
	 * @param string $password
	 * @param int $targetEnvironment
	 *
	 * @throws \Exception
	 */
	function __construct( $login = '', $password = '', $targetEnvironment = ResursEnvironments::ENVIRONMENT_NOT_SET ) {
		if ( defined( 'RB_API_PATH' ) ) {
			$this->classPath = RB_API_PATH;
		}
		if ( isset( $_SERVER['HTTP_HOST'] ) ) {
			$theHost = $_SERVER['HTTP_HOST'];
		} else {
			$theHost = "nohost.localhost";
		}
		$this->checkoutShopUrl           = $this->hasHttps( true ) . "://" . $theHost;
		$this->soapOptions['cache_wsdl'] = ( defined( 'WSDL_CACHE_BOTH' ) ? WSDL_CACHE_BOTH : true );
		$this->soapOptions['ssl_method'] = ( defined( 'SOAP_SSL_METHOD_TLS' ) ? SOAP_SSL_METHOD_TLS : false );

		$this->setAuthentication($login, $password);
		if ( $targetEnvironment != ResursEnvironments::ENVIRONMENT_NOT_SET ) {
			$this->setEnvironment( $targetEnvironment );
		}
		$this->setUserAgent();
	}

	/**
	 * Check HTTPS-requirements, if they pass.
	 *
	 * Resurs Bank requires secure connection to the webservices, so your PHP-version must support SSL. Normally this is not a problem, but since there are server- and hosting providers that is actually having this disabled, the decision has been made to do this check.
	 * @throws \Exception
	 */
	private function testWrappers() {
		if ( ! in_array( 'https', @stream_get_wrappers() ) ) {
			throw new \Exception( __FUNCTION__ . ": HTTPS wrapper can not be found", \RESURS_EXCEPTIONS::SSL_WRAPPER_MISSING );
		}
	}

	/**
	 * Wsdl initialization
	 *
	 * @deprecated 1.0.1 Unless you don't need this, do run through InitializeServices instead.
	 * @deprecated 1.1.1 Unless you don't need this, do run through InitializeServices instead.
	 */
	public function InitializeWsdl() {
		$this->InitializeServices();
	}

	/**
	 * Everything that communicates with Resurs Bank should go here, wheter is is web services or curl/json data. The former name of this
	 * function is InitializeWsdl, but since we are handling nonWsdl-calls differently, but still needs some kind of compatibility in dirty
	 * code structures, everything needs to be done from here. For now. In future version, this is probably deprecated too, as it is an
	 * obsolete way of getting things done as Resurs Bank has more than one way to pick things up in the API suite.
	 *
	 * @return bool
	 * @throws \Exception
	 * @since 1.0.1
	 * @since 1.1.1
	 */
	private function InitializeServices() {
		// 1.0.4/1.1.4: No longer checking includes
		$this->hasServicesInitialization = $this->initWsdl();
		$this->getSslValidation();
		return $this->hasServicesInitialization;
	}

	/**
	 * @param bool $debugModeState
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	public function setDebug($debugModeState = false) {
		$this->InitializeServices();
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
	 * Return the CURL communication handle to the client, when in debug mode (Read only)
	 *
	 * @return Tornevall_cURL
	 * @throws \Exception
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	public function getCurlHandle() {
		$this->InitializeServices();
		if ($this->debug) {
			return $this->CURL;
		} else {
			throw new \Exception("Can't return handle. The module is in wrong state (non-debug mode)", 403);
		}
	}

	/**
	 *
	 * Make it possible, in test mode, to replace the old curl handle with a new reconfigured one
	 *
	 * @param $newCurlHandle
	 *
	 * @return Tornevall_cURL
	 * @throws \Exception
	 * @since 1.0.23
	 * @since 1.1.23
	 * @since 1.2.0
	 */
	public function setCurlHandle($newCurlHandle) {
		$this->InitializeServices();
		if ($this->debug) {
			$this->CURL = $newCurlHandle;
		} else {
			throw new \Exception("Can't return handle. The module is in wrong state (non-debug mode)", 403);
		}
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
	public function setSslValidation($validationEnabled = false) {
		$this->InitializeServices();
		if ($this->debug && $this->current_environment == ResursEnvironments::ENVIRONMENT_TEST) {
			$this->curlSslDisable = true;
		} else {
			throw new \Exception("Can't set SSL validation in relaxed mode. Debug mode is disabled and/or test environment are not set", 403);
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
		if ( is_null( $testVersion ) ) {
			return ! $this->NETWORK->getVersionTooOld( $this->getVersionNumber( false ), $this->gitUrl );
		} else {
			return ! $this->NETWORK->getVersionTooOld( $testVersion, $this->gitUrl );
		}
	}

	/**
	 * Try to fetch a list of versions for EComPHP by its git tags
	 *
	 * @return array
	 * @since 1.0.26
	 * @since 1.1.26
	 * @since 1.2.0
	 */
	public function getVersionsByGitTag() {
		return $this->NETWORK->getGitTagsByUrl( $this->gitUrl );
	}

	/**
	 * Initializer for WSDL before calling services. Decides what username and environment to use. Default is always test.
	 *
	 * @throws \Exception
	 * @deprecated 1.0.4
	 * @deprecated 1.1.4
	 */
	private function initWsdl() {
		$this->testWrappers();
		// Make sure that the correct webservice is loading first (if necessary). The topmost service has the highest priority and will not be
		// overwritten once loaded. For example, if ShopFlowService is loaded before the SimplifiedShopFlowService, you won't be able to use
		// the SimplifiedShopFlowService at all.
		// This behaviour is deprecated since 1.0.4/1.1.4 since we're removing the support of wsdl in phases.
		$apiFileLoads   = 0;
		$currentService = "";

		// Try to autodetect wsdl location and set up new path for where they can be loaded
		if ( ! $this->classes( $this->classPath ) ) {
			if ( $this->classes( $this->classPath . "/classes" ) ) {
				$this->classPath = $this->classPath . "/classes";
			}
			if ( $this->classes( $this->classPath . "/classes/rbwsdl" ) ) {
				$this->classPath = $this->classPath . "/classes/rbwsdl";
			}
			if ( $this->classes( $this->classPath . "/rbwsdl" ) ) {
				$this->classPath = $this->classPath . "/rbwsdl";
			}
			if ( $this->classes( $this->classPath . "/../rbwsdl" ) ) {
				$this->classPath = realpath( $this->classPath . "/../rbwsdl" );
			}
			/*
			 * Fail down to unsafer path.
			 */
			if ( $this->classes( $this->classPath . "/wsdl" ) ) {
				$this->classPath = $this->classPath . "/wsdl";
			}
			if ( $this->classes( $this->classPath . "/../wsdl" ) ) {
				$this->classPath = realpath( $this->classPath . "/../rbwsdl" );
			}
		}
		if ( in_array( 'simplifiedshopflowservice', array_map( "strtolower", $this->Include ) ) && file_exists( $this->classPath . '/simplifiedshopflowservice-client/Resurs_SimplifiedShopFlowService.php' ) ) {
			require $this->classPath . '/simplifiedshopflowservice-client/Resurs_SimplifiedShopFlowService.php';
			$apiFileLoads ++;
		}
		if ( in_array( 'configurationservice', array_map( "strtolower", $this->Include ) ) && file_exists( $this->classPath . '/configurationservice-client/Resurs_ConfigurationService.php' ) ) {
			require $this->classPath . '/configurationservice-client/Resurs_ConfigurationService.php';
			$apiFileLoads ++;
		}
		if ( in_array( 'aftershopflowservice', array_map( "strtolower", $this->Include ) ) && file_exists( $this->classPath . '/aftershopflowservice-client/Resurs_AfterShopFlowService.php' ) ) {
			require $this->classPath . '/aftershopflowservice-client/Resurs_AfterShopFlowService.php';
			$apiFileLoads ++;
		}
		/**
		 * Loads the deprecated flow as the last class, if found in our library. However, we normally don't deliver this setup in our EComPHP-package, so if we find this
		 * the developer may have added it him/herself.
		 */
		if ( in_array( 'shopflowservice', array_map( "strtolower", $this->Include ) ) && file_exists( $this->classPath . '/shopflowservice-client/Resurs_ShopFlowService.php' ) ) {
			require $this->classPath . '/shopflowservice-client/Resurs_ShopFlowService.php';
			$apiFileLoads ++;
		}
		if ( $apiFileLoads >= 1 ) {
			$this->hasWsdl = true;
		}

		// Requiring that SSL is available on the current server, will throw an exception if no HTTPS-wrapper is found.
		if ( $this->username != null ) {
			$this->soapOptions['login'] = $this->username;
		}
		if ( $this->password != null ) {
			$this->soapOptions['password'] = $this->password;
		}
		if ( $this->current_environment == self::ENVIRONMENT_TEST ) {
			$this->environment = $this->env_test;
		} else {
			$this->environment = $this->env_prod;
		}
		$this->soapOptions = $this->sslGetOptionsStream( $this->soapOptions, array( 'http' => array( "user_agent" => $this->myUserAgent ) ) );

		if ( $this->hasWsdl ) {
			/*
			 * 1.0 vs 1.1: Keeping backwards compatibility in the major version of rbapiloader by looking for namespaced classes.
			 */
			try {
				// 1.0
				if ( class_exists( 'Resurs_SimplifiedShopFlowService' ) ) {
					$currentService                  = "simplifiedShopFlowService";
					$this->simplifiedShopFlowService = new Resurs_SimplifiedShopFlowService( $this->soapOptions, $this->environment . "SimplifiedShopFlowService?wsdl" );
				}
				// 1.1
				if ( class_exists( '\Resursbank\RBEcomPHP\Resurs_SimplifiedShopFlowService' ) ) {
					$this->hasNameSpace              = true;
					$currentService                  = "simplifiedShopFlowService";
					$this->simplifiedShopFlowService = new Resurs_SimplifiedShopFlowService( $this->soapOptions, $this->environment . "SimplifiedShopFlowService?wsdl" );
				}
				// 1.0
				if ( class_exists( 'Resurs_ConfigurationService' ) ) {
					$currentService             = "configurationService";
					$this->configurationService = new Resurs_ConfigurationService( $this->soapOptions, $this->environment . "ConfigurationService?wsdl" );
				}
				// 1.1
				if ( class_exists( '\Resursbank\RBEcomPHP\Resurs_ConfigurationService' ) ) {
					$this->hasNameSpace         = true;
					$currentService             = "configurationService";
					$this->configurationService = new Resurs_ConfigurationService( $this->soapOptions, $this->environment . "ConfigurationService?wsdl" );
				}

				// 1.0
				if ( class_exists( 'Resurs_AfterShopFlowService' ) ) {
					$currentService             = "afterShopFlowService";
					$this->afterShopFlowService = new Resurs_AfterShopFlowService( $this->soapOptions, $this->environment . "AfterShopFlowService?wsdl" );
				}
				// 1.1
				if ( class_exists( '\Resursbank\RBEcomPHP\Resurs_AfterShopFlowService' ) ) {
					$this->hasNameSpace         = true;
					$currentService             = "afterShopFlowService";
					$this->afterShopFlowService = new Resurs_AfterShopFlowService( $this->soapOptions, $this->environment . "AfterShopFlowService?wsdl" );
				}

				// 1.0
				if ( class_exists( 'Resurs_ShopFlowService' ) ) {
					$currentService        = "shopFlowService";
					$this->shopFlowService = new Resurs_ShopFlowService( $this->soapOptions, $this->environment . "ShopFlowService?wsdl" );
				}
				// 1.1
				if ( class_exists( '\Resursbank\RBEcomPHP\Resurs_ShopFlowService' ) ) {
					$this->hasNameSpace = true;
					$currentService        = "shopFlowService";
					$this->shopFlowService = new Resurs_ShopFlowService( $this->soapOptions, $this->environment . "ShopFlowService?wsdl" );
				}
			} catch ( \Exception $e ) {
				/** Adds the $currentService to the message, to show which service that failed */
				throw new \Exception( __FUNCTION__ . ": " . $e->getMessage() . "\nStuck on service: " . $currentService, \RESURS_EXCEPTIONS::WSDL_APILOAD_EXCEPTION, $e );
			}
		}

		if ( class_exists( '\Resursbank\RBEcomPHP\Tornevall_cURL' ) ) {
			$this->CURL = new \Resursbank\RBEcomPHP\Tornevall_cURL();
			$this->CURL->setStoreSessionExceptions( true );
			$this->CURL->setAuthentication( $this->soapOptions['login'], $this->soapOptions['password'] );
			$this->CURL->setUserAgent( $this->myUserAgent );
			$this->NETWORK = new \Resursbank\RBEcomPHP\TorneLIB_Network();
			$this->BIT = $this->NETWORK->BIT;
		}
		// Prepare services URL in case of nonWsdl mode.
		// This makes the throwing on "no available services" unnecessary
		if ( count( $this->Include ) < 1 ) {
			$this->hasWsdl = false;
		}
		$this->wsdlServices = array();
		foreach ( $this->ServiceRequestList as $reqType => $reqService ) {
			$this->wsdlServices[ $reqService ] = true;
		}
		foreach ( $this->wsdlServices as $ServiceName => $isAvailableBoolean ) {
			$this->URLS[ $ServiceName ] = $this->environment . $ServiceName . "?wsdl";
		}

		return true;
	}

	/**
	 * Set up a user-agent to identify with webservices.
	 *
	 * @param string $MyUserAgent
	 *
	 * @since 1.0.2
	 * @since 1.1.2
	 */
	public function setUserAgent( $MyUserAgent = '') {
		if ( ! empty( $MyUserAgent ) ) {
			$this->myUserAgent = $MyUserAgent . " +" . $this->getVersionFull();
		} else {
			$this->myUserAgent = $this->getVersionFull();
		}
		if ($this->customerUserAgentPush && isset($_SERVER['HTTP_USER_AGENT'])) {
			$this->myUserAgent .= " +CLI-" . $this->T_CRYPTO->base64_compress($_SERVER['HTTP_USER_AGENT']);
		}
	}

	/**
	 * Set internal flag parameter
	 *
	 * @param string $flagKey
	 * @param string $flagValue
	 * @return bool If successful
	 * @throws \Exception
	 * @since 1.0.23
	 * @since 1.1.23
	 * @since 1.2.0
	 */
	public function setFlag($flagKey = '', $flagValue = '') {
		if (!empty($flagKey)) {
			$this->internalFlags[$flagKey] = $flagValue ;
			return true;
		}
		throw new \Exception("Flags can not be empty", 500);
	}

	/**
	 * Get internal flag
	 * @param string $flagKey
	 *
	 * @return mixed|null
	 * @since 1.0.23
	 * @since 1.1.23
	 * @since 1.2.0
	 */
	public function getFlag($flagKey = '') {
		if (isset($this->internalFlags[$flagKey])) {
			return $this->internalFlags[$flagKey];
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
	 * @since 1.0.25
	 * @since 1.1.25
	 * @since 1.2.0
	 */
	public function deleteFlag($flagKey) {
		if ($this->hasFlag($flagKey)) {
			unset($this->internalFlags[$flagKey]);
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
	public function isFlag($flagKey = '') {
		if ($this->hasFlag($flagKey)) {
			return ($this->getFlag($flagKey) === 1 || $this->getFlag($flagKey) === true ? true : false);
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
	public function hasFlag($flagKey = '') {
		if (!is_null($this->getFlag($flagKey))) {
			return true;
		}
		return false;
	}

	/**
	 * Find classes
	 *
	 * @param string $path
	 *
	 * @return bool
	 * @deprecated Not in use internally
	 */
	private function classes( $path = '' ) {
		foreach ( $this->classPathFiles as $file ) {
			if ( file_exists( $path . "/" . $file ) ) {
				return true;
			}
		}

		return false;
	}


	/////////// INTERNAL CALL FORWARDER (REQUIRES WSDL)

	/**
	 * Method calls that should be passed directly to a webservice
	 *
	 * Unknown calls passed through __call(), so that we may cover functions unsupported by the gateway.
	 * This stub-gateway processing is also checking if the methods really exist in the stubs and passing them over is they do.
	 *
	 * NOTE: If you're going nonWsdl, this method might go deprecated as curl works differently
	 *
	 * GETTING DATA AS ARRAYS (DEPRECATED)
	 * This method takes control of responses and returns the object "return" if it exists.
	 * The function also supports array, by adding "Array" to the end of the method).
	 *
	 * @param null $func
	 * @param array $args
	 *
	 * @return array|null
	 * @throws \Exception
	 * @deprecated 1.0.8
	 * @deprecated 1.1.8
	 */
	public function __call( $func = null, $args = array() ) {
		// Initializing wsdl if not done is required here
		$this->InitializeServices();

		$returnObject        = null;
		$this->serviceReturn = null;
		$returnAsArray       = false;
		$classfunc           = null;
		$funcArgs            = null;
		$returnContent       = null;
		//if (isset($args[0]) && is_array($args[0])) {}
		$classfunc = "resurs_" . $func;
		if ( preg_match( "/Array$/", $func ) ) {
			$func          = preg_replace( "/Array$/", '', $func );
			$classfunc     = preg_replace( "/Array$/", '', $classfunc );
			$returnAsArray = true;
		}
		if ( $this->hasWsdl ) {
			$useNameSpace = "";
			foreach ( get_declared_classes() as $className ) {
				if ( preg_match( "/rbecomphp/i", $className ) && preg_match( "/resursbank/i", $className ) ) {
					$useNameSpace = "\\Resursbank\\RBEcomPHP\\";
					break;
				}
			}
			try {
				$reflectionClassName = "{$useNameSpace}{$classfunc}";
				$reflection          = new \ReflectionClass( $reflectionClassName );
				$instance            = $reflection->newInstanceArgs( $args );
				// Check availability, fetch and stop on first match
				if ( ! isset( $returnObject ) && in_array( $func, get_class_methods( "{$useNameSpace}Resurs_SimplifiedShopFlowService" ) ) ) {
					$this->serviceReturn = "SimplifiedShopFlowService";
					$returnObject        = $this->simplifiedShopFlowService->$func( $instance );
				}
				if ( ! isset( $returnObject ) && in_array( $func, get_class_methods( "{$useNameSpace}Resurs_ConfigurationService" ) ) ) {
					$this->serviceReturn = "ConfigurationService";
					$returnObject        = $this->configurationService->$func( $instance );
				}
				if ( ! isset( $returnObject ) && in_array( $func, get_class_methods( "{$useNameSpace}Resurs_AfterShopFlowService" ) ) ) {
					$this->serviceReturn = "AfterShopFlowService";
					$returnObject        = $this->afterShopFlowService->$func( $instance );
				}
				if ( ! isset( $returnObject ) && in_array( $func, get_class_methods( "{$useNameSpace}Resurs_ShopFlowService" ) ) ) {
					$this->serviceReturn = "ShopFlowService";
					$returnObject        = $this->shopFlowService->$func( $instance );
				}
			} catch ( \Exception $e ) {
				throw new \Exception( __FUNCTION__ . "/" . $func . "/" . $classfunc . ": " . $e->getMessage(), \RESURS_EXCEPTIONS::WSDL_PASSTHROUGH_EXCEPTION );
			}
		}
		try {
			if ( isset( $returnObject ) && ! empty( $returnObject ) && isset( $returnObject->return ) && ! empty( $returnObject->return ) ) {
				/* Issue #63127 - make some dataobjects storable */
				if ( $this->convertObjectsOnGet && preg_match( "/^get/i", $func ) ) {
					$returnContent = $this->getDataObject( $returnObject->return );
				} else {
					$returnContent = $returnObject->return;
				}
				if ( $returnAsArray ) {
					return $this->parseReturn( $returnContent );
				}
			} else {
				/* Issue #62975: Fixes empty responses from requests not containing a return-object */
				if ( empty( $returnObject ) ) {
					if ( $returnAsArray ) {
						return array();
					}
				} else {
					if ( $returnAsArray ) {
						return $this->parseReturn( $returnContent );
					} else {
						return $returnObject;
					}
				}
			}

			return $returnContent;
		} catch ( \Exception $returnObjectException ) {
		}
		if ( $returnAsArray ) {
			return $this->parseReturn( $returnObject );
		}

		return $returnObject;
	}



	/////////// Standard getters and setters

	/**
	 * Allow older/obsolete PHP Versions (Follows the obsolete php versions rules - see the link for more information).
	 *
	 * @param bool $activate
	 *
	 * @link https://test.resurs.com/docs/x/TYNM#ECommercePHPLibrary-ObsoletePHPversions
	 * @deprecated Removed in 1.2
	 */
	public function setObsoletePhp( $activate = false ) {
		$this->allowObsoletePHP = $activate;
	}

	/**
	 * Define current environment
	 *
	 * @param int $environmentType
	 */
	public function setEnvironment( $environmentType = ResursEnvironments::ENVIRONMENT_TEST ) {
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
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	public function setAuthentication($username = '', $password = '') {
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
	 * Function to enable/disabled SSL Peer/Host verification, if problems occur with certificates
	 *
	 * @param bool|true $enabledFlag
	 *
	 * @deprecated 1.0.1
	 * @deprecated 1.1.1
	 */
	public function setSslVerify( $enabledFlag = true ) {
		$this->sslVerify = $enabledFlag;
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

	/**
	 * TestCerts - Test if your webclient has certificates available (make sure the $testssldeprecated are enabled if you want to test older PHP-versions - meaning older than 5.6.0)
	 * @return bool
	 * @deprecated 1.0.1
	 * @deprecated 1.1.1
	 */
	public function TestCerts() {
		return $this->openssl_guess();
	}

	/**
	 * Get a timestamp for when the last cache of a call was requested and saved from Ecommerce
	 *
	 * @param $cachedArrayName
	 *
	 * @return int
	 * @deprecated 1.0.8
	 * @deprecated 1.1.8
	 */
	public function getLastCall( $cachedArrayName ) {
		if ( isset( $this->configurationArray['lastUpdate'] ) && isset( $this->configurationArray['lastUpdate'][ $cachedArrayName ] ) ) {
			return time() - intval( $this->configurationArray['lastUpdate'][ $cachedArrayName ] );
		}

		return time(); /* If nothing is set, indicate that it has been a long time since last call was made... */
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
		} catch (\Exception $restException) {
			throw new \Exception($restException->getMessage(), $restException->getCode());
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
			if (!isset($ResursResponseArray['UPDATE'])) {
				$updateResponse = $this->getRegisteredEventCallback(RESURS_CALLBACK_TYPES::CALLBACK_TYPE_UPDATE);
				if (is_object($updateResponse) && isset($updateResponse->uriTemplate)) {
					$ResursResponseArray['UPDATE'] = $updateResponse->uriTemplate;
				}
			}
			return $ResursResponseArray;
		}
		$hasUpdate = false;
		foreach ($ResursResponse as $responseObject) {
			if (isset($responseObject->eventType) && $responseObject->eventType == "UPDATE") {
				$hasUpdate = true;
			}
		}
		if (!$hasUpdate) {
			$updateResponse = $this->getRegisteredEventCallback(RESURS_CALLBACK_TYPES::CALLBACK_TYPE_UPDATE);
			if (isset($updateResponse->uriTemplate) && !empty($updateResponse->uriTemplate)) {
				if (!isset($updateResponse->eventType)) {
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
	 * @return mixed
	 * @since 1.x.x
	 */
	public function getRegisteredEventCallback( $callbackType = RESURS_CALLBACK_TYPES::CALLBACK_TYPE_NOT_SET ) {
		$this->InitializeServices();
		$fetchThisCallback        = $this->getCallbackTypeString( $callbackType );
		$getRegisteredCallbackUrl = $this->getServiceUrl( "getRegisteredEventCallback" );
		// We are not using postService here, since we are dependent on the response code rather than the response itself
		$renderedResponse = $this->CURL->doPost( $getRegisteredCallbackUrl )->getRegisteredEventCallback( array( 'eventType' => $fetchThisCallback ) );
		$parsedResponse = $this->CURL->getParsedResponse($renderedResponse);
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
		$returnSuccess = false;
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
			$code             = $this->CURL->getResponseCode();
		} else {
			$renderCallbackUrl = $this->getServiceUrl( "registerEventCallback" );
			// We are not using postService here, since we are dependent on the response code rather than the response itself
			$renderedResponse = $this->CURL->doPost( $renderCallbackUrl )->registerEventCallback( $renderCallback );
			$code             = $renderedResponse['code'];
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

		return false;
	}

	/**
	 * Simplifies removal of callbacks even when they does not exist at first.
	 *
	 * @param int $callbackType
	 *
	 * @return bool
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
				if ( $curlResponse['code'] >= 200 && $curlResponse['code'] <= 250 ) {
					return true;
				}
			} else {
				$this->InitializeServices();
				// Not using postService here, since we're
				$curlResponse = $this->CURL->doGet( $this->getServiceUrl( 'unregisterEventCallback' ) )->unregisterEventCallback( array( 'eventType' => $callbackType ) );
				if ( $curlResponse['code'] >= 200 && $curlResponse['code'] <= 250 ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Simplifed callback registrator. Also handles re-registering of callbacks in case of already found in system.
	 *
	 * @param int $callbackType
	 * @param string $callbackUriTemplate
	 * @param array $callbackDigest If no parameters are set, this will be handled automatically.
	 * @param null $basicAuthUserName
	 * @param null $basicAuthPassword
	 *
	 * @return bool
	 * @throws \Exception
	 * @deprecated 1.0.1
	 * @deprecated 1.1.1
	 */
	public function setCallback( $callbackType = RESURS_CALLBACK_TYPES::CALLBACK_TYPE_NOT_SET, $callbackUriTemplate = "", $callbackDigest = array(), $basicAuthUserName = null, $basicAuthPassword = null ) {
		return $this->setRegisterCallback( $callbackType, $callbackUriTemplate, $callbackDigest, $basicAuthUserName, $basicAuthPassword );
	}

	/**
	 * Simplifies removal of callbacks even when they does not exist at first.
	 *
	 * @param int $callbackType
	 *
	 * @return bool
	 * @throws \Exception
	 * @deprecated 1.0.1 Use unregisterEventCallback instead
	 * @deprecated 1.1.1 Use unregisterEventCallback instead
	 */
	public function unSetCallback( $callbackType = RESURS_CALLBACK_TYPES::CALLBACK_TYPE_NOT_SET ) {
		return $this->unregisterEventCallback( $callbackType );
	}

	/**
	 * Trigger registered callback event TEST
	 *
	 * @return bool
	 * @deprecated 1.0.1 Use triggerCallback() instead
	 * @deprecated 1.1.1 Use triggerCallback() instead
	 */
	public function testCallback() {
		return $this->triggerCallback();
	}

	/**
	 * Trigger the registered callback event TEST if set. Returns true if trigger call was successful, otherwise false (Observe that this not necessarily is a successful completion of the callback)
	 *
	 * @return bool
	 * @since 1.0.2
	 * @since 1.1.2
	 */
	public function triggerCallback() {
		$this->InitializeServices();
		$envUrl = $this->env_test;
		$curEnv = $this->getEnvironment();
		if ($curEnv == ResursEnvironments::ENVIRONMENT_PRODUCTION) {
			$envUrl = $this->env_prod;
		}
		$serviceUrl = $envUrl . "DeveloperWebService?wsdl";
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
	 * @since 1.0.1
	 * @since 1.1.1
	 */
	public function getServiceUrl( $ServiceName = '' ) {
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
		$this->setPreferredPaymentFlowService($flowType);
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
	 * Configure EComPHP to use a specific flow
	 * @param int $flowType
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
		$soapBody = null;
		if (!empty($serviceNameUrl) && !is_null($this->CURL)) {
			$Service = $this->CURL->doGet( $serviceNameUrl );
			try {
				$RequestService = $Service->$serviceName( $resursParameters );
			} catch (\Exception $serviceRequestException) {
				// Try to fetch previous exception (This is what we actually want)
				$previousException = $serviceRequestException->getPrevious();
				if ( !empty($previousException)) {
					$previousExceptionMessage = $previousException->getMessage();
					$previousExceptionCode    = $previousException->getCode();
				}
				if (!empty($previousExceptionMessage)) {
					$exceptionMessage = $previousExceptionMessage;
					$exceptionCode = $previousExceptionCode;
				} else {
					$exceptionCode    = $serviceRequestException->getCode();
					$exceptionMessage = $serviceRequestException->getMessage();
				}
				if (isset($previousException->detail) && is_object($previousException->detail) && isset($previousException->detail->ECommerceError) && is_object($previousException->detail->ECommerceError)) {
					$objectDetails = $previousException->detail->ECommerceError;
					if (isset($objectDetails->errorTypeId) && intval($objectDetails->errorTypeId) > 0) {
						$exceptionCode = $objectDetails->errorTypeId;
					}
					if (isset($previousException->detail->userErrorMessage)) {
						$exceptionMessage = $objectDetails->userErrorMessage;
					}
				}
				if (empty($exceptionCode) || $exceptionCode == "0") {
					$exceptionCode = \RESURS_EXCEPTIONS::UNKOWN_SOAP_EXCEPTION_CODE_ZERO;
				}
				// Cast internal soap errors into a new, since the exception code is lost
				throw new \Exception( $exceptionMessage, $exceptionCode, $serviceRequestException );
			}
			$ParsedResponse = $Service->getParsedResponse( $RequestService );
			$ResponseCode   = $Service->getResponseCode();
			if ($this->debug) {
				if ( ! isset( $this->curlStats['calls'] ) ) {
					$this->curlStats['calls'] = 1;
				}
				$this->curlStats['calls'] ++;
				$this->curlStats['internals'] = $this->CURL->getDebugData();
			}
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
	 * @since 1.0.13
	 * @since 1.1.13
	 * @since 1.2.0
	 */
	public function setPushCustomerUserAgent($enableCustomerUserAgent = false) {
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
	public function getNextInvoiceNumber( $initInvoice = true, $firstInvoiceNumber = 1 ) {
		$this->InitializeServices();
		$invoiceNumber = 0;
		if ( $initInvoice ) {
			try {
				$invoiceNumber = $this->postService( "peekInvoiceSequence" )->nextInvoiceNumber;
			} catch ( \Exception $e ) {

			}
		}
		if ( is_numeric( $invoiceNumber ) && $invoiceNumber > 0 && $initInvoice ) {
			try {
				$this->postService( "setInvoiceSequence", array( 'nextInvoiceNumber' => $firstInvoiceNumber ) );
				$invoiceNumber = $firstInvoiceNumber;
			} catch ( \Exception $e ) {
				// If the initialization failed, due to an already set invoice number, we will fall back to the last one
				$invoiceNumber = $this->postService( "peekInvoiceSequence", array( 'nextInvoiceNumber' => null ) )->nextInvoiceNumber;
			}
		}

		return $invoiceNumber;
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
		$realPaymentMethods = $this->sanitizePaymentMethods($paymentMethods);
		return $realPaymentMethods;
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
	public function sanitizePaymentMethods($paymentMethods = array()) {
		$realPaymentMethods = array();
		$paymentSevice = $this->getPreferredPaymentFlowService();
		if (is_array($paymentMethods) && count($paymentMethods)) {
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
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	public function setPaymentMethodIdSanitizing($doSanitize = false) {
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
	public function getPaymentMethodIdSanitizing(){
		return $this->paymentMethodIdSanitizing;
	}

	/**
	 * If the merchant has PSP methods available in the simplified and hosted flow where it is normally not supported, this should be set to true. setStrictPsp() overrides this setting.
	 *
	 * @param bool $allowed
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	public function setSimplifiedPsp($allowed = false) {
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
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	public function setStrictPsp($isStrict = false) {
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
	public function getStrictPsp($isStrict = false) {
		return $this->paymentMethodsIsStrictPsp;
	}

	/**
	 * @param string $governmentId
	 * @param string $customerType
	 * @param string $customerIpAddress
	 *
	 * @return array|mixed|null
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
	 * @since 1.1.24
	 */
	public function getAnnuityFactorByDuration($paymentMethodIdOrFactorObject, $duration) {
		$returnFactor = 0;
		$factorObject = $paymentMethodIdOrFactorObject;
		if (is_string($paymentMethodIdOrFactorObject) && !empty($paymentMethodIdOrFactorObject)) {
			$factorObject = $this->getAnnuityFactors($paymentMethodIdOrFactorObject);
		}
		if (is_array($factorObject)) {
			foreach ($factorObject as $factorObjectData) {
				if ($factorObjectData->duration == $duration && isset($factorObjectData->factor)) {
					return (float)$factorObjectData->factor;
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
	 */
	public function getAnnuityPriceByDuration($totalAmount, $paymentMethodIdOrFactorObject, $duration) {
		$durationFactor = $this->getAnnuityFactorByDuration($paymentMethodIdOrFactorObject, $duration);
		if ($durationFactor > 0) {
			return round($durationFactor * $totalAmount);
		}
	}

	/**
	 * Retrieves detailed information about the payment.
	 *
	 * @param string $paymentId
	 *
	 * @return array|mixed|null
	 * @link https://test.resurs.com/docs/x/moEW getPayment() documentation
	 * @since 1.0.1
	 * @since 1.1.1
	 */
	public function getPayment( $paymentId = '' ) {
		return $this->postService( "getPayment", array( 'paymentId' => $paymentId ) );
	}

	/**
	 * Make sure a payment will always be returned correctly. If string, getPayment will run first. If array/object, it will continue to look like one.
	 *
	 * @param array $paymentArrayOrPaymentId
	 *
	 * @return array|mixed|null
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
		$result = $this->CURL->doPut($url, array( 'paymentReference' => $to), CURL_POST_AS::POST_AS_JSON);
		$ResponseCode = $this->CURL->getResponseCode($result);
		if ( $ResponseCode >= 200 && $ResponseCode <= 250 ) {
			return true;
		}
		if ($ResponseCode >= 400) {
			throw new \Exception("Payment reference could not be updated", $ResponseCode);
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
		if ( ! isset( $checkPayment->id ) ) {
			throw new \Exception( $customErrorMessage );
		}
		$metaDataArray    = array(
			'paymentId' => $paymentId,
			'key'       => $metaDataKey,
			'value'     => $metaDataValue
		);
		$metaDataResponse = $this->CURL->doGet( $this->getServiceUrl( "addMetaData" ) )->addMetaData( $metaDataArray );
		if ( $metaDataResponse['code'] >= 200 ) {
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
			$ExternalPostData = array( 'link' => $this->NETWORK->base64url_encode( $useUrl ), "returnEncoded"=>true );
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
			$base64url = $this->base64url_encode($useUrl);
			if (isset($ParsedResponse->{$base64url}) && isset( $ParsedResponse->{$base64url}->exceptiondata->errorcode ) && ! empty( $ParsedResponse->{$base64url}->exceptiondata->errorcode ) ) {
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
		$primaryAddress = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : "127.0.0.1";
		// Warning: This is untested and currently returns an array instead of a string, which may break ecommerce
		if ( $this->preferCustomerProxy && ! empty( $this->NETWORK ) && count( $this->NETWORK->getProxyHeaders() ) ) {
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
	 * Convert a object to a data object
	 *
	 * @param array $d
	 * @param bool $forceConversion
	 * @param bool $preventConversion
	 *
	 * @return array|mixed|null
	 * @deprecated 1.0.8
	 * @deprecated 1.1.8
	 */
	private function getDataObject( $d = array(), $forceConversion = false, $preventConversion = false ) {
		if ( $preventConversion ) {
			return $d;
		}
		if ( $this->convertObjects || $forceConversion ) {
			/**
			 * If json_decode and json_encode exists as function, do it the simple way.
			 * http://php.net/manual/en/function.json-encode.php
			 */
			if ( function_exists( 'json_decode' ) && function_exists( 'json_encode' ) ) {
				return json_decode( json_encode( $d ) );
			}
			$newArray = array();
			if ( is_array( $d ) || is_object( $d ) ) {
				foreach ( $d as $itemKey => $itemValue ) {
					if ( is_array( $itemValue ) ) {
						$newArray[ $itemKey ] = (array) $this->getDataObject( $itemValue );
					} elseif ( is_object( $itemValue ) ) {
						$newArray[ $itemKey ] = (object) (array) $this->getDataObject( $itemValue );
					} else {
						$newArray[ $itemKey ] = $itemValue;
					}
				}
			}
		} else {
			return $d;
		}

		return $newArray;
	}

	/**
	 * ResponseObjectArrayParser. Translates a return-object to a clean array
	 *
	 * @param null $returnObject
	 *
	 * @return array
	 * @deprecated 1.0.8
	 * @deprecated 1.1.8
	 */
	public function parseReturn( $returnObject = null ) {
		$hasGet = false;
		if ( is_array( $returnObject ) ) {
			$parsedArray = array();
			foreach ( $returnObject as $arrayName => $objectArray ) {
				$classMethods = get_class_methods( $objectArray );
				if ( is_array( $classMethods ) ) {
					foreach ( $classMethods as $classMethodId => $classMethod ) {
						if ( preg_match( "/^get/i", $classMethod ) ) {
							$hasGet        = true;
							$field         = lcfirst( preg_replace( "/^get/i", '', $classMethod ) );
							$objectContent = $objectArray->$classMethod();
							if ( is_array( $objectContent ) ) {
								$parsedArray[ $arrayName ][ $field ] = $this->parseReturn( $objectContent );
							} else {
								$parsedArray[ $arrayName ][ $field ] = $objectContent;
							}
						}
					}
				}
			}
			/* Failver test */
			if ( ! $hasGet && ! count( $parsedArray ) ) {
				return $this->objectsIntoArray( $returnObject );
			}

			return $parsedArray;
		}

		return array(); /* Fail with empty array, if there is no recursive array  */
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
								if ($keepOpposite) {
									// This little one does the opposite of what this function normally do: Remove everything from the array except the found row.
									$cleanedArray[] = $currentObject;
								}
								break;
							}
						}
					} else if (is_array($currentCleanObject)) {
						// This is above, but based on incoming array
						if ( ! empty( $currentObject->artNo ) ) {
							if ( $currentObject->artNo == $currentCleanObject['artNo'] ) {    // No longer searching on id, as that is an incremental value rather than a dynamically added.
								$foundObject = true;
								if ($keepOpposite) {
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
	protected function getVersionFull( $getDecimals = false ) {
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
	protected function getVersionNumber( $getDecimals = false ) {
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
	protected function getCreatedBy() {
		$createdBy = $this->realClientName . "_" . $this->getVersionNumber( true );
		if ( ! empty( $this->loggedInuser ) ) {
			$createdBy .= "/" . $this->loggedInuser;
		}

		return $createdBy;
	}

	/**
	 * Adds your client name to the current client name string which is used as User-Agent when communicating with ecommerce.
	 *
	 * @param string $clientNameString
	 *
	 * @deprecated 1.0.2 Use setUserAgent
	 * @deprecated 1.1.2 Use setUserAgent
	 */
	public function setClientName( $clientNameString = "" ) {
		if (!empty($clientNameString)) {
			$this->setUserAgent( $clientNameString );
		}
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
	 * @since 1.0.4
	 * @since 1.1.4
	 */
	public function setShopUrl( $shopUrl = '', $validateFormat = true ) {
		$this->InitializeServices();
		if ( ! empty( $shopUrl ) ) {
			$this->checkoutShopUrl = $shopUrl;
		}
		if ($validateFormat) {
			$shopUrlValidate = $this->NETWORK->getUrlDomain($this->checkoutShopUrl);
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
	public function setValidateCheckoutShopUrl($validateEnabled = true) {
		$this->validateCheckoutShopUrl = $validateEnabled;
	}

	/////////// JSON AREA (PUBLICS + PRIVATES) - IN DECISION OF DEPRECATION

	/**
	 * dataContainer array to JSON converter
	 *
	 * This part of EComPHP only makes sure, if the customer are using the simplifiedFlow structure in a payment method
	 * that is not simplified, that the array gets converted to the right format. This part is ONLY needed if the plugin of the
	 * representative doesn't do it properly.
	 *
	 * @param array $dataContainer
	 * @param int $paymentMethodType
	 * @param bool $updateCart Defines if this a cart upgrade only
	 *
	 * @return array|mixed|string|void
	 * @deprecated 1.0.2
	 * @deprecated 1.1.2
	 */
	public function toJsonByType( $dataContainer = array(), $paymentMethodType = RESURS_FLOW_TYPES::FLOW_SIMPLIFIED_FLOW, $updateCart = false ) {
		// We need the content as is at this point since this part normally should be received as arrays
		$newDataContainer = $this->getDataObject( $dataContainer, false, true );
		if ( ! isset( $newDataContainer['type'] ) || empty( $newDataContainer['type'] ) ) {
			if ( $paymentMethodType == RESURS_FLOW_TYPES::FLOW_HOSTED_FLOW ) {
				$newDataContainer['type'] = 'hosted';
			} else if ( $paymentMethodType == RESURS_FLOW_TYPES::METHOD_OMNI ) {
				$newDataContainer['type'] = 'omni';
			}
		}
		if ( isset( $newDataContainer['type'] ) && ! empty( $newDataContainer['type'] ) ) {
			/**
			 * Hosted flow ruleset
			 */
			if ( strtolower( $newDataContainer['type'] ) == "hosted" ) {
				/* If the specLines are defined as simplifiedFlowSpecrows, we need to convert them to hosted speclines */
				$hasSpecLines = false;
				/* If there is an old array containing specLines, this has to be renamed to orderLines */
				if ( isset( $newDataContainer['orderData']['specLines'] ) ) {
					$newDataContainer['orderData']['orderLines'] = $newDataContainer['orderData']['specLines'];
					unset( $newDataContainer['orderData']['specLines'] );
					$hasSpecLines = true;
				}
				/* If there is a specLine defined in the parent array ... */
				if ( isset( $newDataContainer['specLine'] ) ) {
					/* ... then check if we miss orderLines ... */
					if ( ! $hasSpecLines ) {
						/* ... and add them on demand */
						$newDataContainer['orderData']['orderLines'] = $newDataContainer['specLine'];
					}
					/* Then unset the old array */
					unset( $newDataContainer['specLine'] );
				}
				/* If there is an address array on first level, we need to move the array to the customerArray*/
				if ( isset( $newDataContainer['address'] ) ) {
					$newDataContainer['customer']['address'] = $newDataContainer['address'];
					unset( $newDataContainer['address'] );
				}
				/* The same rule as in the address case applies to the deliveryAddress */
				if ( isset( $newDataContainer['deliveryAddress'] ) ) {
					$newDataContainer['customer']['deliveryAddress'] = $newDataContainer['deliveryAddress'];
					unset( $newDataContainer['deliveryAddress'] );
				}
				/* Now, let's see if there is a simplifiedFlow country applied to the customer data. In that case, we need to convert it to at countryCode. */
				if ( isset( $newDataContainer['customer']['address']['country'] ) ) {
					$newDataContainer['customer']['address']['countryCode'] = $newDataContainer['customer']['address']['country'];
					unset( $newDataContainer['customer']['address']['country'] );
				}
				/* The same rule applied to the deliveryAddress */
				if ( isset( $newDataContainer['customer']['deliveryAddress']['country'] ) ) {
					$newDataContainer['customer']['deliveryAddress']['countryCode'] = $newDataContainer['customer']['deliveryAddress']['country'];
					unset( $newDataContainer['customer']['deliveryAddress']['country'] );
				}
				if ( isset( $newDataContainer['signing'] ) ) {
					if ( ! isset( $newDataContainer['successUrl'] ) && isset( $newDataContainer['signing']['successUrl'] ) ) {
						$newDataContainer['successUrl'] = $newDataContainer['signing']['successUrl'];
					}
					if ( ! isset( $newDataContainer['failUrl'] ) && isset( $newDataContainer['signing']['failUrl'] ) ) {
						$newDataContainer['failUrl'] = $newDataContainer['signing']['failUrl'];
					}
					if ( ! isset( $newDataContainer['forceSigning'] ) && isset( $newDataContainer['signing']['forceSigning'] ) ) {
						$newDataContainer['forceSigning'] = $newDataContainer['signing']['forceSigning'];
					}
					unset( $newDataContainer['signing'] );
				}
				$this->jsonHosted = $this->getDataObject( $newDataContainer, true );
			}

			/**
			 * OmniCheckout Ruleset
			 */
			if ( strtolower( $newDataContainer['type'] ) == "omni" ) {
				if ( isset( $newDataContainer['specLine'] ) ) {
					$newDataContainer['orderLines'] = $newDataContainer['specLine'];
					unset( $newDataContainer['specLine'] );
				}
				if ( isset( $newDataContainer['specLines'] ) ) {
					$newDataContainer['orderLines'] = $newDataContainer['specLines'];
					unset( $newDataContainer['specLines'] );
				}
				/*
                 * OmniFrameJS helper.
                 */
				if ( ! isset( $newDataContainer['shopUrl'] ) ) {
					$newDataContainer['shopUrl'] = $this->checkoutShopUrl;
				}

				$orderlineProps = array(
					"artNo",
					"vatPcs",
					"vatPct",
					"unitMeasure",
					"quantity",
					"description",
					"unitAmountWithoutVat"
				);
				/**
				 * Sanitizing orderlines in case it's an orderline conversion from a simplified shopflow.
				 */
				if ( isset( $newDataContainer['orderLines'] ) && is_array( $newDataContainer['orderLines'] ) ) {
					$orderLineClean = array();
					/*
                     * Single Orderline Compatibility: When an order line is not properly sent to the handler, it has to be converted to an indexed array first,
                     */
					if ( $newDataContainer['type'] == "omni" ) {
						unset( $newDataContainer['paymentData'], $newDataContainer['customer'] );
					}
					if ( isset( $newDataContainer['orderLines']['artNo'] ) ) {
						$singleOrderLine                = $newDataContainer['orderLines'];
						$newDataContainer['orderLines'] = array( $singleOrderLine );
					}
					unset( $newDataContainer['customer'], $newDataContainer['paymentData'] );
					foreach ( $newDataContainer['orderLines'] as $orderLineId => $orderLineArray ) {
						if ( is_array( $orderLineArray ) ) {
							foreach ( $orderLineArray as $orderLineArrayKey => $orderLineArrayValue ) {
								if ( ! in_array( $orderLineArrayKey, $orderlineProps ) ) {
									unset( $orderLineArray[ $orderLineArrayKey ] );
								}
							}
							$orderLineClean[] = $orderLineArray;
						}
					}
					$newDataContainer['orderLines'] = $orderLineClean;
				}
				if ( isset( $newDataContainer['address'] ) ) {
					unset( $newDataContainer['address'] );
				}
				if ( isset( $newDataContainer['uniqueId'] ) ) {
					unset( $newDataContainer['uniqueId'] );
				}
				if ( isset( $newDataContainer['signing'] ) ) {
					if ( ! isset( $newDataContainer['successUrl'] ) && isset( $newDataContainer['signing']['successUrl'] ) ) {
						$newDataContainer['successUrl'] = $newDataContainer['signing']['successUrl'];
					}
					if ( ! isset( $newDataContainer['backUrl'] ) && isset( $newDataContainer['signing']['failUrl'] ) ) {
						$newDataContainer['backUrl'] = $newDataContainer['signing']['failUrl'];
					}
					unset( $newDataContainer['signing'] );
				}
				if ( isset( $newDataContainer['customer']['phone'] ) ) {
					if ( ! isset( $newDataContainer['customer']['mobile'] ) || ( isset( $newDataContainer['customer']['mobile'] ) && empty( $newDataContainer['customer']['mobile'] ) ) ) {
						$newDataContainer['customer']['mobile'] = $newDataContainer['customer']['phone'];
					}
					unset( $newDataContainer['customer']['phone'] );
				}
				if ( $updateCart ) {
					/*
                     * Return orderLines only, if this function is called as an updateCart.
                     */
					$newDataContainer = array(
						'orderLines' => is_array( $newDataContainer['orderLines'] ) ? $newDataContainer['orderLines'] : array()
					);
				}
				$this->jsonOmni = $newDataContainer;
			}
		}
		if ( isset( $newDataContainer['type'] ) ) {
			unset( $newDataContainer['type'] );
		}
		if ( isset( $newDataContainer['uniqueId'] ) ) {
			unset( $newDataContainer['uniqueId'] );
		}
		$returnJson = $this->toJson( $newDataContainer );

		return $returnJson;
	}

	/**
	 * @param int $method
	 *
	 * @return stdClass|string
	 * @deprecated 1.0.8
	 * @deprecated 1.1.8
	 */
	public function getBookedJsonObject( $method = RESURS_FLOW_TYPES::FLOW_NOT_SET ) {
		$returnObject = new \stdClass();
		if ( $method == RESURS_FLOW_TYPES::FLOW_SIMPLIFIED_FLOW ) {
			return $returnObject;
		} elseif ( $method == RESURS_FLOW_TYPES::FLOW_HOSTED_FLOW ) {
			return $this->jsonHosted;
		} else {
			return $this->jsonOmni;
		}
	}

	/**
	 * Convert array to json
	 *
	 * @param array $jsonData
	 *
	 * @return array|mixed|string|void
	 * @throws \Exception
	 * @deprecated 1.0.8
	 * @deprecated 1.1.8
	 */
	private function toJson( $jsonData = array() ) {
		if ( is_array( $jsonData ) || is_object( $jsonData ) ) {
			$jsonData = json_encode( $jsonData );
			if ( json_last_error() ) {
				throw new \Exception( __FUNCTION__ . ": " . json_last_error_msg(), json_last_error() );
			}
		}

		return $jsonData;
	}

	/**
	 * Create a simple engine for cURL, for use with for example hosted flow.
	 *
	 * @param string $url
	 * @param string $jsonData
	 * @param int $curlMethod POST, GET, DELETE, etc
	 *
	 * @return mixed
	 * @throws \Exception
	 * @deprecated 1.0.1 As this is a posting function, this has been set to go through the CURL library
	 * @deprecated 1.1.1 As this is a posting function, this has been set to go through the CURL library
	 */
	private function createJsonEngine( $url = '', $jsonData = "", $curlMethod = RESURS_CURL_METHODS::METHOD_POST ) {
		if ( empty( $this->CURL ) ) {
			$this->InitializeServices();
		}
		$CurlLibResponse = null;
		$this->CURL->setAuthentication( $this->username, $this->password );
		$this->CURL->setUserAgent( $this->myUserAgent );

		if ( $curlMethod == RESURS_CURL_METHODS::METHOD_POST ) {
			$CurlLibResponse = $this->CURL->doPost( $url, $jsonData, CURL_POST_AS::POST_AS_JSON );
		} else if ( $curlMethod == RESURS_CURL_METHODS::METHOD_PUT ) {
			$CurlLibResponse = $this->CURL->doPut( $url, $jsonData, CURL_POST_AS::POST_AS_JSON );
		} else {
			$CurlLibResponse = $this->CURL->doGet( $url, CURL_POST_AS::POST_AS_JSON );
		}
		if ( $CurlLibResponse['code'] >= 400 ) {
			$useResponseCode = $CurlLibResponse['code'];
			if ( is_object( $CurlLibResponse['parsed'] ) ) {
				$ResursResponse = $CurlLibResponse['parsed'];
				if ( isset( $ResursResponse->error ) ) {
					if ( isset( $ResursResponse->status ) ) {
						$useResponseCode = $ResursResponse->status;
					}
					throw new \Exception( $ResursResponse->error, $useResponseCode );
				}
				/*
                 * Must handle ecommerce errors too.
                 */
				if ( isset( $ResursResponse->errorCode ) ) {
					if ( $ResursResponse->errorCode > 0 ) {
						throw new \Exception( isset( $ResursResponse->description ) && ! empty( $ResursResponse->description ) ? $ResursResponse->description : "Unknown error in " . __FUNCTION__, $ResursResponse->errorCode );
					} else if ( $CurlLibResponse['code'] >= 500 ) {
						/*
                         * If there are any internal server errors returned, the errorCode tend to be unset (0) and therefore not trigged. In this case, as the server won't do anything good anyway, we should throw an exception
                         */
						throw new \Exception( isset( $ResursResponse->description ) && ! empty( $ResursResponse->description ) ? $ResursResponse->description : "Unknown error in " . __FUNCTION__, $ResursResponse->errorCode );
					}
				}
			} else {
				throw new \Exception( ! empty( $CurlLibResponse['body'] ) ? $CurlLibResponse['body'] : "Unknown error from server in " . __FUNCTION__, $CurlLibResponse['code'] );
			}
		} else {
			/*
             * Receiving code 200 here is flawless
             */
			return $CurlLibResponse;
		}
	}


	/////// SSL Related deprecations

	/**
	 * Generate a correctified stream context depending on what happened in openssl_guess(), which also is running in this operation.
	 *
	 * Function created for moments when ini_set() fails in openssl_guess() and you don't want to "recalculate" the location of a valid certificates.
	 * This normally occurs in improper configured environments (where this bulk of functions actually also has been tested in).
	 * Recommendation of Usage: Do not copy only those functions, use the full version of tornevall_curl.php since there may be dependencies in it.
	 *
	 * @return array
	 * @link http://developer.tornevall.net/apigen/TorneLIB-5.0/class-TorneLIB.Tornevall_cURL.html sslStreamContextCorrection() is a part of TorneLIB 5.0, described here
	 * @deprecated 1.0.8
	 * @deprecated 1.1.8
	 */
	public function sslStreamContextCorrection() {
		if ( ! $this->openSslGuessed ) {
			$this->openssl_guess( true );
		}
		$caCert    = $this->getCertFile();
		$sslVerify = true;
		$sslSetup  = array();
		if ( isset( $this->sslVerify ) ) {
			$sslVerify = $this->sslVerify;
		}
		if ( ! empty( $caCert ) ) {
			$sslSetup = array(
				'cafile'            => $caCert,
				'verify_peer'       => $sslVerify,
				'verify_peer_name'  => $sslVerify,
				'verify_host'       => $sslVerify,
				'allow_self_signed' => true
			);
		}

		return $sslSetup;
	}

	/**
	 * Automatically generates stream_context and appends it to whatever you need it for.
	 *
	 * Example:
	 *  $appendArray = array('http' => array("user_agent" => "MyUserAgent"));
	 *  $this->soapOptions = sslGetDefaultStreamContext($this->soapOptions, $appendArray);
	 *
	 * @param array $optionsArray
	 * @param array $selfContext
	 *
	 * @return array
	 * @link http://developer.tornevall.net/apigen/TorneLIB-5.0/class-TorneLIB.Tornevall_cURL.html sslGetOptionsStream() is a part of TorneLIB 5.0, described here
	 * @deprecated 1.0.8
	 * @deprecated 1.1.8
	 */
	public function sslGetOptionsStream( $optionsArray = array(), $selfContext = array() ) {
		$streamContextOptions = array();
		$sslCorrection        = $this->sslStreamContextCorrection();
		if ( count( $sslCorrection ) ) {
			$streamContextOptions['ssl'] = $this->sslStreamContextCorrection();
		}
		foreach ( $selfContext as $contextKey => $contextValue ) {
			$streamContextOptions[ $contextKey ] = $contextValue;
		}
		$optionsArray['stream_context'] = stream_context_create( $streamContextOptions );

		return $optionsArray;
	}

	/**
	 * SSL Cerificate Handler
	 *
	 * This method tries to handle SSL Certification locations where PHP can't handle that part itself. In some environments (normally customized), PHP sometimes have
	 * problems with finding certificates, in case for example where they are not placed in standard locations. When running the testing, we will also try to set up
	 * a correct location for the certificates, if any are found somewhere else.
	 *
	 * The default configuration for this method is to not run any test, since there should be no problems of running in properly installed environments.
	 * If there are known problems in the environment that is being used, you can try to set $testssl to true.
	 *
	 * At first, the variable $testssl is used to automatically try to find out if there is valid certificate bundles installed on the running system. In PHP 5.6.0 and higher
	 * this procedure is simplified with the help of openssl_get_cert_locations(), which gives us a default path to installed certificates. In this case we will first look there
	 * for the certificate bundle. If we do fail there, or if your system is running something older, the testing are running in guessing mode.
	 *
	 * The method is untested in Windows server environments when using OpenSSL.
	 *
	 * @param bool $forceTesting Force testing even if $testssl is disabled
	 *
	 * @link https://phpdoc.tornevall.net/TorneLIBv5/class-TorneLIB.Tornevall_cURL.html openssl_guess() is a part of TorneLIB 5.0, described here
	 * @return bool
	 * @deprecated 1.0.8
	 * @deprecated 1.1.8
	 */
	private function openssl_guess( $forceTesting = false ) {
		$pemLocation = "";
		if ( ini_get( 'open_basedir' ) == '' ) {
			if ( $this->testssl || $forceTesting ) {
				$this->openSslGuessed = true;
				if ( version_compare( PHP_VERSION, "5.6.0", ">=" ) && function_exists( "openssl_get_cert_locations" ) ) {
					$locations = openssl_get_cert_locations();
					if ( is_array( $locations ) ) {
						if ( isset( $locations['default_cert_file'] ) ) {
							/* If it exists don't bother */
							if ( file_exists( $locations['default_cert_file'] ) ) {
								$this->hasCertFile        = true;
								$this->useCertFile        = $locations['default_cert_file'];
								$this->hasDefaultCertFile = true;
							}
							if ( file_exists( $locations['default_cert_dir'] ) ) {
								$this->hasCertDir = true;
							}
							/* Sometimes certificates are located in a default location, which is /etc/ssl/certs - this part scans through such directories for a proper cert-file */
							if ( ! $this->hasCertFile && is_array( $this->sslPemLocations ) && count( $this->sslPemLocations ) ) {
								/* Loop through suggested locations and set a cafile if found */
								foreach ( $this->sslPemLocations as $pemLocation ) {
									if ( file_exists( $pemLocation ) ) {
										ini_set( 'openssl.cafile', $pemLocation );
										$this->useCertFile = $pemLocation;
										$this->hasCertFile = true;
									}
								}
							}
						}
					}
					/* On guess, disable verification if failed */
					if ( ! $this->hasCertFile ) {
						$this->setSslVerify( false );
					}
				} else {
					/* If we run on other PHP versions than 5.6.0 or higher, try to fall back into a known directory */
					if ( $this->testssldeprecated ) {
						if ( ! $this->hasCertFile && is_array( $this->sslPemLocations ) && count( $this->sslPemLocations ) ) {
							/* Loop through suggested locations and set a cafile if found */
							foreach ( $this->sslPemLocations as $pemLocation ) {
								if ( file_exists( $pemLocation ) ) {
									ini_set( 'openssl.cafile', $pemLocation );
									$this->useCertFile = $pemLocation;
									$this->hasCertFile = true;
								}
							}
						}
						if ( ! $this->hasCertFile ) {
							$this->setSslVerify( false );
						}
					}
				}
			}
		} else {
			// Assume there is a valid certificate if jailed by open_basedir
			$this->hasCertFile = true;

			return true;
		}

		return $this->hasCertFile;
	}

	/**
	 * Return the current certificate bundle file, chosen by autodetection
	 * @return string
	 * @deprecated 1.0.8
	 * @deprecated 1.1.8
	 */
	public function getCertFile() {
		return $this->useCertFile;
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
	 * @deprecated 1.0.8 It is strongly recommended that you are generating all this by yourself in an integratio
	 * @deprecated 1.1.8 It is strongly recommended that you are generating all this by yourself in an integration
	 */
	public function setFormTemplateRules( $customerType, $methodType, $fieldArray ) {
		$this->formTemplateRuleArray = array(
			$customerType => array(
				'fields' => array(
					$methodType => $fieldArray
				)
			)
		);

		return $this->formTemplateRuleArray;
	}

	/**
	 * Retrieve html-form rules for each payment method type, including regular expressions for the form fields, to validate against.
	 *
	 * @return array
	 * @deprecated 1.0.8 It is strongly recommended that you are generating all this by yourself in an integration.
	 * @deprecated 1.1.8 It is strongly recommended that you are generating all this by yourself in an integration.
	 */
	private function getFormTemplateRules() {

		// TODO: New regex for swedish phone numbers that supports +4607[...]-typos (see the extra 0)
		// ^((0|\\+46||0046)[ |-]?(200|20|70|73|76|74|1-9{0,2})([ |-]?[0-9]){5,8})?$

		$formTemplateRules = array(
			'NATURAL' => array(
				'fields' => array(
					'INVOICE'          => array(
						'applicant-government-id',
						'applicant-telephone-number',
						'applicant-mobile-number',
						'applicant-email-address'
					),
					'CARD'             => array( 'applicant-government-id', 'card-number' ),
					'REVOLVING_CREDIT' => array(
						'applicant-government-id',
						'applicant-telephone-number',
						'applicant-mobile-number',
						'applicant-email-address'
					),
					'PART_PAYMENT'     => array(
						'applicant-government-id',
						'applicant-telephone-number',
						'applicant-mobile-number',
						'applicant-email-address'
					)
				)
			),
			'LEGAL'   => array(
				'fields' => array(
					'INVOICE' => array(
						'applicant-government-id',
						'applicant-telephone-number',
						'applicant-mobile-number',
						'applicant-email-address',
						'applicant-full-name',
						'contact-government-id'
					),
				)
			),
			'display' => array(
				'applicant-government-id',
				'card-number',
				'applicant-full-name',
				'contact-government-id'
			),
			'regexp'  => array(
				'SE' => array(
					'NATURAL' => array(
						'applicant-government-id'    => '^(18\d{2}|19\d{2}|20\d{2}|\d{2})(0[1-9]|1[0-2])([0][1-9]|[1-2][0-9]|3[0-1])(\-|\+)?([\d]{4})$',
						'applicant-telephone-number' => '^(0|\+46|0046)[ |-]?(200|20|70|73|76|74|[1-9][0-9]{0,2})([ |-]?[0-9]){5,8}$',
						'applicant-mobile-number'    => '^(0|\+46|0046)[ |-]?(200|20|70|73|76|74|[1-9][0-9]{0,2})([ |-]?[0-9]){5,8}$',
						'applicant-email-address'    => '^[A-Za-z0-9!#%&\'*+/=?^_`~-]+(\.[A-Za-z0-9!#%&\'*+/=?^_`~-]+)*@([A-Za-z0-9]+)(([\.\-]?[a-zA-Z0-9]+)*)\.([A-Za-z]{2,})$',
						'card-number'                => '^([1-9][0-9]{3}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4})$'
					),
					'LEGAL'   => array(
						'applicant-government-id'    => '^(16\d{2}|18\d{2}|19\d{2}|20\d{2}|\d{2})(\d{2})(\d{2})(\-|\+)?([\d]{4})$',
						'applicant-telephone-number' => '^(0|\+46|0046)[ |-]?(200|20|70|73|76|74|[1-9][0-9]{0,2})([ |-]?[0-9]){5,8}$',
						'applicant-mobile-number'    => '^(0|\+46|0046)[ |-]?(200|20|70|73|76|74|[1-9][0-9]{0,2})([ |-]?[0-9]){5,8}$',
						'applicant-email-address'    => '^[A-Za-z0-9!#%&\'*+/=?^_`~-]+(\.[A-Za-z0-9!#%&\'*+/=?^_`~-]+)*@([A-Za-z0-9]+)(([\.\-]?[a-zA-Z0-9]+)*)\.([A-Za-z]{2,})$',
						'card-number'                => '^([1-9][0-9]{3}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4})$'
					)
				),
				'DK' => array(
					'NATURAL' => array(
						'applicant-government-id'    => '^((3[0-1])|([1-2][0-9])|(0[1-9]))((1[0-2])|(0[1-9]))(\d{2})(\-)?([\d]{4})$',
						'applicant-telephone-number' => '^(\+45|0045|)?[ |-]?[2-9]([ |-]?[0-9]){7,7}$',
						'applicant-mobile-number'    => '^(\+45|0045|)?[ |-]?[2-9]([ |-]?[0-9]){7,7}$',
						'applicant-email-address'    => '^[A-Za-z0-9!#%&\'*+/=?^_`~-]+(\.[A-Za-z0-9!#%&\'*+/=?^_`~-]+)*@([A-Za-z0-9]+)(([\.\-]?[a-zA-Z0-9]+)*)\.([A-Za-z]{2,})$',
						'card-number'                => '^([1-9][0-9]{3}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4})$'
					),
					'LEGAL'   => array(
						'applicant-government-id'    => null,
						'applicant-telephone-number' => '^(\+45|0045|)?[ |-]?[2-9]([ |-]?[0-9]){7,7}$',
						'applicant-mobile-number'    => '^(\+45|0045|)?[ |-]?[2-9]([ |-]?[0-9]){7,7}$',
						'applicant-email-address'    => '^[A-Za-z0-9!#%&\'*+/=?^_`~-]+(\.[A-Za-z0-9!#%&\'*+/=?^_`~-]+)*@([A-Za-z0-9]+)(([\.\-]?[a-zA-Z0-9]+)*)\.([A-Za-z]{2,})$',
						'card-number'                => '^([1-9][0-9]{3}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4})$'
					)
				),
				'NO' => array(
					'NATURAL' => array(
						'applicant-government-id'    => '^([0][1-9]|[1-2][0-9]|3[0-1])(0[1-9]|1[0-2])(\d{2})(\-)?([\d]{5})$',
						'applicant-telephone-number' => '^(\+47|0047|)?[ |-]?[2-9]([ |-]?[0-9]){7,7}$',
						'applicant-mobile-number'    => '^(\+47|0047|)?[ |-]?[2-9]([ |-]?[0-9]){7,7}$',
						'applicant-email-address'    => '^[A-Za-z0-9!#%&\'*+/=?^_`~-]+(\.[A-Za-z0-9!#%&\'*+/=?^_`~-]+)*@([A-Za-z0-9]+)(([\.\-]?[a-zA-Z0-9]+)*)\.([A-Za-z]{2,})$',
						'card-number'                => '^([1-9][0-9]{3}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4})$'
					),
					'LEGAL'   => array(
						'applicant-government-id'    => '^([89]([ |-]?[0-9]){8})$',
						'applicant-telephone-number' => '^(\+47|0047|)?[ |-]?[2-9]([ |-]?[0-9]){7,7}$',
						'applicant-mobile-number'    => '^(\+47|0047|)?[ |-]?[2-9]([ |-]?[0-9]){7,7}$',
						'applicant-email-address'    => '^[A-Za-z0-9!#%&\'*+/=?^_`~-]+(\.[A-Za-z0-9!#%&\'*+/=?^_`~-]+)*@([A-Za-z0-9]+)(([\.\-]?[a-zA-Z0-9]+)*)\.([A-Za-z]{2,})$',
						'card-number'                => '^([1-9][0-9]{3}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4})$'
					)
				),
				'FI' => array(
					'NATURAL' => array(
						'applicant-government-id'    => '^([\d]{6})[\+\-A]([\d]{3})([0123456789ABCDEFHJKLMNPRSTUVWXY])$',
						'applicant-telephone-number' => '^((\+358|00358|0)[-| ]?(1[1-9]|[2-9]|[1][0][1-9]|201|2021|[2][0][2][4-9]|[2][0][3-8]|29|[3][0][1-9]|71|73|[7][5][0][0][3-9]|[7][5][3][0][3-9]|[7][5][3][2][3-9]|[7][5][7][5][3-9]|[7][5][9][8][3-9]|[5][0][0-9]{0,2}|[4][0-9]{1,3})([-| ]?[0-9]){3,10})?$',
						'applicant-mobile-number'    => '^((\+358|00358|0)[-| ]?(1[1-9]|[2-9]|[1][0][1-9]|201|2021|[2][0][2][4-9]|[2][0][3-8]|29|[3][0][1-9]|71|73|[7][5][0][0][3-9]|[7][5][3][0][3-9]|[7][5][3][2][3-9]|[7][5][7][5][3-9]|[7][5][9][8][3-9]|[5][0][0-9]{0,2}|[4][0-9]{1,3})([-| ]?[0-9]){3,10})?$',
						'applicant-email-address'    => '^[A-Za-z0-9!#%&\'*+/=?^_`~-]+(\.[A-Za-z0-9!#%&\'*+/=?^_`~-]+)*@([A-Za-z0-9]+)(([\.\-]?[a-zA-Z0-9]+)*)\.([A-Za-z]{2,})$',
						'card-number'                => '^([1-9][0-9]{3}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4})$'
					),
					'LEGAL'   => array(
						'applicant-government-id'    => '^((\d{7})(\-)?\d)$',
						'applicant-telephone-number' => '^((\+358|00358|0)[-| ]?(1[1-9]|[2-9]|[1][0][1-9]|201|2021|[2][0][2][4-9]|[2][0][3-8]|29|[3][0][1-9]|71|73|[7][5][0][0][3-9]|[7][5][3][0][3-9]|[7][5][3][2][3-9]|[7][5][7][5][3-9]|[7][5][9][8][3-9]|[5][0][0-9]{0,2}|[4][0-9]{1,3})([-| ]?[0-9]){3,10})?$',
						'applicant-mobile-number'    => '^((\+358|00358|0)[-| ]?(1[1-9]|[2-9]|[1][0][1-9]|201|2021|[2][0][2][4-9]|[2][0][3-8]|29|[3][0][1-9]|71|73|[7][5][0][0][3-9]|[7][5][3][0][3-9]|[7][5][3][2][3-9]|[7][5][7][5][3-9]|[7][5][9][8][3-9]|[5][0][0-9]{0,2}|[4][0-9]{1,3})([-| ]?[0-9]){3,10})?$',
						'applicant-email-address'    => '^[A-Za-z0-9!#%&\'*+/=?^_`~-]+(\.[A-Za-z0-9!#%&\'*+/=?^_`~-]+)*@([A-Za-z0-9]+)(([\.\-]?[a-zA-Z0-9]+)*)\.([A-Za-z]{2,})$',
						'card-number'                => '^([1-9][0-9]{3}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4})$'
					)
				),
			)
		);
		if ( isset( $this->formTemplateRuleArray ) && is_array( $this->formTemplateRuleArray ) && count( $this->formTemplateRuleArray ) ) {
			foreach ( $this->formTemplateRuleArray as $cType => $cArray ) {
				$formTemplateRules[ $cType ] = $cArray;
			}
		}

		return $formTemplateRules;
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
	 * @deprecated 1.0.8 It is strongly recommended that you are generating all this by yourself in an integration
	 * @deprecated 1.1.8 It is strongly recommended that you are generating all this by yourself in an integration
	 */
	public function getRegEx( $formFieldName = '', $countryCode, $customerType ) {
		$returnRegEx = array();

		$templateRule = $this->getFormTemplateRules();
		$returnRegEx  = $templateRule['regexp'];

		if ( empty( $countryCode ) ) {
			throw new \Exception( __FUNCTION__ . ": Country code is missing in getRegEx-request for form fields", \RESURS_EXCEPTIONS::REGEX_COUNTRYCODE_MISSING );
		}
		if ( empty( $customerType ) ) {
			throw new \Exception( __FUNCTION__ . ": Customer type is missing in getRegEx-request for form fields", \RESURS_EXCEPTIONS::REGEX_CUSTOMERTYPE_MISSING );
		}

		if ( ! empty( $countryCode ) && isset( $returnRegEx[ strtoupper( $countryCode ) ] ) ) {
			$returnRegEx = $returnRegEx[ strtoupper( $countryCode ) ];
			if ( ! empty( $customerType ) ) {
				if ( ! is_array( $customerType ) ) {
					if ( isset( $returnRegEx[ strtoupper( $customerType ) ] ) ) {
						$returnRegEx = $returnRegEx[ strtoupper( $customerType ) ];
						if ( isset( $returnRegEx[ strtolower( $formFieldName ) ] ) ) {
							$returnRegEx = $returnRegEx[ strtolower( $formFieldName ) ];
						}
					}
				} else {
					foreach ( $customerType as $cType ) {
						if ( isset( $returnRegEx[ strtoupper( $cType ) ] ) ) {
							$returnRegEx = $returnRegEx[ strtoupper( $cType ) ];
							if ( isset( $returnRegEx[ strtolower( $formFieldName ) ] ) ) {
								$returnRegEx = $returnRegEx[ strtolower( $formFieldName ) ];
							}
						}
					}
				}
			}
		}

		return $returnRegEx;
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
	 * @deprecated 1.0.8 It is strongly recommended that you are generating all this by yourself in an integration
	 * @deprecated 1.1.8 It is strongly recommended that you are generating all this by yourself in an integration
	 */
	public function canHideFormField( $formField = "", $canThrow = false ) {
		$canHideSet = false;

		if ( is_array( $this->templateFieldsByMethodResponse ) && count( $this->templateFieldsByMethodResponse ) && isset( $this->templateFieldsByMethodResponse['fields'] ) && isset( $this->templateFieldsByMethodResponse['display'] ) ) {
			$currentDisplay = $this->templateFieldsByMethodResponse['display'];
			if ( in_array( $formField, $currentDisplay ) ) {
				$canHideSet = false;
			} else {
				$canHideSet = true;
			}
		} else {
			/* Make sure that we don't hide things that does not exists in our configuration */
			$canHideSet = false;
		}

		if ( $canThrow && ! $canHideSet ) {
			throw new \Exception( __FUNCTION__ . ": templateFieldsByMethodResponse is empty. You have to run getTemplateFieldsByMethodType first", \RESURS_EXCEPTIONS::FORMFIELD_CANHIDE_EXCEPTION );
		}

		return $canHideSet;
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
	 * @deprecated 1.0.8 It is strongly recommended that you are generating all this by yourself in an integration
	 * @deprecated 1.1.8 It is strongly recommended that you are generating all this by yourself in an integration
	 */
	public function getTemplateFieldsByMethodType( $paymentMethodName = "", $customerType = "", $specificType = "" ) {
		$templateRules     = $this->getFormTemplateRules();
		$returnedRules     = array();
		$returnedRuleArray = array();
		/* If the client is requesting a getPaymentMethod-object we'll try to handle that information instead (but not if it is empty) */
		if ( is_object( $paymentMethodName ) || is_array( $paymentMethodName ) ) {
			if ( is_object( $paymentMethodName ) ) {
				// Prevent arrays to go through here and crash something
				if ( ! is_array( $customerType ) ) {
					if ( isset( $templateRules[ strtoupper( $customerType ) ] ) && isset( $templateRules[ strtoupper( $customerType ) ]['fields'][ strtoupper( $paymentMethodName->specificType ) ] ) ) {
						$returnedRuleArray = $templateRules[ strtoupper( $customerType ) ]['fields'][ strtoupper( $paymentMethodName->specificType ) ];
					}
				}
			} else if ( is_array( $paymentMethodName ) ) {
				/*
				 * This should probably not happen and the developers should probably also stick to objects as above.
				 */
				if ( count( $paymentMethodName ) ) {
					if ( isset( $templateRules[ strtoupper( $customerType ) ] ) && isset( $templateRules[ strtoupper( $customerType ) ]['fields'][ strtoupper( $paymentMethodName['specificType'] ) ] ) ) {
						$returnedRuleArray = $templateRules[ strtoupper( $customerType ) ]['fields'][ strtoupper( $paymentMethodName['specificType'] ) ];
					}
				}
			}
		} else {
			if ( isset( $templateRules[ strtoupper( $customerType ) ] ) && isset( $templateRules[ strtoupper( $customerType ) ]['fields'][ strtoupper( $paymentMethodName ) ] ) ) {
				$returnedRuleArray = $templateRules[ strtoupper( $customerType ) ]['fields'][ strtoupper( $specificType ) ];
			}
		}
		$returnedRules                        = array(
			'fields'  => $returnedRuleArray,
			'display' => $templateRules['display'],
			'regexp'  => $templateRules['regexp']
		);
		$this->templateFieldsByMethodResponse = $returnedRules;

		return $returnedRules;
	}

	/**
	 * Get template fields by a specific payment method. This function retrieves the payment method in real time.
	 *
	 * @param string $paymentMethodName
	 *
	 * @return array
	 * @deprecated 1.0.8 It is strongly recommended that you are generating all this by yourself in an integration
	 * @deprecated 1.1.8 It is strongly recommended that you are generating all this by yourself in an integration
	 */
	public function getTemplateFieldsByMethod( $paymentMethodName = "" ) {
		return $this->getTemplateFieldsByMethodType( $this->getPaymentMethodSpecific( $paymentMethodName ) );
	}

	/**
	 * Get form fields by a specific payment method. This function retrieves the payment method in real time.
	 *
	 * @param string $paymentMethodName
	 *
	 * @return array
	 * @deprecated 1.0.8 It is strongly recommended that you are generating all this by yourself in an integration
	 * @deprecated 1.1.8 It is strongly recommended that you are generating all this by yourself in an integration
	 */
	public function getFormFieldsByMethod( $paymentMethodName = "" ) {
		return $this->getTemplateFieldsByMethod( $paymentMethodName );
	}




	/////////// DEPRECATED STUFF

	/**
	 * Configuration storage: Find out if we have internal configuration enabled. The config file supports serialized (php) data and json-encoded content, but saves all data serialized.
	 *
	 * @return bool
	 * @throws \Exception
	 * @deprecated 1.0.1
	 * @deprecated 1.1.1
	 */
	private function hasConfiguration() {
		if ( version_compare( PHP_VERSION, '5.3.0', "<" ) ) {
			if ( ! $this->allowObsoletePHP ) {
				throw new \Exception( __FUNCTION__ . ": PHP 5.3 or later are required for this module to work. If you feel safe with running this with an older version, you can use the function setObsoletePhp()", 500 );
			}
		}
		/* Internally stored configuration - has to be activated on use */
		if ( $this->configurationInternal ) {
			if ( defined( 'RB_API_CONFIG' ) && file_exists( RB_API_CONFIG . "/" . $this->configurationSystem ) ) {
				$this->configurationStorage = RB_API_CONFIG . "/" . $this->configurationSystem . "/config.data";
			} elseif ( file_exists( __DIR__ . "/" . $this->configurationSystem ) ) {
				$this->configurationStorage = __DIR__ . "/" . $this->configurationSystem . "/config.data";
			}
			/* Initialize configuration storage if exists */
			if ( ! empty( $this->configurationStorage ) && ! file_exists( $this->configurationStorage ) ) {
				$defaults = array(
					'system' => array(
						'representative' => $this->username
					)
				);

				@file_put_contents( $this->configurationStorage, serialize( $defaults ), LOCK_EX );
				if ( ! file_exists( $this->configurationStorage ) ) {
					/* Disable internal configuration during this call, if no file has been found after initialization */
					$this->configurationInternal = false;

					return false;
				}
			}
		}
		if ( $this->configurationInternal ) {
			$this->config = file_get_contents( $this->configurationStorage );
			$getArray     = @unserialize( $this->config );
			if ( ! is_array( $getArray ) ) {
				$getArray = @json_decode( $this->config, true );
			}
			if ( ! is_array( $getArray ) ) {
				$this->configurationInternal = false;

				return false;
			} else {
				$this->configurationArray = $getArray;

				return true;
			}
		}

		return false;
	}

	/**
	 * If configuration file exists, this is the place where we're updating the content of it.
	 *
	 * @param string $arrayName The name of the array we're going to save
	 * @param array $arrayContent The content of the array
	 *
	 * @return bool If save is successful, we return true
	 * @deprecated 1.0.1
	 * @deprecated 1.1.1
	 */
	private function updateConfig( $arrayName, $arrayContent = array() ) {
		/* Make sure that the received array is really an array, since objects from ecom may be trashed during serialziation */
		$arrayContent = $this->objectsIntoArray( $arrayContent );
		if ( $this->configurationInternal && ! empty( $arrayName ) ) {
			$this->configurationArray[ $arrayName ]               = $arrayContent;
			$this->configurationArray['lastUpdate'][ $arrayName ] = time();
			$serialized                                           = @serialize( $this->configurationArray );
			if ( file_exists( $this->configurationStorage ) ) {
				@file_put_contents( $this->configurationStorage, $serialized, LOCK_EX );

				return true;
			}
		}

		return false;
	}

	/**
	 * Returns an array with stored configuration (if stored configuration are enabled)
	 * @return array
	 * @deprecated 1.0.1
	 * @deprecated 1.1.1
	 */
	public function getConfigurationCache() {
		return $this->configurationArray;
	}

	/**
	 * Special set up for internal tests putting tests outside mock but still in test
	 * @deprecated 1.0.1 As this has not been used for a long time it will be removed in a future release
	 * @deprecated 1.1.1 As this has not been used for a long time it will be removed in a future release
	 */
	public function setNonMock() {
		$this->env_test = "https://test.resurs.com/ecommerce/ws/V4/";
	}

	/**
	 * Get the list of Resurs Bank payment methods from cache, instead of live (Cache function needs to be active)
	 *
	 * @return array
	 * @throws \Exception
	 * @deprecated 1.0.1
	 * @deprecated 1.1.1
	 */
	public function getPaymentMethodsCache() {
		if ( $this->hasConfiguration() ) {
			if ( isset( $this->configurationArray['getPaymentMethods'] ) && is_array( $this->configurationArray['getPaymentMethods'] ) && count( $this->configurationArray ) && ! $this->cacheExpired( 'getPaymentMethods' ) ) {
				return $this->configurationArray['getPaymentMethods'];
			} else {
				return $this->objectsIntoArray( $this->getPaymentMethods() );
			}
		}
		throw new \Exception( __FUNCTION__ . ": Can not fetch payment methods from cache. You must enable internal caching first.", \RESURS_EXCEPTIONS::PAYMENT_METHODS_CACHE_DISABLED );
	}

	/**
	 * Test if a stored configuration (cache) has expired and needs to be renewed.
	 *
	 * @param $cachedArrayName
	 *
	 * @return bool
	 * @deprecated 1.0.1
	 * @deprecated 1.1.1
	 */
	private function cacheExpired( $cachedArrayName ) {
		if ( $this->getLastCall( $cachedArrayName ) >= $this->configurationCacheTimeout ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Get annuityfactors from payment method through cache, instead of live (Cache function needs to be active)
	 *
	 * @param string $paymentMethod Given payment method
	 *
	 * @return array
	 * @throws \Exception
	 * @deprecated 1.0.1
	 * @deprecated 1.1.1
	 */
	public function getAnnuityFactorsCache( $paymentMethod ) {
		if ( $this->hasConfiguration() ) {
			if ( isset( $this->configurationArray['getAnnuityFactors'] ) && isset( $this->configurationArray['getAnnuityFactors'][ $paymentMethod ] ) && is_array( $this->configurationArray['getAnnuityFactors'] ) && is_array( $this->configurationArray['getAnnuityFactors'][ $paymentMethod ] ) && count( $this->configurationArray['getAnnuityFactors'][ $paymentMethod ] ) && ! $this->cacheExpired( 'getPaymentMethods' ) ) {
				return $this->configurationArray['getAnnuityFactors'][ $paymentMethod ];
			} else {
				return $this->objectsIntoArray( $this->getAnnuityFactors( $paymentMethod ) );
			}
		} else {
			throw new \Exception( __FUNCTION__ . ": Can not fetch annuity factors from cache. You must enable internal caching first.", \RESURS_EXCEPTIONS::ANNUITY_FACTORS_CACHE_DISABLED );
		}
	}

	/**
	 * getAnnuityFactors Displays
	 *
	 * Retrieves the annuity factors for a given payment method. The duration is given is months. While this makes most sense for payment methods that consist of part payments (i.e. new account), it is possible to use for all types. It returns a list of with one annuity factor per payment plan of the payment method. There are typically between three and six payment plans per payment method. If no payment method are given to this function, the first available method will be used (meaning this function will also make a getPaymentMethods()-request which will delay the primary call a bit).
	 * @link https://test.resurs.com/docs/display/ecom/Get+Annuity+Factors Ecommerce Docs for getAnnuityFactors
	 *
	 * @param string $paymentMethodId
	 *
	 * @return mixed
	 * @throws \Exception
	 * @deprecated 1.0.1
	 * @deprecated 1.1.1
	 */
	public function getAnnuityFactorsDeprecated( $paymentMethodId = '' ) {
		$this->InitializeServices();
		$firstMethod = array();
		if ( empty( $paymentMethodId ) || is_null( $paymentMethodId ) ) {
			$methodsAvailable = $this->getPaymentMethods();
			if ( is_array( $methodsAvailable ) && count( $methodsAvailable ) ) {
				$firstMethod     = array_pop( $methodsAvailable );
				$paymentMethodId = isset( $firstMethod->id ) ? $firstMethod->id : null;
				if ( empty( $paymentMethodId ) ) {
					throw new \Exception( __FUNCTION__ . ": getAnnuityFactorsException  No available payment method", \RESURS_EXCEPTIONS::ANNUITY_FACTORS_METHOD_UNAVAILABLE );
				}
			}
		}
		$annuityParameters = new resurs_getAnnuityFactors( $paymentMethodId );
		$return            = $this->getDataObject( $this->simplifiedShopFlowService->getAnnuityFactors( $annuityParameters )->return );
		if ( $this->configurationInternal ) {
			$CurrentAnnuityFactors                     = isset( $this->configurationArray['getAnnuityFactors'] ) ? $this->configurationArray['getAnnuityFactors'] : array();
			$CurrentAnnuityFactors[ $paymentMethodId ] = $return;
			$this->updateConfig( 'getAnnuityFactors', $CurrentAnnuityFactors );
		}

		return $return;
	}




	/////////// PRIMARY INTERNAL SHOPFLOW SECTION
	////// HELPERS
	/**
	 * Generates a unique "preferredId" out of a datestamp
	 *
	 * @param int $maxLength The maximum recommended length of a preferred id is currently 25. The order numbers may be shorter (the minimum length is 14, but in that case only the timestamp will be returned)
	 * @param string $prefix Prefix to prepend at unique id level
	 * @param bool $dualUniq Be paranoid and sha1-encrypt the first random uniq id first.
	 *
	 * @return string
	 * @since 1.0.0
	 * @since 1.1.0
	 * @deprecated 1.0.13 Will be replaced with getPreferredPaymentId
	 * @deprecated 1.1.13 Will be replaced with getPreferredPaymentId
	 */
	public function getPreferredId( $maxLength = 25, $prefix = "", $dualUniq = true ) {
		return $this->getPreferredPaymentId($maxLength, $prefix, $dualUniq);
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
		if ( ! empty( $this->preferredId ) && !$force ) {
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
	 * Generates a unique "preferredId" out of a datestamp
	 *
	 * @param int $maxLength The maximum recommended length of a preferred id is currently 25. The order numbers may be shorter (the minimum length is 14, but in that case only the timestamp will be returned)
	 * @param string $prefix Prefix to prepend at unique id level
	 * @param bool $dualUniq Be paranoid and sha1-encrypt the first random uniq id first.
	 *
	 * @return string
	 * @deprecated 1.0.2 Use getPreferredId directly instead
	 * @deprected 1.1.2 Use getPreferredId directly instead
	 */
	public function generatePreferredId( $maxLength = 25, $prefix = "", $dualUniq = true ) {
		return $this->getPreferredId( $maxLength, $prefix, $dualUniq );
	}

	/**
	 * Check if there is a parameter send through externals, during a bookPayment
	 *
	 * @param string $parameter
	 * @param array $externalParameters
	 * @param bool $getValue
	 *
	 * @return bool|null
	 *
	 * @deprecated 1.0.1 Switching over to a more fresh API
	 * @deprecated 1.1.1 Switching over to a more fresh API
	 */
	private function bookHasParameter( $parameter = '', $externalParameters = array(), $getValue = false ) {
		if ( is_array( $externalParameters ) ) {
			if ( isset( $externalParameters[ $parameter ] ) ) {
				if ( $getValue ) {
					return $externalParameters[ $parameter ];
				} else {
					return true;
				}
			}
		}
		if ( $getValue ) {
			return null;
		}

		return false;
	}

	/**
	 * Get extra parameters during a bookPayment
	 *
	 * @param string $parameter
	 * @param array $externalParameters
	 *
	 * @return bool|null
	 *
	 * @deprecated 1.0.1 Switching over to a more fresh API
	 * @deprecated 1.1.1 Switching over to a more fresh API
	 */
	private function getBookParameter( $parameter = '', $externalParameters = array() ) {
		return $this->bookHasParameter( $parameter, $externalParameters );
	}

	/**
	 * Prepare bookedCallbackUrl (Resurs Checkout)
	 *
	 * @param string $bookedCallbackUrl
	 *
	 * @deprecated 1.0.1 Never used, since we preferred to use the former callbacks instead (Recommended)
	 * @deprecated 1.1.1 Never used, since we preferred to use the former callbacks instead (Recommended)
	 */
	public function setBookedCallbackUrl( $bookedCallbackUrl = "" ) {
		if ( ! empty( $bookedCallbackUrl ) ) {
			$this->_bookedCallbackUrl = $bookedCallbackUrl;
		}
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
	 * Returns true if updateCart has interfered with the specRows (this is a good way to indicate if something went wrong with the handling)
	 *
	 * @return bool
	 * @deprecated 1.0.1 Never used
	 * @deprecated 1.1.1 Never used
	 */
	public function isCartFixed() {
		return $this->bookPaymentCartFixed;
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

		if (is_null($articleType)) {
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
			if (isset($this->SpecLines['artNo'])) {
				$this->SpecLines = array(
					$this->SpecLines
				);
			}
			foreach ( $this->SpecLines as $specIndex => $specRow ) {
				if ( is_array($specRow) && ! isset( $specRow['unitMeasure'] ) ) {
					$this->SpecLines[ $specIndex ]['unitMeasure'] = $this->defaultUnitMeasure;
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
	 * be set with the function setPreferredPaymentService() instead. If no preferred are set, we will fall back to the simplified flow.
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
			if ($myFlow !== RESURS_FLOW_TYPES::FLOW_RESURS_CHECKOUT) {
				$paymentMethodInfo = $this->getPaymentMethodSpecific( $payment_id_or_method );
				if ( isset( $paymentMethodInfo->id ) ) {
					$this->PaymentMethod = $paymentMethodInfo;
				}
			}
		} catch (\Exception $e) {

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
		if ( trim( strtolower( $this->username ) ) == "exshop" ) {
			throw new \Exception( "The use of exshop is no longer supported", \RESURS_EXCEPTIONS::EXSHOP_PROHIBITED );
		}
		$error  = array();
		$myFlow = $this->getPreferredPaymentFlowService();
		// Using this function to validate that card data info is properly set up during the deprecation state in >= 1.0.2/1.1.1
		if ( $myFlow == RESURS_FLOW_TYPES::FLOW_SIMPLIFIED_FLOW ) {
			$paymentMethodInfo = $this->getPaymentMethodSpecific( $payment_id_or_method );
			if ( isset($paymentMethodInfo) && is_object($paymentMethodInfo) ) {
				if (isset($paymentMethodInfo->specificType) && $paymentMethodInfo->specificType == "CARD" || $paymentMethodInfo->specificType == "NEWCARD" || $paymentMethodInfo->specificType == "REVOLVING_CREDIT") {
					$this->validateCardData( $paymentMethodInfo->specificType );
				}
			}
			$myFlowResponse  = $this->postService( 'bookPayment', $this->Payload );
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
			$responseCode   = $this->CURL->getResponseCode( $hostedResponse );
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
	 * @since 1.0.5
	 * @since 1.1.5
	 */
	public function bookSignedPayment( $paymentId = '' ) {
		return $this->postService( "bookSignedPayment", array( 'paymentId' => $paymentId ) );
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
				if ( $this->envCountry == ResursCountry::COUNTRY_DK ) {
					$this->defaultUnitMeasure = "st";
				} else if ( $this->envCountry == ResursCountry::COUNTRY_NO ) {
					$this->defaultUnitMeasure = "st";
				} else if ( $this->envCountry == ResursCountry::COUNTRY_FI ) {
					$this->defaultUnitMeasure = "kpl";
				} else {
					$this->defaultUnitMeasure = "st";
				}
			} else {
				$this->defaultUnitMeasure = "st";
			}
		}
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

		if ( empty( $this->defaultUnitMeasure ) ) {
			$this->setDefaultUnitMeasure();
		}
		if ( ! $this->enforceService ) {
			$this->setPreferredPaymentFlowService( RESURS_FLOW_TYPES::FLOW_SIMPLIFIED_FLOW );
		}
		if ( $this->enforceService === RESURS_FLOW_TYPES::FLOW_RESURS_CHECKOUT ) {
			if ( empty( $payment_id_or_method ) && empty($this->preferredId)) {
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
		} else if (!isset($this->Payload['orderLines']) && count($this->SpecLines)) {
			// Fix desynched orderlines
			$this->Payload['orderLines'] = $this->SpecLines;
			$this->renderPaymentSpec();
		}
		if ( $this->enforceService === RESURS_FLOW_TYPES::FLOW_HOSTED_FLOW || $this->enforceService === RESURS_FLOW_TYPES::FLOW_SIMPLIFIED_FLOW ) {
			$paymentDataPayload ['paymentData'] = array(
				'paymentMethodId'   => $payment_id_or_method,
				'preferredId'       => $this->getPreferredPaymentId(),
				'customerIpAddress' => $this->getCustomerIp()
			);
			if ( $this->enforceService === RESURS_FLOW_TYPES::FLOW_SIMPLIFIED_FLOW ) {
				if ( ! isset( $this->Payload['storeId'] ) && ! empty( $this->storeId ) ) {
					$this->Payload['storeId'] = $this->storeId;
				}
			}
			$this->handlePayload( $paymentDataPayload );
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
					if ($this->validateCheckoutShopUrl) {
						$shopUrlValidate = $this->NETWORK->getUrlDomain( $this->checkoutShopUrl );
						$this->checkoutShopUrl = $shopUrlValidate[1] . "://" . $shopUrlValidate[0];
					}
					$this->Payload['shopUrl'] = $this->checkoutShopUrl;
				}
			}
		}
		// If card data has been included in the payload, make sure that the card data is validated if the payload has been sent
		// by manual hands (deprecated mode)
		if (isset($this->Payload['card'])) {
			if (isset($this->PaymentMethod->specificType)) {
				$this->validateCardData($this->PaymentMethod->specificType);
			}
		}
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
			'checkout'   => array(
				'artNo',
				'description',
				'quantity',
				'unitMeasure',
				'unitAmountWithoutVat',
				'vatPct',
				'type'
			),
			'hosted'     => array(
				'artNo',
				'description',
				'quantity',
				'unitMeasure',
				'unitAmountWithoutVat',
				'vatPct',
				'totalVatAmount',
				'totalAmount'
			),
			'simplified' => array(
				'id',
				'artNo',
				'description',
				'quantity',
				'unitMeasure',
				'unitAmountWithoutVat',
				'vatPct',
				'totalVatAmount',
				'totalAmount'
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
			}
			foreach ( $specLines as $specIndex => $specArray ) {
				foreach ( $specArray as $key => $value ) {
					if ( strtolower( $key ) == "unitmeasure" && empty( $value ) ) { $specArray[ $key ] = $this->defaultUnitMeasure;	}
					if ( ! in_array( strtolower( $key ), array_map( "strtolower", $mySpecRules ) ) ) { unset( $specArray[ $key ] );	}
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
		$trimAddress = trim($addressRow2); // PHP Compatibility
		if ( ! empty( $trimAddress ) ) {
			$ReturnAddress['addressRow2'] = $addressRow2;
		}
		$targetCountry = $this->getCountry();
		if (empty($country) && !empty($targetCountry)) {
			$country = $targetCountry;
		} else if (!empty($country) && empty($targetCountry)) {
			// Giving internal country data more influence on this method
			$this->setCountryByCountryCode($targetCountry);
		}
		if ( $this->enforceService === RESURS_FLOW_TYPES::FLOW_SIMPLIFIED_FLOW ) {
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
			if (isset($addressData['countryCode']) && !empty($addressData['countryCode'])) {
				$addressData['country'] = $addressData['countryCode'];
				unset($addressData['countryCode']);
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
	 * @return array
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
	 * @param string $successUrl
	 * @param string $failUrl
	 * @param bool $forceSigning
	 *
	 * @since 1.0.6
	 * @since 1.1.6
	 */
	public function setSigning( $successUrl = '', $failUrl = '', $forceSigning = false ) {
		$SigningPayload['signing'] = array(
			'successUrl'   => $successUrl,
			'failUrl'      => $failUrl,
			'forceSigning' => $forceSigning
		);
		$this->handlePayload( $SigningPayload );
	}

	/**
	 * Helper function. This actually does what setSigning do, but with lesser confusion.
	 *
	 * @param string $successUrl
	 * @param string $backUrl
	 */
	public function setCheckoutUrls($successUrl = '', $backUrl = '') {
		$this->setSigning($successUrl, $backUrl);
	}

	//// PAYLOAD HANDLER!

	/**
	 * Compile user defined payload with payload that may have been pre-set by other calls
	 *
	 * @param array $userDefinedPayload
	 *
	 * @since 1.0.2
	 * @since 1.1.2
	 */
	private function handlePayload( $userDefinedPayload = array() ) {
		$myFlow = $this->getPreferredPaymentFlowService();
		if ( is_array( $userDefinedPayload ) && count( $userDefinedPayload ) ) {
			foreach ( $userDefinedPayload as $payloadKey => $payloadContent ) {
				if ( ! isset( $this->Payload[ $payloadKey ] ) ) {
					$this->Payload[ $payloadKey ] = $payloadContent;
				} else {
					// If the payloadkey already exists, there might be something that wants to share information.
					// In this case, append more data to the children
					foreach ( $userDefinedPayload[ $payloadKey ] as $subKey => $subValue ) {
						if ( ! isset( $this->Payload[ $payloadKey ][ $subKey ] ) ) {
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
	}

	/**
	 * Returns the final payload
	 *
	 * @return mixed
	 * @since 1.0.2
	 * @since 1.1.2
	 * @since 1.2.0
	 */
	public function getPayload() {
		$this->preparePayload();
		// Making sure payloads are returned as they should look
		if (isset($this->Payload)) {
			if (!is_array($this->Payload)) {
				$this->Payload = array();
			}
		} else {
			$this->Payload = array();
		}
		return $this->Payload;
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
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	public function getOrderData() {
		$this->preparePayload();
		return isset($this->Payload['orderData']) ? $this->Payload['orderData'] : array();
	}

	/**
	 * bookPayment - Compiler for bookPayment.
	 *
	 * This is the entry point of the simplified version of bookPayment. The normal action here is to send a bulked array with settings for how the payment should be handled (see https://test.resurs.com/docs/x/cIZM)
	 * Minor notice: We are currently preparing support for hosted flow by sending array('type' => 'hosted'). It is however not ready to run yet.
	 *
	 * @param string $paymentMethodIdOrPaymentReference
	 * @param array $bookData
	 * @param bool $getReturnedObjectAsStd Returning a stdClass instead of a Resurs class
	 * @param bool $keepReturnObject Making EComPHP backwards compatible when a webshop still needs the complete object, not only $bookPaymentResult->return
	 * @param array $externalParameters External parameters
	 *
	 * @return object
	 * @throws \Exception
	 * @link https://test.resurs.com/docs/x/cIZM bookPayment EComPHP Reference
	 * @link https://test.resurs.com/docs/display/ecom/bookPayment bookPayment reference
	 * @deprecated 1.1.2
	 * @deprecated 1.0.2
	 */
	public function bookPayment( $paymentMethodIdOrPaymentReference = '', $bookData = array(), $getReturnedObjectAsStd = true, $keepReturnObject = false, $externalParameters = array() ) {
		return $this->createPayment( $paymentMethodIdOrPaymentReference, $bookData );

		/* If the bookData-array seems empty, we'll try to import the internally set bookData */
		if ( is_array( $bookData ) && ! count( $bookData ) ) {
			if ( is_array( $this->bookData ) && count( $this->bookData ) ) {
				$bookData = $this->bookData;
			} else {
				throw new \Exception( __FUNCTION__ . ": There is no bookData available for the booking", \RESURS_EXCEPTIONS::BOOKPAYMENT_NO_BOOKDATA );
			}
		}
		$returnBulk = $this->bookPaymentBulk( $paymentMethodIdOrPaymentReference, $bookData, $getReturnedObjectAsStd, $keepReturnObject, $externalParameters );

		return $returnBulk;
	}


	/**
	 * Booking payments as a bulk (bookPaymentBuilder)
	 *
	 * This is where the priary payment booking renderer resides, where all required data are precompiled for the booking.
	 * Needs an array that is built in a similar way that is documented in the simplifiedShopFlow-reference at test.resurs.com.
	 *
	 * @link https://test.resurs.com/docs/x/cIZM bookPayment EComPHP Reference
	 * @link https://test.resurs.com/docs/display/ecom/bookPayment bookPayment reference
	 *
	 * @param string $paymentMethodId For Resurs Checkout, you should pass the reference ID here
	 * @param array $bookData
	 * @param bool $getReturnedObjectAsStd Returning a stdClass instead of a Resurs class
	 * @param bool $keepReturnObject Making EComPHP backwards compatible when a webshop still needs the complete object, not only $bookPaymentResult->return
	 * @param array $externalParameters External parameters
	 *
	 * @return array|mixed|null This normally returns an object depending on your platform request
	 * @throws \Exception
	 * @deprecated 1.1.2
	 * @deprecated 1.0.2
	 */
	private function bookPaymentBulk( $paymentMethodId = '', $bookData = array(), $getReturnedObjectAsStd = true, $keepReturnObject = false, $externalParameters = array() ) {
		if ( empty( $paymentMethodId ) ) {
			return new \stdClass();
		}
		if ( $this->enforceService == RESURS_FLOW_TYPES::METHOD_OMNI ) {
			$bookData['type'] = "omni";
		} else {
			if ( isset( $bookData['type'] ) == "omni" ) {
				$this->enforceService = RESURS_FLOW_TYPES::METHOD_OMNI;
				$this->isOmniFlow     = true;
			}
		}
		if ( $this->enforceService == RESURS_FLOW_TYPES::FLOW_HOSTED_FLOW ) {
			$bookData['type'] = "hosted";
		} else {
			if ( isset( $bookData['type'] ) == "hosted" ) {
				$this->enforceService = RESURS_FLOW_TYPES::FLOW_HOSTED_FLOW;
				$this->isHostedFlow   = true;
			}
		}
		$skipSteps = array();
		/* Special rule preparation for Resurs Bank hosted flow */
		if ( $this->getBookParameter( 'type', $externalParameters ) == "hosted" || ( isset( $bookData['type'] ) && $bookData['type'] == "hosted" ) ) {
			$this->isHostedFlow = true;
		}
		/* Special rule preparation for Resurs Bank Omnicheckout */
		if ( $this->getBookParameter( 'type', $externalParameters ) == "omni" || ( isset( $bookData['type'] ) && $bookData['type'] == "omni" ) ) {
			$this->isOmniFlow = true;
			/*
			 * In omnicheckout the first variable is not the payment method, it is the preferred order id
			 */
			if ( empty( $this->preferredId ) ) {
				$this->preferredId = $paymentMethodId;
			}
		}
		/* Make EComPHP ignore some steps that is not required in an omni checkout */
		if ( $this->isOmniFlow ) {
			$skipSteps['address'] = true;
		}
		/* Prepare for a simplified flow */
		if ( ! $this->isOmniFlow && ! $this->isHostedFlow ) {
			// Do not use wsdl stubs if we are targeting rest services
			$this->InitializeServices();
		}
		$this->updatePaymentdata( $paymentMethodId, isset( $bookData['paymentData'] ) && is_array( $bookData['paymentData'] ) && count( $bookData['paymentData'] ) ? $bookData['paymentData'] : array() );
		if ( isset( $bookData['specLine'] ) && is_array( $bookData['specLine'] ) ) {
			$this->updateCart( isset( $bookData['specLine'] ) ? $bookData['specLine'] : array() );
		} else {
			// For omni and hosted flow, if specLine is not set
			if ( isset( $bookData['orderLines'] ) && is_array( $bookData['orderLines'] ) ) {
				$this->updateCart( isset( $bookData['orderLines'] ) ? $bookData['orderLines'] : array() );
			}
		}
		$this->updatePaymentSpec( $this->_paymentSpeclines );
		/* Prepare address data for hosted flow and simplified, ignore if we're on omni, where this data is not required */
		if ( ! isset( $skipSteps['address'] ) ) {
			if ( isset( $bookData['deliveryAddress'] ) ) {
				$addressArray = array(
					'address'         => $bookData['address'],
					'deliveryAddress' => $bookData['deliveryAddress']
				);
				$this->updateAddress( isset( $addressArray ) ? $addressArray : array(), isset( $bookData['customer'] ) ? $bookData['customer'] : array() );
			} else {
				$this->updateAddress( isset( $bookData['address'] ) ? $bookData['address'] : array(), isset( $bookData['customer'] ) ? $bookData['customer'] : array() );
			}
		}
		/* Prepare and collect data for a bookpayment - if the flow is simple */
		if ( ( ! $this->isOmniFlow && ! $this->isHostedFlow ) && ( class_exists( 'Resursbank\RBEcomPHP\resurs_bookPayment' ) || class_exists( 'resurs_bookPayment' ) ) ) {
			/* Only run this if it exists, and the plans is to go through simplified flow */
			$bookPaymentInit = new resurs_bookPayment( $this->_paymentData, $this->_paymentOrderData, $this->_paymentCustomer, $this->_bookedCallbackUrl );
		} else {
			/*
			 * If no "new flow" are detected during the handle of payment here, and the class also exists so no booking will be possible, we should
			 * throw an execption here.
			 */
			if ( ! $this->isOmniFlow && ! $this->isHostedFlow ) {
				throw new \Exception( __FUNCTION__ . ": bookPaymentClass not found, and this is neither an omni nor hosted flow", \RESURS_EXCEPTIONS::BOOKPAYMENT_NO_BOOKPAYMENT_CLASS );
			}
		}
		if ( ! empty( $this->cardDataCardNumber ) || $this->cardDataUseAmount ) {
			$bookPaymentInit->card = $this->updateCardData();
		}
		if ( ! empty( $this->_paymentDeliveryAddress ) && is_object( $this->_paymentDeliveryAddress ) ) {
			$bookPaymentInit->customer->deliveryAddress = $this->_paymentDeliveryAddress;
		}
		/* If the preferredId is set, check if there is a request for this varaible in the signing urls */
		if ( isset( $this->_paymentData->preferredId ) ) {
			// Make sure that the search and replace really works for unique id's
			if ( ! isset( $bookData['uniqueId'] ) ) {
				$bookData['uniqueId'] = "";
			}
			if ( isset( $bookData['signing']['successUrl'] ) ) {
				$bookData['signing']['successUrl'] = str_replace( '$preferredId', $this->_paymentData->preferredId, $bookData['signing']['successUrl'] );
				$bookData['signing']['successUrl'] = str_replace( '%24preferredId', $this->_paymentData->preferredId, $bookData['signing']['successUrl'] );
				if ( isset( $bookData['uniqueId'] ) ) {
					$bookData['signing']['successUrl'] = str_replace( '$uniqueId', $bookData['uniqueId'], $bookData['signing']['successUrl'] );
					$bookData['signing']['successUrl'] = str_replace( '%24uniqueId', $bookData['uniqueId'], $bookData['signing']['successUrl'] );
				}
			}
			if ( isset( $bookData['signing']['failUrl'] ) ) {
				$bookData['signing']['failUrl'] = str_replace( '$preferredId', $this->_paymentData->preferredId, $bookData['signing']['failUrl'] );
				$bookData['signing']['failUrl'] = str_replace( '%24preferredId', $this->_paymentData->preferredId, $bookData['signing']['failUrl'] );
				if ( isset( $bookData['uniqueId'] ) ) {
					$bookData['signing']['failUrl'] = str_replace( '$uniqueId', $bookData['uniqueId'], $bookData['signing']['failUrl'] );
					$bookData['signing']['failUrl'] = str_replace( '%24uniqueId', $bookData['uniqueId'], $bookData['signing']['failUrl'] );
				}
			}
		}
		/* If this request actually belongs to an omni flow, let's handle the incoming data differently */
		if ( $this->isOmniFlow ) {
			/* Prepare a frame for omni checkout */
			try {
				$preOmni = $this->prepareOmniFrame( $bookData, $paymentMethodId, ResursCheckoutCallTypes::METHOD_PAYMENTS );
				if ( isset( $preOmni->html ) ) {
					$this->omniFrame = $preOmni->html;
				}
			} catch ( \Exception $omniFrameException ) {
				throw new \Exception( __FUNCTION__ . "/prepareOmniFrame: " . $omniFrameException->getMessage(), $omniFrameException->getCode() );
			}
			if ( isset( $this->omniFrame->faultCode ) ) {
				throw new \Exception( __FUNCTION__ . "/prepareOmniFrame-bookPaymentOmniFrame: " . ( isset( $this->omniFrame->description ) ? $this->omniFrame->description : "Unknown error received from Resurs Bank OmniAPI" ), $this->omniFrame->faultCode );
			}

			return $this->omniFrame;
		}
		/* Now, if this is a request for hosted flow, handle the completed data differently */
		if ( $this->isHostedFlow ) {
			$bookData['orderData'] = $this->objectsIntoArray( $this->_paymentOrderData );
			try {
				$hostedResult = $this->bookPaymentHosted( $paymentMethodId, $bookData, $getReturnedObjectAsStd, $keepReturnObject );
			} catch ( \Exception $hostedException ) {
				throw new \Exception( __FUNCTION__ . ": " . $hostedException->getMessage(), $hostedException->getCode() );
			}
			if ( isset( $hostedResult->location ) ) {
				return $hostedResult->location;
			} else {
				throw new \Exception( __FUNCTION__ . "/bookPaymentHosted: Can not find location in hosted flow", 404 );
			}
		}
		/* If this request was not about an omni flow, let's continue prepare the signing data */
		if ( isset( $bookData['signing'] ) ) {
			$bookPaymentInit->signing = $bookData['signing'];
		}
		try {
			$bookPaymentResult = $this->simplifiedShopFlowService->bookPayment( $bookPaymentInit );
		} catch ( \Exception $bookPaymentException ) {
			if ( isset( $bookPaymentException->faultstring ) ) {
				throw new \Exception( $bookPaymentException->faultstring, 500 );
			}
			throw new \Exception( __FUNCTION__ . ": " . $bookPaymentException->getMessage(), $bookPaymentException->getCode() );
		}
		if ( $getReturnedObjectAsStd ) {
			if ( isset( $bookPaymentResult->return ) ) {
				/* Set up a globally reachable result for the last booked payment */
				$this->lastBookPayment = $bookPaymentResult->return;
				if ( ! $keepReturnObject ) {
					return $this->getDataObject( $bookPaymentResult->return );
				} else {
					return $this->getDataObject( $bookPaymentResult );
				}
			} else {
				throw new \Exception( __FUNCTION__ . ": bookPaymentResult does not contain a return object", 500 );
			}
		}

		return $bookPaymentResult;
	}

	/**
	 * @param string $parameter
	 * @param null $object
	 *
	 * @return object
	 * @deprecated 1.0.2
	 * @deprecated 1.1.2
	 */
	private function getBookedParameter( $parameter = '', $object = null ) {
		if ( is_null( $object ) && is_object( $this->lastBookPayment ) ) {
			$object = $this->lastBookPayment;
		}
		if ( isset( $object->return ) ) {
			$object = $object->return;
		}
		if ( is_object( $object ) || is_array( $object ) ) {
			if ( isset( $object->$parameter ) ) {
				return $object->$parameter;
			}
		}

		return null;
	}

	/**
	 * Get the booked payment status
	 *
	 * @param null $lastBookPayment
	 *
	 * @return string
	 * @deprecated 1.0.2
	 * @deprecated 1.1.2
	 */
	public function getBookedStatus( $lastBookPayment = null ) {
		$bookStatus = $this->getBookedParameter( 'bookPaymentStatus', $lastBookPayment );
		if ( ! empty( $bookStatus ) ) {
			return $bookStatus;
		}

		return null;
	}

	/**
	 * Get the booked payment id out of a payment
	 *
	 * @param null $lastBookPayment
	 *
	 * @return string
	 * @deprecated 1.0.2
	 * @deprecated 1.1.2
	 */
	public function getBookedPaymentId( $lastBookPayment = null ) {
		$paymentId = $this->getBookedParameter( 'paymentId', $lastBookPayment );
		if ( ! empty( $paymentId ) ) {
			return $paymentId;
		} else {
			$id = $this->getBookedParameter( 'id', $lastBookPayment );
			if ( ! empty( $id ) ) {
				return $id;
			}
		}

		return null;
	}

	/**
	 * Extract the signing url from the booking
	 *
	 * @param null $lastBookPayment
	 *
	 * @return string
	 * @deprecated 1.0.2
	 * @deprecated 1.1.2
	 */
	public function getBookedSigningUrl( $lastBookPayment = null ) {
		return $this->getBookedParameter( 'signingUrl', $lastBookPayment );
	}


	////// RESURSCHECKOUT -- FORMERLY KNOWN AS OMNICHECKOUT

	/**
	 * @param array $bookData
	 * @param string $orderReference
	 * @param int $omniCallType
	 *
	 * @return mixed
	 * @throws \Exception
	 * @deprecated 1.0.2
	 * @deprecated 1.1.2
	 */
	public function prepareOmniFrame( $bookData = array(), $orderReference = "", $omniCallType = ResursCheckoutCallTypes::METHOD_PAYMENTS ) {
		if ( empty( $this->preferredId ) ) {
			$this->preferredId = $this->generatePreferredId();
		}
		if ( $this->current_environment == ResursEnvironments::ENVIRONMENT_TEST ) {
			$this->env_omni_current = $this->env_omni_test;
		} else {
			$this->env_omni_current = $this->env_omni_prod;
		}
		if ( empty( $orderReference ) && ! isset( $bookData['orderReference'] ) ) {
			throw new \Exception( __FUNCTION__ . ": You must proved omnicheckout with a orderReference", 500 );
		}
		if ( empty( $orderReference ) && isset( $bookData['orderReference'] ) ) {
			$orderReference = $bookData['orderReference'];
		}
		if ( $omniCallType == ResursCheckoutCallTypes::METHOD_PAYMENTS ) {
			$omniSubPath = "/checkout/payments/" . $orderReference;
		}
		if ( $omniCallType == ResursCheckoutCallTypes::METHOD_CALLBACK ) {
			$omniSubPath = "/callbacks/";
			throw new \Exception( __FUNCTION__ . ": METHOD_CALLBACK for OmniCheckout is not yet implemented" );
		}
		$omniReferenceUrl = $this->env_omni_current . $omniSubPath;
		try {
			$bookDataJson          = $this->toJsonByType( $bookData, RESURS_FLOW_TYPES::FLOW_RESURS_CHECKOUT );
			$this->simpleWebEngine = $this->createJsonEngine( $omniReferenceUrl, $bookDataJson );
			$omniErrorResult       = $this->omniError( $this->simpleWebEngine );
			// Compatibility fixed for PHP 5.3
			if ( ! empty( $omniErrorResult ) ) {
				$omniErrNo = $this->omniErrNo( $this->simpleWebEngine );
				throw new \Exception( __FUNCTION__ . ": " . $omniErrorResult, $omniErrNo );
			}
		} catch ( \Exception $jsonException ) {
			throw new \Exception( __FUNCTION__ . ": " . $jsonException->getMessage(), $jsonException->getCode() );
		}

		return $this->simpleWebEngine['parsed'];
	}

	/**
	 * getOmniFrame: Only used to fix ocShop issues
	 *
	 * This can also be done directly from bookPayment by the use of booked payment result (response->html).
	 *
	 * @param array $omniResponse
	 * @param bool $ocShopInternalHandle Make EComPHP will try to find and strip the script tag for the iframe resizer, if this is set to true
	 *
	 * @return mixed|null|string
	 * @deprecated 1.0.1
	 * @deprecated 1.1.1
	 */
	public function getOmniFrame( $omniResponse = array(), $ocShopInternalHandle = false ) {
		/*
		 * As we are using TorneLIB Curl Library, the Resurs Checkout iframe will be loaded properly without those checks.
		 */
		if ( is_string( $omniResponse ) && ! empty( $omniResponse ) ) {
			if ( isset( $omniResponse ) ) {
				return $this->clearOcShop( $this->omniFrame, $ocShopInternalHandle );
			}
		}

		return null;
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
	 * @param string $iframeString
	 *
	 * @return null
	 * @deprecated 1.0.2
	 * @deprecated 1.1.2
	 */
	public function getIframeSrc( $iframeString = "" ) {
		if ( is_string( $iframeString ) && preg_match( "/iframe src=\"(.*?)/i", $iframeString ) ) {
			preg_match_all( "/iframe src=\"(.*?)\"/", $iframeString, $iframeData );
			if ( isset( $iframeData[1] ) && isset( $iframeData[1][0] ) ) {
				return $iframeData[1][0];
			}
		}

		return null;
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
	public function getCheckoutUrl( $EnvironmentRequest = ResursEnvironments::ENVIRONMENT_TEST, $getCurrentIfSet = true ) {
		/*
		 * If current_environment is set, override incoming variable
		 */
		if ( $getCurrentIfSet && $this->current_environment_updated ) {
			if ( $this->current_environment == ResursEnvironments::ENVIRONMENT_PRODUCTION ) {
				return $this->env_omni_prod;
			} else {
				return $this->env_omni_test;
			}
		}
		if ( $EnvironmentRequest == ResursEnvironments::ENVIRONMENT_PRODUCTION ) {
			return $this->env_omni_prod;
		} else {
			return $this->env_omni_test;
		}
	}

	/**
	 * Retrieve the correct omnicheckout url depending chosen environment
	 *
	 * @param int $EnvironmentRequest
	 * @param bool $getCurrentIfSet
	 *
	 * @return string
	 * @deprecated 1.0.1
	 * @deprecated 1.1.1
	 */
	public function getOmniUrl( $EnvironmentRequest = ResursEnvironments::ENVIRONMENT_TEST, $getCurrentIfSet = true ) {
		return $this->getCheckoutUrl( $EnvironmentRequest, $getCurrentIfSet );
	}

	/**
	 * Return a string containing the last error for the current session. Returns null if no errors occured
	 *
	 * @param array $omniObject
	 *
	 * @return string
	 * @deprecated 1.0.2
	 * @deprecated 1.1.2
	 */
	private function omniError( $omniObject = array() ) {
		if ( isset( $omniObject ) && isset( $omniObject->exception ) && isset( $omniObject->message ) ) {
			return $omniObject->message;
		} else if ( isset( $omniObject ) && isset( $omniObject->error ) && ! empty( $omniObject->error ) ) {
			return $omniObject->error;
		}

		return "";
	}

	/**
	 * @param array $omniObject
	 *
	 * @return string
	 * @deprecated 1.0.2
	 * @deprecated 1.1.2
	 */
	private function omniErrNo( $omniObject = array() ) {
		if ( isset( $omniObject ) && isset( $omniObject->exception ) && isset( $omniObject->status ) ) {
			return $omniObject->status;
		} else if ( isset( $omniObject ) && isset( $omniObject->error ) && ! empty( $omniObject->error ) ) {
			if ( isset( $omniObject->status ) ) {
				return $omniObject->status;
			}
		}

		return "";
	}

	/**
	 * @param $jsonData
	 * @param string $paymentId
	 *
	 * @return mixed
	 * @throws \Exception
	 * @deprecated 1.0.8
	 * @deprecated 1.1.8
	 */
	public function omniUpdateOrder( $jsonData, $paymentId = '' ) {
		return $this->setCheckoutFrameOrderLines( $paymentId, $jsonData );
	}

	/**
	 * Update the Checkout iframe
	 *
	 * @param string $paymentId
	 * @param array $orderLines
	 *
	 * @return array
	 * @throws \Exception
	 * @since 1.0.8
	 * @since 1.1.8
	 * @deprecated Use updateCheckoutOrderLines() instead
	 */
	public function setCheckoutFrameOrderLines( $paymentId = '', $orderLines = array() ) {
		$this->updateCheckoutOrderLines($paymentId, $orderLines);
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
		$outputOrderLines = array();
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
		if ( $this->current_environment == ResursEnvironments::ENVIRONMENT_TEST ) {
			return $this->env_hosted_test;
		} else {
			return $this->env_hosted_prod;
		}
	}

	/**
	 * Book payment through hosted flow
	 *
	 * A bookPayment method that utilizes the data we get from a regular bookPayment and converts it to hostedFlow looking data.
	 * Warning: This method is not yet finished.
	 *
	 * @param string $paymentMethodId
	 * @param array $bookData
	 * @param bool $getReturnedObjectAsStd Returning a stdClass instead of a Resurs class
	 * @param bool $keepReturnObject Making EComPHP backwards compatible when a webshop still needs the complete object, not only $bookPaymentResult->return
	 *
	 * @return array|mixed|object
	 * @throws \Exception
	 * @deprecated 1.0.2
	 * @deprecated 1.1.2
	 */
	private function bookPaymentHosted( $paymentMethodId = '', $bookData = array(), $getReturnedObjectAsStd = true, $keepReturnObject = false ) {
		if ( $this->current_environment == ResursEnvironments::ENVIRONMENT_TEST ) {
			$this->env_hosted_current = $this->env_hosted_test;
		} else {
			$this->env_hosted_current = $this->env_hosted_prod;
		}
		/**
		 * Missing fields may be caused by a conversion of the simplified flow, so we'll try to fill that in here
		 */
		if ( empty( $this->preferredId ) ) {
			$this->preferredId = $this->generatePreferredId();
		}
		if ( ! isset( $bookData['paymentData']['paymentMethodId'] ) ) {
			$bookData['paymentData']['paymentMethodId'] = $paymentMethodId;
		}
		if ( ! isset( $bookData['paymentData']['preferredId'] ) || ( isset( $bookData['paymentData']['preferredId'] ) && empty( $bookData['paymentData']['preferredId'] ) ) ) {
			$bookData['paymentData']['preferredId'] = $this->preferredId;
		}
		/**
		 * Some of the paymentData are not located in the same place as simplifiedShopFlow. This part takes care of that part.
		 */
		if ( isset( $bookData['paymentData']['waitForFraudControl'] ) ) {
			$bookData['waitForFraudControl'] = $bookData['paymentData']['waitForFraudControl'];
		}
		if ( isset( $bookData['paymentData']['annulIfFrozen'] ) ) {
			$bookData['annulIfFrozen'] = $bookData['paymentData']['annulIfFrozen'];
		}
		if ( isset( $bookData['paymentData']['finalizeIfBooked'] ) ) {
			$bookData['finalizeIfBooked'] = $bookData['paymentData']['finalizeIfBooked'];
		}
		$jsonBookData          = $this->toJsonByType( $bookData, RESURS_FLOW_TYPES::FLOW_HOSTED_FLOW );
		$this->simpleWebEngine = $this->createJsonEngine( $this->env_hosted_current, $jsonBookData );
		$hostedErrorResult     = $this->hostedError( $this->simpleWebEngine );
		// Compatibility fixed for PHP 5.3
		if ( ! empty( $hostedErrorResult ) ) {
			$hostedErrNo = $this->hostedErrNo( $this->simpleWebEngine );
			throw new \Exception( __FUNCTION__ . ": " . $hostedErrorResult, $hostedErrNo );
		}

		return $this->simpleWebEngine['parsed'];
	}

	/**
	 * Return a string containing the last error for the current session. Returns null if no errors occured
	 *
	 * @param array $hostedObject
	 *
	 * @return string
	 * @deprecated 1.0.2
	 * @deprecated 1.1.2
	 */
	private function hostedError( $hostedObject = array() ) {
		if ( isset( $hostedObject ) && isset( $hostedObject->exception ) && isset( $hostedObject->message ) ) {
			return $hostedObject->message;
		}

		return "";
	}

	/**
	 * @param array $hostedObject
	 *
	 * @return string
	 * @deprecated 1.0.2
	 * @deprecated 1.1.2
	 */
	private function hostedErrNo( $hostedObject = array() ) {
		if ( isset( $hostedObject ) && isset( $hostedObject->exception ) && isset( $hostedObject->status ) ) {
			return $hostedObject->status;
		}

		return "";
	}


	////// MASTER SHOPFLOWS - THE OTHER ONES

	/**
	 * Prepare a payment by setting it up
	 *
	 * customerIpAddress has a failover: If we don't receive a proper customer ip, we will try to check if there is a REMOTE_ADDR set by the server. If neither of those values are set, we will finally fail over to 127.0.0.1
	 * preferredId is set to a internally generated id instead of null, unless you apply your own (if set to null, Resurs Bank decides what order number to be used)
	 *
	 * @param $paymentMethodId
	 * @param array $paymentDataArray
	 *
	 * @throws \Exception
	 * @deprecated 1.0.2
	 * @deprecated 1.1.2
	 */
	public function updatePaymentdata( $paymentMethodId, $paymentDataArray = array() ) {
		$this->InitializeServices();
		if ( empty( $this->preferredId ) ) {
			$this->preferredId = $this->generatePreferredId();
		}
		if ( ! is_object( $this->_paymentData ) && ( class_exists( 'Resursbank\RBEcomPHP\resurs_paymentData' ) || class_exists( 'resurs_paymentData' ) ) ) {
			$this->_paymentData = new resurs_paymentData( $paymentMethodId );
		} else {
			// If there are no wsdl-classes loaded, we should consider a default stdClass as object
			$this->_paymentData = new \stdClass();
		}
		$this->_paymentData->preferredId         = isset( $paymentDataArray['preferredId'] ) && ! empty( $paymentDataArray['preferredId'] ) ? $paymentDataArray['preferredId'] : $this->preferredId;
		$this->_paymentData->paymentMethodId     = $paymentMethodId;
		$this->_paymentData->customerIpAddress   = ( isset( $paymentDataArray['customerIpAddress'] ) && ! empty( $paymentDataArray['customerIpAddress'] ) ? $paymentDataArray['customerIpAddress'] : ( isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1' ) );
		$this->_paymentData->waitForFraudControl = isset( $paymentDataArray['waitForFraudControl'] ) && ! empty( $paymentDataArray['waitForFraudControl'] ) ? $paymentDataArray['waitForFraudControl'] : false;
		$this->_paymentData->annulIfFrozen       = isset( $paymentDataArray['annulIfFrozen'] ) && ! empty( $paymentDataArray['annulIfFrozen'] ) ? $paymentDataArray['annulIfFrozen'] : false;
		$this->_paymentData->finalizeIfBooked    = isset( $paymentDataArray['finalizeIfBooked'] ) && ! empty( $paymentDataArray['finalizeIfBooked'] ) ? $paymentDataArray['finalizeIfBooked'] : false;
	}

	/**
	 * Creation of specrows lands here
	 *
	 * @param array $speclineArray
	 *
	 * @return null
	 * @throws \Exception
	 * @deprecated 1.0.2
	 * @deprecated 1.1.2
	 */
	public function updateCart( $speclineArray = array() ) {
		if ( ! $this->isOmniFlow && ! $this->isHostedFlow ) {
			if ( ! class_exists( 'Resursbank\RBEcomPHP\resurs_specLine' ) && ! class_exists( 'resurs_specLine' ) ) {
				throw new \Exception( __FUNCTION__ . ": Class specLine does not exist", \RESURS_EXCEPTIONS::UPDATECART_NOCLASS_EXCEPTION );
			}
		}
		$this->InitializeServices();
		$realSpecArray = array();
		if ( isset( $speclineArray['artNo'] ) ) {
			// If this require parameter is found first in the array, it's a single specrow.
			// In that case, push it out to be a multiple.
			array_push( $realSpecArray, $speclineArray );
		} else {
			$realSpecArray = $speclineArray;
		}
		// Handle the specrows as they were many.
		foreach ( $realSpecArray as $specIndex => $speclineArray ) {
			$quantity                   = ( isset( $speclineArray['quantity'] ) && ! empty( $speclineArray['quantity'] ) ? $speclineArray['quantity'] : 1 );
			$unitAmountWithoutVat       = ( is_numeric( floatval( $speclineArray['unitAmountWithoutVat'] ) ) ? $speclineArray['unitAmountWithoutVat'] : 0 );
			$vatPct                     = ( isset( $speclineArray['vatPct'] ) && ! empty( $speclineArray['vatPct'] ) ? $speclineArray['vatPct'] : 0 );
			$totalVatAmountInternal     = ( $unitAmountWithoutVat * ( $vatPct / 100 ) ) * $quantity;
			$totalAmountInclTax         = round( ( $unitAmountWithoutVat * $quantity ) + $totalVatAmountInternal, $this->bookPaymentRoundDecimals );
			$totalAmountInclTaxInternal = $totalAmountInclTax;

			if ( ! $this->bookPaymentInternalCalculate ) {
				if ( isset( $speclineArray['totalVatAmount'] ) && ! empty( $speclineArray['totalVatAmount'] ) ) {
					$totalVatAmount = $speclineArray['totalVatAmount'];
					// Controls the totalVatAmount
					if ( $totalVatAmount != $totalVatAmountInternal ) {
						$totalVatAmount             = $totalVatAmountInternal;
						$this->bookPaymentCartFixed = true;
					}
					if ( $totalAmountInclTax != $totalAmountInclTaxInternal ) {
						$this->bookPaymentCartFixed = true;
						$totalAmountInclTax         = $totalAmountInclTaxInternal;
					}
					$totalAmountInclTax = ( $unitAmountWithoutVat * $quantity ) + $totalVatAmount;
				} else {
					$totalVatAmount = $totalVatAmountInternal;
				}
			} else {
				$totalVatAmount = $totalVatAmountInternal;
			}
			$this->_specLineID ++;
			/*
             * When the class for resurs_SpecLine is missing (e.g. during omni/hosted), the variables below must be set in a different way.
             * In this function we'll let the array right through without any class definition.
             *
             * id
             * artNo
             * description
             * quantity
             * unitMeasure
             * unitAmountWithoutVat
             * vatPct
             * totalVatAmount
             * totalAmount
             */
			if ( class_exists( 'Resursbank\RBEcomPHP\resurs_specLine' ) || class_exists( 'resurs_specLine' ) ) {
				$this->_paymentSpeclines[] = new resurs_specLine(
					$this->_specLineID,
					$speclineArray['artNo'],
					$speclineArray['description'],
					$speclineArray['quantity'],
					( isset( $speclineArray['unitMeasure'] ) && ! empty( $speclineArray['unitMeasure'] ) ? $speclineArray['unitMeasure'] : $this->defaultUnitMeasure ),
					$unitAmountWithoutVat,
					$vatPct,
					$totalVatAmount,
					$totalAmountInclTax
				);
			} else {
				if ( is_array( $speclineArray ) ) {
					$this->_paymentSpeclines[] = $speclineArray;
				}
			}
		}

		return $this->_paymentSpeclines;
	}

	/**
	 * Update payment specs and prepeare specrows
	 *
	 * @param array $specLineArray
	 *
	 * @throws \Exception
	 * @deprecated 1.0.2
	 * @deprecated 1.1.2
	 */
	public function updatePaymentSpec( $specLineArray = array() ) {
		$this->InitializeServices();
		if ( class_exists( 'Resursbank\RBEcomPHP\resurs_paymentSpec' ) || class_exists( 'resurs_paymentSpec' ) ) {
			$totalAmount    = 0;
			$totalVatAmount = 0;
			if ( is_array( $specLineArray ) && count( $specLineArray ) ) {
				foreach ( $specLineArray as $specRow => $specRowArray ) {
					$totalAmount    += ( isset( $specRowArray->totalAmount ) ? $specRowArray->totalAmount : 0 );
					$totalVatAmount += ( isset( $specRowArray->totalVatAmount ) ? $specRowArray->totalVatAmount : 0 );
				}
			}
			$this->_paymentOrderData                 = new resurs_paymentSpec( $specLineArray, $totalAmount, 0 );
			$this->_paymentOrderData->totalVatAmount = floatval( $totalVatAmount );
		}
	}

	/**
	 * Prepare customer address data
	 *
	 * Note: Customer types LEGAL needs to be defined as $custeromArray['type'] = "LEGAL", if the booking is about LEGAL customers, since we need to extend the address data for such customers.
	 *
	 * @param array $addressArray
	 * @param array $customerArray
	 *
	 * @throws \Exception
	 * @deprecated 1.0.2
	 * @deprecated 1.1.2
	 */
	public function updateAddress( $addressArray = array(), $customerArray = array() ) {
		$this->InitializeServices();
		$address               = null;
		$resursDeliveryAddress = null;
		$customerGovId         = isset( $customerArray['governmentId'] ) && ! empty( $customerArray['governmentId'] ) ? $customerArray['governmentId'] : "";
		$customerContactGovId  = isset( $customerArray['contactGovernmentId'] ) && ! empty( $customerArray['contactGovernmentId'] ) ? $customerArray['contactGovernmentId'] : "";
		$customerType          = isset( $customerArray['type'] ) && ! empty( $customerArray['type'] ) ? $customerArray['type'] : "NATURAL";
		if ( count( $addressArray ) ) {
			if ( isset( $addressArray['address'] ) ) {
				$address = new resurs_address( $addressArray['address']['fullName'], $addressArray['address']['firstName'], $addressArray['address']['lastName'], ( isset( $addressArray['address']['addressRow1'] ) ? $addressArray['address']['addressRow1'] : null ), ( isset( $addressArray['address']['addressRow2'] ) ? $addressArray['address']['addressRow2'] : null ), $addressArray['address']['postalArea'], $addressArray['address']['postalCode'], $addressArray['address']['country'] );
				if ( isset( $addressArray['deliveryAddress'] ) ) {
					$resursDeliveryAddress = new resurs_address( $addressArray['deliveryAddress']['fullName'], $addressArray['deliveryAddress']['firstName'], $addressArray['deliveryAddress']['lastName'], ( isset( $addressArray['deliveryAddress']['addressRow1'] ) ? $addressArray['deliveryAddress']['addressRow1'] : null ), ( isset( $addressArray['deliveryAddress']['addressRow2'] ) ? $addressArray['deliveryAddress']['addressRow2'] : null ), $addressArray['deliveryAddress']['postalArea'], $addressArray['deliveryAddress']['postalCode'], $addressArray['deliveryAddress']['country'] );
				}
			} else {
				$address = new resurs_address( $addressArray['fullName'], $addressArray['firstName'], $addressArray['lastName'], ( isset( $addressArray['addressRow1'] ) ? $addressArray['addressRow1'] : null ), ( isset( $addressArray['addressRow2'] ) ? $addressArray['addressRow2'] : null ), $addressArray['postalArea'], $addressArray['postalCode'], $addressArray['country'] );
			}
		}
		if ( count( $customerArray ) ) {
			$customer              = new resurs_customer( $address, $customerArray['phone'], $customerArray['email'], $customerArray['type'] );
			$this->_paymentAddress = $address;
			if ( ! empty( $customerGovId ) ) {
				$customer->governmentId = $customerGovId;
			} else if ( ! empty( $customerContactGovId ) ) {
				$customer->governmentId = $customerContactGovId;
			}
			$customer->type = $customerType;
			if ( ! empty( $resursDeliveryAddress ) || $customerArray['type'] == "LEGAL" || $this->alwaysUseExtendedCustomer === true ) {
				if ( isset( $resursDeliveryAddress ) && is_array( $resursDeliveryAddress ) ) {
					$this->_paymentDeliveryAddress = $resursDeliveryAddress;
				}
				$extendedCustomer               = new resurs_extendedCustomer( $resursDeliveryAddress, $customerArray['phone'], $customerArray['email'], $customerArray['type'] );
				$this->_paymentExtendedCustomer = $extendedCustomer;
				/* #59042 => #59046 (Additionaldata should be empty) */
				if ( empty( $this->_paymentExtendedCustomer->additionalData ) ) {
					unset( $this->_paymentExtendedCustomer->additionalData );
				}
				if ( $customerArray['type'] == "LEGAL" ) {
					$extendedCustomer->contactGovernmentId = $customerArray['contactGovernmentId'];
				}
				if ( ! empty( $customerArray['cellPhone'] ) ) {
					$extendedCustomer->cellPhone = $customerArray['cellPhone'];
				}
			}
			$this->_paymentCustomer = $customer;
			if ( isset( $extendedCustomer ) ) {
				if ( ! empty( $customerGovId ) ) {
					$extendedCustomer->governmentId = $customerGovId;
				} else if ( ! empty( $customerContactGovId ) ) {
					$extendedCustomer->governmentId = $customerContactGovId;
				}
				$extendedCustomer->phone   = $customerArray['phone'];
				$extendedCustomer->type    = $customerType;
				$extendedCustomer->address = $address;
				$this->_paymentCustomer    = $extendedCustomer;
			}
		}
	}

	/**
	 * Internal handler for carddata
	 * @throws \Exception
	 * @deprecated 1.0.2
	 * @deprecated 1.1.2
	 */
	private function updateCardData() {
		$amount                 = null;
		$this->_paymentCardData = new resurs_cardData();
		if ( ! isset( $this->cardDataCardNumber ) ) {
			if ( $this->cardDataUseAmount && $this->cardDataOwnAmount ) {
				$this->_paymentCardData->amount = $this->cardDataOwnAmount;
			} else {
				$this->_paymentCardData->amount = $this->_paymentOrderData->totalAmount;
			}
		} else {
			if ( isset( $this->cardDataCardNumber ) && ! empty( $this->cardDataCardNumber ) ) {
				$this->_paymentCardData->cardNumber = $this->cardDataCardNumber;
			}
		}
		if ( ! empty( $this->cardDataCardNumber ) && ! empty( $this->cardDataUseAmount ) ) {
			throw new \Exception( __FUNCTION__ . ": Card number and amount can not be set at the same time", \RESURS_EXCEPTIONS::UPDATECARD_DOUBLE_DATA_EXCEPTION );
		}

		return $this->_paymentCardData;
	}

	/**
	 * Prepare API for cards. Make sure only one of the parameters are used. Cardnumber cannot be combinad with amount.
	 *
	 * @param null $cardNumber
	 * @param bool|false $useAmount Set to true when using new cards
	 * @param bool|false $setOwnAmount If customer applies for a new card specify the credit amount that is applied for. If $setOwnAmount is not null, this amount will be used instead of the specrow data
	 *
	 * @throws \Exception
	 * @deprecated 1.0.2 Use setCardData instead
	 * @deprecated 1.1.2 Use setCardData instead
	 */
	public function prepareCardData( $cardNumber = null, $useAmount = false, $setOwnAmount = null ) {
		$this->setCardData( $cardNumber, $setOwnAmount );
	}

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
			$this->Payload['card']['cardNumber'] = $cardNumber;
		}
		if ( $cardAmount > 0 ) {
			$this->Payload['card']['amount'] = $cardAmount;
		}
	}

	/**
	 * Payment card validity check for deprecation layer
	 *
	 * @param string $specificType
	 * @since 1.0.2
	 * @since 1.1.2
	 */
	private function validateCardData($specificType = "") {
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
		if (isset($this->Payload['customer'])) {
			// CARD + (NEWCARD, REVOLVING_CREDIT)
			$mandatoryExtendedCustomerFields = array('governmentId', 'address', 'phone', 'email', 'type');
			if ( $specificType == "CARD" ) {
				$mandatoryExtendedCustomerFields = array('governmentId');
			} else if (($specificType == "REVOLVING_CREDIT" || $specificType == "NEWCARD")) {
				$mandatoryExtendedCustomerFields = array('governmentId', 'phone', 'email');
			}
			if (count($mandatoryExtendedCustomerFields)) {
				foreach ( $this->Payload['customer'] as $customerKey => $customerValue ) {
					// If the key belongs to extendedCustomer, is mandatory for the specificType and is empty,
					// this means we can not deliver this data as a null value to ecommerce. Therefore, we have to remove it.
					// The control being made here will skip the address object as we will only check the non-recursive data strings.
					if (is_string($customerValue)) {
						$trimmedCustomerValue = trim($customerValue);
					} else {
						// Do not touch if this is not an array (and consider that something was sent into this part, that did not belong here?)
						$trimmedCustomerValue = $customerValue;
					}
					if ( ! is_array($customerValue) &&  ! in_array( $customerKey, $mandatoryExtendedCustomerFields ) && empty( $trimmedCustomerValue ) ) {
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
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	public function getPaymentSpecCount($paymentIdOrPaymentObject) {
		$countObject = $this->getPaymentSpecByStatus($paymentIdOrPaymentObject);
		$returnedCountObject = array();
		foreach ($countObject as $status => $theArray) {
			$returnedCountObject[$status] = count($theArray);
		}
		return $returnedCountObject;
	}

	/**
	 * Returns a complete payment spec grouped by status. This function does not merge articles, even if there are multiple rows with the same article number. This normally indicates order modifications, so the are returned raw as is.
	 *
	 * @param $paymentIdOrPaymentObject
	 *
	 * @return array
	 */
	public function getPaymentSpecByStatus( $paymentIdOrPaymentObject ) {
		$usePayment         = $paymentIdOrPaymentObject;
		// Current specs available: AUTHORIZE, DEBIT, CREDIT, ANNUL
		$orderLinesByStatus = array(
			'AUTHORIZE' => array(),
			'DEBIT' => array(),
			'CREDIT' => array(),
			'ANNUL' => array(),
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
					// Second, make sure that the paymentdiffs are collected as one array per specType (AUTHORIZE,DEBIT,CREDIT,ANULL)
					if ( is_array( $paymentDiffObject->paymentSpec->specLines ) ) {
						// Note: array_merge won't work if the initial array is empty. Instead we'll append it to the above array.
						// Also note that appending with += may fail when indexes matches each other on both sides - in that case
						// not all objects will be attached properly to this array.
						if (!$this->isFlag('MERGEBYSTATUS_DEPRECATED_METHOD')) {
							foreach ($paymentDiffObject->paymentSpec->specLines as $arrayObject)
							{
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
	 */
	public function sanitizeAfterShopSpec($paymentIdOrPaymentObjectData = '', $renderType = RESURS_AFTERSHOP_RENDER_TYPES::AFTERSHOP_NO_CHOICE) {
		$returnSpecObject = null;

		$this->BIT->setBitStructure(
			array(
				'FINALIZE' => RESURS_AFTERSHOP_RENDER_TYPES::AFTERSHOP_FINALIZE,
				'CREDIT' => RESURS_AFTERSHOP_RENDER_TYPES::AFTERSHOP_CREDIT,
				'ANNUL' => RESURS_AFTERSHOP_RENDER_TYPES::AFTERSHOP_ANNUL,
				'AUTHORIZE' => RESURS_AFTERSHOP_RENDER_TYPES::AFTERSHOP_AUTHORIZE,
			)
		);

		// Get payment spec bulked
		$paymentIdOrPaymentObject = $this->getPaymentSpecByStatus( $paymentIdOrPaymentObjectData );

		if ( $this->BIT->isBit(RESURS_AFTERSHOP_RENDER_TYPES::AFTERSHOP_FINALIZE, $renderType) ) {
			$returnSpecObject = $this->removeFromArray( $paymentIdOrPaymentObject['AUTHORIZE'], array_merge( $paymentIdOrPaymentObject['DEBIT'], $paymentIdOrPaymentObject['ANNUL'], $paymentIdOrPaymentObject['CREDIT'] ) );
		} else if ( $this->BIT->isBit(RESURS_AFTERSHOP_RENDER_TYPES::AFTERSHOP_CREDIT, $renderType) ) {
			$returnSpecObject = $this->removeFromArray( $paymentIdOrPaymentObject['DEBIT'], array_merge( $paymentIdOrPaymentObject['ANNUL'], $paymentIdOrPaymentObject['CREDIT'] ) );
		} else if ( $this->BIT->isBit(RESURS_AFTERSHOP_RENDER_TYPES::AFTERSHOP_ANNUL, $renderType) ) {
			$returnSpecObject = $this->removeFromArray( $paymentIdOrPaymentObject['AUTHORIZE'], array_merge( $paymentIdOrPaymentObject['DEBIT'], $paymentIdOrPaymentObject['ANNUL'], $paymentIdOrPaymentObject['CREDIT'] ) );
		} else {
			// If no type is chosen, return all rows
			$returnSpecObject = $this->removeFromArray($paymentIdOrPaymentObject, array());
		}
		return $returnSpecObject;
	}

	/**
	 * Sets a preferred transaction id
	 *
	 * @param $preferredTransactionId
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	public function setAfterShopPreferredTransactionId($preferredTransactionId) {
		if (!empty($preferredTransactionId)) {
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
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	public function setAfterShopOrderId($orderId) {
		if (!empty($orderId)) {
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
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	public function setAfterShopInvoiceId($invoiceId) {
		if (!empty($invoiceId)) {
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
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	public function setAfterShopInvoiceExtRef($invoiceExtRef) {
		if (!empty($invoiceExtRef)) {
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
	 * @param $paymentId
	 * @return bool
	 */
	private function aftershopPrepareMetaData($paymentId) {
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
	public function setCustomerId($customerId = "") {
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
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	private function getAfterShopObjectByPayload($paymentId = "", $customPayloadItemList = array(), $payloadType = RESURS_AFTERSHOP_RENDER_TYPES::NONE) {

		$finalAfterShopSpec = array(
			'paymentId' => $paymentId
		);
		if (!is_array($customPayloadItemList)) {$customPayloadItemList = array();} // Make sure this is correct

		$storedPayment = $this->getPayment($paymentId);
		$paymentMethod = $storedPayment->paymentMethodId;
		$paymentMethodData = $this->getPaymentMethodSpecific($paymentMethod);
		$paymentSpecificType = strtoupper(isset($paymentMethodData->specificType) ? $paymentMethodData->specificType : null);
		if ($paymentSpecificType == "INVOICE") {
			$finalAfterShopSpec['orderDate']   = date( 'Y-m-d', time() );
			$finalAfterShopSpec['invoiceDate'] = date( 'Y-m-d', time() );
			if (empty($this->afterShopInvoiceId)) {
				$finalAfterShopSpec['invoiceId'] = $this->getNextInvoiceNumber();
			}
			$extRef = $this->getAfterShopInvoiceExtRef();
			if (!empty($extRef)) {
				$this->addMetaData($paymentId, 'invoiceExtRef', $extRef);
			}
		}

		// Rendered order spec, use when customPayloadItemList is not set, to handle full orders
		$actualEcommerceOrderSpec = $this->sanitizeAfterShopSpec($storedPayment, $payloadType);

		$finalAfterShopSpec['createdBy'] = $this->getCreatedBy();
		$this->renderPaymentSpec( RESURS_FLOW_TYPES::FLOW_SIMPLIFIED_FLOW );

		try {
			// Try to fetch internal order data.
			$orderDataArray = $this->getOrderData();
		} catch (\Exception $getOrderDataException) {
			// If there is no payload, make sure we'll render this from the current payment
			if ($getOrderDataException->getCode() == \RESURS_EXCEPTIONS::BOOKPAYMENT_NO_BOOKDATA && !count($customPayloadItemList)) {
				//array_merge($this->SpecLines, $actualEcommerceOrderSpec);
				$this->SpecLines += $this->objectsIntoArray($actualEcommerceOrderSpec); // Convert objects
			}
		}

		if (count($customPayloadItemList)) {
			// If there is a customized specrowArray injected, no appending should occur.
			//$this->SpecLines += $this->objectsIntoArray($customPayloadItemList);
			$this->SpecLines = $this->objectsIntoArray($customPayloadItemList);
		}
		$this->renderPaymentSpec( RESURS_FLOW_TYPES::FLOW_SIMPLIFIED_FLOW );
		$orderDataArray = $this->getOrderData();

		if (isset($orderDataArray['specLines'])) {
			$orderDataArray['partPaymentSpec'] = $orderDataArray;
		}

		$finalAfterShopSpec += $orderDataArray;
		return $finalAfterShopSpec;
	}

	/**
	 * Identical to paymentFinalize but used for testing errors
	 */
	public function paymentFinalizeTest() {
		if (defined('TEST_OVERRIDE_AFTERSHOP_PAYLOAD') && $this->current_environment == ResursEnvironments::ENVIRONMENT_TEST) {
			$this->postService( "finalizePayment", unserialize( TEST_OVERRIDE_AFTERSHOP_PAYLOAD ) );
		}
	}

	/**
	 * Clean up payload after usage
	 * @since 1.1.22
	 */
	private function resetPayload() {
		$this->SpecLines = array();
		$this->Payload = array();
	}

	/**
	 * Aftershop Payment Finalization (DEBIT)
	 *
	 * Make sure that you are running this with try-catches in cases where failures may occur.
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
	public function paymentFinalize( $paymentId = "", $customPayloadItemList = array() ) {
		$afterShopObject = $this->getAfterShopObjectByPayload( $paymentId, $customPayloadItemList, RESURS_AFTERSHOP_RENDER_TYPES::AFTERSHOP_FINALIZE );
		$this->aftershopPrepareMetaData( $paymentId );
		$afterShopResponseCode = $this->postService( "finalizePayment", $afterShopObject, true );
		if ( $afterShopResponseCode >= 200 && $afterShopResponseCode < 300 ) {
			$this->resetPayload();
			return true;
		}
		return false;
	}

	/**
	 * Aftershop Payment Annulling (ANNUL)
	 *
	 * Make sure that you are running this with try-catches in cases where failures may occur.
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
	public function paymentAnnul( $paymentId = "", $customPayloadItemList = array() ) {
		$afterShopObject = $this->getAfterShopObjectByPayload( $paymentId, $customPayloadItemList, RESURS_AFTERSHOP_RENDER_TYPES::AFTERSHOP_ANNUL );
		$this->aftershopPrepareMetaData( $paymentId );
		$afterShopResponseCode = $this->postService( "annulPayment", $afterShopObject, true );
		if ( $afterShopResponseCode >= 200 && $afterShopResponseCode < 300 ) {
			$this->resetPayload();
			return true;
		}
		return false;
	}

	/**
	 * Aftershop Payment Crediting (CREDIT)
	 *
	 * Make sure that you are running this with try-catches in cases where failures may occur.
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
	public function paymentCredit( $paymentId = "", $customPayloadItemList = array()) {
		$afterShopObject = $this->getAfterShopObjectByPayload( $paymentId, $customPayloadItemList, RESURS_AFTERSHOP_RENDER_TYPES::AFTERSHOP_CREDIT );
		$this->aftershopPrepareMetaData( $paymentId );
		$afterShopResponseCode = $this->postService( "creditPayment", $afterShopObject, true );
		if ( $afterShopResponseCode >= 200 && $afterShopResponseCode < 300 ) {
			$this->resetPayload();
			return true;
		}
		return false;
	}

	/**
	 * Aftershop Payment Cancellation (ANNUL+CREDIT)
	 *
	 * This function cancels a full order depending on the order content. Payloads MAY be customized but on your own risk!
	 *
	 * @param $paymentId
	 * @param array $customPayloadItemList
	 * @return bool
	 * @throws \Exception
	 * @since 1.0.22
	 * @since 1.1.22
	 * @since 1.2.0
	 */
	public function paymentCancel( $paymentId = "", $customPayloadItemList = array() ) {
		// Collect the payment
		$currentPayment = $this->getPayment($paymentId);
		// Collect the payment sorted by status
		$currentPaymentSpec = $this->getPaymentSpecByStatus($currentPayment);

		// Sanitized paymentspec based on what to CREDIT
		$creditObject = $this->sanitizeAfterShopSpec( $currentPayment, RESURS_AFTERSHOP_RENDER_TYPES::AFTERSHOP_CREDIT);
		// Sanitized paymentspec based on what to ANNUL
		$annulObject = $this->sanitizeAfterShopSpec( $currentPayment, RESURS_AFTERSHOP_RENDER_TYPES::AFTERSHOP_ANNUL);

		if (is_array($customPayloadItemList) && count($customPayloadItemList)) {
			$this->SpecLines = array_merge($this->SpecLines, $customPayloadItemList);
		}
		$this->renderPaymentSpec(RESURS_FLOW_TYPES::FLOW_SIMPLIFIED_FLOW);

		$this->aftershopPrepareMetaData( $paymentId );
		try {
			// Render and check if this is customized
			$currentOrderLines = $this->getOrderLines();

			if (count($currentOrderLines)) {
				// If it is customized, we need to render the cancellation differently to specify what's what.

				// Validation object - Contains everything that CAN be credited
				$validatedCreditObject = $this->removeFromArray( $currentPaymentSpec['DEBIT'], array_merge( $currentPaymentSpec['ANNUL'], $currentPaymentSpec['CREDIT'] ) );
				// Validation object - Contains everything that CAN be annulled
				$validatedAnnulmentObject = $this->removeFromArray( $currentPaymentSpec['AUTHORIZE'], array_merge( $currentPaymentSpec['DEBIT'], $currentPaymentSpec['ANNUL'], $currentPaymentSpec['CREDIT'] ) );

				// Clean up selected rows from the credit element and keep those rows than still can be credited and matches the orderRow-request
				$newCreditObject = $this->objectsIntoArray($this->removeFromArray($validatedCreditObject, $currentOrderLines, true));

				// Clean up selected rows from the credit element and keep those rows than still can be annulled and matches the orderRow-request
				$newAnnulObject = $this->objectsIntoArray($this->removeFromArray($validatedAnnulmentObject, $currentOrderLines, true));
				if (count($newCreditObject)) {$this->paymentCredit( $paymentId, $newCreditObject );}
				if (count($newAnnulObject)) {$this->paymentAnnul( $paymentId, $newAnnulObject );}
			} else {
				if (count($creditObject)) {$this->paymentCredit( $paymentId, $creditObject );}
				if (count($annulObject)) {$this->paymentAnnul( $paymentId, $annulObject );}
			}
		} catch (\Exception $cancelException) {
			return false;
		}
		$this->resetPayload();
		return true;
	}

	/**
	 * Automatically cancel (credit or annul) a payment with "best practice".
	 *
	 * Since the rendered container only returns payment data if the rows are available for the current requested action, this function will try to both credit a payment and annul it depending on the status.
	 *
	 * @param string $paymentId
	 * @param array $clientPaymentSpec
	 * @param array $cancelParams
	 * @param bool $quantityMatch
	 * @param bool $useSpecifiedQuantity If set to true, the quantity set clientside will be used rather than the exact quantity from the spec in getPayment (This requires that $quantityMatch is set to false)
	 *
	 * @return bool
	 * @throws \Exception
	 * @deprecated 1.0.22
	 * @deprecated 1.1.22
	 */
	public function cancelPayment( $paymentId = "", $clientPaymentSpec = array(), $cancelParams = array(), $quantityMatch = true, $useSpecifiedQuantity = false ) {
		return $this->paymentCancel($paymentId, $clientPaymentSpec);
	}

	/**
	 * Finalize payment by payment ID. Finalizes an order based on the order content.
	 *
	 * @param string $paymentId
	 * @param array $clientPaymentSpec (Optional) paymentspec if only specified lines are being finalized
	 * @param array $finalizeParams
	 * @param bool $quantityMatch (Optional) Match quantity. If false, quantity will be ignored during finalization and all client specified paymentspecs will match
	 * @param bool $useSpecifiedQuantity If set to true, the quantity set clientside will be used rather than the exact quantity from the spec in getPayment (This requires that $quantityMatch is set to false)
	 *
	 * @return bool True if successful
	 * @throws \Exception
	 * @deprecated 1.0.22
	 * @deprecated 1.1.22
	 */
	public function finalizePayment( $paymentId = "", $clientPaymentSpec = array(), $finalizeParams = array(), $quantityMatch = true, $useSpecifiedQuantity = false ) {
		return $this->paymentFinalize($paymentId, $clientPaymentSpec);
	}

	/**
	 * Credit a payment
	 *
	 * If you need fully automated credits (where payment specs are sorted automatically) you should use cancelPayment
	 *
	 * @param string $paymentId
	 * @param array $clientPaymentSpec
	 * @param array $creditParams
	 * @param bool $quantityMatch
	 * @param bool $useSpecifiedQuantity
	 *
	 * @return bool
	 * @throws \Exception
	 * @deprecated 1.0.22
	 * @deprecated 1.1.22
	 */
	public function creditPayment( $paymentId = "", $clientPaymentSpec = array(), $creditParams = array(), $quantityMatch = true, $useSpecifiedQuantity = false ) {
		return $this->paymentCredit($paymentId, $clientPaymentSpec);
	}

	/**
	 * Annul a payment
	 *
	 * If you need fully automated annullments (where payment specs are sorted automatically) you should use cancelPayment
	 *
	 * @param string $paymentId
	 * @param array $clientPaymentSpec
	 * @param array $annulParams
	 * @param bool $quantityMatch
	 * @param bool $useSpecifiedQuantity If set to true, the quantity set clientside will be used rather than the exact quantity from the spec in getPayment (This requires that $quantityMatch is set to false)
	 *
	 * @return bool
	 * @throws \Exception
	 * @deprecated 1.0.22
	 * @deprecated 1.1.22
	 */
	public function annulPayment( $paymentId = "", $clientPaymentSpec = array(), $annulParams = array(), $quantityMatch = true, $useSpecifiedQuantity = false ) {
		return $this->paymentAnnul($paymentId,$clientPaymentSpec);
	}

	/**
	 * Add an additional orderline to a payment
	 *
	 * With setLoggedInUser() you can also set up a user identification for the createdBy-parameter sent with the additional debig. If not set, EComPHP will use the merchant credentials.
	 *
	 * @param string $paymentId
	 *
	 * @return bool
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
			//$this->Payload['orderData'] = array();
			//$this->SpecLines            = array();
			$this->resetPayload();
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Returns all invoice numbers for a specific payment
	 *
	 * @param string $paymentIdOrPaymentObject
	 *
	 * @return array
	 * @since 1.0.11
	 * @since 1.1.11
	 * @since 1.2.0
	 */
	public function getPaymentInvoices($paymentIdOrPaymentObject = '') {
		$invoices = array();
		if (is_string($paymentIdOrPaymentObject)) {
			$paymentData = $this->getPayment( $paymentIdOrPaymentObject );
		} else if (is_object($paymentIdOrPaymentObject)) {
			$paymentData = $paymentIdOrPaymentObject;
		} else {
			return array();
		}
		if (!empty($paymentData) && isset($paymentData->paymentDiffs)) {
			foreach ($paymentData->paymentDiffs as $paymentRow) {
				if (isset($paymentRow->type) && $paymentRow->type == "DEBIT" && isset($paymentRow->invoiceId)) {
					$invoices[] = $paymentRow->invoiceId;
				}
			}
		}
		return $invoices;
	}

	/**
	 * Generic orderstatus content information that checks payment statuses instead of callback input and decides what happened to the payment
	 *
	 * @param array $paymentData
	 *
	 * @return int
	 * @since 1.0.26
	 * @since 1.1.26
	 * @since 1.2.0
	 */
	private function getOrderStatusByPaymentStatuses( $paymentData = array() ) {
		$resursTotalAmount = $paymentData->totalAmount;
		if ( ! $this->canDebit( $paymentData ) && $this->getIsDebited( $paymentData ) && $resursTotalAmount > 0 ) {
			return RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_COMPLETED;
		}
		if ( $this->getIsAnnulled( $paymentData ) && ! $this->getIsCredited( $paymentData ) && $resursTotalAmount == 0 ) {
			return RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_ANNULLED;
		}
		if ( $this->getIsCredited( $paymentData ) && $resursTotalAmount == 0 ) {
			return RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_CREDITED;
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
				if ( ( isset( $paymentData->frozen ) && ! $paymentData->frozen ) || ! isset( $paymentData->frozen ) ) {
					return RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_PROCESSING;
				} else {
					return RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_PENDING;
				}
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
	public function getOrderStatusStringByReturnCode($returnCode = RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_STATUS_COULD_NOT_BE_SET) {
		switch ($returnCode) {
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
}
