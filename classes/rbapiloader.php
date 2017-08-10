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
 * @version 1.1.13
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
require_once(RB_API_PATH . '/rbapiloader/ResursEnvironments.php');
require_once(RB_API_PATH . '/rbapiloader/ResursException.php');

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
	public $debug = false;
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
	/** @var bool User activation flag */
	private $allowObsoletePHP = false;

	///// Environment and API
	/** @var int Current targeted environment - default is always test, as we don't like that mistakes are going production */
	public $current_environment = self::ENVIRONMENT_TEST;
	/** @var null The username used with the webservices */
	public $username = null;
	/** @var null The password used with the webservices */
	public $password = null;

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
	public $customerId = "";

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
	private $version = "1.1.13";
	/** @var string Identify current version release (as long as we are located in v1.0.0beta this is necessary */
	private $lastUpdate = "20170810";
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
	 * @var \TorneLIB\Tornevall_cURL
	 * @since 1.0.1
	 * @since 1.1.1
	 */
	private $CURL;
	/**
	 * @var \TorneLIB\TorneLIB_Network Class for handling Network related checks
	 * @since 1.0.1
	 * @since 1.1.1
	 */
	private $NETWORK;
	/**
	 * @var \TorneLIB\TorneLIB_Crypto Class for handling data encoding/encryption
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
	 * Payment spec (orderlines)
	 * @var
	 * @since 1.0.2
	 * @since 1.1.2
	 */
	private $SpecLines;

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

	/**
	 * @var null
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
	/** @var string The current directory of RB Classes */
	private $classPath = "";
	/** @var array Files to look for in class directories, to find RB */
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

	/** @var string Default unit measure. "st" or styck for Sweden. If your plugin is not used for Sweden, use the proper unit for your country. */
	private $defaultUnitMeasure;

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
		if ( ! is_null( $login ) ) {
			$this->soapOptions['login'] = $login;
			$this->username             = $login; // For use with initwsdl
		}
		if ( ! is_null( $password ) ) {
			$this->soapOptions['password'] = $password;
			$this->password                = $password; // For use with initwsdl
		}
		// PreSelect environment when creating the class
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
			throw new \Exception( __FUNCTION__ . ": HTTPS wrapper can not be found", \ResursExceptions::SSL_WRAPPER_MISSING );
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

		return $this->hasServicesInitialization;
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
			/** @noinspection PhpIncludeInspection */
			require $this->classPath . '/simplifiedshopflowservice-client/Resurs_SimplifiedShopFlowService.php';
			$apiFileLoads ++;
		}
		if ( in_array( 'configurationservice', array_map( "strtolower", $this->Include ) ) && file_exists( $this->classPath . '/configurationservice-client/Resurs_ConfigurationService.php' ) ) {
			/** @noinspection PhpIncludeInspection */
			require $this->classPath . '/configurationservice-client/Resurs_ConfigurationService.php';
			$apiFileLoads ++;
		}
		if ( in_array( 'aftershopflowservice', array_map( "strtolower", $this->Include ) ) && file_exists( $this->classPath . '/aftershopflowservice-client/Resurs_AfterShopFlowService.php' ) ) {
			/** @noinspection PhpIncludeInspection */
			require $this->classPath . '/aftershopflowservice-client/Resurs_AfterShopFlowService.php';
			$apiFileLoads ++;
		}
		/**
		 * Loads the deprecated flow as the last class, if found in our library. However, we normally don't deliver this setup in our EComPHP-package, so if we find this
		 * the developer may have added it him/herself.
		 */
		if ( in_array( 'shopflowservice', array_map( "strtolower", $this->Include ) ) && file_exists( $this->classPath . '/shopflowservice-client/Resurs_ShopFlowService.php' ) ) {
			/** @noinspection PhpIncludeInspection */
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
					/** @noinspection PhpUnusedLocalVariableInspection */
					$currentService        = "shopFlowService";
					$this->shopFlowService = new Resurs_ShopFlowService( $this->soapOptions, $this->environment . "ShopFlowService?wsdl" );
				}
				// 1.1
				if ( class_exists( '\Resursbank\RBEcomPHP\Resurs_ShopFlowService' ) ) {
					$this->hasNameSpace = true;
					/** @noinspection PhpUnusedLocalVariableInspection */
					$currentService        = "shopFlowService";
					$this->shopFlowService = new Resurs_ShopFlowService( $this->soapOptions, $this->environment . "ShopFlowService?wsdl" );
				}
			} catch ( \Exception $e ) {
				/** Adds the $currentService to the message, to show which service that failed */
				throw new \Exception( __FUNCTION__ . ": " . $e->getMessage() . "\nStuck on service: " . $currentService, \ResursExceptions::WSDL_APILOAD_EXCEPTION, $e );
			}
		}

		if ( class_exists( '\TorneLIB\Tornevall_cURL' ) ) {
			$this->CURL = new \TorneLIB\Tornevall_cURL();
			$this->CURL->setStoreSessionExceptions( true );
			$this->CURL->setAuthentication( $this->soapOptions['login'], $this->soapOptions['password'] );
			$this->CURL->setUserAgent( $this->myUserAgent );
		}
		if ( class_exists( '\TorneLIB\TorneLIB_Network' ) ) {
			$this->NETWORK = new \TorneLIB\TorneLIB_Network();
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
	 * Find classes
	 *
	 * @param string $path
	 *
	 * @return bool
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
				throw new \Exception( __FUNCTION__ . "/" . $func . "/" . $classfunc . ": " . $e->getMessage(), \ResursExceptions::WSDL_PASSTHROUGH_EXCEPTION );
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
	public function setTestUrl( $newUrl = '', $FlowType = ResursMethodTypes::METHOD_UNDEFINED ) {
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
		if ( $FlowType == ResursMethodTypes::METHOD_SIMPLIFIED ) {
			$this->env_test = $newUrl;
		} else if ( $FlowType == ResursMethodTypes::METHOD_HOSTED ) {
			$this->env_hosted_test = $newUrl;
		} else if ( $FlowType == ResursMethodTypes::METHOD_CHECKOUT ) {
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
	private function getCallbackTypeString( $callbackType = ResursCallbackTypes::UNDEFINED ) {
		if ( $callbackType == ResursCallbackTypes::UNDEFINED ) {
			return null;
		}
		if ( $callbackType == ResursCallbackTypes::ANNULMENT ) {
			return "ANNULMENT";
		}
		if ( $callbackType == ResursCallbackTypes::AUTOMATIC_FRAUD_CONTROL ) {
			return "AUTOMATIC_FRAUD_CONTROL";
		}
		if ( $callbackType == ResursCallbackTypes::FINALIZATION ) {
			return "FINALIZATION";
		}
		if ( $callbackType == ResursCallbackTypes::TEST ) {
			return "TEST";
		}
		if ( $callbackType == ResursCallbackTypes::UNFREEZE ) {
			return "UNFREEZE";
		}
		if ( $callbackType == ResursCallbackTypes::UPDATE ) {
			return "UPDATE";
		}
		if ( $callbackType == ResursCallbackTypes::BOOKED ) {
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
			return ResursCallbackTypes::ANNULMENT;
		}
		if ( strtoupper( $callbackTypeString ) == "UPDATE" ) {
			return ResursCallbackTypes::UPDATE;
		}
		if ( strtoupper( $callbackTypeString ) == "TEST" ) {
			return ResursCallbackTypes::TEST;
		}
		if ( strtoupper( $callbackTypeString ) == "FINALIZATION" ) {
			return ResursCallbackTypes::FINALIZATION;
		}
		if ( strtoupper( $callbackTypeString ) == "AUTOMATIC_FRAUD_CONTROL" ) {
			return ResursCallbackTypes::AUTOMATIC_FRAUD_CONTROL;
		}
		if ( strtoupper( $callbackTypeString ) == "UNFREEZE" ) {
			return ResursCallbackTypes::UNFREEZE;
		}
		if ( strtoupper( $callbackTypeString ) == "BOOKED" ) {
			return ResursCallbackTypes::BOOKED;
		}

		return ResursCallbackTypes::UNDEFINED;
	}

	/**
	 * Set up digestive parameters baed on requested callback type
	 *
	 * @param int $callbackType
	 *
	 * @return array
	 */
	private function getCallbackTypeParameters( $callbackType = ResursCallbackTypes::UNDEFINED ) {
		if ( $callbackType == ResursCallbackTypes::ANNULMENT ) {
			return array( 'paymentId' );
		}
		if ( $callbackType == ResursCallbackTypes::AUTOMATIC_FRAUD_CONTROL ) {
			return array( 'paymentId', 'result' );
		}
		if ( $callbackType == ResursCallbackTypes::FINALIZATION ) {
			return array( 'paymentId' );
		}
		if ( $callbackType == ResursCallbackTypes::TEST ) {
			return array( 'param1', 'param2', 'param3', 'param4', 'param5' );
		}
		if ( $callbackType == ResursCallbackTypes::UNFREEZE ) {
			return array( 'paymentId' );
		}
		if ( $callbackType == ResursCallbackTypes::UPDATE ) {
			return array( 'paymentId' );
		}
		if ( $callbackType == ResursCallbackTypes::BOOKED ) {
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
	public function setCallbackDigest( $digestSaltString = '', $callbackType = ResursCallbackTypes::UNDEFINED ) {
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
	public function setCallbackDigestSalt( $digestSaltString = '', $callbackType = ResursCallbackTypes::UNDEFINED ) {
		// Make sure the digestSaltString is never empty
		if ( ! empty( $digestSaltString ) ) {
			$currentDigest = $digestSaltString;
		} else {
			$currentDigest = $this->getSaltKey( 4, 10 );
		}
		if ( $callbackType !== ResursCallbackTypes::UNDEFINED ) {
			$callbackTypeString                     = $this->getCallbackTypeString( ! is_null( $callbackType ) ? $callbackType : ResursCallbackTypes::UNDEFINED );
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
	 * @link https://test.resurs.com/docs/display/ecom/ECommerce+PHP+Library#ECommercePHPLibrary-getCallbacksByRest
	 * @since 1.0.1
	 */
	public function getCallBacksByRest( $ReturnAsArray = false ) {
		$this->InitializeServices();
		$ResursResponse = $this->CURL->getParsedResponse( $this->CURL->doGet( $this->getCheckoutUrl() . "/callbacks" ) );
		if ( $ReturnAsArray ) {
			$ResursResponseArray = array();
			if ( is_array( $ResursResponse ) && count( $ResursResponse ) ) {
				foreach ( $ResursResponse as $object ) {
					if ( isset( $object->eventType ) ) {
						$ResursResponseArray[ $object->eventType ] = isset( $object->uriTemplate ) ? $object->uriTemplate : "";
					}
				}
			}

			return $ResursResponseArray;
		}

		return $ResursResponse;
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
	public function setRegisterCallback( $callbackType = ResursCallbackTypes::UNDEFINED, $callbackUriTemplate = "", $digestData = array(), $basicAuthUserName = null, $basicAuthPassword = null ) {
		$returnSuccess = false;
		$this->InitializeServices();
		if ( is_array( $this->validateExternalUrl ) && count( $this->validateExternalUrl ) ) {
			$isValidAddress = $this->validateExternalAddress();
			if ( $isValidAddress == ResursCallbackReachability::IS_NOT_REACHABLE ) {
				throw new \Exception( "Reachability Response: Your site might not be available to our callbacks" );
			} else if ( $isValidAddress == ResursCallbackReachability::IS_REACHABLE_WITH_PROBLEMS ) {
				throw new \Exception( "Reachability Response: Your site is availble from the outide. However, problems occured during tests, that indicates that your site is not available to our callbacks" );
			}
		}
		// The final array
		$renderCallback = array();

		// DEFAULT SETUP
		$renderCallback['eventType'] = $this->getCallbackTypeString( $callbackType );
		if ( empty( $renderCallback['eventType'] ) ) {
			throw new \Exception( __FUNCTION__ . ": The callback type you are trying to register is not supported by EComPHP", \ResursExceptions::CALLBACK_TYPE_UNSUPPORTED );
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
			throw new \Exception( "Can not continue without a digest salt key", \ResursExceptions::CALLBACK_SALTDIGEST_MISSING );
		}
		////// DIGEST CONFIGURATION FINISH
		if ( $this->registerCallbacksViaRest ) {
			$serviceUrl        = $this->getCheckoutUrl() . "/callbacks";
			$renderCallbackUrl = $serviceUrl . "/" . $renderCallback['eventType'];
			if ( isset( $renderCallback['eventType'] ) ) {
				unset( $renderCallback['eventType'] );
			}
			$renderedResponse = $this->CURL->doPost( $renderCallbackUrl, $renderCallback, \TorneLIB\CURL_POST_AS::POST_AS_JSON );
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
	public function unregisterEventCallback( $callbackType = ResursCallbackTypes::UNDEFINED ) {
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
	public function setCallback( $callbackType = ResursCallbackTypes::UNDEFINED, $callbackUriTemplate = "", $callbackDigest = array(), $basicAuthUserName = null, $basicAuthPassword = null ) {
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
	public function unSetCallback( $callbackType = ResursCallbackTypes::UNDEFINED ) {
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
		$serviceUrl = $this->env_test . "DeveloperWebService?wsdl";
		$CURL       = new \TorneLIB\Tornevall_cURL();
		$CURL->setAuthentication( $this->username, $this->password );
		$CURL->setUserAgent( $this->myUserAgent );
		$eventRequest    = $CURL->doGet( $serviceUrl );
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
		if ( ! empty( $this->CURL ) ) {
			if ( isset( $this->ServiceRequestList[ $ServiceName ] ) && isset( $this->URLS[ $this->ServiceRequestList[ $ServiceName ] ] ) ) {
				$properService = $this->URLS[ $this->ServiceRequestList[ $ServiceName ] ];
			}
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
		if ( ! empty( $this->CURL ) ) {
			if ( isset( $this->ServiceRequestMethods[ $ServiceName ] ) ) {
				$ReturnMethod = $this->ServiceRequestMethods[ $ServiceName ];
			}
		}

		return strtolower( $ReturnMethod );
	}

	/**
	 * Enforce another method than the simplified flow
	 *
	 * @param int $methodType
	 *
	 * @since 1.0.0
	 * @since 1.1.0
	 */
	public function setPreferredPaymentService( $methodType = ResursMethodTypes::METHOD_UNDEFINED ) {
		$this->enforceService = $methodType;
		if ( $methodType == ResursMethodTypes::METHOD_HOSTED ) {
			$this->isHostedFlow = true;
			$this->isOmniFlow   = false;
		} elseif ( $methodType == ResursMethodTypes::METHOD_CHECKOUT ) {
			$this->isHostedFlow = false;
			$this->isOmniFlow   = true;
		} elseif ( $methodType == ResursMethodTypes::METHOD_SIMPLIFIED ) {
			$this->isHostedFlow = false;
			$this->isOmniFlow   = false;
		} else {
			$this->isHostedFlow = false;
			$this->isOmniFlow   = false;
		}
	}

	/**
	 * Return the current set "preferred payment service" (hosted, checkout, simplified)
	 * @return null
	 * @since 1.0.0
	 * @since 1.1.0
	 */
	public function getPreferredPaymentService() {
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
	 * @since 1.0.2
	 * @since 1.1.2
	 */
	private function postService( $serviceName = "", $resursParameters = array(), $getResponseCode = false ) {
		$this->InitializeServices();
		$Service        = $this->CURL->doGet( $this->getServiceUrl( $serviceName ) );
		$RequestService = $Service->$serviceName( $resursParameters );
		$ParsedResponse = $Service->getParsedResponse( $RequestService );
		$ResponseCode   = $Service->getResponseCode();
		if ( ! $getResponseCode ) {
			return $ParsedResponse;
		} else {
			return $ResponseCode;
		}
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
		if ( class_exists( '\TorneLIB\TorneLIB_Crypto' ) ) {
			$this->T_CRYPTO = new \TorneLIB\TorneLIB_Crypto();
		}
		if (!empty($this->T_CRYPTO)) {
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
	 * @throws ResursException
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

		return $paymentMethods;
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
	 * @param  string $paymentId [The current paymentId]
	 * @param  string $to [What it should be updated to]
	 *
	 * @return mixed
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
		$result = $this->CURL->doPut($url, array( 'paymentReference' => $to), \TorneLIB\CURL_POST_AS::POST_AS_JSON);
		$ResponseCode = $this->CURL->getResponseCode($result);
		if ( $ResponseCode >= 200 && $ResponseCode <= 250 ) {
			return true;
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
	 * @return int Returns a value from the class ResursCallbackReachability
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
				return ResursCallbackReachability::IS_REACHABLE_NOT_AVAILABLE;
			}
			$Expect           = $this->validateExternalUrl['http_accept'];
			$UnExpect         = $this->validateExternalUrl['http_error'];
			$useUrl           = $this->validateExternalUrl['url'];
			$ExternalPostData = array( 'link' => $this->NETWORK->base64url_encode( $useUrl ), "returnEncoded"=>true );
			try {
				$this->CURL->doPost( $ExternalAPI, $ExternalPostData, \TorneLIB\CURL_POST_AS::POST_AS_JSON );
				$WebResponse = $this->CURL->getParsedResponse();
			} catch ( \Exception $e ) {
				return ResursCallbackReachability::IS_REACHABLE_NOT_KNOWN;
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
				return ResursCallbackReachability::IS_NOT_REACHABLE;
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
				return ResursCallbackReachability::IS_FULLY_REACHABLE;
			}
			if ( $expectedResults > 0 && $unExpectedResults > 0 ) {
				return ResursCallbackReachability::IS_REACHABLE_WITH_PROBLEMS;
			}
			if ( $neitherResults > 0 ) {
				return ResursCallbackReachability::IS_REACHABLE_NOT_KNOWN;
			}
			if ( $expectedResults === 0 ) {
				return ResursCallbackReachability::IS_NOT_REACHABLE;
			}
		}
		return ResursCallbackReachability::IS_REACHABLE_NOT_KNOWN;
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
	 * PaymentSpecCleaner
	 *
	 * @param array $currentArray The current speclineArray
	 * @param array $cleanWith The array with the speclines that should be removed from currentArray
	 * @param bool $includeId Include matching against id (meaning both id and artNo is trying to match to make the search safer)
	 *
	 * @return array New array
	 * @since 1.0.0
	 * @since 1.1.0
	 */
	private function removeFromArray( $currentArray = array(), $cleanWith = array(), $includeId = false ) {
		$cleanedArray = array();
		foreach ( $currentArray as $currentObject ) {
			if ( is_array( $cleanWith ) ) {
				$foundObject = false;
				foreach ( $cleanWith as $currentCleanObject ) {
					if ( is_object( $currentCleanObject ) ) {
						if ( ! empty( $currentObject->artNo ) ) {
							/**
							 * Search with both id and artNo - This may fail so we are normally ignoring the id from a specline.
							 * If you are absolutely sure that your speclines are fully matching each other, you may enabled id-searching.
							 */
							if ( ! $includeId ) {
								if ( $currentObject->artNo == $currentCleanObject->artNo ) {
									$foundObject = true;
									break;
								}
							} else {
								if ( $currentObject->id == $currentCleanObject->id && $currentObject->artNo == $currentCleanObject->artNo ) {
									$foundObject = true;
									break;
								}
							}
						}
					}
				}
				if ( ! $foundObject ) {
					$cleanedArray[] = $currentObject;
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
			return $this->version . "-" . $this->lastUpdate;
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
	 *
	 * @since 1.0.4
	 * @since 1.1.4
	 */
	public function setShopUrl( $shopUrl = '' ) {
		if ( ! empty( $shopUrl ) ) {
			$this->checkoutShopUrl = $shopUrl;
		}
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
	public function toJsonByType( $dataContainer = array(), $paymentMethodType = ResursMethodTypes::METHOD_SIMPLIFIED, $updateCart = false ) {
		// We need the content as is at this point since this part normally should be received as arrays
		$newDataContainer = $this->getDataObject( $dataContainer, false, true );
		if ( ! isset( $newDataContainer['type'] ) || empty( $newDataContainer['type'] ) ) {
			if ( $paymentMethodType == ResursMethodTypes::METHOD_HOSTED ) {
				$newDataContainer['type'] = 'hosted';
			} else if ( $paymentMethodType == ResursMethodTypes::METHOD_OMNI ) {
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
	public function getBookedJsonObject( $method = ResursMethodTypes::METHOD_UNDEFINED ) {
		$returnObject = new \stdClass();
		if ( $method == ResursMethodTypes::METHOD_SIMPLIFIED ) {
			return $returnObject;
		} elseif ( $method == ResursMethodTypes::METHOD_HOSTED ) {
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
	private function createJsonEngine( $url = '', $jsonData = "", $curlMethod = ResursCurlMethods::METHOD_POST ) {
		if ( empty( $this->CURL ) ) {
			$this->InitializeServices();
		}
		$CurlLibResponse = null;
		$this->CURL->setAuthentication( $this->username, $this->password );
		$this->CURL->setUserAgent( $this->myUserAgent );

		if ( $curlMethod == ResursCurlMethods::METHOD_POST ) {
			$CurlLibResponse = $this->CURL->doPost( $url, $jsonData, \TorneLIB\CURL_POST_AS::POST_AS_JSON );
		} else if ( $curlMethod == ResursCurlMethods::METHOD_PUT ) {
			$CurlLibResponse = $this->CURL->doPut( $url, $jsonData, \TorneLIB\CURL_POST_AS::POST_AS_JSON );
		} else {
			$CurlLibResponse = $this->CURL->doGet( $url, \TorneLIB\CURL_POST_AS::POST_AS_JSON );
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





	/////////// PAYMENT SPECS AND ROWS - IN DECISION OF DEPRECATION

	/**
	 * Scan client specific specrows for matches
	 *
	 * @param $clientPaymentSpec
	 * @param $artNo
	 * @param $quantity
	 * @param bool $quantityMatch
	 * @param bool $useSpecifiedQuantity If set to true, the quantity set clientside will be used rather than the exact quantity from the spec in getPayment (This requires that $quantityMatch is set to false)
	 *
	 * @return bool
	 */
	private function inSpec( $clientPaymentSpec, $artNo, $quantity, $quantityMatch = true, $useSpecifiedQuantity = false ) {
		$foundArt = false;
		foreach ( $clientPaymentSpec as $row ) {
			if ( isset( $row['artNo'] ) ) {
				if ( $row['artNo'] == $artNo ) {
					// If quantity match is true, quantity must match the request to return a true value
					if ( $quantityMatch ) {
						// Consider full quantity if no quantity is set
						if ( ! isset( $row['quantity'] ) ) {
							$foundArt = true;

							return true;
						}
						if ( isset( $row['quantity'] ) && (float) $row['quantity'] === (float) $quantity ) {
							return true;
						} else {
							// Eventually set this to false, unless the float controll is successful
							$foundArt = false;
							// If the float control fails, also try check against integers
							if ( isset( $row['quantity'] ) && intval( $row['quantity'] ) > 0 && intval( $quantity ) > 0 && intval( $row['quantity'] ) === intval( $quantity ) ) {
								return true;
							}
						}
					} else {
						return true;
					}
				} else {
					$foundArt = false;
				}
			}
		}

		return $foundArt;
	}

	/**
	 * @param array $specLines
	 *
	 * @return array
	 */
	private function stripPaymentSpec( $specLines = array() ) {
		$newSpec = array();
		if ( is_array( $specLines ) && count( $specLines ) ) {
			foreach ( $specLines as $specRow ) {
				if ( isset( $specRow->artNo ) && ! empty( $specRow->artNo ) ) {
					$newSpec[] = array(
						'artNo'    => $specRow->artNo,
						'quantity' => $specRow->quantity
					);
				}
			}
		}

		return $newSpec;
	}

	private function handleClientPaymentSpec( $clientPaymentSpec = array() ) {
		/**
		 * Make sure we are pushing in this spec in the correct format, which is:
		 * array(
		 *  [0] => array(
		 *      'artNo' => [...]
		 *      ),
		 *  [1] = array(
		 *      'artNo' => [...]
		 *      )
		 * )
		 * - etc and not like: array('artNo'=>[...]);
		 */
		if ( isset( $clientPaymentSpec['artNo'] ) ) {
			$newClientSpec   = array();
			$newClientSpec[] = $clientPaymentSpec;
		} else {
			$newClientSpec = $clientPaymentSpec;
		}

		return $newClientSpec;
	}

	/**
	 * Render a specLine-array depending on the needs (This function is explicitly used for the AfterShopFlow together with Finalize/Annul/Credit
	 *
	 * @param array $paymentArray
	 * @param int $renderType
	 * @param array $finalizeParams
	 *
	 * @return array
	 * @throws \Exception
	 */
	private function renderSpecLine( $paymentArray = array(), $renderType = ResursAfterShopRenderTypes::NONE, $finalizeParams = array() ) {
		$returnSpecObject = array();
		if ( $renderType == ResursAfterShopRenderTypes::NONE ) {
			throw new \Exception( __FUNCTION__ . ": Can not render specLines without RenderType", 500 );
		}
		/* Preparation of the returning array*/
		$specLines = array();

		/* Preparation */
		$currentSpecs = array(
			'AUTHORIZE' => array(),
			'DEBIT'     => array(),
			'CREDIT'    => array(),
			'ANNUL'     => array()
		);

		/*
		 * This method summarizes all specrows in a proper objectarray, depending on the paymentdiff type.
		 */
		/** @noinspection PhpUndefinedFieldInspection */
		if ( isset( $paymentArray->paymentDiffs->paymentSpec->specLines ) ) {
			/** @noinspection PhpUndefinedFieldInspection */
			$specType = $paymentArray->paymentDiffs->type;
			/** @noinspection PhpUndefinedFieldInspection */
			$specLineArray = $paymentArray->paymentDiffs->paymentSpec->specLines;
			if ( is_array( $specLineArray ) ) {
				foreach ( $specLineArray as $subObjects ) {
					array_push( $currentSpecs[ $specType ], $subObjects );
				}
			} else {
				array_push( $currentSpecs[ $specType ], $specLineArray );
			}
		} else {
			// If the paymentarray does not have speclines, something else has been done with this payment
			if ( isset( $paymentArray->paymentDiffs ) ) {
				foreach ( $paymentArray->paymentDiffs as $specsObject ) {
					/* Catch up the payment and split it up */
					$specType = $specsObject->type;
					/* Making sure that everything is handled equally */
					$specLineArray = $specsObject->paymentSpec->specLines;
					if ( isset( $specsObject->paymentSpec->specLines ) ) {
						if ( is_array( $specLineArray ) ) {
							foreach ( $specLineArray as $subObjects ) {
								array_push( $currentSpecs[ $specType ], $subObjects );
							}
						} else {
							array_push( $currentSpecs[ $specType ], $specLineArray );
						}
					}
				}
			}
		}

		/* Finalization is being done on all authorized rows that is not already finalized (debit), annulled or crediter*/
		if ( $renderType == ResursAfterShopRenderTypes::FINALIZE ) {
			$returnSpecObject = $this->removeFromArray( $currentSpecs['AUTHORIZE'], array_merge( $currentSpecs['DEBIT'], $currentSpecs['ANNUL'], $currentSpecs['CREDIT'] ) );
		}
		/* Credit is being done on all authorized rows that is not annuled or already credited */
		if ( $renderType == ResursAfterShopRenderTypes::CREDIT ) {
			$returnSpecObject = $this->removeFromArray( $currentSpecs['DEBIT'], array_merge( $currentSpecs['ANNUL'], $currentSpecs['CREDIT'] ) );
		}
		/* Annul is being done on all authorized rows that is not already annulled, debited or credited */
		if ( $renderType == ResursAfterShopRenderTypes::ANNUL ) {
			$returnSpecObject = $this->removeFromArray( $currentSpecs['AUTHORIZE'], array_merge( $currentSpecs['DEBIT'], $currentSpecs['ANNUL'], $currentSpecs['CREDIT'] ) );
		}
		if ( $renderType == ResursAfterShopRenderTypes::UPDATE ) {
			$returnSpecObject = $currentSpecs['AUTHORIZE'];
		}

		return $returnSpecObject;
	}

	/**
	 * Render a full paymentSpec for AfterShop
	 *
	 * Depending on the rendering type the specrows may differ, but the primary goal is to only handle rows from a payment spec that is actual for the moment.
	 *
	 * Examples:
	 *   - Request: Finalize. The payment has credited and annulled rows. Credited, annulled and formerly debited rows are ignored.
	 *   - Request: Annul. The payment is partially debited. Debited and former annulled rows are ignored.
	 *   - Request: Annul. The payment is partially credited. Credited and debited rows is ignored.
	 *   - Request: Credit. The payment is partially annulled. Only debited rows will be chosen.
	 *
	 * @param $paymentId
	 * @param $renderType
	 * @param array $paymentArray The actual full speclineArray to handle
	 * @param array $clientPaymentSpec (Optional) paymentspec if only specified lines are being finalized
	 * @param array $renderParams Finalize parameters received from the server-application
	 * @param bool $quantityMatch (Optional, Passthrough) Match quantity. If false, quantity will be ignored during finalization and all client specified paymentspecs will match
	 * @param bool $useSpecifiedQuantity If set to true, the quantity set clientside will be used rather than the exact quantity from the spec in getPayment (This requires that $quantityMatch is set to false)
	 *
	 * @return array
	 * @throws \Exception
	 * @deprecated 1.0.2
	 * @deprecated 1.1.2
	 */
	public function renderPaymentSpecContainer( $paymentId, $renderType, $paymentArray = array(), $clientPaymentSpec = array(), $renderParams = array(), $quantityMatch = true, $useSpecifiedQuantity = false ) {
		$paymentSpecLine         = $this->renderSpecLine( $paymentArray, $renderType, $renderParams );
		$totalAmount             = 0;
		$totalVatAmount          = 0;
		$newSpecLine             = array();
		$paymentContainerContent = array();
		$paymentContainer        = array();
		$paymentMethodType       = isset( $paymentArray->paymentMethodType ) ? $paymentArray->paymentMethodType : "";
		$isInvoice               = false;
		if ( strtoupper( $paymentMethodType ) === "INVOICE" ) {
			$isInvoice = true;
		}

		if ( ! count( $paymentSpecLine ) ) {
			/* Should occur when you for example try to annul an order that is already debited or credited */
			throw new \Exception( __FUNCTION__ . ": No articles was added during the renderingprocess (RenderType $renderType)", \ResursExceptions::PAYMENTSPEC_EMPTY );
		}

		if ( is_array( $paymentSpecLine ) && count( $paymentSpecLine ) ) {
			/* Calculate totalAmount to finalize */
			foreach ( $paymentSpecLine as $row ) {
				if ( is_array( $clientPaymentSpec ) && count( $clientPaymentSpec ) ) {
					/**
					 * Partial payments control
					 * If the current article is missing in the requested $clientPaymentSpec, it should be included into the summary and therefore not be calculated.
					 */
					if ( $this->inSpec( $clientPaymentSpec, $row->artNo, $row->quantity, $quantityMatch, $useSpecifiedQuantity ) ) {
						/**
						 * Partial specrow quantity modifier - Beta
						 * Activated by setting $useSpecifiedQuantity to true
						 * Warning: Do not use this special feature unless you know what you are doing
						 *
						 * Used when own quantity values are set instead of the one set in the received payment spec. This is actually being used
						 * when we are for example are trying to annul parts of a specrow instead of the full row.
						 */
						if ( $useSpecifiedQuantity ) {
							foreach ( $clientPaymentSpec as $item ) {
								if ( isset( $item['artNo'] ) && ! empty( $item['artNo'] ) && $item['artNo'] == $row->artNo && isset( $item['quantity'] ) && intval( $item['quantity'] ) > 0 ) {
									/* Recalculate the new totalVatAmount */
									$newTotalVatAmount = ( $row->unitAmountWithoutVat * ( $row->vatPct / 100 ) ) * $item['quantity'];
									/* Recalculate the new totalAmount */
									$newTotalAmount = ( $row->unitAmountWithoutVat * $item['quantity'] ) + $newTotalVatAmount;
									/* Change the new values in the current row */
									$row->quantity       = $item['quantity'];
									$row->totalVatAmount = $newTotalVatAmount;
									$row->totalAmount    = $newTotalAmount;
									break;
								}
							}
							/* Put the manipulated row into the specline*/
							$newSpecLine[]  = $this->objectsIntoArray( $row );
							$totalAmount    += $row->totalAmount;
							$totalVatAmount += $row->totalVatAmount;
						} else {
							$newSpecLine[]  = $this->objectsIntoArray( $row );
							$totalAmount    += $row->totalAmount;
							$totalVatAmount += $row->totalVatAmount;
						}
					}
				} else {
					$newSpecLine[]  = $this->objectsIntoArray( $row );
					$totalAmount    += $row->totalAmount;
					$totalVatAmount += $row->totalVatAmount;
				}
			}
			$paymentSpec             = array(
				'specLines'      => $newSpecLine,
				'totalAmount'    => $totalAmount,
				'totalVatAmount' => $totalVatAmount
			);
			$paymentContainerContent = array(
				'paymentId'       => $paymentId,
				'partPaymentSpec' => $paymentSpec
			);

			/**
			 * Note: If the paymentspec are rendered without speclines, this may be caused by for example a finalization where the speclines already are finalized.
			 */
			if ( ! count( $newSpecLine ) ) {
				throw new \Exception( __FUNCTION__ . ": No articles has been added to the paymentspec due to mismatching clientPaymentSpec", \ResursExceptions::PAYMENTSPEC_EMPTY );
			}

			/* If no invoice id is set, we are assuming that Resurs Bank Invoice numbering sequence is the right one - Enforcing an invoice number if not exists */
			if ( $isInvoice ) {
				$paymentContainerContent['orderDate']   = date( 'Y-m-d', time() );
				$paymentContainerContent['invoiceDate'] = date( 'Y-m-d', time() );
				if ( ! isset( $renderParams['invoiceId'] ) ) {
					$renderParams['invoiceId'] = $this->getNextInvoiceNumber();
				}
			}
			$renderParams['createdBy'] = $this->getCreatedBy();
			$paymentContainer          = array_merge( $paymentContainerContent, $renderParams );

			/*
			 * Other data fields that can be sent from client (see below).
			 * Please note the orderId, this may be important for the order.
			 */

			/*
				$renderParams['ourReference'] = '';
				$renderParams['yourReference'] = '';
				$renderParams['preferredTransactionId'] = '';
				$renderParams['orderId'] = '';
			*/
		}

		return $paymentContainer;
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
			throw new \Exception( __FUNCTION__ . ": Country code is missing in getRegEx-request for form fields", \ResursExceptions::REGEX_COUNTRYCODE_MISSING );
		}
		if ( empty( $customerType ) ) {
			throw new \Exception( __FUNCTION__ . ": Customer type is missing in getRegEx-request for form fields", \ResursExceptions::REGEX_CUSTOMERTYPE_MISSING );
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
			throw new \Exception( __FUNCTION__ . ": templateFieldsByMethodResponse is empty. You have to run getTemplateFieldsByMethodType first", \ResursExceptions::FORMFIELD_CANHIDE_EXCEPTION );
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
					/** @noinspection PhpUndefinedFieldInspection */
					if ( isset( $templateRules[ strtoupper( $customerType ) ] ) && isset( $templateRules[ strtoupper( $customerType ) ]['fields'][ strtoupper( $paymentMethodName->specificType ) ] ) ) {
						/** @noinspection PhpUndefinedFieldInspection */
						$returnedRuleArray = $templateRules[ strtoupper( $customerType ) ]['fields'][ strtoupper( $paymentMethodName->specificType ) ];
					}
				}
			} else if ( is_array( $paymentMethodName ) ) {
				/*
				 * This should probably not happen and the developers should probably also stick to objects as above.
				 */
				if ( count( $paymentMethodName ) ) {
					if ( isset( $templateRules[ strtoupper( $customerType ) ] ) && isset( $templateRules[ strtoupper( $customerType ) ]['fields'][ strtoupper( $paymentMethodName['specificType'] ) ] ) ) {
						/** @noinspection PhpUndefinedFieldInspection */
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
		throw new \Exception( __FUNCTION__ . ": Can not fetch payment methods from cache. You must enable internal caching first.", \ResursExceptions::PAYMENT_METHODS_CACHE_DISABLED );
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
			throw new \Exception( __FUNCTION__ . ": Can not fetch annuity factors from cache. You must enable internal caching first.", \ResursExceptions::ANNUITY_FACTORS_CACHE_DISABLED );
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
					throw new \Exception( __FUNCTION__ . ": getAnnuityFactorsException  No available payment method", \ResursExceptions::ANNUITY_FACTORS_METHOD_UNAVAILABLE );
				}
			}
		}
		/** @noinspection PhpParamsInspection */
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
	public function setCountry( $Country = ResursCountry::COUNTRY_UNSET ) {
		if ( $Country === ResursCountry::COUNTRY_DK ) {
			$this->envCountry = "DK";
		} else if ( $Country === ResursCountry::COUNTRY_NO ) {
			$this->envCountry = "NO";
		} else if ( $Country === ResursCountry::COUNTRY_FI ) {
			$this->envCountry = "FI";
		} else if ( $Country === ResursCountry::COUNTRY_SE ) {
			$this->envCountry = "SE";
		} else {
			$this->envCountry = null;
		}

		return $this->envCountry;
	}

	/**
	 * Set up a country based on a country code string. Supported countries are SE, DK, NO and FI. Anything else than this defaults to SE
	 *
	 * @param string $countryCodeString
	 */
	public function setCountryByCountryCode( $countryCodeString = "" ) {
		if ( strtolower( $countryCodeString ) == "dk" ) {
			$this->setCountry( ResursCountry::COUNTRY_DK );
		} else if ( strtolower( $countryCodeString ) == "no" ) {
			$this->setCountry( ResursCountry::COUNTRY_NO );
		} else if ( strtolower( $countryCodeString ) == "fi" ) {
			$this->setCountry( ResursCountry::COUNTRY_FI );
		} else {
			$this->setCountry( ResursCountry::COUNTRY_SE );
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
	 */
	public function addOrderLine( $articleNumberOrId = '', $description = '', $unitAmountWithoutVat = 0, $vatPct = 0, $unitMeasure = 'st', $articleType = "ORDER_LINE", $quantity = 1 ) {
		if ( ! is_array( $this->SpecLines ) ) {
			$this->SpecLines = array();
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
	private function renderPaymentSpec( $overrideFlow = ResursMethodTypes::METHOD_UNDEFINED ) {
		$myFlow = $this->getPreferredPaymentService();
		if ( $overrideFlow !== ResursMethodTypes::METHOD_UNDEFINED ) {
			$myFlow = $overrideFlow;
		}
		$paymentSpec = array();
		if ( is_array( $this->SpecLines ) && count( $this->SpecLines ) ) {
			foreach ( $this->SpecLines as $specIndex => $specRow ) {
				if ( ! isset( $specRow['unitMeasure'] ) ) {
					$this->SpecLines[ $specIndex ]['unitMeasure'] = $this->defaultUnitMeasure;
				}
				if ( $myFlow === ResursMethodTypes::METHOD_SIMPLIFIED ) {
					$this->SpecLines[ $specIndex ]['id'] = ( $specIndex ) + 1;
				}
				if ( $myFlow === ResursMethodTypes::METHOD_HOSTED || $myFlow === ResursMethodTypes::METHOD_SIMPLIFIED ) {
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
			if ( $myFlow === ResursMethodTypes::METHOD_SIMPLIFIED ) {
				// Do not forget to pass over $myFlow-overriders to sanitizer as it might be sent from additionalDebitOfPayment rather than a regular bookPayment sometimes
				$this->Payload['orderData'] = array(
					'specLines'      => $this->sanitizePaymentSpec( $this->SpecLines, $myFlow ),
					'totalAmount'    => $paymentSpec['totalAmount'],
					'totalVatAmount' => $paymentSpec['totalVatAmount']
				);
			}
			if ( $myFlow === ResursMethodTypes::METHOD_HOSTED ) {
				// Do not forget to pass over $myFlow-overriders to sanitizer as it might be sent from additionalDebitOfPayment rather than a regular bookPayment sometimes
				$this->Payload['orderData'] = array(
					'orderLines'     => $this->sanitizePaymentSpec( $this->SpecLines, $myFlow ),
					'totalAmount'    => $paymentSpec['totalAmount'],
					'totalVatAmount' => $paymentSpec['totalVatAmount']
				);
			}
			if ( $myFlow == ResursMethodTypes::METHOD_CHECKOUT ) {
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
		// Payloads are built here
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
			throw new \Exception( "The use of exshop is no longer supported", \ResursExceptions::EXSHOP_PROHIBITED );
		}
		$error  = array();
		$myFlow = $this->getPreferredPaymentService();
		// Using this function to validate that card data info is properly set up during the deprecation state in >= 1.0.2/1.1.1
		if ( $myFlow == ResursMethodTypes::METHOD_SIMPLIFIED ) {
			$paymentMethodInfo = $this->getPaymentMethodSpecific( $payment_id_or_method );
			if ( $paymentMethodInfo->specificType == "CARD" || $paymentMethodInfo->specificType == "NEWCARD" || $paymentMethodInfo->specificType == "REVOLVING_CREDIT" ) {
				$this->validateCardData();
			}
			$myFlowResponse  = $this->postService( 'bookPayment', $this->Payload );
			$this->SpecLines = array();
			return $myFlowResponse;
		} else if ( $myFlow == ResursMethodTypes::METHOD_CHECKOUT ) {
			$checkoutUrl      = $this->getCheckoutUrl() . "/checkout/payments/" . $payment_id_or_method;
			$checkoutResponse = $this->CURL->doPost( $checkoutUrl, $this->Payload, \TorneLIB\CURL_POST_AS::POST_AS_JSON );
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
		} else if ( $myFlow == ResursMethodTypes::METHOD_HOSTED ) {
			$hostedUrl      = $this->getHostedUrl();
			$hostedResponse = $this->CURL->doPost( $hostedUrl, $this->Payload, \TorneLIB\CURL_POST_AS::POST_AS_JSON );
			$parsedResponse = $this->CURL->getParsedResponse( $hostedResponse );
			$responseCode   = $this->CURL->getResponseCode( $hostedResponse );
			// Do not trust response codes!
			if ( isset( $parsedResponse->location ) ) {
				$this->SpecLines = array();

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
			$this->setPreferredPaymentService( ResursMethodTypes::METHOD_SIMPLIFIED );
		}
		if ( $this->enforceService === ResursMethodTypes::METHOD_CHECKOUT ) {
			if ( empty( $payment_id_or_method ) && empty($this->preferredId)) {
				throw new \Exception( "A payment method or payment id must be defined", \ResursExceptions::CREATEPAYMENT_NO_ID_SET );
			}
			$payment_id_or_method = $this->preferredId;
		}
		if ( ! count( $this->Payload ) ) {
			throw new \Exception( "No payload are set for this payment", \ResursExceptions::BOOKPAYMENT_NO_BOOKDATA );
		}

		// Obsolete way to handle multidimensional specrows (1.0.0-1.0.1)
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
		}
		if ( $this->enforceService === ResursMethodTypes::METHOD_HOSTED || $this->enforceService === ResursMethodTypes::METHOD_SIMPLIFIED ) {
			$paymentDataPayload ['paymentData'] = array(
				'paymentMethodId'   => $payment_id_or_method,
				'preferredId'       => $this->getPreferredPaymentId(),
				'customerIpAddress' => $this->getCustomerIp()
			);
			if ( $this->enforceService === ResursMethodTypes::METHOD_SIMPLIFIED ) {
				if ( ! isset( $this->Payload['storeId'] ) && ! empty( $this->storeId ) ) {
					$this->Payload['storeId'] = $this->storeId;
				}
			}
			$this->handlePayload( $paymentDataPayload );
		}
		if ( ( $this->enforceService == ResursMethodTypes::METHOD_CHECKOUT || $this->enforceService == ResursMethodTypes::METHOD_HOSTED ) ) {
			// Convert signing to checkouturls if exists (not receommended as failurl might not always be the backurl)
			// However, those variables will only be replaced in the correct payload if they are not already there.
			if ( isset( $this->Payload['signing'] ) ) {
				if ( $this->enforceService == ResursMethodTypes::METHOD_HOSTED ) {
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
			if ( $this->enforceService == ResursMethodTypes::METHOD_CHECKOUT ) {
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
					$this->Payload['shopUrl'] = $this->checkoutShopUrl;
				}
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
	public function sanitizePaymentSpec( $specLines = array(), $myFlowOverrider = ResursMethodTypes::METHOD_UNDEFINED ) {
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
			$myFlow = $this->getPreferredPaymentService();
			if ( $myFlowOverrider !== ResursMethodTypes::METHOD_UNDEFINED ) {
				$myFlow = $myFlowOverrider;
			}
			$mySpecRules = array();
			if ( $myFlow == ResursMethodTypes::METHOD_SIMPLIFIED ) {
				$mySpecRules = $specRules['simplified'];
			} else if ( $myFlow == ResursMethodTypes::METHOD_HOSTED ) {
				$mySpecRules = $specRules['hosted'];
			} else if ( $myFlow == ResursMethodTypes::METHOD_CHECKOUT ) {
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
		if ( ! empty( trim( $addressRow2 ) ) ) {
			$ReturnAddress['addressRow2'] = $addressRow2;
		}
		if ( $this->enforceService === ResursMethodTypes::METHOD_SIMPLIFIED ) {
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
	 * @param $getaddressdata_or_governmentid
	 *
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
			throw new \Exception( "No customer type has been set. Use NATURAL or LEGAL to proceed", \ResursExceptions::BOOK_CUSTOMERTYPE_MISSING );
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
		$myFlow = $this->getPreferredPaymentService();
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
			if ( $myFlow == ResursMethodTypes::METHOD_HOSTED && isset( $this->Payload['customer']['address']['country'] ) ) {
				$this->Payload['customer']['address']['countryCode'] = $this->Payload['customer']['address']['country'];
			}
			unset( $this->Payload['address'] );
		}
		if ( isset( $this->Payload['deliveryAddress'] ) ) {
			$this->Payload['customer']['deliveryAddress'] = $this->Payload['deliveryAddress'];
			if ( $myFlow == ResursMethodTypes::METHOD_HOSTED && isset( $this->Payload['customer']['deliveryAddress']['country'] ) ) {
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
	 */
	public function getPayload() {
		$this->preparePayload();
		return $this->Payload;
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
				throw new \Exception( __FUNCTION__ . ": There is no bookData available for the booking", \ResursExceptions::BOOKPAYMENT_NO_BOOKDATA );
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
		if ( $this->enforceService == ResursMethodTypes::METHOD_OMNI ) {
			$bookData['type'] = "omni";
		} else {
			if ( isset( $bookData['type'] ) == "omni" ) {
				$this->enforceService = ResursMethodTypes::METHOD_OMNI;
				$this->isOmniFlow     = true;
			}
		}
		if ( $this->enforceService == ResursMethodTypes::METHOD_HOSTED ) {
			$bookData['type'] = "hosted";
		} else {
			if ( isset( $bookData['type'] ) == "hosted" ) {
				$this->enforceService = ResursMethodTypes::METHOD_HOSTED;
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
			/** @noinspection PhpParamsInspection */
			$bookPaymentInit = new resurs_bookPayment( $this->_paymentData, $this->_paymentOrderData, $this->_paymentCustomer, $this->_bookedCallbackUrl );
		} else {
			/*
			 * If no "new flow" are detected during the handle of payment here, and the class also exists so no booking will be possible, we should
			 * throw an execption here.
			 */
			if ( ! $this->isOmniFlow && ! $this->isHostedFlow ) {
				throw new \Exception( __FUNCTION__ . ": bookPaymentClass not found, and this is neither an omni nor hosted flow", \ResursExceptions::BOOKPAYMENT_NO_BOOKPAYMENT_CLASS );
			}
		}
		if ( ! empty( $this->cardDataCardNumber ) || $this->cardDataUseAmount ) {
			$bookPaymentInit->card = $this->updateCardData();
		}
		if ( ! empty( $this->_paymentDeliveryAddress ) && is_object( $this->_paymentDeliveryAddress ) ) {
			/** @noinspection PhpUndefinedFieldInspection */
			$bookPaymentInit->customer->deliveryAddress = $this->_paymentDeliveryAddress;
		}
		/* If the preferredId is set, check if there is a request for this varaible in the signing urls */
		/** @noinspection PhpUndefinedFieldInspection */
		if ( isset( $this->_paymentData->preferredId ) ) {
			// Make sure that the search and replace really works for unique id's
			if ( ! isset( $bookData['uniqueId'] ) ) {
				$bookData['uniqueId'] = "";
			}
			if ( isset( $bookData['signing']['successUrl'] ) ) {
				/** @noinspection PhpUndefinedFieldInspection */
				$bookData['signing']['successUrl'] = str_replace( '$preferredId', $this->_paymentData->preferredId, $bookData['signing']['successUrl'] );
				/** @noinspection PhpUndefinedFieldInspection */
				$bookData['signing']['successUrl'] = str_replace( '%24preferredId', $this->_paymentData->preferredId, $bookData['signing']['successUrl'] );
				if ( isset( $bookData['uniqueId'] ) ) {
					$bookData['signing']['successUrl'] = str_replace( '$uniqueId', $bookData['uniqueId'], $bookData['signing']['successUrl'] );
					$bookData['signing']['successUrl'] = str_replace( '%24uniqueId', $bookData['uniqueId'], $bookData['signing']['successUrl'] );
				}
			}
			if ( isset( $bookData['signing']['failUrl'] ) ) {
				/** @noinspection PhpUndefinedFieldInspection */
				$bookData['signing']['failUrl'] = str_replace( '$preferredId', $this->_paymentData->preferredId, $bookData['signing']['failUrl'] );
				/** @noinspection PhpUndefinedFieldInspection */
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
			$bookDataJson          = $this->toJsonByType( $bookData, ResursMethodTypes::METHOD_CHECKOUT );
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
				/** @noinspection PhpUndefinedFieldInspection */
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
		if ( empty( $paymentId ) ) {
			throw new \Exception( "Payment id not set" );
		}
		$omniUrl        = $this->getCheckoutUrl();
		$omniRefUrl     = $omniUrl . "/checkout/payments/" . $paymentId;
		$engineResponse = $this->createJsonEngine( $omniRefUrl, $jsonData, ResursCurlMethods::METHOD_PUT );

		return $engineResponse;
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
	 * @return array
	 * @throws \Exception
	 * @since 1.0.8
	 * @since 1.1.8
	 */
	public function setCheckoutFrameOrderLines( $paymentId = '', $orderLines = array() ) {
		if ( empty( $paymentId ) ) {
			throw new \Exception( "Payment id not set" );
		}
		if ( ! $this->hasServicesInitialization ) {
			$this->InitializeServices();
		}
		$outputOrderLines = array();
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
		$sanitizedOutputOrderLines = $this->sanitizePaymentSpec( $outputOrderLines, ResursMethodTypes::METHOD_CHECKOUT );

		return $this->CURL->doPut( $this->getCheckoutUrl() . "/checkout/payments/" . $paymentId, array( 'orderLines' => $sanitizedOutputOrderLines ), \TorneLIB\CURL_POST_AS::POST_AS_JSON );
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
		$jsonBookData          = $this->toJsonByType( $bookData, ResursMethodTypes::METHOD_HOSTED );
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
	 * @throws ResursException
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
				throw new \Exception( __FUNCTION__ . ": Class specLine does not exist", \ResursExceptions::UPDATECART_NOCLASS_EXCEPTION );
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
	 * @throws ResursException
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
	 * @throws ResursException
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
				/** @noinspection PhpParamsInspection */
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
				/** @noinspection PhpUndefinedFieldInspection */
				$this->_paymentCardData->amount = $this->_paymentOrderData->totalAmount;
			}
		} else {
			if ( isset( $this->cardDataCardNumber ) && ! empty( $this->cardDataCardNumber ) ) {
				$this->_paymentCardData->cardNumber = $this->cardDataCardNumber;
			}
		}
		if ( ! empty( $this->cardDataCardNumber ) && ! empty( $this->cardDataUseAmount ) ) {
			throw new \Exception( __FUNCTION__ . ": Card number and amount can not be set at the same time", \ResursExceptions::UPDATECARD_DOUBLE_DATA_EXCEPTION );
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
	 * @since 1.0.2
	 * @since 1.1.2
	 */
	private function validateCardData() {
		// Keeps compatibility with card data sets
		if ( isset( $this->Payload['orderData']['totalAmount'] ) && $this->getPreferredPaymentService() == ResursMethodTypes::METHOD_SIMPLIFIED ) {
			$cardInfo = isset( $this->Payload['card'] ) ? $this->Payload['card'] : array();
			if ( ( isset( $cardInfo['cardNumber'] ) && empty( $cardInfo['cardNumber'] ) ) || ! isset( $cardInfo['cardNumber'] ) ) {
				if ( ( isset( $cardInfo['amount'] ) && empty( $cardInfo['amount'] ) ) || ! isset( $cardInfo['amount'] ) ) {
					// Adding the exact total amount as we do not rule of exchange rates. For example, adding 500 extra to the total
					// amount in sweden will work, but will on the other hand be devastating for countries using euro.
					$this->Payload['card']['amount'] = $this->Payload['orderData']['totalAmount'];
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
	 * Get a payment spec for a specific order in which we see what state each orderline is in for the moment
	 *
	 * @param $paymentIdOrSpec
	 *
	 * @return array
	 */
	public function getPaymentSpecByStatus( $paymentIdOrSpec ) {
		$usePayment         = $paymentIdOrSpec;
		$currentSpecs       = array(
			'AUTHORIZE' => array(),
			'DEBIT'     => array(),
			'CREDIT'    => array(),
			'ANNUL'     => array()
		);
		$orderLinesByStatus = array();
		if ( is_string( $paymentIdOrSpec ) ) {
			$usePayment = $this->getPayment( $paymentIdOrSpec );
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
						$orderLinesByStatus[ $paymentDiffObject->type ] += $paymentDiffObject->paymentSpec->specLines;
					} else if ( is_object( $paymentDiffObject ) ) {
						$orderLinesByStatus[ $paymentDiffObject->type ][] = $paymentDiffObject->paymentSpec->specLines;
					}
				}
				//$paymentSpecType = $paymentDiff->type;
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
	 */
	public function cancelPayment( $paymentId = "", $clientPaymentSpec = array(), $cancelParams = array(), $quantityMatch = true, $useSpecifiedQuantity = false ) {
		try {
			if ( empty( $this->customerId ) ) {
				$this->customerId = "-";
			}
			$this->addMetaData( $paymentId, "CustomerId", $this->customerId );
		} catch ( \Exception $metaResponseException ) {

		}
		$clientPaymentSpec  = $this->handleClientPaymentSpec( $clientPaymentSpec );
		$creditStateSuccess = false;
		$annulStateSuccess  = false;
		$cancelStateSuccess = false;
		$lastErrorMessage   = "";
		$cancelPaymentArray = $this->getPayment( $paymentId );
		$creditSpecLines    = array();
		$annulSpecLines     = array();

		/*
		 * If no clientPaymentSpec are defined, we should consider this a full cancellation. In that case, we'll sort the full payment spec so we'll pick up rows
		 * that should be credited separately and vice versa for annullments. If the clientPaymentSpec are defined, the $cancelPaymentArray will be used as usual.
		 */
		if ( is_array( $clientPaymentSpec ) && ! count( $clientPaymentSpec ) ) {
			try {
				$creditSpecLines = $this->stripPaymentSpec( $this->renderSpecLine( $creditSpecLines, ResursAfterShopRenderTypes::CREDIT, $cancelParams ) );
			} catch ( \Exception $ignoreCredit ) {
				$creditSpecLines = array();
			}
			try {
				$annulSpecLines = $this->stripPaymentSpec( $this->renderSpecLine( $annulSpecLines, ResursAfterShopRenderTypes::ANNUL, $cancelParams ) );
			} catch ( \Exception $ignoreAnnul ) {
				$annulSpecLines = array();
			}
		}
		/* First, try to credit the requested order, if debited rows are found */
		try {
			if ( ! count( $creditSpecLines ) ) {
				$creditPaymentContainer = $this->renderPaymentSpecContainer( $paymentId, ResursAfterShopRenderTypes::CREDIT, $cancelPaymentArray, $clientPaymentSpec, $cancelParams, $quantityMatch, $useSpecifiedQuantity );
			} else {
				$creditPaymentContainer = $this->renderPaymentSpecContainer( $paymentId, ResursAfterShopRenderTypes::CREDIT, $creditSpecLines, $clientPaymentSpec, $cancelParams, $quantityMatch, $useSpecifiedQuantity );
			}
			$Result             = $this->postService( "creditPayment", $creditPaymentContainer );
			$creditStateSuccess = true;
		} catch ( \Exception $e ) {
			$creditStateSuccess = false;
			$lastErrorMessage   = $e->getMessage();
		}
		/* Second, try to annul the rest of the order, if authorized (not debited) rows are found */
		try {
			if ( ! count( $creditSpecLines ) ) {
				$annulPaymentContainer = $this->renderPaymentSpecContainer( $paymentId, ResursAfterShopRenderTypes::ANNUL, $cancelPaymentArray, $clientPaymentSpec, $cancelParams, $quantityMatch, $useSpecifiedQuantity );
			} else {
				$annulPaymentContainer = $this->renderPaymentSpecContainer( $paymentId, ResursAfterShopRenderTypes::ANNUL, $annulSpecLines, $clientPaymentSpec, $cancelParams, $quantityMatch, $useSpecifiedQuantity );
			}
			if ( is_array( $annulPaymentContainer ) && count( $annulPaymentContainer ) ) {
				$Result            = $this->postService( "annulPayment", $annulPaymentContainer );
				$annulStateSuccess = true;
			}
		} catch ( \Exception $e ) {
			$annulStateSuccess = false;
			$lastErrorMessage  = $e->getMessage();
		}

		/* Check if one of the above statuses is true and set the cancel as successful. If none of them are true, the cancellation has failed completely. */
		if ( $creditStateSuccess ) {
			$cancelStateSuccess = true;
		}
		if ( $annulStateSuccess ) {
			$cancelStateSuccess = true;
		}

		/* On total fail, throw the last error */
		if ( ! $cancelStateSuccess ) {
			throw new \Exception( __FUNCTION__ . ": " . $lastErrorMessage, 500 );
		}

		return $cancelStateSuccess;
	}

	/**
	 * Finalize payment by payment ID. Finalizes an order based on the order content.
	 *
	 * clientPaymentSpec (not required) should contain array[] => ('artNo' => 'articleNumber', 'quantity' => numberOfArticles). Example:
	 * array(
	 *      0=>array(
	 *          'artNo' => 'art999',
	 *          'quantity' => '1'
	 *      ),
	 *      1=>array(
	 *          'artNo' => 'art333',
	 *          'quantity' => '2'
	 *      )
	 * );
	 *
	 * @param string $paymentId
	 * @param array $clientPaymentSpec (Optional) paymentspec if only specified lines are being finalized
	 * @param array $finalizeParams
	 * @param bool $quantityMatch (Optional) Match quantity. If false, quantity will be ignored during finalization and all client specified paymentspecs will match
	 * @param bool $useSpecifiedQuantity If set to true, the quantity set clientside will be used rather than the exact quantity from the spec in getPayment (This requires that $quantityMatch is set to false)
	 *
	 * @return bool True if successful
	 * @throws \Exception
	 * @throws \Exception
	 */
	public function finalizePayment( $paymentId = "", $clientPaymentSpec = array(), $finalizeParams = array(), $quantityMatch = true, $useSpecifiedQuantity = false ) {
		try {
			if ( empty( $this->customerId ) ) {
				$this->customerId = "-";
			}
			$this->addMetaData( $paymentId, "CustomerId", $this->customerId );
		} catch ( \Exception $metaResponseException ) {

		}

		$clientPaymentSpec = $this->handleClientPaymentSpec( $clientPaymentSpec );
		$finalizeResult    = false;
		if ( null === $paymentId ) {
			throw new \Exception( "Payment ID must be ID" );
		}
		$paymentArray             = $this->getPayment( $paymentId );
		$finalizePaymentContainer = $this->renderPaymentSpecContainer( $paymentId, ResursAfterShopRenderTypes::FINALIZE, $paymentArray, $clientPaymentSpec, $finalizeParams, $quantityMatch, $useSpecifiedQuantity );
		if ( isset( $paymentArray->id ) ) {
			try {
				$Result         = $this->postService( "finalizePayment", $finalizePaymentContainer );
				$finalizeResult = true;
			} catch ( \Exception $e ) {
				throw new \Exception( __FUNCTION__ . ": " . $e->getMessage(), 500 );
			}
		}

		return $finalizeResult;
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
	 *
	 */
	public function creditPayment( $paymentId = "", $clientPaymentSpec = array(), $creditParams = array(), $quantityMatch = true, $useSpecifiedQuantity = false ) {
		try {
			if ( empty( $this->customerId ) ) {
				$this->customerId = "-";
			}
			$this->addMetaData( $paymentId, "CustomerId", $this->customerId );
		} catch ( \Exception $metaResponseException ) {

		}

		$clientPaymentSpec = $this->handleClientPaymentSpec( $clientPaymentSpec );
		$creditResult      = false;
		if ( null === $paymentId ) {
			throw new \Exception( "Payment ID must be ID" );
		}
		$paymentArray           = $this->getPayment( $paymentId );
		$creditPaymentContainer = $this->renderPaymentSpecContainer( $paymentId, ResursAfterShopRenderTypes::CREDIT, $paymentArray, $clientPaymentSpec, $creditParams, $quantityMatch, $useSpecifiedQuantity );
		if ( isset( $paymentArray->id ) ) {
			try {
				$Result       = $this->postService( "creditPayment", $creditPaymentContainer );
				$creditResult = true;
			} catch ( \Exception $e ) {
				throw new \Exception( __FUNCTION__ . ": " . $e->getMessage(), $e->getCode() );
			}
		}

		return $creditResult;
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
	 */
	public function annulPayment( $paymentId = "", $clientPaymentSpec = array(), $annulParams = array(), $quantityMatch = true, $useSpecifiedQuantity = false ) {
		try {
			if ( empty( $this->customerId ) ) {
				$this->customerId = "-";
			}
			$this->addMetaData( $paymentId, "CustomerId", $this->customerId );
		} catch ( \Exception $metaResponseException ) {

		}

		$clientPaymentSpec = $this->handleClientPaymentSpec( $clientPaymentSpec );
		$annulResult       = false;
		if ( null === $paymentId ) {
			throw new \Exception( "Payment ID must be ID" );
		}
		$paymentArray          = $this->getPayment( $paymentId );
		$annulPaymentContainer = $this->renderPaymentSpecContainer( $paymentId, ResursAfterShopRenderTypes::ANNUL, $paymentArray, $clientPaymentSpec, $annulParams, $quantityMatch, $useSpecifiedQuantity );
		if ( isset( $paymentArray->id ) ) {
			try {
				$Result      = $this->postService( "annulPayment", $annulPaymentContainer );
				$annulResult = true;
			} catch ( \Exception $e ) {
				throw new \Exception( __FUNCTION__ . ": " . $e->getMessage(), $e->getCode() );
			}
		}

		return $annulResult;
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
		$this->renderPaymentSpec( ResursMethodTypes::METHOD_SIMPLIFIED );
		$additionalDataArray = array(
			'paymentId'   => $paymentId,
			'paymentSpec' => $this->Payload['orderData'],
			'createdBy'   => $createdBy
		);
		$Result              = $this->postService( "additionalDebitOfPayment", $additionalDataArray, true );
		if ( $Result >= 200 && $Result <= 250 ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Returns all invoice numbers for a specific payment
	 *
	 * @param string $paymentId
	 *
	 * @return array
	 * @since 1.0.11
	 * @since 1.1.11
	 * @since 1.2.0
	 */
	public function getPaymentInvoices($paymentId = '') {
		$invoices = array();
		$paymentData = $this->getPayment($paymentId);
		if (!empty($paymentData) && isset($paymentData->paymentDiffs)) {
			foreach ($paymentData->paymentDiffs as $paymentRow) {
				if (isset($paymentRow->type) && $paymentRow->type == "DEBIT" && isset($paymentRow->invoiceId)) {
					$invoices[] = $paymentRow->invoiceId;
				}
			}
		}
		return $invoices;
	}
}
