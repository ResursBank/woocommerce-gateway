<?php

/**
 * Resurs Bank API Wrapper - A silent flow normalizer for Resurs Bank.
 *
 * @package Resursbank
 * @author Resurs Bank <support@resurs.se>
 * @author Tomas Tornevall <tomas.tornevall@resurs.se>
 * @branch 1.3
 * @link https://test.resurs.com/docs/x/KYM0 Get started - PHP Section
 * @link https://test.resurs.com/docs/x/TYNM EComPHP Usage
 * @link https://test.resurs.com/docs/x/KAH1 EComPHP: Bitmasking features
 * @license Apache License
 */

namespace Resursbank\RBEcomPHP;

// This is a global setter but it has to be set before the inclusions. Why?
// It's a result of a legacy project that's not adapted to proper PSR standards.
if (!defined('ECOM_SKIP_AUTOLOAD')) {
    define('ECOM_CLASS_EXISTS_AUTOLOAD', true);
} else {
    define('ECOM_CLASS_EXISTS_AUTOLOAD', false);
    if (!defined('NETCURL_SKIP_AUTOLOAD')) {
        define('NETCURL_SKIP_AUTOLOAD', true);
    }
    if (!defined('CRYPTO_SKIP_AUTOLOAD')) {
        define('CRYPTO_SKIP_AUTOLOAD', true);
    }
    if (!defined('IO_SKIP_AUTOLOAD')) {
        define('IO_SKIP_AUTOLOAD', true);
    }
}

/** @noinspection ClassConstantCanBeUsedInspection */
if (class_exists('ResursBank', ECOM_CLASS_EXISTS_AUTOLOAD) &&
    class_exists('Resursbank\RBEcomPHP\ResursBank', ECOM_CLASS_EXISTS_AUTOLOAD)
) {
    return;
}

require_once(__DIR__ . '/rbapiloader/ResursForms.php');
require_once(__DIR__ . '/rbapiloader/ResursTypeClasses.php');
require_once(__DIR__ . '/rbapiloader/ResursException.php');

if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once(__DIR__ . '/../../vendor/autoload.php');
}

use Exception;
use RESURS_EXCEPTIONS;
use ResursException;
use stdClass;
use TorneLIB\Config\Flag;
use TorneLIB\Data\Compress;
use TorneLIB\Data\Password;
use TorneLIB\Exception\ExceptionHandler;
use TorneLIB\Helpers\NetUtils;
use TorneLIB\Model\Type\dataType;
use TorneLIB\Model\Type\requestMethod;
use TorneLIB\Module\Bit;
use TorneLIB\Module\Network\NetWrapper;
use TorneLIB\MODULE_NETWORK;
use TorneLIB\Utils\Generic;

// Globals starts here. But should be deprecated if version tag can be fetched through their doc-blocks.
if (!defined('ECOMPHP_VERSION')) {
    define('ECOMPHP_VERSION', (new Generic())->getVersionByAny(__FILE__, 3, ResursBank::class));
}
if (!defined('ECOMPHP_MODIFY_DATE')) {
    define('ECOMPHP_MODIFY_DATE', '20210111');
}

/**
 * By default Test environment are set. To switch over to production, you explicitly need to tell EComPHP to do
 * this. This a security setup so testings won't be sent into production by mistake.
 */

/**
 * Class ResursBank
 * @package Resursbank\RBEcomPHP
 * @version 1.3.48
 */
class ResursBank
{
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
    /**
     * Current targeted environment - default is always test, as we don't like that mistakes are going production
     *
     * @var int
     */
    public $current_environment = self::ENVIRONMENT_TEST;
    /**
     * The username used with the webservices
     *
     * @var string
     */
    public $username;

    ///// Environment and API
    /**
     * The password used with the webservices
     *
     * @var string
     */
    public $password;
    /**
     * Always append amount data and ending urls (cost examples)
     *
     * @var bool
     */
    public $alwaysAppendPriceLast = false;
    /**
     * Standard SOAP interface configurables.
     *
     * @var array
     */
    var $soapOptions = [
        'exceptions' => 1,
        'connection_timeout' => 60,
        'login' => '',
        'password' => '',
        'trace' => 1,
    ];

    /// Web Services (WSDL) available in case of needs to call services directly
    /**
     * Debug mode on or off
     *
     * @var bool
     */
    private $debug = false;
    /**
     * @var bool Cached api calls enabled or disabled.
     * @since 1.3.26
     */
    private $apiCacheActive = true;
    /**
     * Which services we do support (picked up automatically from $ServiceRequestList)
     *
     * @var array
     */
    private $wsdlServices = [];
    /**
     * @var int $getPaymentRequests Debugging only.
     * @since 1.3.26
     */
    private $getPaymentRequests = 0;
    /**
     * @var int $getCachedPaymentRequests Debugging only.
     * @since 1.3.26
     */
    private $getPaymentCachedRequests = 0;

    ///// Shop related
    /**
     * @var array $paymentMethodsCache
     */
    private $paymentMethodsCache = ['params' => [], 'methods' => []];
    /**
     * @var int
     * @since 1.3.45
     */
    private $getPaymentRequestMethod = 0;
    /**
     * Customer id used at afterShopFlow
     *
     * @var string
     */
    private $customerId = '';
    /**
     * If the merchant has PSP methods available in the simplified and hosted flow where it is normally not supported,
     * this should be set to true via setSimplifiedPsp(true)
     *
     * @var bool
     */
    private $paymentMethodsHasPsp = false;
    /**
     * If the strict control of payment methods vs PSP is set, we will never show any payment method that is based on
     * PAYMENT_PROVIDER - this might be good to use in mixed environments
     *
     * @var bool
     */
    private $paymentMethodsIsStrictPsp = false;
    /**
     * Setting this to true should help developers have their payment method ids returned in a consistent format
     *
     * @var bool
     */
    private $paymentMethodIdSanitizing = false;
    /**
     * This setting is true if a flow is about to run through a PSP method
     *
     * @var bool
     */
    private $paymentMethodIsPsp = false;
    /**
     * Defines if there is a SoapClient available
     *
     * @var bool
     */
    private $SOAP_AVAILABLE = false;
    /** @var */
    private $FUNCTIONS_DISABLED;
    /**
     * If a choice of payment method are discovered during the flow, this is set here
     *
     * @var $desiredPaymentMethod
     * @since 1.0.26
     * @since 1.1.26
     * @since 1.2.0
     */
    private $desiredPaymentMethod;
    /**
     * Keys to purge from a match-session in aftershop.
     *
     * @var array
     * @since 1.3.23
     */
    private $getPaymentDefaultPurge = ['totalVatAmount', 'totalAmount', 'quantity', 'id'];
    /**
     * Notify internally if the keyset has been changed.
     *
     * @var bool
     * @since 1.3.23
     */
    private $getPaymentDefaultPurgeSet = false;
    /**
     * Keys to keep original values for, in a match-session during aftershop.
     *
     * @var array
     * @since 1.3.23
     */
    private $getPaymentDefaultUnPurge = [];

    ////////// Private variables
    ///// Client Specific Settings
    /**
     * Enable the possibility to push over User-Agent from customer into header (debugging related)
     *
     * @var bool
     */
    private $customerUserAgentPush = false;
    /**
     * The version of this gateway
     *
     * @var string
     */
    private $version = ECOMPHP_VERSION;
    /**
     * Identify current version release
     *
     * @var string
     */
    private $lastUpdate = ECOMPHP_MODIFY_DATE;
    /**
     * EComPHP GIT Repo URL
     *
     * @var string
     */
    private $gitUrl = 'https://bitbucket.org/resursbankplugins/resurs-ecomphp';
    /**
     * @var string
     */
    private $clientName = 'EComPHP';
    /**
     * Replacing $clientName on usage of setClientName
     *
     * @var string
     */
    private $realClientName = 'EComPHP';
    /**
     * Flags up that client name has been set by user/developer/integrator
     *
     * @var bool
     * @since 1.3.23
     */
    private $userSetClientName = false;
    /**
     * @var array Last stored getPayment()
     */
    private $lastPaymentStored = [];

    ///// Package related
    /**
     * @var int $lastGetPaymentMaxCacheTime Number of seconds.
     */
    private $lastGetPaymentMaxCacheTime = 3;
    /**
     * Has the necessary services been initialized yet?
     *
     * @var bool
     */
    private $hasServicesInitialization = false;
    /**
     * Future functionality to backtrace customer ip address to something else than REMOTE_ADDR (if proxified)
     *
     * @var bool
     */
    private $preferCustomerProxy = false;

    ///// Communication
    /**
     * Indicates if there was deprecated calls in progress during the use of ECom
     *
     * @var bool
     */
    private $hasDeprecatedCall = false;
    /**
     * Primary class for handling all HTTP calls
     *
     * @var Netwrapper
     * @since 1.0.1
     * @since 1.1.1
     */
    private $CURL;
    /**
     * @var null
     */
    private $CURLDRIVER_VERSION = null;
    /**
     * @var int
     */
    private $CURLDRIVER_WSDL_CACHE = 0;
    /**
     * @var Netwrapper $CURL_USER_DEFINED
     */
    private $CURL_USER_DEFINED;
    /**
     * Handles created during in one http call are collected here
     *
     * @var array
     */
    private $CURL_HANDLE_COLLECTOR = [];
    /**
     * Info and statistics from the CURL-client
     *
     * @var array
     */
    private $curlStats = [];
    /**
     * Class for handling Network related checks
     *
     * @var MODULE_NETWORK
     * @since 1.0.1
     * @since 1.1.1
     */
    private $NETWORK;
    /**
     * Another way to handle bitmasks (might be deprecated in future releases)
     *
     * @var Bit
     */
    private $BIT;
    /**
     * Deprecated flow class (for forms etc)
     *
     * @var RESURS_DEPRECATED_FLOW
     */
    private $E_DEPRECATED;
    /**
     * The payload rendered out from CreatePayment()
     *
     * @var array
     * @since 1.0.1
     * @since 1.1.1
     */
    private $Payload = [];
    /**
     * Historical payload collection
     *
     * @var array
     * @since 1.0.31
     * @since 1.1.31
     * @since 1.2.4
     * @since 1.3.4
     */
    private $PayloadHistory = [];
    /**
     * If there is a chosen payment method, the information about it (received from Resurs Ecommerce)
     * will be stored here
     *
     * @var array $PaymentMethod
     * @since 1.0.13
     * @since 1.1.13
     * @since 1.2.0
     */
    private $PaymentMethod;
    /**
     * Payment spec (orderLines)
     *
     * @var array
     * @since 1.0.2
     * @since 1.1.2
     */
    private $SpecLines = [];
    /**
     * Boolean value that has purpose when using addOrderLines to customize aftershop.
     *
     * @var bool $specLineCustomization
     * @since 1.3.23
     */
    private $specLineCustomization = false;

    /// Environment URLs
    /**
     * @var bool
     * @since 1.3.23
     */
    private $skipAfterShopPaymentValidation = true;
    /**
     * Chosen environment
     *
     * @var string
     */
    private $environment;
    /**
     * Default test URL
     *
     * @var string
     */
    private $env_test = 'https://test.resurs.com/ecommerce-test/ws/V4/';
    /**
     * Default production URL
     *
     * @var string
     */
    private $env_prod = 'https://ecommerce.resurs.com/ws/V4/';
    /**
     * Default test URL for hosted flow
     *
     * @var string
     */
    private $env_hosted_test = 'https://test.resurs.com/ecommerce-test/hostedflow/back-channel';
    /**
     * Default production URL for hosted flow
     *
     * @var string
     */
    private $env_hosted_prod = 'https://ecommerce-hosted.resurs.com/back-channel';
    /**
     * Default test URL for Resurs Checkout
     *
     * @var string
     */
    private $environmentRcoStandardTest = 'https://omnitest.resurs.com';
    /**
     * Default production URL for Resurs Checkout
     *
     * @var string
     */
    private $environmentRcoStandardProduction = 'https://checkout.resurs.com';
    /**
     * Default test URL for Resurs Checkout POS
     *
     * @var string
     */
    private $environmentRcoPosTest = 'https://postest.resurs.com';
    /**
     * Default production URL for Resurs Checkout POS
     *
     * @var string
     */
    private $environmentRcoPosProduction = 'https://poscheckout.resurs.com';
    /**
     * Defines if environment will point at Resurs Checkout POS or not and in that case return the URL for the POS.
     * Set up with setPos() and retrieve the state with getPos()
     *
     * @var bool
     */
    private $envOmniPos = false;
    /**
     * Contains a possible origin source after RCO has loaded a frame.
     * @var string $iframeOrigin
     */
    private $iframeOrigin;
    /**
     * @var string
     */
    private $environmentRcoOverrideUrl;
    /**
     * @var string
     */
    private $fullCheckoutResponse;
    /**
     * Country of choice
     *
     * @var
     */
    private $envCountry;
    /**
     * ShopUrl to use with Resurs Checkout
     *
     * @var string
     */
    private $checkoutShopUrl = '';
    /**
     * Set to true via setValidateCheckoutShopUrl() if you require validation of a proper shopUrl
     *
     * @var bool
     */
    private $validateCheckoutShopUrl = false;
    /**
     * Default current environment. Always set to test (security reasons)
     *
     * @var bool
     */
    private $current_environment_updated = false;
    /**
     * Store ID
     *
     * @var string
     */
    private $storeId;
    /**
     * EcomPHP session, use for saving data in $_SESSION for EComPHP
     *
     * @var
     */
    private $ecomSession;
    /**
     * EComPHP User-Agent identifier
     *
     * @var string
     */
    private $myUserAgent = null;
    /**
     * Internal configurable flags
     *
     * @var array
     */
    private $internalFlags = [];
    /**
     * Include fraud statuses in order status returns (manual inspection flagged)
     *
     * @var bool
     * @since 1.0.40
     * @since 1.1.40
     * @since 1.3.13
     */
    private $fraudStatusAllowed = false;
    /**
     * @var RESURS_FLOW_TYPES
     */
    private $enforceService = null;
    /**
     * @var array $urls URLS pointing to direct access of Resurs Bank, instead of WSDL-stubs.
     * @since 1.0.1
     * @since 1.1.1
     */
    private $URLS;
    /**
     * @var array An index of where to find each service for webservices
     * @since 1.0.1
     * @since 1.1.1
     */
    private $ServiceRequestList = [
        'getPaymentMethods' => 'SimplifiedShopFlowService',
        'getAddress' => 'SimplifiedShopFlowService',
        'getAddressByPhone' => 'SimplifiedShopFlowService',
        'getAnnuityFactors' => 'SimplifiedShopFlowService',
        'getCostOfPurchaseHtml' => 'SimplifiedShopFlowService',
        'bookPayment' => 'SimplifiedShopFlowService',
        'bookSignedPayment' => 'SimplifiedShopFlowService',
        'getPayment' => 'AfterShopFlowService',
        'findPayments' => 'AfterShopFlowService',
        'addMetaData' => 'AfterShopFlowService',
        'annulPayment' => 'AfterShopFlowService',
        'creditPayment' => 'AfterShopFlowService',
        'additionalDebitOfPayment' => 'AfterShopFlowService',
        'finalizePayment' => 'AfterShopFlowService',
        'registerEventCallback' => 'ConfigurationService',
        'unregisterEventCallback' => 'ConfigurationService',
        'getRegisteredEventCallback' => 'ConfigurationService',
        'peekInvoiceSequence' => 'ConfigurationService',
        'setInvoiceSequence' => 'ConfigurationService',
    ];
    /**
     * Validating URLs are made through a third party API and is disabled by default.
     * Used for checking if URLs are reachable.
     *
     * @var string
     */
    private $externalApiAddress = 'https://api.tornevall.net/3.0/';
    /**
     * An array that defines an url to test and which response codes (OK-200, and
     * errors when for example a digest fails) from the webserver that is expected
     *
     * @var array
     */
    private $validateExternalUrl = null;
    /** @var string $createPaymentExecuteCommand Prepared variable for execution */
    private $createPaymentExecuteCommand;
    /** @var bool Enforce the Execute() */
    private $forceExecute = false;
    /**
     * Defines which way we are actually communicating - if the WSDL stubs are left out of the package, this will
     * remain false. If the package do contain the full release packages, this will be switched over to true.
     *
     * @var bool
     */
    private $skipCallbackValidation = true;

    /// SOAP and WSDL
    /**
     * The choice of using rest instead of a soapClient when registering callbacks
     *
     * @var bool
     */
    private $registerCallbacksViaRest = false;
    /**
     * Don't want to use SSL verifiers in curl mode
     *
     * @var bool
     */
    private $curlSslValidationDisable = false;

    ///// ShopRelated
    /// Customizable
    /**
     * Eventually a logged in user on the platform using EComPHP (used in aftershopFlow)
     *
     * @var string
     */
    private $loggedInUser = '';
    /**
     * Get Cost of Purchase Custom HTML - Before html code received from webservices
     *
     * @var string
     */
    private $getCostHtmlBefore = '';
    /**
     * Get Cost of Purchase Custom HTML - After html code received from webservices
     *
     * @var string
     */
    private $getCostHtmlAfter = '';

    /// Callback handling
    /**
     * Callback related variables
     *
     * @var array
     */
    private $digestKey = [];
    /**
     * Globally set digestive key
     *
     * @var string
     */
    private $globalDigestKey = '';

    /// Shop flow
    /**
     * Defines whether we have detected a hosted flow request or not
     *
     * @var bool
     */
    private $isHostedFlow = false;
    /**
     * Defines whether we have detected a ResursCheckout flow request or not
     *
     * @var bool
     */
    private $isOmniFlow = false;
    /**
     * The preferred payment order reference, set in a shop flow. Reachable through getPreferredPaymentId()
     *
     * @var string
     */
    private $preferredId = null;
    /**
     * @var
     */
    private $paymentSessionId;
    /**
     * List of available payment method names (for use with getPaymentMethodNames())
     *
     * @var array
     */
    private $paymentMethodNames = [];
    /**
     * Defines if the checkout should honor the customer field array
     *
     * @var bool
     */
    private $checkoutCustomerFieldSupport = false;

    /// AfterShop Flow
    /**
     * Preferred transaction id for aftershop
     *
     * @var string
     */
    private $afterShopPreferredTransactionId = '';
    /**
     * Order id for aftershop
     *
     * @var string
     */
    private $afterShopOrderId = '';
    /**
     * Invoice id (Optional) for aftershop
     *
     * @var string
     */
    private $afterShopInvoiceId = '';
    /**
     * Invoice external reference for aftershop
     *
     * @var string
     */
    private $afterShopInvoiceExtRef = '';
    /**
     * @var bool
     */
    private $isFirstInvoiceId = false;

    /**
     * Default unit measure. "st" or styck for Sweden. If your plugin is not used for Sweden,
     * use the proper unit for your country.
     *
     * @var string
     */
    private $defaultUnitMeasure = 'st';

    /// Resurs Checkout
    /**
     * When using clearOcShop(), the Resurs Checkout tailing script (resizer) will be stored here
     *
     * @var
     */
    private $ocShopScript;

    /**
     * Payment method types (from getPaymentMethods) that probably is automatically debiting as
     * soon as transfers been made
     *
     * @var array
     */
    private $autoDebitableTypes = [];

    /**
     * Discover payments that probably has been automatically debited - default is active
     *
     * @var bool
     */
    private $autoDebitableTypesActive = true;

    /**
     * When instant finalization is used, we normally cache information about the chosen
     * payment method to not overload stuff with calls
     *
     * @var object
     */
    private $autoDebitablePaymentMethod;

    /////////// INITIALIZERS

    /**
     * Constructor method for Resurs Bank WorkFlows
     *
     * This method prepares initial variables for the workflow. No connections are being made from this point.
     *
     * @param string $login
     * @param string $password
     * @param int $targetEnvironment
     * @param bool $debug
     * @param array $paramFlagSet
     * @throws Exception
     */
    function __construct(
        $login = '',
        $password = '',
        $targetEnvironment = RESURS_ENVIRONMENTS::TEST,
        $debug = false,
        $paramFlagSet = []
    ) {
        if (is_array($paramFlagSet) && count($paramFlagSet)) {
            $this->preSetEarlyFlags($paramFlagSet);
        }

        $memSafeLimit = -1;
        if (defined('MEMORY_SAFE_LIMIT')) {
            $memSafeLimit = MEMORY_SAFE_LIMIT;
        }
        $memoryLimit = defined('MEMORY_SAFE_LIMIT') && !empty($memSafeLimit) ? $memSafeLimit : -1;
        $this->getMemoryLimitAdjusted('128M', $memoryLimit);

        if (is_bool($debug) && $debug) {
            $this->debug = $debug;
        }

        if ($this->hasSoap()) {
            $this->SOAP_AVAILABLE = true;
        }

        // We automatically add all methods that for sure will FINALIZE payments before shipping has been made.
        // As of oct 2018 it is only SWISH that is known for this behaviour. This may change in future, however
        // this can manually be pushed into ECom by using the setAutoDebitableType().
        $this->setAutoDebitableType('SWISH');

        $this->checkoutShopUrl = $this->hasHttps(true) . '://' . $this->getHostnameByServer();
        $this->soapOptions['cache_wsdl'] = (defined('WSDL_CACHE_BOTH') ? WSDL_CACHE_BOTH : true);
        $this->soapOptions['ssl_method'] = (defined('SOAP_SSL_METHOD_TLS') ? SOAP_SSL_METHOD_TLS : false);

        $this->setAuthentication($login, $password);
        $this->setEnvironment($targetEnvironment);
        $this->setUserAgent();
        $this->E_DEPRECATED = new RESURS_DEPRECATED_FLOW();
    }

    /**
     * @param $flagArray
     * @throws Exception
     * @since 1.3.26
     */
    private function preSetEarlyFlags($flagArray)
    {
        foreach ($flagArray as $key => $value) {
            $this->setFlag($key, $value);
            // Simple pass through setup.
            if (method_exists($this, $key)) {
                $this->{$key}($value);
            }
        }
    }

    /**
     * Set internal flag parameter
     *
     * @param string $flagKey
     * @param string $flagValue Will be boolean==true if empty
     *
     * @return bool If successful
     * @throws Exception
     * @since 1.0.23
     * @since 1.1.23
     * @since 1.2.0
     */
    public function setFlag($flagKey = '', $flagValue = null)
    {
        if (($flagKey === 'CURL_TIMEOUT' || $flagKey === 'NETCURL_TIMEOUT') && intval($flagValue) > 0) {
            $this->internalFlags[$flagKey] = $flagValue;
        }

        if (is_null($this->CURL)) {
            $this->InitializeServices();
        }
        if (is_null($flagValue)) {
            $flagValue = true;
        }

        if (!empty($flagKey)) {
            // CURL pass through
            //$this->CURL->setFlag($flagKey, $flagValue);
            $this->internalFlags[$flagKey] = $flagValue;

            return true;
        }
        throw new ResursException('Flags can not be empty!', 500);
    }

    /**
     * Everything that communicates with Resurs Bank should go here, whether is is web services or curl/json data. The
     * former name of this function is InitializeWsdl, but since we are handling nonWsdl-calls differently, but still
     * needs some kind of compatibility in dirty code structures, everything needs to be done from here. For now. In
     * future version, this is probably deprecated too, as it is an obsolete way of getting things done as Resurs Bank
     * has more than one way to pick things up in the API suite.
     *
     * @param bool $reInitializeCurl
     *
     * @return bool
     * @throws Exception
     * @since 1.0.1
     * @since 1.1.1
     */
    private function InitializeServices($reInitializeCurl = true)
    {
        if (is_null($this->CURL)) {
            $reInitializeCurl = true;
        }

        if (!$reInitializeCurl) {
            return null;
        }

        $this->sessionActivate();
        $this->hasServicesInitialization = true;
        $this->testWrappers();
        if ($this->current_environment == self::ENVIRONMENT_TEST) {
            $this->environment = $this->env_test;
        } else {
            $this->environment = $this->env_prod;
        }
        if (!is_null($this->CURL_USER_DEFINED)) {
            $this->CURL = $this->CURL_USER_DEFINED;
        } else {
            $this->CURL = new Netwrapper();
        }
        if (method_exists($this->CURL, 'setIdentifiers')) {
            $this->CURL->setIdentifiers(true);
        }
        $this->CURLDRIVER_VERSION = $this->getNcVersion();
        $this->CURL->setWsdlCache($this->CURLDRIVER_WSDL_CACHE);

        $this->CURL->setAuthentication($this->soapOptions['login'], $this->soapOptions['password']);
        $this->CURL->setUserAgent($this->myUserAgent);
        if (($cTimeout = $this->getFlag('CURL_TIMEOUT')) > 0) {
            $this->CURL->setTimeout($cTimeout);
        }
        $this->BIT = new Bit();

        $this->wsdlServices = [];
        foreach ($this->ServiceRequestList as $reqType => $reqService) {
            $this->wsdlServices[$reqService] = true;
        }
        foreach ($this->wsdlServices as $ServiceName => $isAvailableBoolean) {
            $this->URLS[$ServiceName] = $this->environment . $ServiceName . '?wsdl';
        }
        $this->getSslValidation();

        return $this;
    }

    /**
     * @param $timeout
     * @param false $useMillisec
     * @return Netwrapper
     * @throws Exception
     * @since 1.3.47
     */
    public function setTimeout($timeout, $useMillisec = false)
    {
        $this->InitializeServices(false);
        return $this->CURL->setTimeout($timeout, $useMillisec);
    }

    /**
     * Session usage
     *
     * @return bool
     * @since 1.0.29
     * @since 1.1.29
     * @since 1.2.2
     * @since 1.3.2
     */
    private function sessionActivate()
    {
        if (Flag::isFlag('ALLOW_ECOM_SESSION')) {
            try {
                if (session_status() === PHP_SESSION_NONE) {
                    @session_start();
                    $this->ecomSession = session_id();
                    if (!empty($this->ecomSession)) {
                        return true;
                    }
                } else {
                    $this->ecomSession = session_id();
                }
            } catch (Exception $sessionActivationException) {
            }
        }

        return false;
    }

    /**
     * Check HTTPS-requirements, if they pass.
     *
     * Resurs Bank requires secure connection to the webservices, so your PHP-version must support SSL. Normally this
     * is not a problem, but since there are server- and hosting providers that is actually having this disabled, the
     * decision has been made to do this check.
     *
     * @throws ResursException
     */
    private function testWrappers()
    {
        // suddenly, in some system, this data returns null without any reason
        $streamWrappers = @stream_get_wrappers();
        if (!is_array($streamWrappers)) {
            $streamWrappers = [];
        }
        if (!in_array('https', array_map('strtolower', $streamWrappers))) {
            throw new ResursException(
                sprintf(
                    '%s exception: HTTPS wrapper can not be found.',
                    __FUNCTION__
                ),
                RESURS_EXCEPTIONS::SSL_WRAPPER_MISSING
            );
        }
    }

    /**
     * @return string
     */
    private function getNcVersion()
    {
        $return = '';

        if (defined('NETCURL_RELEASE')) {
            $return = NETCURL_RELEASE;
        }
        if (defined('NETCURL_VERSION')) {
            $return = NETCURL_VERSION;
        }

        return $return;
    }

    /**
     * @return bool
     * @since 1.3.27
     */
    public function getSslSecurityDisabled()
    {
        return $this->curlSslValidationDisable;
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
    public function getFlag($flagKey = '')
    {
        if (isset($this->internalFlags[$flagKey])) {
            return $this->internalFlags[$flagKey];
        }

        return null;
    }

    /**
     * @since 1.0.23
     * @since 1.1.23
     * @since 1.2.0
     */
    public function getSslValidation()
    {
        return $this->curlSslValidationDisable;
    }

    /**
     * Enforce automatic adjustment if memory limit is set too low (or your defined value).
     *
     * @param string $minLimit
     * @param string $maxLimit
     * @return bool
     */
    public function getMemoryLimitAdjusted($minLimit = '256M', $maxLimit = '-1')
    {
        $return = false;
        $currentLimit = $this->getBytes(ini_get('memory_limit'));
        $myLimit = $this->getBytes($minLimit);
        if ($currentLimit <= $myLimit) {
            $return = $this->setMemoryLimit($maxLimit);
        }
        return $return;
    }

    /**
     * WP Style byte conversion for memory limits.
     *
     * @param $value
     * @return mixed
     */
    public function getBytes($value)
    {
        $value = strtolower(trim($value));
        $bytes = (int)$value;

        if (false !== strpos($value, 't')) {
            $bytes *= 1024 * 1024 * 1024 * 1024;
        } elseif (false !== strpos($value, 'g')) {
            $bytes *= 1024 * 1024 * 1024;
        } elseif (false !== strpos($value, 'm')) {
            $bytes *= 1024 * 1024;
        } elseif (false !== strpos($value, 'k')) {
            $bytes *= 1024;
        } elseif (false !== strpos($value, 'b')) {
            $bytes *= 1;
        }

        // Deal with large (float) values which run into the maximum integer size.
        return min($bytes, PHP_INT_MAX);
    }

    /**
     * Set new memory limit for PHP.
     *
     * @param string $newLimitValue
     * @return bool
     */
    public function setMemoryLimit($newLimitValue = '512M')
    {
        $return = false;

        $oldMemoryValue = $this->getBytes(ini_get('memory_limit'));
        if ($this->getIniSettable('memory_limit')) {
            $blindIniSet = ini_set('memory_limit', $newLimitValue) !== false ? true : false;
            $newMemoryValue = $this->getBytes(ini_get('memory_limit'));
            $return = $blindIniSet && $oldMemoryValue !== $newMemoryValue ? true : false;
        }

        return $return;
    }

    /**
     * Check if the setting is settable with ini_set(). Partially borrowed from WordPress.
     *
     * @param $setting
     * @return bool
     */
    public function getIniSettable($setting)
    {
        static $ini_all;

        if (!function_exists('ini_set')) {
            return false;
        }

        if (!isset($ini_all)) {
            $ini_all = false;
            // Sometimes `ini_get_all()` is disabled via the `disable_functions` option for "security purposes".
            if (function_exists('ini_get_all')) {
                $ini_all = ini_get_all();
            }
        }

        // Bit operator to workaround https://bugs.php.net/bug.php?id=44936 which changes access level
        // to 63 in PHP 5.2.6 - 5.2.17.
        if (isset($ini_all[$setting]['access']) &&
            (INI_ALL === ($ini_all[$setting]['access'] & 7)
                || INI_USER === ($ini_all[$setting]['access'] & 7))
        ) {
            return true;
        }

        // If we were unable to retrieve the details, fail gracefully to assume it's changeable.
        if (!is_array($ini_all)) {
            return true;
        }

        return false;
    }

    /**
     * Partially tells EComPHP whether SOAP can be used or not, when dependencies requires this.
     *
     * @return bool
     * @since 1.3.13
     */
    public function hasSoap()
    {
        $return = false;

        if (class_exists('SoapClient', ECOM_CLASS_EXISTS_AUTOLOAD)) {
            $return = true;
        }

        /** @var array $defConstants PHP 5.3 compliant defined constants list */
        $defConstants = get_defined_constants();
        foreach ($defConstants as $constant => $value) {
            if (preg_match('/^soap_/i', $constant)) {
                $return = true;
            }
        }

        $disabledClasses = $this->getDisabledClasses();
        if (is_array($disabledClasses) && in_array('SoapClient', $disabledClasses)) {
            $return = false;
        }

        return $return;
    }

    /**
     * @return array
     */
    public function getDisabledClasses()
    {
        $disabledFunctions = @ini_get('disable_classes');
        $disabledArray = array_map('trim', explode(',', $disabledFunctions));
        $this->FUNCTIONS_DISABLED = is_array($disabledArray) ? $disabledArray : [];

        return $this->FUNCTIONS_DISABLED;
    }

    /**
     * Add new payment method type that should consider automatically debited before shipping
     *
     * @param string $type Example SWISH
     * @since 1.0.41
     * @since 1.1.41
     * @since 1.3.14
     */
    public function setAutoDebitableType($type = '')
    {
        $this->prepareAutoDebitableTypes();
        if (!empty($type) && !in_array($type, $this->autoDebitableTypes)) {
            $this->autoDebitableTypes[] = $type;
        }
    }

    /**
     * Prepare automatically debitable payment method types (Internal function to set up destroyed (if) arrays for types
     *
     * @since 1.0.41
     * @since 1.1.41
     * @since 1.3.14
     */
    private function prepareAutoDebitableTypes()
    {
        if (!is_array($this->autoDebitableTypes)) {
            $this->autoDebitableTypes = [];
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
    private function hasHttps($returnProtocol = false)
    {
        if (isset($_SERVER['HTTPS'])) {
            if ($_SERVER['HTTPS'] == 'on') {
                if (!$returnProtocol) {
                    return true;
                } else {
                    return 'https';
                }
            } else {
                if (!$returnProtocol) {
                    return false;
                } else {
                    return 'http';
                }
            }
        }
        if (!$returnProtocol) {
            return false;
        } else {
            return 'http';
        }
    }

    /**
     * Pre-ShopUrl if not defined.
     *
     * @return string
     * @since 1.3.26
     */
    private function getHostnameByServer()
    {
        return isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'local.localhost';
    }

    /**
     * Set up authentication for ecommerce
     *
     * @param string $username
     * @param string $password
     * @param bool $validate
     * @return bool
     * @throws Exception
     * @since 1.0.22
     * @since 1.1.22
     * @since 1.2.0
     */
    public function setAuthentication($username = '', $password = '', $validate = false)
    {
        $result = null;

        $this->username = $username;
        $this->password = $password;
        if ($username !== null) {
            $this->soapOptions['login'] = $username;
            $this->username = $username;
        }
        if ($password !== null) {
            $this->soapOptions['password'] = $password;
            $this->password = $password;
        }

        if ($validate) {
            if (!$this->validateCredentials($this->current_environment, $username, $password)) {
                throw new ResursException('Invalid credentials!', 401);
            }
            // Returning boolean is normally used for test cases.
            $result = true;
        }

        return $result;
    }

    /**
     * Validate entered credentials. If credentials is initialized via the constructor, no extra parameters are
     * required.
     *
     * @param int $environment
     * @param string $username
     * @param string $password
     * @return bool
     * @throws Exception 417 (Expectation Failed) here (https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/417)
     * @since 1.1.42
     * @since 1.0.42
     * @since 1.3.15
     * @noinspection PhpUnusedParameterInspection
     */
    public function validateCredentials($environment = RESURS_ENVIRONMENTS::TEST, $username = '', $password = '')
    {
        if (empty($username) && empty($password) && empty($this->username) && empty($this->password)) {
            throw new ResursException(
                'Validating credentials means you have to define credentials before ' .
                'validating them. Use setAuthentication() or push your credentials into this method directly.',
                417
            );
        }
        if (!empty($username)) {
            $this->setAuthentication($username, $password);
        }

        try {
            $this->getRegisteredEventCallback(RESURS_CALLBACK_TYPES::BOOKED);
            $result = true;
        } catch (Exception $ignoreMyException) {
            $result = false;
        }

        return $result;
    }

    /**
     * Reimplementation of getRegisteredEventCallback due to #78124
     * @param int $callbackType
     * @return mixed
     * @throws Exception
     */
    public function getRegisteredEventCallback($callbackType = RESURS_CALLBACK_TYPES::NOT_SET)
    {
        $this->InitializeServices();
        $fetchThisCallback = $this->getCallbackTypeString($callbackType);

        if (is_null($fetchThisCallback)) {
            $returnArray = [];
            foreach ([1, 2, 4, 8, 16, 32, 64] as $typeBit) {
                if (($callbackType & $typeBit)) {
                    $objectResponse = $this->getRegisteredEventCallback($typeBit);
                    if (isset($objectResponse->uriTemplate)) {
                        $returnArray[$this->getCallbackTypeString($typeBit)] = $objectResponse->uriTemplate;
                    }
                }
            }
            return $returnArray;
        }

        $getRegisteredCallbackUrl = $this->getServiceUrl('getRegisteredEventCallback');
        /** @noinspection PhpUndefinedMethodInspection */
        $parsedResponse = $this->CURL->request(
            $getRegisteredCallbackUrl,
            null,
            requestMethod::METHOD_POST
        )->getRegisteredEventCallback(['eventType' => $fetchThisCallback]);

        return $parsedResponse;
    }

    /**
     * Convert callback types to string names
     *
     * @param int $callbackType
     * @return null|string
     * @since 1.3.13 Private changed to public
     */
    public function getCallbackTypeString($callbackType = RESURS_CALLBACK_TYPES::NOT_SET)
    {
        $return = null;

        if ($callbackType == RESURS_CALLBACK_TYPES::ANNULMENT) {
            $return = 'ANNULMENT';
        }
        if ($callbackType == RESURS_CALLBACK_TYPES::AUTOMATIC_FRAUD_CONTROL) {
            $return = 'AUTOMATIC_FRAUD_CONTROL';
        }
        if ($callbackType == RESURS_CALLBACK_TYPES::FINALIZATION) {
            $return = 'FINALIZATION';
        }
        if ($callbackType == RESURS_CALLBACK_TYPES::TEST) {
            $return = 'TEST';
        }
        if ($callbackType == RESURS_CALLBACK_TYPES::UNFREEZE) {
            $return = 'UNFREEZE';
        }
        if ($callbackType == RESURS_CALLBACK_TYPES::UPDATE) {
            $return = 'UPDATE';
        }
        if ($callbackType == RESURS_CALLBACK_TYPES::BOOKED) {
            $return = 'BOOKED';
        }

        return $return;
    }

    /**
     * Internal function to get the correct service URL for a specific call.
     *
     * @param string $ServiceName
     *
     * @return string
     * @throws Exception
     * @since 1.0.1
     * @since 1.1.1
     */
    public function getServiceUrl($ServiceName = '')
    {
        $this->InitializeServices();
        $properService = '';
        if (isset($this->ServiceRequestList[$ServiceName]) &&
            isset($this->URLS[$this->ServiceRequestList[$ServiceName]])
        ) {
            $properService = $this->URLS[$this->ServiceRequestList[$ServiceName]];
        }

        return $properService;
    }

    /**
     * Set up a user-agent to identify with webservices.
     *
     * @param string $MyUserAgent
     *
     * @throws Exception
     * @since 1.1.2
     * @since 1.0.2
     */
    public function setUserAgent($MyUserAgent = '')
    {
        if (!empty($MyUserAgent)) {
            $this->myUserAgent = $MyUserAgent . ' +' . $this->getVersionFull() .
                (defined('PHP_VERSION') ? '/PHP-' . PHP_VERSION : '');
        } else {
            $this->myUserAgent = $this->getVersionFull() . (defined('PHP_VERSION') ? '/PHP-' . PHP_VERSION : '');
        }
        if ($this->customerUserAgentPush && isset($_SERVER['HTTP_USER_AGENT'])) {
            $this->myUserAgent .= sprintf(
                ' +CLI %s',
                (new Compress())->getGzEncode($_SERVER['HTTP_USER_AGENT'])
            );
        }
    }

    /**
     * Get current client name and version
     *
     * @param bool $getDecimals (Get it as decimals, simple mode)
     *
     * @return string
     * @since 1.0.0
     * @since 1.1.0
     */
    public function getVersionFull($getDecimals = false)
    {
        if (!$getDecimals) {
            return $this->clientName . ' v' . $this->version . '-' . $this->lastUpdate;
        }

        return $this->clientName . '_' . $this->versionToDecimals();
    }

    /**
     * Convert version number to decimals
     *
     * @return string
     * @since 1.0.0
     * @since 1.1.0
     */
    private function versionToDecimals()
    {
        $splitVersion = explode('.', $this->version);
        $decVersion = '';
        foreach ($splitVersion as $ver) {
            $decVersion .= str_pad(intval($ver), 2, '0', STR_PAD_LEFT);
        }

        return $decVersion;
    }

    /**
     * @param $enable
     * @return $this
     * @throws Exception
     */
    public function setWsdlCache($enable)
    {
        $this->InitializeServices(false);

        if (version_compare($this->CURLDRIVER_VERSION, '6.1.0', '>=') && !is_null($this->CURL)) {
            if ($enable) {
                $this->CURLDRIVER_WSDL_CACHE = (defined('WSDL_CACHE_BOTH') ? WSDL_CACHE_BOTH : 3);
                $this->CURL->setWsdlCache((defined('WSDL_CACHE_BOTH') ? WSDL_CACHE_BOTH : 3));
            } else {
                $this->CURLDRIVER_WSDL_CACHE = (defined(WSDL_CACHE_NONE) ? WSDL_CACHE_NONE : 0);
                $this->CURL->setWsdlCache((defined(WSDL_CACHE_NONE) ? WSDL_CACHE_NONE : 0));
            }
        }

        return $this;
    }

    /**
     * Remove current stored variable from customer session
     *
     * @param string $key
     *
     * @return bool
     * @since 1.0.29
     * @since 1.1.29
     * @since 1.2.2
     * @since 1.3.2
     */
    public function deleteSessionVar($key = '')
    {
        $this->sessionActivate();
        if (isset($_SESSION) && isset($_SESSION[$key])) {
            unset($_SESSION[$key]);

            return true;
        }

        return false;
    }

    /**
     * Get debugging information
     *
     * @return array
     * @since 1.0.22
     * @since 1.1.22
     * @since 1.2.0
     */
    public function getDebug()
    {
        $this->curlStats['debug'] = $this->debug;

        return $this->curlStats;
    }

    /**
     * @param bool $debugModeState
     *
     * @throws Exception
     * @since 1.0.22
     * @since 1.1.22
     * @since 1.2.0
     */
    public function setDebug($debugModeState = false)
    {
        $this->InitializeServices(false);
        $this->debug = $debugModeState;
    }

    /**
     * Get curl mode version without the debugging requirement
     *
     * @param bool $fullRelease
     *
     * @return string
     */
    public function getCurlVersion($fullRelease = false)
    {
        if ($this->CURL !== null) {
            return $this->CURL->getVersion($fullRelease);
        }

        return null;
    }

    /**
     * Make EComPHP go through the POS endpoint rather than the standard Checkout endpoint
     *
     * @param bool $activatePos
     *
     * @since 1.0.36
     * @since 1.1.36
     * @since 1.3.9
     * @since 2.0.0
     */
    public function setPos($activatePos = true)
    {
        $this->envOmniPos = $activatePos;
    }

    /**
     * Set overriding url for RCO (mostly used for test).
     *
     * @param string $environmentUrl
     * @since 1.3.27
     */
    public function setEnvRcoUrl($environmentUrl = '')
    {
        $this->environmentRcoOverrideUrl = $environmentUrl;
    }

    /**
     * Return URL info for which is used for the moment in RCO.
     *
     * @return string
     * @since 1.3.27
     */
    public function getEnvRcoUrl()
    {
        return $this->environmentRcoOverrideUrl;
    }

    /**
     * Put SSL Validation into relaxed mode (Test and debug only) - this disables SSL certificate validation off
     *
     * @throws Exception
     * @since 1.0.23
     * @since 1.1.23
     * @since 1.2.0
     * @deprecated Do not use this weird method.
     */
    public function setSslValidation()
    {
        $this->InitializeServices();
        if ($this->debug && $this->current_environment == RESURS_ENVIRONMENTS::TEST) {
            $this->curlSslValidationDisable = true;
        } else {
            throw new ResursException(
                'Can not set SSL validation in relaxed mode. ' .
                'Debug mode is disabled and/or test environment are not set',
                403
            );
        }
    }

    /**
     * Enables strict SSL validation or put in "relaxed mode".
     *
     * @param bool $disableSecurity
     * @return ResursBank
     * @throws Exception
     * @since 1.3.27
     */
    public function setSslSecurityDisabled($disableSecurity = true)
    {
        $this->InitializeServices();
        $this->curlSslValidationDisable = $disableSecurity;
        return $this;
    }


    /////////// Standard getters and setters

    /**
     * Returns true if the URL call was set to be unsafe (disabled)
     *
     * @return bool
     * @since 1.0.23
     * @since 1.1.23
     * @since 1.2.0
     */
    public function getSslIsUnsafe()
    {
        return $this->CURL->getSslIsUnsafe();
    }

    /**
     * Returns true if your version of EComPHP is the current (based on git tags)
     *
     * @param string $testVersion
     *
     * @return bool
     * @throws Exception
     * @since 1.0.26
     * @since 1.1.26
     * @since 1.2.0
     * @deprecated Do not use this. There's no guarantee that it will work.
     */
    public function getIsCurrent($testVersion = '')
    {
        $this->isNetWork();
        if (empty($testVersion)) {
            return !$this->NETWORK->getVersionTooOld($this->getVersionNumber(false), $this->gitUrl);
        } else {
            return !$this->NETWORK->getVersionTooOld($testVersion, $this->gitUrl);
        }
    }

    /**
     * Initialize networking functions
     *
     * @since 1.0.35
     * @since 1.1.35
     * @since 1.2.8
     * @since 1.3.8
     */
    private function isNetWork()
    {
        // When no initialization of this library has been done yet
        if (is_null($this->NETWORK)) {
            $this->NETWORK = new MODULE_NETWORK();
        }
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
    public function getVersionNumber($getDecimals = false)
    {
        if (!$getDecimals) {
            return $this->version; // . "-" . $this->lastUpdate;
        } else {
            return $this->versionToDecimals();
        }
    }

    /**
     * @return mixed
     * @throws Exception
     * @since 1.0.35
     * @since 1.1.35
     * @since 1.2.8
     * @since 1.3.8
     * @deprecated Do not use this. There's no guarantee that it will work.
     */
    public function getCurrentRelease()
    {
        $tags = $this->getVersionsByGitTag();

        return array_pop($tags);
    }



    /// DEBUGGING AND DEVELOPMENT

    /**
     * Try to fetch a list of versions for EComPHP by its git tags
     *
     * @return array
     * @throws Exception
     * @since 1.0.26
     * @since 1.1.26
     * @since 1.2.0
     * @deprecated Do not use this. There's no guarantee that it will work.
     */
    public function getVersionsByGitTag()
    {
        $this->isNetWork();

        return (new NetUtils())->getGitTagsByUrl($this->gitUrl);
    }





    /////////// STRING BEHAVIOUR

    /**
     * Get current user agent info IF has been forced to set (returns null if we are using default)
     *
     * @return string
     * @since 1.0.26
     * @since 1.1.26
     * @since 1.2.0
     */
    public function getUserAgent()
    {
        return $this->myUserAgent;
    }

    /**
     * Clean up internal flags
     *
     * @since 1.0.25
     * @since 1.1.25
     * @since 1.2.0
     */
    public function clearAllFlags()
    {
        $this->internalFlags = [];
    }


    /////////// CALLBACK BEHAVIOUR HELPERS

    /**
     * Set a new url to the chosen test flow (this is prohibited in production sets)
     *
     * @param string $newUrl
     * @param int $FlowType
     *
     * @return string
     */
    public function setTestUrl($newUrl = '', $FlowType = RESURS_FLOW_TYPES::NOT_SET)
    {
        if (!preg_match('/^http/i', $newUrl)) {
            /*
             * Automatically base64-decode if encoded
             */
            $testDecoded = $this->base64url_decode($newUrl);
            if (preg_match('/^http/i', $testDecoded)) {
                $newUrl = $testDecoded;
            } else {
                $newUrl = 'https://' . $newUrl;
            }
        }
        if ($FlowType == RESURS_FLOW_TYPES::SIMPLIFIED_FLOW) {
            $this->env_test = $newUrl;
        } elseif ($FlowType == RESURS_FLOW_TYPES::HOSTED_FLOW) {
            $this->env_hosted_test = $newUrl;
        } elseif ($FlowType == RESURS_FLOW_TYPES::RESURS_CHECKOUT) {
            $this->environmentRcoStandardTest = $newUrl;
        } else {
            /*
             * If this developer wasn't sure of what to change, we'd change all.
             */
            $this->env_test = $newUrl;
            $this->env_hosted_test = $newUrl;
            $this->environmentRcoStandardTest = $newUrl;
        }

        return $newUrl;
    }

    /**
     * base64_decode for urls
     *
     * @param $data
     *
     * @return string
     */
    private function base64url_decode($data)
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }

    /**
     * Set up this to automatically validate a destination url.
     *
     * @param null $url
     * @param string $expectedHttpAcceptCode Expected success code
     * @param string $expectedHttpErrorCode Expected failing code
     */
    public function setValidateExternalCallbackUrl(
        $url = null,
        $expectedHttpAcceptCode = '200',
        $expectedHttpErrorCode = '403'
    ) {
        $this->validateExternalUrl = [
            'url' => $url,
            'http_accept' => $expectedHttpAcceptCode,
            'http_error' => $expectedHttpErrorCode,
        ];
    }

    /**
     * Get salt by crypto library
     *
     * @param int $complexity
     * @param int $totalLength
     * @return string
     * @throws Exception
     * @since 1.3.4
     */
    public function getSaltByCrypto($complexity = 3, $totalLength = 24)
    {
        return (new Password())->mkpass(
            $complexity,
            $totalLength
        );
    }

    /**
     * @param string $callbackTypeString
     *
     * @return int
     */
    public function getCallbackTypeByString($callbackTypeString = '')
    {
        $return = RESURS_CALLBACK_TYPES::NOT_SET;

        if (strtoupper($callbackTypeString) == 'ANNULMENT') {
            $return = RESURS_CALLBACK_TYPES::ANNULMENT;
        }
        if (strtoupper($callbackTypeString) == 'UPDATE') {
            $return = RESURS_CALLBACK_TYPES::UPDATE;
        }
        if (strtoupper($callbackTypeString) == 'TEST') {
            $return = RESURS_CALLBACK_TYPES::TEST;
        }
        if (strtoupper($callbackTypeString) == 'FINALIZATION') {
            $return = RESURS_CALLBACK_TYPES::FINALIZATION;
        }
        if (strtoupper($callbackTypeString) == 'AUTOMATIC_FRAUD_CONTROL') {
            $return = RESURS_CALLBACK_TYPES::AUTOMATIC_FRAUD_CONTROL;
        }
        if (strtoupper($callbackTypeString) == 'UNFREEZE') {
            $return = RESURS_CALLBACK_TYPES::UNFREEZE;
        }
        if (strtoupper($callbackTypeString) == 'BOOKED') {
            $return = RESURS_CALLBACK_TYPES::BOOKED;
        }

        return $return;
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
    public function setCallbackDigest(
        $digestSaltString = '',
        $callbackType = RESURS_CALLBACK_TYPES::NOT_SET
    ) {
        return $this->setCallbackDigestSalt($digestSaltString, $callbackType);
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
    public function setCallbackDigestSalt(
        $digestSaltString = '',
        $callbackType = RESURS_CALLBACK_TYPES::NOT_SET
    ) {
        // Make sure the digestSaltString is never empty
        if (!empty($digestSaltString)) {
            $currentDigest = $digestSaltString;
        } else {
            $currentDigest = $this->getSaltKey(4, 10);
        }
        if ($callbackType !== RESURS_CALLBACK_TYPES::NOT_SET) {
            $callbackTypeString = $this->getCallbackTypeString(
                !is_null($callbackType) ? $callbackType : RESURS_CALLBACK_TYPES::NOT_SET
            );
            $this->digestKey[$callbackTypeString] = $currentDigest;
        } else {
            $this->globalDigestKey = $currentDigest;
        }

        // Confirm the set up
        return $currentDigest;
    }

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
     * @param null $setMax
     * @return string
     */
    public function getSaltKey($complexity = 1, $setMax = null)
    {

        $retp = null;
        $characterListArray = [
            'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'abcdefghijklmnopqrstuvwxyz',
            '0123456789',
            '!@#$%*?',
        ];

        // Set complexity to no limit if type 5 is requested
        if ($complexity == 5) {
            $characterListArray = [];
            for ($unlim = 0; $unlim <= 255; $unlim++) {
                $characterListArray[0] .= chr($unlim);
            }
            if ($setMax == null) {
                $setMax = 15;
            }
        }

        // Backward-compatibility in the complexity will still give us captcha-capabilities for simpler users
        $max = 8;    // Longest complexity
        if ($complexity == 1) {
            unset($characterListArray[1], $characterListArray[2], $characterListArray[3]);
            $max = 6;
        }
        if ($complexity == 2) {
            unset($characterListArray[2], $characterListArray[3]);
            $max = 10;
        }
        if ($complexity == 3) {
            unset($characterListArray[3]);
            $max = 10;
        }
        if ($setMax > 0) {
            $max = $setMax;
        }
        $chars = [];
        $numchars = [];
        for ($i = 0; $i < $max; $i++) {
            $charListId = rand(0, count($characterListArray) - 1);
            // Set $numchars[ $charListId ] to a zero a value if not set before.
            // This might render ugly notices about undefined offsets in some cases.
            if (!isset($numchars[$charListId])) {
                $numchars[$charListId] = 0;
            }
            $numchars[$charListId]++;
            $chars[] = $characterListArray[$charListId][mt_rand(
                0,
                (
                    strlen(
                        $characterListArray[$charListId]
                    ) - 1
                )
            )];
        }
        shuffle($chars);
        $retp = implode('', $chars);

        return $retp;
    }

    /**
     * Setting this to false enables URI validation controls while registering callbacks
     *
     * @param bool $callbackValidationDisable
     */
    public function setSkipCallbackValidation($callbackValidationDisable = true)
    {
        $this->skipCallbackValidation = $callbackValidationDisable;
    }

    /**
     * @return bool
     * @since 1.3.47
     */
    public function getRegisterCallbacksViaRest()
    {
        return $this->registerCallbacksViaRest;
    }

    /**
     * If you want to register callbacks through the rest API instead of SOAP, set this to true
     *
     * @param bool $useRest
     * @return ResursBank
     * @noinspection ParameterDefaultValueIsNotNullInspection
     */
    public function setRegisterCallbacksViaRest($useRest = true)
    {
        $this->registerCallbacksViaRest = $useRest;
        return $this;
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
     * @throws Exception
     * @since 1.0.1
     * @since 1.1.1
     */
    public function setRegisterCallback(
        $callbackType = RESURS_CALLBACK_TYPES::NOT_SET,
        $callbackUriTemplate = '',
        $digestData = [],
        $basicAuthUserName = null,
        $basicAuthPassword = null
    ) {
        $registerCallbackException = null;
        $requestedCallbackType = $callbackType;
        $this->InitializeServices();

        // Not thrown = Success or skipped
        $this->setRegisterCallbackAddressValidation();

        // The final array
        $renderCallback = [];

        // DEFAULT SETUP
        $renderCallback['eventType'] = $this->getCallbackTypeString($callbackType);
        if (empty($renderCallback['eventType'])) {
            throw new ResursException(
                sprintf(
                    '%s exception: The callback type you are trying to register is not supported by EComPHP',
                    __FUNCTION__
                ),
                RESURS_EXCEPTIONS::CALLBACK_TYPE_UNSUPPORTED
            );
        }
        $renderCallback['uriTemplate'] = $callbackUriTemplate;

        // BASIC AUTH CONTROL
        if (!empty($basicAuthUserName) && !empty($basicAuthPassword)) {
            $renderCallback['basicAuthUserName'] = $basicAuthUserName;
            $renderCallback['basicAuthPassword'] = $basicAuthPassword;
        }

        ////// DIGEST CONFIGURATION BEGIN
        $renderCallback['digestConfiguration'] = [
            'digestParameters' => $this->getCallbackTypeParameters($callbackType),
        ];

        if (isset($digestData['digestAlgorithm']) &&
            (
                strtolower($digestData['digestAlgorithm']) === 'sha1' ||
                strtolower($digestData['digestAlgorithm']) === 'md5'
            )
        ) {
            $renderCallback['digestConfiguration']['digestAlgorithm'] = strtoupper($digestData['digestAlgorithm']);
        } else {
            // Always uppercase.
            $renderCallback['digestConfiguration']['digestAlgorithm'] = 'SHA1';
        }

        $hasDigest = false;
        if (isset($digestData['digestSalt']) && !empty($digestData['digestSalt'])) {
            $renderCallback['digestConfiguration']['digestSalt'] = $digestData['digestSalt'];
            $hasDigest = true;
        }

        if (!$hasDigest) {
            if (isset($this->digestKey[$renderCallback['eventType']]) &&
                !empty($this->digestKey[$renderCallback['eventType']])) {
                $renderCallback['digestConfiguration']['digestSalt'] = $this->digestKey[$renderCallback['eventType']];
            } elseif (!empty($this->globalDigestKey)) {
                $renderCallback['digestConfiguration']['digestSalt'] = $this->globalDigestKey;
            }
        }

        if (empty($renderCallback['digestConfiguration']['digestSalt'])) {
            throw new ResursException(
                'Digest salt key is missing. Unable to continue.',
                RESURS_EXCEPTIONS::CALLBACK_SALTDIGEST_MISSING
            );
        }
        ////// DIGEST CONFIGURATION FINISH
        if ($this->registerCallbacksViaRest && $callbackType !== RESURS_CALLBACK_TYPES::UPDATE) {
            $registerBy = 'rest';
            $serviceUrl = $this->getCheckoutUrl() . '/callbacks';
            $renderCallbackUrl = $serviceUrl . '/' . $renderCallback['eventType'];
            if (isset($renderCallback['eventType'])) {
                unset($renderCallback['eventType']);
            }
            try {
                $renderedResponse = $this->CURL->request(
                    $renderCallbackUrl,
                    $renderCallback,
                    requestMethod::METHOD_POST,
                    dataType::JSON
                );
                $code = $this->CURL->getCode();
            } catch (Exception $e) {
                $code = $e->getCode();
                $registerCallbackException = $e;
            }
        } else {
            $registerBy = 'wsdl';
            $renderCallbackUrl = $this->getServiceUrl('registerEventCallback');
            // We are not using postService here, since we are dependent on the
            // response code rather than the response itself
            /** @noinspection PhpUndefinedMethodInspection */
            $renderedResponse = $this->CURL->request($renderCallbackUrl, null, requestMethod::METHOD_POST);
            $renderedResponse->registerEventCallback($renderCallback);
            $code = $renderedResponse->getCode();
        }
        if ($code >= 200 && $code <= 250) {
            if (isset($this->skipCallbackValidation) && $this->skipCallbackValidation === false) {
                $callbackUriControl = $this->CURL->request($renderCallbackUrl)->getParsed();
                if (isset($callbackUriControl->uriTemplate) && is_string($callbackUriControl->uriTemplate) &&
                    strtolower($callbackUriControl->uriTemplate) == strtolower($callbackUriTemplate)
                ) {
                    return true;
                }
            }

            return true;
        }

        throw new ResursException(
            sprintf(
                '%s exception code %d: Failed to register callback event %s (originally %s) via service %s.',
                __FUNCTION__,
                $code,
                isset($renderCallback['eventType']) ?
                    $renderCallback['eventType'] : 'Unknown eventType: $renderCallback[eventType] was never set',
                'RESURS_CALLBACK_TYPES::' . $requestedCallbackType,
                $registerBy
            ),
            RESURS_EXCEPTIONS::CALLBACK_REGISTRATION_ERROR,
            $registerCallbackException
        );
    }

    /**
     * Check if external url validation should be done and throw only on failures.
     * This is per default skipped unless requested.
     *
     * @return bool
     * @throws ResursException
     */
    private function setRegisterCallbackAddressValidation()
    {
        if (is_array($this->validateExternalUrl) && count($this->validateExternalUrl)) {
            $isValidAddress = $this->validateExternalAddress();
            if ($isValidAddress == RESURS_CALLBACK_REACHABILITY::IS_NOT_REACHABLE) {
                throw new ResursException(
                    'Reachable response: Your site might not be available to our callbacks.'
                );
            } elseif ($isValidAddress == RESURS_CALLBACK_REACHABILITY::IS_REACHABLE_WITH_PROBLEMS) {
                throw new ResursException(
                    'Reachable response: Your site is available from the outside, ' .
                    'but problems occurred, that indicates that your site can not respond to external calls.'
                );
            }
        }
        return true;
    }

    /**
     * Run external URL validator and see whether an URL is really reachable or not (unsupported)
     *
     * @return int Returns a value from the class RESURS_CALLBACK_REACHABILITY
     * @throws Exception
     * @since 1.0.3
     * @since 1.1.3
     */
    public function validateExternalAddress()
    {
        $this->isNetWork();
        if (is_array($this->validateExternalUrl) && count($this->validateExternalUrl)) {
            $this->InitializeServices();
            $ExternalAPI = $this->externalApiAddress . 'urltest/isavailable/';
            $UrlDomain = $this->NETWORK->getUrlDomain($this->validateExternalUrl['url']);
            if (!preg_match('/^http/i', $UrlDomain[1])) {
                return RESURS_CALLBACK_REACHABILITY::IS_REACHABLE_NOT_AVAILABLE;
            }
            $Expect = $this->validateExternalUrl['http_accept'];
            $UnExpect = $this->validateExternalUrl['http_error'];
            $useUrl = $this->validateExternalUrl['url'];
            $base64url = $this->base64url_encode($useUrl);
            $ExternalPostData = ['link' => $useUrl, 'returnEncoded' => true];
            try {
                $this->CURL->request(
                    $ExternalAPI,
                    $ExternalPostData,
                    requestMethod::METHOD_POST,
                    dataType::JSON
                );
                $WebResponse = $this->CURL->getParsed();
            } catch (Exception $e) {
                return RESURS_CALLBACK_REACHABILITY::IS_REACHABLE_NOT_KNOWN;
            }
            if (isset($WebResponse->response->isAvailableResponse)) {
                /** @noinspection PhpUndefinedFieldInspection */
                $ParsedResponse = $WebResponse->response->isAvailableResponse;
            } else {
                if (isset($WebResponse->errors) && !empty($WebResponse->errors->faultstring)) {
                    throw new ResursException($WebResponse->errors->faultstring, $WebResponse->errors->code);
                } else {
                    throw new ResursException('No response returned from API', 500);
                }
            }
            if (isset($ParsedResponse->{$base64url}) &&
                isset($ParsedResponse->{$base64url}->exceptiondata->errorcode) &&
                !empty($ParsedResponse->{$base64url}->exceptiondata->errorcode)
            ) {
                return RESURS_CALLBACK_REACHABILITY::IS_NOT_REACHABLE;
            }
            $UrlResult = $ParsedResponse->{$base64url}->result;
            $totalResults = 0;
            $expectedResults = 0;
            $unExpectedResults = 0;
            $neitherResults = 0;
            foreach ($UrlResult as $BrowserName => $BrowserResponse) {
                $totalResults++;
                if ($BrowserResponse == $Expect) {
                    $expectedResults++;
                } elseif ($BrowserResponse == $UnExpect) {
                    $unExpectedResults++;
                } else {
                    $neitherResults++;
                }
            }
            if ($totalResults == $expectedResults) {
                return RESURS_CALLBACK_REACHABILITY::IS_FULLY_REACHABLE;
            }
            if ($expectedResults > 0 && $unExpectedResults > 0) {
                return RESURS_CALLBACK_REACHABILITY::IS_REACHABLE_WITH_PROBLEMS;
            }
            if ($neitherResults > 0) {
                return RESURS_CALLBACK_REACHABILITY::IS_REACHABLE_NOT_KNOWN;
            }
            if ($expectedResults === 0) {
                return RESURS_CALLBACK_REACHABILITY::IS_NOT_REACHABLE;
            }
        }

        return RESURS_CALLBACK_REACHABILITY::IS_REACHABLE_NOT_KNOWN;
    }

    /**
     * base64_encode for urls
     *
     * @param $data
     *
     * @return string
     */
    private function base64url_encode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Set up digestive parameters based on requested callback type
     *
     * @param int $callbackType
     *
     * @return array
     */
    private function getCallbackTypeParameters($callbackType = RESURS_CALLBACK_TYPES::NOT_SET)
    {
        $return = [];

        if ($callbackType == RESURS_CALLBACK_TYPES::ANNULMENT) {
            $return = ['paymentId'];
        }
        if ($callbackType == RESURS_CALLBACK_TYPES::AUTOMATIC_FRAUD_CONTROL) {
            $return = ['paymentId', 'result'];
        }
        if ($callbackType == RESURS_CALLBACK_TYPES::FINALIZATION) {
            $return = ['paymentId'];
        }
        if ($callbackType == RESURS_CALLBACK_TYPES::TEST) {
            $return = ['param1', 'param2', 'param3', 'param4', 'param5'];
        }
        if ($callbackType == RESURS_CALLBACK_TYPES::UNFREEZE) {
            $return = ['paymentId'];
        }
        if ($callbackType == RESURS_CALLBACK_TYPES::UPDATE) {
            $return = ['paymentId'];
        }
        if ($callbackType == RESURS_CALLBACK_TYPES::BOOKED) {
            $return = ['paymentId'];
        }

        return $return;
    }

    /////////// SERVICE HELPERS AND INTERNAL FLOW FUNCTIONS

    /**
     * Retrieve the correct RCO url depending chosen environment.
     *
     * @param int $requestedEnvironment
     * @param bool $getCurrentIfSet Always return "current" if it has been set first
     * @return string
     * @since 1.0.1
     * @since 1.1.1
     */
    public function getCheckoutUrl($requestedEnvironment = RESURS_ENVIRONMENTS::TEST, $getCurrentIfSet = true)
    {
        $return = $this->getUrlRcoStandard($requestedEnvironment);
        // Total overrider. Regardless of prepared environment. Use with caution.
        if (!empty($this->environmentRcoOverrideUrl)) {
            $return = $this->environmentRcoOverrideUrl;
        }

        if ($getCurrentIfSet && $this->current_environment_updated) {
            if ($this->current_environment === RESURS_ENVIRONMENTS::PRODUCTION) {
                $return = $this->getPos() ? $this->environmentRcoPosProduction :
                    $this->environmentRcoStandardProduction;
            } elseif ($this->getPos()) {
                $return = $this->environmentRcoPosTest;
            } else {
                $return = $this->environmentRcoStandardTest;
            }
        }

        return $return;
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
    public function getPos()
    {
        return $this->envOmniPos;
    }

    /**
     * @param $requestedEnvironment
     * @return string
     * @since 1.3.27
     */
    private function getUrlRcoStandard($requestedEnvironment)
    {
        $return = $this->environmentRcoStandardTest;

        if ($requestedEnvironment === RESURS_ENVIRONMENTS::PRODUCTION) {
            $return = $this->environmentRcoStandardProduction;
        }

        return $return;
    }

    /**
     * Simplifies removal of callbacks even when they does not exist at first.
     *
     * @param int $callbackType
     * @param bool $isMultiple Consider callback type bit range when true, where the value 255 is all callbacks at once.
     * @param bool $forceSoap
     * @return array|bool
     * @throws Exception
     * @since 1.0.1
     * @since 1.1.1
     * @noinspection ParameterDefaultValueIsNotNullInspection
     */
    public function unregisterEventCallback(
        $callbackType = RESURS_CALLBACK_TYPES::NOT_SET,
        $isMultiple = false,
        $forceSoap = false
    ) {
        $callbackArray = [];
        if ($isMultiple) {
            $this->BIT = new Bit();
            $this->BIT->setBitStructure(
                [
                    'UNFREEZE' => RESURS_CALLBACK_TYPES::UNFREEZE,
                    'ANNULMENT' => RESURS_CALLBACK_TYPES::ANNULMENT,
                    'AUTOMATIC_FRAUD_CONTROL' => RESURS_CALLBACK_TYPES::AUTOMATIC_FRAUD_CONTROL,
                    'FINALIZATION' => RESURS_CALLBACK_TYPES::FINALIZATION,
                    'TEST' => RESURS_CALLBACK_TYPES::TEST,
                    'UPDATE' => RESURS_CALLBACK_TYPES::UPDATE,
                    'BOOKED' => RESURS_CALLBACK_TYPES::BOOKED,
                ]
            );
            $callbackTypes = $this->BIT->getBitArray($callbackType);
            // Fetch list of currently present callbacks at Resurs Bank.
            $callbackArray = $this->getCallBacksByRest(true);
        }

        $callbackType = $this->getCallbackTypeString($callbackType);

        if (!isset($callbackTypes) || !is_array($callbackTypes)) {
            $callbackTypes = [$callbackType];
        }

        $unregisteredCallbacks = [];
        foreach ($callbackTypes as $callbackType) {
            if ($isMultiple && is_array($callbackArray) && !isset($callbackArray[$callbackType])) {
                // Skip this callback request if it's not present at Resurs Bank and no errors occurred
                // during first request.
                continue;
            }

            if (!empty($callbackType)) {
                if ($this->registerCallbacksViaRest && $callbackType !== 'UPDATE' && !$forceSoap) {
                    $this->InitializeServices();
                    $serviceUrl = $this->getCheckoutUrl() . '/callbacks';
                    $renderCallbackUrl = $serviceUrl . '/' . $callbackType;
                    try {
                        $curlResponse = $this->CURL->request(
                            $renderCallbackUrl,
                            [],
                            requestMethod::METHOD_DELETE,
                            dataType::JSON
                        );
                        $curlCode = $curlResponse->getCode();
                    } catch (Exception $e) {
                        // If this one suddenly starts throwing exceptions.
                        $curlCode = $e->getCode();
                    }
                    if ($curlCode >= 200 && $curlCode <= 250) {
                        if (!$isMultiple) {
                            return true;
                        } else {
                            $unregisteredCallbacks[$callbackType] = true;
                        }
                    }
                } else {
                    $this->InitializeServices();
                    try {
                        // Proper SOAP request.
                        $curlSoapRequest = $this->CURL->request($this->getServiceUrl('unregisterEventCallback'));
                        $curlSoapRequest->unregisterEventCallback(
                            ['eventType' => $callbackType]
                        );
                        $curlCode = $curlSoapRequest->getCode();
                    } catch (Exception $e) {
                        // If this one suddenly starts throwing exceptions.
                        $curlCode = $e->getCode();
                    }
                    if ($curlCode >= 200 && $curlCode <= 250) {
                        if (!$isMultiple) {
                            return true;
                        } else {
                            $unregisteredCallbacks[$callbackType] = true;
                        }
                    }
                }
            }
        }
        if (!$isMultiple) {
            return false;
        } else {
            return $unregisteredCallbacks;
        }
    }

    /**
     * Get a full list of, by merchant, registered callbacks
     *
     * The callback list will return only existing eventTypes, so if no event types exists, the returned array or
     * object will be empty. Developer note: Changing this behaviour so all event types is always returned even if they
     * don't exist (meaning ecomphp fills in what's missing) might break plugins that is already in production.
     *
     * @param bool $ReturnAsArray
     *
     * @return array
     * @throws Exception
     * @link  https://test.resurs.com/docs/display/ecom/ECommerce+PHP+Library#ECommercePHPLibrary-getCallbacksByRest
     * @since 1.0.1
     */
    public function getCallBacksByRest($ReturnAsArray = false)
    {
        $ResursResponse = [];
        $hasUpdate = false;

        $this->InitializeServices();
        try {
            if (Flag::isFlag('callback_rest_500')) {
                throw new Exception(
                    'This exception is not real and only a part of testings.',
                    500
                );
            }
            $ResursResponse = $this->CURL->request($this->getCheckoutUrl() . '/callbacks')->getParsed();
        } catch (Exception $restException) {
            $message = $restException->getMessage();
            $code = $restException->getCode();

            $failover = false;
            if ($code >= 500) {
                try {
                    $failover = true;
                    $hasUpdate = true;
                    $ResursResponse = $this->getRegisteredEventCallback(255);
                    if (!$ReturnAsArray && is_array($ResursResponse)) {
                        foreach ($ResursResponse as $callbackKey => $callbackUrl) {
                            $callbackClass = new stdClass();
                            $callbackClass->eventType = $callbackKey;
                            $callbackClass->uriTemplate = $callbackUrl;
                            $returnObject[] = $callbackClass;
                        }
                        if (is_array($returnObject)) {
                            $ResursResponse = $returnObject;
                        }
                    }
                } catch (Exception $e) {
                }
            }

            if (!$failover) {
                // Special recipes extracted from netcurl-6.1
                if (method_exists($restException, 'getExtendException')) {
                    $extendedClass = $restException->getExtendException();
                    if (is_object($extendedClass) && method_exists($extendedClass, 'getParsed')) {
                        $parsedExtended = $extendedClass->getParsed();
                        if (isset($parsedExtended->description)) {
                            $message .= ' (' . $parsedExtended->description . ')';
                        }
                        if (isset($parsedExtended->code) && $parsedExtended->code > 0) {
                            $code = $parsedExtended->code;
                        }
                    }
                }
                throw new ResursException($message, $code, $restException);
            }
        }
        if ($ReturnAsArray) {
            $ResursResponseArray = [];
            if (is_array($ResursResponse) && count($ResursResponse)) {
                foreach ($ResursResponse as $object) {
                    if (isset($object->eventType)) {
                        $ResursResponseArray[$object->eventType] = (
                        isset($object->uriTemplate) ? $object->uriTemplate : ''
                        );
                    }
                }
            }
            if (!isset($ResursResponseArray['UPDATE'])) {
                $updateResponse = $this->getRegisteredEventCallback(RESURS_CALLBACK_TYPES::UPDATE);
                if (is_object($updateResponse) && isset($updateResponse->uriTemplate)) {
                    $ResursResponseArray['UPDATE'] = $updateResponse->uriTemplate;
                }
            }

            return $ResursResponseArray;
        }

        if (is_array($ResursResponse) || is_object($ResursResponse)) {
            foreach ($ResursResponse as $responseObject) {
                if (isset($responseObject->eventType) && $responseObject->eventType == 'UPDATE') {
                    $hasUpdate = true;
                }
            }
        }
        if (!$hasUpdate) {
            $updateResponse = $this->getRegisteredEventCallback(RESURS_CALLBACK_TYPES::UPDATE);
            if (isset($updateResponse->uriTemplate) && !empty($updateResponse->uriTemplate)) {
                if (!isset($updateResponse->eventType)) {
                    $updateResponse->eventType = 'UPDATE';
                }
                $ResursResponse[] = $updateResponse;
            }
        }

        return $ResursResponse;
    }

    /**
     * Trigger the registered callback event TEST if set. Returns true if trigger call was successful, otherwise false
     * (Observe that this not necessarily is a successful completion of the callback)
     *
     * @return bool
     * @throws Exception
     * @since 1.0.2
     * @since 1.1.2
     */
    public function triggerCallback()
    {
        $this->InitializeServices();
        $envUrl = $this->env_test;
        $curEnv = $this->getEnvironment();
        if ($curEnv == RESURS_ENVIRONMENTS::PRODUCTION) {
            $envUrl = $this->env_prod;
        }
        $serviceUrl = $envUrl . 'DeveloperWebService?wsdl';
        $eventRequest = $this->CURL->request($serviceUrl);
        $eventParameters = [
            'eventType' => 'TEST',
            'param' => [
                rand(10000, 30000),
                rand(10000, 30000),
                rand(10000, 30000),
                rand(10000, 30000),
                rand(10000, 30000),
            ],
        ];
        try {
            /** @noinspection PhpUndefinedMethodInspection */
            $eventRequest->triggerEvent($eventParameters);
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Returns target environment (production or test)
     *
     * @return int
     * @since 1.0.2
     * @since 1.1.2
     */
    public function getEnvironment()
    {
        return $this->current_environment;
    }

    /**
     * Define current environment
     *
     * @param int $environmentType
     */
    public function setEnvironment($environmentType = RESURS_ENVIRONMENTS::TEST)
    {
        $this->current_environment = $environmentType;
        $this->current_environment_updated = true;
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
    public function setPreferredPaymentService($flowType = RESURS_FLOW_TYPES::NOT_SET)
    {
        $this->setPreferredPaymentFlowService($flowType);
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
    public function setPreferredPaymentFlowService($flowType = RESURS_FLOW_TYPES::NOT_SET)
    {
        $this->enforceService = $flowType;
        if ($flowType == RESURS_FLOW_TYPES::HOSTED_FLOW) {
            $this->isHostedFlow = true;
            $this->isOmniFlow = false;
        } elseif ($flowType == RESURS_FLOW_TYPES::RESURS_CHECKOUT) {
            $this->isHostedFlow = false;
            $this->isOmniFlow = true;
        } elseif ($flowType == RESURS_FLOW_TYPES::SIMPLIFIED_FLOW) {
            $this->isHostedFlow = false;
            $this->isOmniFlow = false;
        } else {
            $this->isHostedFlow = false;
            $this->isOmniFlow = false;
        }
    }

    /**
     * @param $curlProxyAddr
     * @param $curlProxyType
     * @return ResursBank
     * @since 1.3.41
     */
    public function setProxy($curlProxyAddr, $curlProxyType)
    {
        $CURL = $this->getCurlHandle();
        $CURL->setProxy($curlProxyAddr, $curlProxyType);
        $this->setCurlHandle($CURL);

        return $this;
    }

    /**
     * Return the CURL communication handle to the client.
     *
     * @param bool $bulk
     * @param bool $reinitialize Get a brand new handle, in case of failures where old handles are inherited the wrong
     *     way.
     * @return array|mixed|Netwrapper
     * @throws Exception
     * @since 1.0.22
     * @since 1.1.22
     * @since 1.2.0
     */
    public function getCurlHandle($bulk = false, $reinitialize = false)
    {
        $this->InitializeServices($reinitialize);
        if ($bulk) {
            if (count($this->CURL_HANDLE_COLLECTOR)) {
                return array_pop($this->CURL_HANDLE_COLLECTOR);
            }

            return $this->CURL_HANDLE_COLLECTOR;
        }

        return $this->CURL;
    }

    /**
     *
     * Make it possible, in test mode, to replace the old curl handle with a new reconfigured one
     *
     * @param $newCurlHandle
     *
     * @throws Exception
     * @since 1.0.23
     * @since 1.1.23
     * @since 1.2.0
     */
    public function setCurlHandle($newCurlHandle)
    {
        $this->InitializeServices();
        $this->CURL = $newCurlHandle;
        $this->CURL_USER_DEFINED = $newCurlHandle;
    }

    /**
     * Return the current set "preferred payment service" (hosted, checkout, simplified)
     *
     * @return RESURS_FLOW_TYPES
     * @since 1.0.0
     * @since 1.1.0
     * @deprecated 1.0.26 Use getPreferredPaymentFlowService
     * @deprecated 1.1.26 Use getPreferredPaymentFlowService
     */
    public function getPreferredPaymentService()
    {
        return $this->getPreferredPaymentFlowService();
    }

    /**
     * Return the current set by user preferred payment flow service
     *
     * @return RESURS_FLOW_TYPES
     * @since 1.0.26
     * @since 1.1.26
     * @since 1.2.0
     */
    public function getPreferredPaymentFlowService()
    {
        $return = $this->enforceService;

        if (is_null($this->enforceService)) {
            $return = RESURS_FLOW_TYPES::NOT_SET;
        }

        if ($this->getFlag('USE_AFTERSHOP_RENDERING') === true) {
            $return = RESURS_FLOW_TYPES::SIMPLIFIED_FLOW;
        }

        return $return;
    }

    /**
     * @param bool $setSoapChainBoolean
     * @return ResursBank
     * @throws Exception
     * @since 1.0.36
     * @since 1.1.36
     * @since 1.3.9
     * @since 2.0.0
     * @deprecated Has no function.
     */
    public function setSoapChain($setSoapChainBoolean = true)
    {
        $this->InitializeServices();
        return $this;
    }

    /**
     * @return mixed|null
     * @throws Exception
     * @since 1.0.36
     * @since 1.1.36
     * @since 1.3.9
     * @since 2.0.0
     * @deprecated Has no function.
     */
    public function getSoapChain()
    {
        $this->InitializeServices();

        return true;
    }

    /**
     * When something from CURL threw an exception and you really need to get detailed information about those
     * exceptions
     *
     * @return array
     * @since 1.0.1
     * @since 1.1.1
     */
    public function getStoredCurlExceptionInformation()
    {
        return $this->CURL->getStoredExceptionInformation();
    }

    /**
     * Special function for pushing user-agent from customer into our ecommerce communication. This must be enabled
     * before setUserAgent.
     *
     * @param bool $enableCustomerUserAgent
     * @return ResursBank
     * @since 1.1.13
     * @since 1.2.0
     * @since 1.0.13
     */
    public function setPushCustomerUserAgent($enableCustomerUserAgent = false)
    {
        $this->customerUserAgentPush = $enableCustomerUserAgent;

        return $this;
    }

    /**
     * Nullify invoice sequence
     *
     * @return int|null
     * @throws Exception
     * @since 1.0.27
     * @since 1.1.27
     * @since 1.2.0
     */
    public function resetInvoiceNumber()
    {
        $this->InitializeServices();

        return $this->postService('setInvoiceSequence');
    }

    /**
     * Speak with webservices
     *
     * @param string $serviceName
     * @param array $resursParameters
     * @param bool $getResponseCode
     * @return int|null
     * @throws Exception
     * @since 1.0.2
     * @since 1.1.2
     * @since 1.2.0
     */
    private function postService($serviceName = '', $resursParameters = [], $getResponseCode = false)
    {
        $this->InitializeServices();
        $serviceNameUrl = $this->getServiceUrl($serviceName);
        $soapBody = null;
        if (!empty($serviceNameUrl) && !is_null($this->CURL)) {
            $Service = $this->CURL->request($serviceNameUrl);
            try {
                // Using call_user_func_array requires the parameters at this level to be pushed into an array.
                //$RequestService = call_user_func_array(array($Service, $serviceName), [$resursParameters]);
                $RequestService = $Service->$serviceName($resursParameters);
            } catch (Exception $serviceRequestException) {
                // Try to fetch previous exception (This is what we actually want)
                $previousException = $serviceRequestException->getPrevious();
                $previousExceptionCode = null;
                if (!empty($previousException)) {
                    $previousExceptionMessage = $previousException->getMessage();
                    $previousExceptionCode = $previousException->getCode();
                }
                if (!empty($previousExceptionMessage)) {
                    $exceptionMessage = $previousExceptionMessage;
                    $exceptionCode = $previousExceptionCode;
                } else {
                    $exceptionCode = $serviceRequestException->getCode();
                    $exceptionMessage = $serviceRequestException->getMessage();
                }
                if (isset($previousException->detail) &&
                    is_object($previousException->detail) &&
                    isset($previousException->detail->ECommerceError) &&
                    is_object($previousException->detail->ECommerceError)
                ) {
                    $objectDetails = $previousException->detail->ECommerceError;
                    if (isset($objectDetails->errorTypeId) && intval($objectDetails->errorTypeId) > 0) {
                        $exceptionCode = $objectDetails->errorTypeId;
                    }
                    if (isset($objectDetails->userErrorMessage)) {
                        $errorTypeDescription = (
                        isset($objectDetails->errorTypeDescription) ? '[' .
                            $objectDetails->errorTypeDescription . '] ' : ''
                        );
                        $exceptionMessage = $errorTypeDescription . $objectDetails->userErrorMessage;
                        if (isset($previousException->faultstring)) {
                            $exceptionMessage .= ' (' . $previousException->getMessage() . ') ';
                        }
                        $fixableByYou = isset($objectDetails->fixableByYou) ? $objectDetails->fixableByYou : null;
                        if ($fixableByYou == 'false') {
                            $fixableByYou = ' (Not fixable by you)';
                        } else {
                            $fixableByYou = ' (Fixable by you)';
                        }
                        $exceptionMessage .= $fixableByYou;
                    }
                }
                if (empty($exceptionCode) || $exceptionCode === '0') {
                    $exceptionCode = RESURS_EXCEPTIONS::UNKOWN_SOAP_EXCEPTION_CODE_ZERO;
                }
                // Cast internal soap errors into a new, since the exception code is lost
                throw new ResursException($exceptionMessage, $exceptionCode, $serviceRequestException);
            }
            $ParsedResponse = $Service->getParsed();
            $ResponseCode = $Service->getCode();
            if ($this->debug) {
                if (!isset($this->curlStats['calls'])) {
                    $this->curlStats['calls'] = 1;
                }
                $this->curlStats['calls']++;
                // getDebugData deprecated in 6.0, removed in 6.1
                $this->curlStats['internals'] = $this->CURL;
            }
            $this->CURL_HANDLE_COLLECTOR[] = $Service;

            if (!$getResponseCode) {
                return $ParsedResponse;
            } else {
                return $ResponseCode;
            }
        }

        return null;
    }

    /**
     * @param $type
     * @param string $customerType
     * @return array
     * @throws Exception
     * @since 1.3.45
     */
    public function getPaymentMethodsByType($type, $customerType = null)
    {
        $return = [];
        $methodList = $this->getPaymentMethods();

        if (is_array($methodList) && count($methodList)) {
            foreach ($methodList as $method) {
                if (isset($method->type) && $method->type === $type) {
                    $foundMethod = $method;
                } elseif (isset($method->specificType) && $method->specificType === $type) {
                    $foundMethod = $method;
                } else {
                    $foundMethod = null;
                }
                if (!empty($foundMethod)) {
                    if (empty($customerType)) {
                        $return[] = $foundMethod;
                    } else {
                        if (in_array($customerType, (array)$foundMethod, true)) {
                            $return[] = $foundMethod;
                        }
                    }
                }
            }
        }

        return $return;
    }

    /**
     * List payment methods
     *
     * Retrieves detailed information on the payment methods available to the representative. Parameters (customerType,
     * language and purchaseAmount) are optional.
     *
     * @param array $parameters
     * @param bool $getAllMethods Manually configured psp-overrider
     * @return mixed
     * @throws Exception
     * @since 1.0.0
     * @since 1.1.0
     * @link  https://test.resurs.com/docs/display/ecom/Get+Payment+Methods
     */
    public function getPaymentMethods($parameters = [], $getAllMethods = false)
    {
        $this->InitializeServices();

        $paymentMethodsParameters = [
            'customerType' => isset($parameters['customerType']) ? $parameters['customerType'] : null,
            'language' => isset($parameters['language']) ? $parameters['language'] : null,
            'purchaseAmount' => isset($parameters['purchaseAmount']) ? $parameters['purchaseAmount'] : null,
        ];

        // Discover changes in request parameters.
        if (isset($this->paymentMethodsCache['params']) && count($this->paymentMethodsCache['methods'])) {
            $currentArray = array_intersect($paymentMethodsParameters, $this->paymentMethodsCache['params']);
            if (count($currentArray) === count($paymentMethodsParameters)) {
                return $this->paymentMethodsCache['methods'];
            }
        }

        $paymentMethods = $this->postService('getPaymentMethods', $paymentMethodsParameters);
        // Make sure this method always returns an array even if it is only one method.
        // Ecommerce will, in case of only one available method return an object instead of an array.
        if (is_object($paymentMethods)) {
            $paymentMethods = [$paymentMethods];
        }
        $realPaymentMethods = $this->sanitizePaymentMethods($paymentMethods, $getAllMethods);
        $this->paymentMethodsCache = [
            'params' => $paymentMethodsParameters,
            'methods' => $realPaymentMethods,
        ];

        return $realPaymentMethods;
    }

    /**
     * Sanitize payment methods locally: make sure, amongst others that also cached payment methods is handled
     * correctly on request, when for example PAYMENT_PROVIDER needs to be cleaned up
     *
     * @param array $paymentMethods
     * @param bool $getAllMethods Manually configured psp-overrider
     * @return array
     * @since 1.0.24
     * @since 1.1.24
     * @since 1.2.0
     */
    public function sanitizePaymentMethods($paymentMethods = [], $getAllMethods = false)
    {
        $realPaymentMethods = [];
        $paymentService = $this->getPreferredPaymentFlowService();
        if (is_array($paymentMethods) && count($paymentMethods)) {
            foreach ($paymentMethods as $paymentMethodIndex => $paymentMethodData) {
                $type = $paymentMethodData->type;
                $addMethod = true;

                if ($this->paymentMethodIdSanitizing && isset($paymentMethods[$paymentMethodIndex]->id)) {
                    $paymentMethods[$paymentMethodIndex]->id = preg_replace(
                        '/[^a-z0-9$]/i',
                        '',
                        $paymentMethods[$paymentMethodIndex]->id
                    );
                }

                if (!$getAllMethods && $this->paymentMethodsIsStrictPsp) {
                    if ($type == 'PAYMENT_PROVIDER') {
                        $addMethod = false;
                    }
                } elseif ($paymentService != RESURS_FLOW_TYPES::RESURS_CHECKOUT) {
                    if (!$getAllMethods && $type == 'PAYMENT_PROVIDER') {
                        $addMethod = false;
                    }
                    if ($getAllMethods || $this->paymentMethodsHasPsp) {
                        $addMethod = true;
                    }
                }

                if ($addMethod) {
                    $realPaymentMethods[] = $paymentMethodData;
                }
            }
        }

        return $realPaymentMethods;
    }

    /**
     * Get list of payment methods (payment method objects), that support annuity factors
     *
     * @param bool $namesOnly
     *
     * @return array
     * @throws Exception
     * @since 1.0.36
     * @since 1.1.36
     * @since 1.3.9
     * @since 2.0.0
     */
    public function getPaymentMethodsByAnnuity($namesOnly = false)
    {
        $allMethods = $this->getPaymentMethods();
        $annuitySupported = ['REVOLVING_CREDIT'];
        $annuityMethods = [];
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
     * Return the payment method id sanitizer status (active=true)
     *
     * @return bool
     * @since 1.0.22
     * @since 1.1.22
     * @since 1.2.0
     */
    public function getPaymentMethodIdSanitizing()
    {
        return $this->paymentMethodIdSanitizing;
    }

    /**
     * Setting this to true should help developers have their payment method ids returned in a consistent format (a-z,
     * 0-9, will be the only accepted characters)
     *
     * @param bool $doSanitize
     *
     * @since 1.0.22
     * @since 1.1.22
     * @since 1.2.0
     */
    public function setPaymentMethodIdSanitizing($doSanitize = false)
    {
        $this->paymentMethodIdSanitizing = $doSanitize;
    }

    /**
     * If the merchant has PSP methods available in the simplified and hosted flow where it is normally not supported,
     * this should be set to true. setStrictPsp() overrides this setting.
     *
     * @param bool $allowed
     *
     * @since 1.0.22
     * @since 1.1.22
     * @since 1.2.0
     */
    public function setSimplifiedPsp($allowed = false)
    {
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
    public function getSimplifiedPsp()
    {
        return $this->paymentMethodsHasPsp;
    }

    /**
     * If the strict control of payment methods vs PSP is set, we will never show any payment method that is based on
     * PAYMENT_PROVIDER.
     *
     * This might be good to use in mixed environments and payment methods are listed regardless of the requested flow.
     * This setting overrides setSimplifiedPsp()
     *
     * @param bool $isStrict
     *
     * @since 1.0.22
     * @since 1.1.22
     * @since 1.2.0
     */
    public function setStrictPsp($isStrict = false)
    {
        $this->paymentMethodsIsStrictPsp = $isStrict;
    }

    /**
     * Returns the value set with setStrictPsp()
     *
     * @return bool
     * @since 1.0.22
     * @since 1.1.22
     * @since 1.2.0
     */
    public function getStrictPsp()
    {
        return $this->paymentMethodsIsStrictPsp;
    }

    /**
     * Get customer address by phone number Currently only works in norway.
     *
     * @param string $phoneNumber
     * @param string $customerType
     * @param string $customerIpAddress
     * @return int|null
     * @throws Exception
     * @since 1.3.32
     */
    public function getAddressByPhone($phoneNumber = '', $customerType = 'NATURAL', $customerIpAddress = '')
    {
        if (!empty($customerIpAddress) && isset($_SERVER['REMOTE_ADDR'])) {
            $customerIpAddress = $_SERVER['REMOTE_ADDR'];
        }

        return $this->postService('getAddressByPhone', [
            'phoneNumber' => $phoneNumber,
            'customerType' => $customerType,
            'customerIpAddress' => $customerIpAddress,
        ]);
    }

    /**
     * Get annuity factor rounded sum by the total price
     *
     * @param $totalAmount
     * @param $paymentMethodIdOrFactorObject
     * @param $duration
     *
     * @return float
     * @throws Exception
     * @since 1.1.24
     */
    public function getAnnuityPriceByDuration($totalAmount, $paymentMethodIdOrFactorObject, $duration)
    {
        $return = 0;
        $durationFactor = $this->getAnnuityFactorByDuration($paymentMethodIdOrFactorObject, $duration);
        if ($durationFactor > 0) {
            $return = round($durationFactor * $totalAmount);
        }

        return $return;
    }

    /**
     * Get annuity factor by duration
     *
     * @param $paymentMethodIdOrFactorObject
     * @param $duration
     *
     * @return float
     * @throws Exception
     * @since 1.1.24
     */
    public function getAnnuityFactorByDuration($paymentMethodIdOrFactorObject, $duration)
    {
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
     * AnnuityFactorsLight - Replacement of the former annuityFactor call, simplified.
     *
     * To use the former method, look for getAnnuityFactorsDeprecated. This function might however disappear in the
     * future.
     *
     * @param string $paymentMethodId
     *
     * @return array|mixed|null
     * @throws Exception
     * @since 1.0.1
     * @since 1.1.1
     * @link https://test.resurs.com/docs/x/JQBH getAnnuityFactors() documentation
     */
    public function getAnnuityFactors($paymentMethodId = '')
    {
        $return = $this->postService('getAnnuityFactors', ['paymentMethodId' => $paymentMethodId]);

        if (is_object($return)) {
            // Embed array.
            $return = [$return];
        }

        return $return;
    }

    /**
     * @param int $keepCacheTimeInSeconds Number of seconds we fetch data from cache instead of live after first call.
     * @since 1.3.26
     */
    public function setApiCacheTime($keepCacheTimeInSeconds = 3)
    {
        $this->lastGetPaymentMaxCacheTime = $keepCacheTimeInSeconds;
    }

    /**
     * @return int Cache time set, in seconds.
     * @since 1.3.26
     */
    public function getApiCacheTime()
    {
        return $this->lastGetPaymentMaxCacheTime;
    }

    /**
     * Set Api Cache enabled or disabled.
     *
     * Function set used to cache the first request during a smaller amount of time to make sure
     * that, if the request are being sent twice during this period, the cache will reply instead of making
     * live responses.
     *
     * @param bool $enabled
     * @since 1.3.26
     */
    public function setApiCache($enabled = true)
    {
        $this->apiCacheActive = $enabled;
    }

    /**
     * Get status of Api Cache.
     *
     * @since 1.3.26
     */
    public function getApiCache()
    {
        return $this->apiCacheActive;
    }

    /**
     * @return int
     * @since 1.3.26
     */
    public function getGetPaymentRequests()
    {
        return $this->getPaymentRequests;
    }

    /**
     * @return int
     * @since 1.3.26
     */
    public function getGetCachedPaymentRequests()
    {
        return $this->getPaymentCachedRequests;
    }

    /**
     * @return int
     * @since 1.3.45
     */
    public function getGetPaymentRequestMethod()
    {
        return $this->getPaymentRequestMethod;
    }

    /**
     * Get a list of current available payment methods, in the form of an array list with id's.
     *
     * @return array
     * @throws Exception
     * @since 1.0.0
     * @since 1.1.0
     */
    public function getPaymentMethodNames()
    {
        $methods = $this->getPaymentMethods();
        if (is_array($methods)) {
            $this->paymentMethodNames = [];
            foreach ($methods as $objectMethod) {
                if (isset($objectMethod->id) && !empty($objectMethod->id) && !in_array(
                        $objectMethod->id,
                        $this->paymentMethodNames
                    )) {
                    $this->paymentMethodNames[$objectMethod->id] = $objectMethod->id;
                }
            }
        }

        return $this->paymentMethodNames;
    }

    /**
     * @param string $paymentId The current paymentId
     * @param string $to What it should be updated to
     *
     * @return bool
     * @throws Exception
     * @since 1.0.1
     * @since 1.1.1
     */
    public function updatePaymentReference($paymentId, $to)
    {
        if (empty($paymentId) || empty($to)) {
            throw new ResursException('Payment id and to must be set.');
        }
        $this->InitializeServices();
        $url = $this->getCheckoutUrl() . '/checkout/payments/' . $paymentId . '/updatePaymentReference';
        try {
            $result = $this->CURL->request(
                $url,
                ['paymentReference' => $to],
                requestMethod::METHOD_PUT,
                dataType::JSON
            );
        } catch (Exception $e) {
            $exceptionFromBody = $this->CURL->getBody();

            if (is_string($exceptionFromBody) && !empty($exceptionFromBody)) {
                $jsonized = @json_decode($exceptionFromBody);
                if (isset($jsonized->errorCode) &&
                    ((int)$jsonized->errorCode > 0 || strlen($jsonized->errorCode) > 3)
                ) {
                    if (isset($jsonized->description)) {
                        $errorMessage = $jsonized->description;
                    } elseif (isset($jsonized->detailedMessage)) {
                        $errorMessage = $jsonized->detailedMessage;
                    } else {
                        $errorMessage = $e->getMessage();
                    }

                    throw new ResursException(
                        $errorMessage,
                        is_numeric($jsonized->errorCode) ? $jsonized->errorCode : 0,
                        null,
                        !is_numeric($jsonized->errorCode) &&
                        is_string($jsonized->errorCode) ? $jsonized->errorCode : null,
                        __FUNCTION__
                    );
                }
            }

            throw $e;
        }
        $ResponseCode = $result->getCode();
        if ($ResponseCode >= 200 && $ResponseCode <= 250) {
            return true;
        }

        // Probably we'll never get here.
        return false;
    }

    /**
     * Get the configured store id
     *
     * @return mixed
     * @since 1.0.7
     * @since 1.1.7
     */
    public function getStoreId()
    {
        return $this->storeId;
    }

    /**
     * Set store id for the payload
     *
     * @param null $storeId
     *
     * @since 1.0.7
     * @since 1.1.7
     */
    public function setStoreId($storeId = null)
    {
        if (!empty($storeId)) {
            $this->storeId = $storeId;
        }
    }

    /**
     * Adds metaData to a payment (before creation)
     *
     * Note that addMetaData adds metaData to a payment AFTER creation. This method occurs DURING a bookPayment
     * rather than after it has been booked.
     *
     * @param $key
     * @param $value
     * @param bool $preventDuplicates
     * @throws ResursException
     * @since 1.0.40
     * @since 1.1.40
     * @since 1.3.13
     */
    public function setMetaData($key, $value, $preventDuplicates = true)
    {
        if (!isset($this->Payload['metaData'])) {
            $this->Payload['metaData'] = [];
        }

        if (!$preventDuplicates || !$this->hasMetaDataKey($key)) {
            if (!empty($key)) {
                if ($this->getPreferredPaymentFlowService() !== RESURS_FLOW_TYPES::HOSTED_FLOW) {
                    $this->Payload['metaData'][] = ['key' => $key, 'value' => $value];
                } else {
                    $this->Payload['metaData'][] = [$key => $value];
                }
            }
        } else {
            throw new ResursException(sprintf('Metadata key "%s" is already set.', $key), 400);
        }
    }

    /**
     * @param $key
     * @return bool
     * @throws Exception
     * @since 1.3.16
     * @since 1.1.44
     * @since 1.0.44
     */
    public function hasMetaDataKey($key)
    {
        $return = false;
        $metaList = $this->getMetaData(null, true, true);

        if (isset($metaList['payloadMetaData'][$key])) {
            $return = true;
        }

        return $return;
    }

    /**
     * Get metadata from a payment. As of 1.3.16 metadata can also be fetched from pre set payload.
     *
     * @param string $paymentId
     * @param bool $internalMetadata
     * @param bool $assoc
     * @return array
     * @throws ResursException
     */
    public function getMetaData($paymentId = '', $internalMetadata = false, $assoc = false)
    {
        $metaDataResponse = [];

        if ($internalMetadata) {
            if (isset($this->Payload['metaData'])) {
                if ($assoc) {
                    $newArray = [];
                    foreach ($this->Payload['metaData'] as $req) {
                        if (isset($req['key'])) {
                            $newArray[$req['key']] = $req['value'];
                        }
                    }
                    $metaDataResponse = [
                        'payloadMetaData' => $newArray,
                    ];
                } else {
                    $metaDataResponse = [
                        'payloadMetaData' => $this->Payload['metaData'],
                    ];
                }
            } else {
                $metaDataResponse = ['payloadMetaData' => []];
            }
        } else {
            if (is_string($paymentId)) {
                $payment = $this->getPayment($paymentId);
            } elseif (is_object($paymentId)) {
                $payment = $paymentId;
            } else {
                if (!$internalMetadata) {
                    throw new ResursException('getMetaDataException: PaymentID is neither and id nor object.', 500);
                }
            }
            if (isset($payment) && isset($payment->metaData)) {
                foreach ($payment->metaData as $metaIndexArray) {
                    if (isset($metaIndexArray->key) && !empty($metaIndexArray->key)) {
                        if (!isset($metaDataResponse[$metaIndexArray->key])) {
                            $metaDataResponse[$metaIndexArray->key] = $metaIndexArray->value;
                        } else {
                            $metaDataResponse[$metaIndexArray->key][] = $metaIndexArray->value;
                        }
                    }
                }
            }
        }

        return $metaDataResponse;
    }

    /**
     * Automated function for getCostOfPurchaseHtml() - Returning content in UTF-8 formatted display if a body are
     * requested
     *
     * @param string $paymentMethod
     * @param int $amount
     * @param bool $returnBody Make this function return a full body with css
     * @param string $callCss Your own css url
     * @param string $hrefTarget Point opening target somewhere else (i.e. _blank opens in a new window)
     *
     * @return string
     * @throws Exception
     * @link https://test.resurs.com/docs/x/_QBV
     * @since 1.0.0
     * @since 1.1.0
     */
    public function getCostOfPurchase(
        $paymentMethod = '',
        $amount = 0,
        $returnBody = false,
        $callCss = 'costofpurchase.css',
        $hrefTarget = '_blank'
    ) {
        $returnHtml = $this->postService('getCostOfPurchaseHtml', [
            'paymentMethodId' => $paymentMethod,
            'amount' => $amount,
        ]);
        // Try to make the target open as a different target, if set. This will not invoke, if not set.
        if (!empty($hrefTarget)) {
            // Check if there are any target set, somewhere in the returned html. If true, we'll consider
            // this already done somewhere else.
            if (!preg_match('/target=/is', $returnHtml)) {
                $returnHtml = preg_replace('/href=/is', 'target="' . $hrefTarget . '" href=', $returnHtml);
            }
        }
        if ($returnBody) {
            $specific = $this->getPaymentMethodSpecific($paymentMethod);
            $methodDescription = htmlentities(
                isset($specific->description) &&
                empty($specific->description) ? $specific->description : 'Payment information'
            );
            $returnBodyHtml = '
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>' . $methodDescription . '</title>
            ';
            if (is_null($callCss)) {
                $callCss = 'costofpurchase.css';
            }
            if (!empty($callCss)) {
                if (!is_array($callCss)) {
                    $returnBodyHtml .= '<link rel="stylesheet" media="all" type="text/css" href="' .
                        $callCss .
                        '">' .
                        "\n";
                } else {
                    foreach ($callCss as $cssLink) {
                        $returnBodyHtml .= '<link rel="stylesheet" media="all" type="text/css" href="' .
                            $cssLink .
                            '">' .
                            "\n";
                    }
                }
            }
            $returnBodyHtml .= '
                </head>
                <body>

                ' . $this->getCostHtmlBefore . '
                ' . $returnHtml . '
                ' . $this->getCostHtmlAfter . '

                </body>
                </html>
            ';
            $returnHtml = $returnBodyHtml;
        }

        return $returnHtml;
    }

    /**
     * Fetch one specific payment method only, from Resurs Bank.
     *
     * As of v1.3.41 this method also accept getPayment()-objects as long as it contains a totalAmount and the used
     * paymentMethodId. In that case, it will extract the name from the payment and use it to fetch the used payment
     * method.
     *
     * @param string $specificMethodId Payment method id or a getPayment()-object
     * @return array If not found, array will be empty
     * @throws Exception
     * @since 1.0.0
     * @since 1.1.0
     * @since 1.3.0
     */
    public function getPaymentMethodSpecific($specificMethodId = '')
    {
        if (is_object($specificMethodId) &&
            isset($specificMethodId->totalAmount) &&
            isset($specificMethodId->paymentMethodId)
        ) {
            $specificMethodId = $specificMethodId->paymentMethodId;
        }

        $methods = $this->getPaymentMethods([], true);
        $methodArray = [];
        if (is_array($methods)) {
            foreach ($methods as $objectMethod) {
                if (isset($objectMethod->id) &&
                    !empty($specificMethodId) &&
                    strtolower($objectMethod->id) === strtolower($specificMethodId)
                ) {
                    $methodArray = $objectMethod;
                    break;
                }
            }
        }

        return $methodArray;
    }

    /**
     * Like getCostOfPurchaseHtml but for priceInfo instead (which is located in legalInfoLinks in getPaymentMethods).
     *
     * On multiple methods, the iframe is used by default! If fetch is false and no iframe is requested, this method
     * will instead return the URL directly to the requested.
     *
     * @param string $paymentMethod Payment method as string or object (multiple methods allowed, due to DK).
     * @param int $amount The amount to show the priceInformation with.
     * @param bool $fetch If ecom should try to download the content from the priceinfolink.
     * @param bool $iframe Pushes the priceinfolink into an iframe. Preferred is to have $fetch false here.
     * @param bool $limitByMinMax By default, ecom only shows priceinformation based on the $amount.
     * @param bool $bodyOnly
     * @return false|mixed|string|null
     * @throws ResursException
     * @since 1.3.30
     */
    public function getCostOfPriceInformation(
        $paymentMethod = '',
        $amount = 0,
        $fetch = false,
        $iframe = false,
        $limitByMinMax = true,
        $bodyOnly = false
    ) {
        $return = '';

        if ($iframe) {
            // Anti collider. If iframe is requested, content don't have to be fetched.
            $fetch = false;
        }

        // If the request contains no specified method, an asterisk or an array of methods
        // we presume the payment information should be "tabbed" with many.
        if (empty($paymentMethod) || $paymentMethod === '*' || is_array($paymentMethod)) {
            $template = $this->getTemplatePriceInfoBlocks();
            if (is_array($paymentMethod)) {
                $methodList = $paymentMethod;
            } else {
                $methodList = $this->getPaymentMethods();
            }

            $tab = '';
            $block = '';
            $hasUrls = false;
            foreach ($methodList as $method) {
                if ((
                        $limitByMinMax &&
                        $this->getMinMax($amount, $method->minLimit, $method->maxLimit)
                    ) ||
                    !$limitByMinMax
                ) {
                    $infoObject = $this->getRenderedPriceInfoTemplates($method, $amount, $fetch);
                    if (!empty($infoObject['tabs'])) {
                        $tab .= $infoObject['tabs'];
                        $block .= $infoObject['block'];
                        $hasUrls = true;
                    }
                }
            }

            if ($hasUrls) {
                $vars = [
                    'priceInfoTabs' => $tab,
                    'priceInfoBlocks' => $block,
                    'bodyOnly' => $bodyOnly,
                ];

                $return = $this->getHtmlTemplate($template['costofpriceinfo'], $vars);
            }
        } else {
            if (is_string($paymentMethod)) {
                $paymentMethod = $this->getPaymentMethodSpecific($paymentMethod);
                if (!isset($paymentMethod->minLimit)) {
                    throw new ResursException(
                        sprintf(
                            '%s exception: Payment method does not support limits!',
                            __FUNCTION__
                        ),
                        400
                    );
                }
            }

            if ((
                    $limitByMinMax &&
                    $this->getMinMax($amount, $paymentMethod->minLimit, $paymentMethod->maxLimit)
                ) ||
                !$limitByMinMax
            ) {
                $return = $this->getPriceInformationUrl($amount, $paymentMethod);
                $infoObject = $this->getRenderedPriceInfoTemplates($paymentMethod, $amount, $fetch, $iframe);

                if ($fetch && !empty($return)) {
                    $curlRequest = $this->CURL->request(sprintf('%s%s', $return, $amount));
                    if (!empty($curlRequest)) {
                        $return = $this->CURL->getBody();
                    }
                } else {
                    if ($iframe) {
                        $return = $infoObject['block'];
                    }
                }
            }
        }

        return $return;
    }

    /**
     * @return array
     * @since 1.3.30
     */
    private function getTemplatePriceInfoBlocks()
    {
        // costofpriceinfo - entire tab block
        // priceinfotab - each clickable tab
        // priceinfoblock - html content of priceinfo (Wants $methodHtml)
        $templates = [
            'costofpriceinfo',
            'priceinfotab',
            'priceinfoblock',
        ];

        $template = [];
        // Prepare template files
        foreach ($templates as $htmlFile) {
            $template[$htmlFile] = $htmlFile;
        }
        return $template;
    }

    /**
     * If payment amount is within allowed limits of payment method
     *
     * @param $totalAmount
     * @param $min
     * @param $max
     * @return bool
     */
    public function getMinMax($totalAmount, $min, $max)
    {
        $return = false;
        if ($totalAmount >= $min && $totalAmount <= $max) {
            $return = true;
        }

        return $return;
    }

    /**
     * @param $method
     * @param $amount
     * @param bool $fetch
     * @param bool $iframe
     * @return array
     * @throws Exception
     * @since 1.3.30
     */
    private function getRenderedPriceInfoTemplates($method, $amount, $fetch = false, $iframe = false)
    {
        $return = [
            'tabs' => '',
            'block' => '',
        ];

        if (is_string($method) && !empty($method)) {
            $method = $this->getPaymentMethodSpecific($method);
        }

        if (!isset($method->id)) {
            return $return;
        }
        $template = $this->getTemplatePriceInfoBlocks();
        $methodUrl = $this->getPriceInformationUrl($amount, $method->id);
        if (!empty($methodUrl)) {
            $priceInfoHtml = '';
            if ($fetch && !$iframe) {
                $curlRequest = $this->CURL->request(sprintf('%s%s', $methodUrl, $amount));
                if (!empty($curlRequest)) {
                    $priceInfoHtml = $this->CURL->getBody();
                }
            }
            $vars = [
                'methodHash' => md5($method->id),
                'methodHtml' => $priceInfoHtml,
                'methodName' => isset($method->description) ? $method->description : $method->id,
                'priceInfoUrl' => $methodUrl,
            ];
            $return['tabs'] .= $this->getHtmlTemplate($template['priceinfotab'], $vars);
            $return['block'] .= $this->getHtmlTemplate($template['priceinfoblock'], $vars);
        }

        return $return;
    }

    /**
     * @param $amount
     * @param $paymentMethod
     * @return string
     * @throws Exception
     * @since 1.3.30
     */
    private function getPriceInformationUrl($amount, $paymentMethod)
    {
        $return = '';

        $urlData = $this->getSekkiUrls($amount, $paymentMethod);
        $finder = ['priceinfo', 'authorizedBankproductId'];

        foreach ($urlData as $urlObj) {
            if (isset($urlObj->url) && isset($urlObj->appendPriceLast)) {
                foreach ($finder as $findWord) {
                    if (preg_match('/' . $findWord . '/', $urlObj->url)) {
                        $return = $urlObj->url;
                        break;
                    }
                }
            }
        }

        return $return;
    }

    /**
     * Update URL's for a payment method to SEKKI-prepared content, if appendPriceLast is set for the method.
     *
     * The request can be sent in three ways (examples):
     *
     *  - Where you have the total amount and a method (Slow, since we need to fetch payment methods live each call,
     *  unless caching is enabled) getSekkiUrls("789.90", "INVOICE")
     *  - Where you have a pre-cached legalInfoLinks (from for example your website). In that case, we're only
     *  appending the amount to the info links getSekkiUrls("789.90", $cachedLegalInfoLinks);
     *  - Where you have a prepared URL. Then we practically do nothing, and we will trust that your URL is correct
     *  when appending the amount.
     *
     * @param int $totalAmount
     * @param array|string $paymentMethodID If paymentMethodID is set as string, we'll try to look up the links
     * @param string $URL
     *
     * @return array|string array if the whole method are requested, string if URL is already prepared as last parameter
     * @throws Exception
     * @since 1.0.0
     * @since 1.1.0
     */
    public function getSekkiUrls($totalAmount = 0, $paymentMethodID = [], $URL = '')
    {
        if (!empty($URL)) {
            return $this->priceAppender($URL, $totalAmount);
        }
        $currentLegalUrls = [];
        // If not an array (string) or array but empty
        if ((!is_array($paymentMethodID)) || (is_array($paymentMethodID) && !count($paymentMethodID))) {
            $methods = $this->getPaymentMethods();
            foreach ($methods as $methodArray) {
                if (isset($methodArray->id)) {
                    $methodId = $methodArray->id;
                    if (isset($methodArray->legalInfoLinks)) {
                        $linkCount = 0;
                        foreach ($methodArray->legalInfoLinks as $legalInfoLinkId => $legalInfoArray) {
                            if (isset($legalInfoArray->appendPriceLast) && ($legalInfoArray->appendPriceLast === true)
                            ) {
                                $appendPriceLast = true;
                            } else {
                                $appendPriceLast = false;
                            }
                            if (isset($this->alwaysAppendPriceLast) && $this->alwaysAppendPriceLast) {
                                $appendPriceLast = true;
                            }
                            $currentLegalUrls[$methodId][$linkCount] = $legalInfoArray;
                            if ($appendPriceLast) {
                                /* Append only amounts higher than 0 */
                                $currentLegalUrls[$methodId][$linkCount]->url = $this->priceAppender(
                                    $currentLegalUrls[$methodId][$linkCount]->url,
                                    ($totalAmount > 0 ? $totalAmount : '')
                                );
                            }
                            $linkCount++;
                        }
                    }
                }
            }
            if (!empty($paymentMethodID)) {
                if (is_object($paymentMethodID)) {
                    // Extract the id when payment method data is returned as a final object.
                    $paymentMethodID = $paymentMethodID->id;
                }
                if (is_string($paymentMethodID) && isset($currentLegalUrls[$paymentMethodID])) {
                    return $currentLegalUrls[$paymentMethodID];
                }
                return []; // Nothing.
            } else {
                return $currentLegalUrls;
            }
        } else {
            $linkCount = 0;
            foreach ($paymentMethodID as $legalInfoLinkId => $legalInfoArray) {
                if (isset($legalInfoArray->appendPriceLast) && ($legalInfoArray->appendPriceLast === true)) {
                    $appendPriceLast = true;
                } else {
                    $appendPriceLast = false;
                }
                $currentLegalUrls[$linkCount] = $legalInfoArray;
                if ($appendPriceLast) {
                    $currentLegalUrls[$linkCount]->url = $this->priceAppender(
                        $currentLegalUrls[$linkCount]->url,
                        ($totalAmount > 0 ? $totalAmount : '')
                    );
                }
                $linkCount++;
            }
        }

        return $currentLegalUrls;
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
    private function priceAppender($URL = '', $Amount = 0, $Parameter = 'amount')
    {
        if (isset($this->priceAppenderParameter) && !empty($this->priceAppenderParameter)) {
            $Parameter = $this->priceAppenderParameter;
        }
        if (preg_match('/=$/', $URL)) {
            return $URL . $Amount;
        } else {
            return $URL . '&' . $Parameter . '=' . $Amount;
        }
    }

    /**
     * @param $templateName
     * @param bool $isHtml
     * @param array $assignedVariables
     * @return false|string
     * @since 1.3.30
     */
    private function getHtmlTemplate($templateName, $isHtml = false, $assignedVariables = [])
    {
        $extension = 'php';

        if (is_object($isHtml) || is_array($isHtml)) {
            $assignedVariables = $isHtml;
        } else {
            if ($isHtml) {
                $extension = 'html';
            }
        }
        foreach ($assignedVariables as $key => $value) {
            if (preg_match('/^\$/', $key)) {
                $key = substr($key, 1);
            }
            ${$key} = $value;
        }
        $templateFile = sprintf('%s/%s.%s', __DIR__ . '/../templates', $templateName, $extension);
        if (file_exists($templateFile)) {
            ob_start();
            /** @noinspection PhpIncludeInspection */
            @include($templateFile);
            $templateHtml = ob_get_clean();
        } else {
            $templateHtml = 'Not a valid page.';
        }
        return $templateHtml;
    }

    /**
     * @param string $htmlData
     * @return $this
     * @since 1.4.0
     * @deprecated Not spelled correctly. Use the proper one.
     */
    public function setCostOfPurcaseHtmlBefore($htmlData = '')
    {
        return $this->setCostOfPurchaseHtmlBefore($htmlData);
    }

    /**
     * While generating a getCostOfPurchase where $returnBody is true, this function adds custom html before the
     * returned html-code from Resurs Bank
     *
     * @param string $htmlData
     * @return ResursBank
     * @since 1.1.0
     * @since 1.4.0
     * @since 1.0.0
     */
    public function setCostOfPurchaseHtmlBefore($htmlData = '')
    {
        $this->getCostHtmlBefore = $htmlData;
        return $this;
    }

    /**
     * @param string $htmlData
     * @return $this
     * @deprecated Not spelled correctly. Use the proper one.
     */
    public function setCostOfPurcaseHtmlAfter($htmlData = '')
    {
        return $this->setCostOfPurchaseHtmlAfter($htmlData);
    }

    /**
     * While generating a getCostOfPurchase where $returnBody is true, this function adds custom html after the
     * returned html-code from Resurs Bank
     *
     * @param string $htmlData
     * @return ResursBank
     * @since 1.1.0
     * @since 1.4.0
     * @since 1.0.0
     */
    public function setCostOfPurchaseHtmlAfter($htmlData = '')
    {
        $this->getCostHtmlAfter = $htmlData;
        return $this;
    }

    /**
     * If you prefer to fetch anything that looks like a proxy if it mismatches to the REMOTE_ADDR, activate this
     * (EXPERIMENTAL!!)
     *
     * @param bool $activated
     *
     * @since 1.0.2
     * @since 1.1.2
     */
    public function setCustomerIpProxy($activated = false)
    {
        $this->preferCustomerProxy = $activated;
    }

    /**
     * Quantity Recalculation of a getPayment. Also supports arrays.
     *
     * When ECom gets an article row that needs recalculation on quantity level, this method can be used.
     * Note: The article row needs to be "completed" from the start, to get a successful recalculation.
     * By means, you need a prepared "proper" payment already, where you only need to adjust the quantity
     * values.
     *
     * @param array|stdClass $artObject
     * @param int $quantity
     *
     * @return mixed
     * @throws ResursException
     * @since 1.3.20
     * @since 1.0.47
     * @since 1.1.47
     */
    public function getRecalculatedQuantity($artObject, $quantity = -1)
    {
        // If no quantity passed into this method, try to reuse quantity in the object.
        if ($quantity === -1) {
            if (isset($artObject->quantity)) {
                $quantity = $artObject->quantity;
            } elseif (isset($artObject['quantity'])) {
                $quantity = $artObject['quantity'];
            } else {
                throw new ResursException(
                    'A valid quantity is required for this recalculation.',
                    RESURS_EXCEPTIONS::INTERNAL_QUANTITY_EXCEPTION
                );
            }
        }

        if (is_object($artObject) &&
            isset($artObject->unitAmountWithoutVat) &&
            isset($artObject->vatPct) &&
            isset($artObject->totalVatAmount) &&
            isset($artObject->totalAmount)
        ) {
            $artObject->totalVatAmount = $this->getTotalVatAmount(
                $artObject->unitAmountWithoutVat,
                $artObject->vatPct,
                $quantity
            );
            $artObject->totalAmount = $this->getTotalAmount(
                $artObject->unitAmountWithoutVat,
                $artObject->vatPct,
                $quantity
            );
            $artObject->quantity = $quantity;
        }

        // If this one arrives as an array, handle this also.
        if (is_array($artObject) &&
            isset($artObject['unitAmountWithoutVat']) &&
            isset($artObject['vatPct']) &&
            isset($artObject['totalVatAmount']) &&
            isset($artObject['totalAmount'])
        ) {
            $artObject['totalVatAmount'] = $this->getTotalVatAmount(
                $artObject['unitAmountWithoutVat'],
                $artObject['vatPct'],
                $quantity
            );
            $artObject['totalAmount'] = $this->getTotalAmount(
                $artObject['unitAmountWithoutVat'],
                $artObject['vatPct'],
                $quantity
            );
            $artObject['quantity'] = $quantity;
        }

        return $artObject;
    }

    /**
     * Lazy calculation of total amounts (make sure the VAT is an integer and not a decimal value).
     *
     * @param float $unitAmountWithoutVat
     * @param int $vatPct
     * @param int $quantity
     * @return float|int
     * @since 1.3.20
     * @since 1.0.47
     * @since 1.1.47
     */
    public function getTotalVatAmount($unitAmountWithoutVat, $vatPct, $quantity)
    {
        return ($unitAmountWithoutVat * $vatPct / 100) * $quantity;
    }

    /**
     * Lazy calculation of total amounts (make sure the VAT is an integer and not a decimal value).
     *
     * @param $unitAmountWithoutVat
     * @param $vatPct
     * @param $quantity
     * @return float|int
     * @since 1.3.20
     * @since 1.0.47
     * @since 1.1.47
     */
    public function getTotalAmount($unitAmountWithoutVat, $vatPct, $quantity)
    {
        return ($unitAmountWithoutVat + ($unitAmountWithoutVat * $vatPct / 100)) * $quantity;
    }

    /**
     * @return string
     * @since 1.3.23
     */
    public function getRealClientName()
    {
        return $this->realClientName;
    }

    /**
     * @param $clientName
     * @since 1.3.23
     */
    public function setRealClientName($clientName)
    {
        $this->userSetClientName = true;
        $this->realClientName = $clientName;
    }

    /**
     * Set a logged in username (will be merged with the client name at aftershopFlow-level)
     *
     * @param string $currentUsername
     *
     * @since 1.0.0
     * @since 1.1.0
     */
    public function setLoggedInUser($currentUsername = '')
    {
        $this->loggedInUser = $currentUsername;
    }

    /////////// OTHER BEHAVIOUR (AS HELPERS, MISCELLANEOUS)

    /**
     * Set an initial shop url to use with Resurs Checkout
     * If this is not set, EComPHP will handle the shopUrl automatically.
     * It is also possible to handle this through the manual payload as always.
     *
     * @param string $shopUrl
     * @param bool $validateFormat Activate URL validation
     * @throws Exception
     * @since 1.0.4
     * @since 1.1.4
     */
    public function setShopUrl($shopUrl = '', $validateFormat = true)
    {
        $this->InitializeServices();
        if (!empty($shopUrl)) {
            $this->checkoutShopUrl = $shopUrl;
        }
        if ($validateFormat) {
            $this->isNetWork();
            $shopUrlValidate = $this->NETWORK->getUrlDomain($this->checkoutShopUrl);
            $this->checkoutShopUrl = $shopUrlValidate[1] . '://' . $shopUrlValidate[0];
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
    public function setValidateCheckoutShopUrl($validateEnabled = true)
    {
        $this->validateCheckoutShopUrl = $validateEnabled;
    }

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
    public function setFormTemplateRules($customerType, $methodType, $fieldArray)
    {
        /** @noinspection PhpDeprecationInspection */
        return $this->E_DEPRECATED->setFormTemplateRules($customerType, $methodType, $fieldArray);
    }

    /**
     * Get regular expression ruleset for a specific payment form field.
     * If no form field name are given, all the fields are returned for a specific payment method.
     * Parameters are case insensitive.
     *
     * @param string $formFieldName
     * @param $countryCode
     * @param $customerType
     * @return array
     * @throws Exception
     * @deprecated 1.0.8 Build your own integration please
     * @deprecated 1.1.8 Build your own integration please
     */
    public function getRegEx($formFieldName = '', $countryCode = '', $customerType = 'NATURAL')
    {
        /** @noinspection PhpDeprecationInspection */
        return $this->E_DEPRECATED->getRegEx($formFieldName, $countryCode, $customerType);
    }

    /**
     * Returns a true/false for a specific form field value depending on the response created by
     * getTemplateFieldsByMethodType.
     *
     * This function is a part of Resurs Bank streamline support and actually defines the recommended value whether the
     * field should try propagate it's data from the current store values or not. Doing this, you may be able to hide
     * form fields that already exists in the store, so the customer does not need to enter the values twice.
     *
     * @param string $formField The field you want to test
     * @param bool $canThrow Make the function throw an exception instead of silently return false if
     *                          getTemplateFieldsByMethodType has not been run yet
     *
     * @return bool Returns false if you should NOT hide the field
     * @throws Exception
     * @deprecated 1.0.8 Build your own integration please
     * @deprecated 1.1.8 Build your own integration please
     */
    public function canHideFormField($formField = '', $canThrow = false)
    {
        /** @noinspection PhpDeprecationInspection */
        return $this->E_DEPRECATED->canHideFormField($formField, $canThrow);
    }

    /**
     * Get field set rules for web-forms
     *
     * $paymentMethodType can be both a string or a object. If it is a object, the function will handle the incoming
     * data as it is the complete payment method configuration (meaning, data may be cached). In this case, it will
     * take care of the types in the method itself. If it is a string, it will handle the data as the configuration has
     * already been solved out.
     *
     * When building forms for a web shop, a specific number of fields are required to show on screen. This function
     * brings the right fields automatically. The deprecated flow generates form fields and returns them to the shop
     * owner platform, with the form fields that is required for the placing an order. It also returns a bunch of
     * regular expressions that is used to validate that the fields is correctly filled in. This function partially
     * emulates that flow, so the only thing a integrating developer needs to take care of is the html code itself.
     *
     * @link https://test.resurs.com/docs/x/s4A0 Regular expressions
     * @param string|array $paymentMethodName
     * @param string $customerType
     * @param string $specificType
     * @return array
     * @deprecated 1.0.8 Build your own integration please
     * @deprecated 1.1.8 Build your own integration please
     */
    public function getTemplateFieldsByMethodType($paymentMethodName = '', $customerType = '', $specificType = '')
    {
        /** @noinspection PhpDeprecationInspection */
        return $this->E_DEPRECATED->getTemplateFieldsByMethodType($paymentMethodName, $customerType, $specificType);
    }

    /**
     * Defines if we are allowed to skip government id validation. Payment provider methods
     * normally does this when running in simplified mode. In other cases, validation will be
     * handled by Resurs Bank and this setting should not be affected by this
     *
     * @return bool
     */
    public function getCanSkipGovernmentIdValidation()
    {
        return $this->E_DEPRECATED->getCanSkipGovernmentIdValidation();
    }


    ////// Client specific

    /**
     * Get template fields by a specific payment method. This function retrieves the payment method in real time.
     *
     * @param string $paymentMethodName
     *
     * @return array
     * @throws Exception
     * @deprecated 1.0.8 Build your own integration please
     * @deprecated 1.1.8 Build your own integration please
     */
    public function getTemplateFieldsByMethod($paymentMethodName = '')
    {
        /** @noinspection PhpDeprecationInspection */
        return $this->E_DEPRECATED->getTemplateFieldsByMethodType($this->getPaymentMethodSpecific($paymentMethodName));
    }

    /**
     * Get form fields by a specific payment method. This function retrieves the payment method in real time.
     *
     * @param string $paymentMethodName
     *
     * @return array
     * @throws Exception
     * @deprecated 1.0.8 Build your own integration please
     * @deprecated 1.1.8 Build your own integration please
     */
    public function getFormFieldsByMethod($paymentMethodName = '')
    {
        /** @noinspection PhpDeprecationInspection */
        return $this->E_DEPRECATED->getTemplateFieldsByMethod($paymentMethodName);
    }

    /**
     * Set your own order reference instead of taking the randomized one
     *
     * @param $myPreferredId
     *
     * @since 1.0.2
     * @since 1.1.2
     */
    public function setPreferredId($myPreferredId)
    {
        $this->preferredId = $myPreferredId;
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
     * @throws Exception
     * @since 1.0.2
     * @since 1.1.2
     * @since 1.2.0
     */
    public function addOrderLine(
        $articleNumberOrId = '',
        $description = '',
        $unitAmountWithoutVat = 0,
        $vatPct = 0,
        $unitMeasure = 'st',
        $articleType = 'ORDER_LINE',
        $quantity = 1
    ) {
        $this->specLineCustomization = true;

        if (!is_array($this->SpecLines)) {
            $this->SpecLines = [];
        }

        if (is_null($articleType)) {
            $articleType = 'ORDER_LINE';
        }

        // Simplified:
        //   id, artNo, description, quantity, unitMeasure, unitAmountWithoutVat, vatPct, totalVatAmount, totalAmount
        // Hosted:
        //   artNo, description, quantity, unitMeasure, unitAmountWithoutVat, vatPct, totalVatAmount, totalAmount
        // Checkout:
        //   artNo, description, quantity, unitMeasure, unitAmountWithoutVat, vatPct, type

        $duplicateArticle = false;
        foreach ($this->SpecLines as $specIndex => $specRow) {
            if ($specRow['artNo'] == $articleNumberOrId && $specRow['unitAmountWithoutVat'] == $unitAmountWithoutVat) {
                $duplicateArticle = true;
                $this->SpecLines[$specIndex]['quantity'] += $quantity;
            }
        }
        if (!$duplicateArticle) {
            $specData = [
                'artNo' => $articleNumberOrId,
                'description' => $description,
                'quantity' => $quantity,
                'unitMeasure' => $unitMeasure,
                'unitAmountWithoutVat' => $unitAmountWithoutVat,
                'vatPct' => $vatPct,
                'type' => !empty($articleType) ? $articleType : '',
            ];
            $newSpecData = $this->event('ecom_article_data', $specData);
            if (!is_null($newSpecData) && is_array($newSpecData)) {
                $specData = $newSpecData;
            }
            $this->SpecLines[] = $specData;
        }
        $this->renderPaymentSpec();
    }

    /**
     * @param $eventName
     *
     * @return mixed|null
     * @since 1.0.36
     * @since 1.1.36
     * @since 1.3.9
     */
    private function event($eventName)
    {
        $args = func_get_args();
        $value = null;

        if (function_exists('ecom_event_run')) {
            $value = ecom_event_run($eventName, $args);
        }

        return $value;
    }

    /**
     * Payment Spec Renderer
     *
     * @param int $overrideFlow
     *
     * @return mixed
     * @throws Exception
     * @since 1.0.2
     * @since 1.1.2
     */
    private function renderPaymentSpec($overrideFlow = RESURS_FLOW_TYPES::NOT_SET)
    {
        $myFlow = $this->getPreferredPaymentFlowService();
        if ($overrideFlow !== RESURS_FLOW_TYPES::NOT_SET) {
            $myFlow = $overrideFlow;
        }
        $paymentSpec = [];
        if (is_array($this->SpecLines) && count($this->SpecLines)) {
            // Try correctify speclines that have been merged in the wrong way
            if (isset($this->SpecLines['artNo'])) {
                $this->SpecLines = [
                    $this->SpecLines,
                ];
            }
            foreach ($this->SpecLines as $specIndex => $specRow) {
                if (is_array($specRow)) {
                    if (!isset($specRow['unitMeasure']) ||
                        (isset($specRow['unitMeasure']) && empty($specRow['unitMeasure']))
                    ) {
                        $this->SpecLines[$specIndex]['unitMeasure'] = $this->defaultUnitMeasure;
                    }
                }
                if ($myFlow === RESURS_FLOW_TYPES::SIMPLIFIED_FLOW) {
                    $this->SpecLines[$specIndex]['id'] = ($specIndex) + 1;
                }
                if ($myFlow === RESURS_FLOW_TYPES::HOSTED_FLOW || $myFlow === RESURS_FLOW_TYPES::SIMPLIFIED_FLOW) {
                    if ($this->isFlag('ALWAYS_RENDER_TOTALS') && isset($specRow['totalVatAmount'])) {
                        // Always recalculate amounts regardless of duplication
                        unset($specRow['totalVatAmount']);
                    }
                    if (!isset($specRow['totalVatAmount'])) {
                        // Always recalculate amounts by its quantity in case there has been changes like
                        // duplicate articles during orderline handling.
                        $this->SpecLines[$specIndex]['totalVatAmount'] = (
                                $specRow['unitAmountWithoutVat'] * $specRow['vatPct'] / 100
                            ) * $specRow['quantity'];
                        $this->SpecLines[$specIndex]['totalAmount'] = (
                                $specRow['unitAmountWithoutVat'] + (
                                    $specRow['unitAmountWithoutVat'] * $specRow['vatPct'] / 100
                                )
                            ) * $specRow['quantity'];
                    }
                    if (!isset($paymentSpec['totalAmount'])) {
                        $paymentSpec['totalAmount'] = 0;
                    }
                    if (!isset($paymentSpec['totalVatAmount'])) {
                        $paymentSpec['totalVatAmount'] = 0;
                    }
                    $paymentSpec['totalAmount'] += $this->SpecLines[$specIndex]['totalAmount'];
                    $paymentSpec['totalVatAmount'] += $this->SpecLines[$specIndex]['totalVatAmount'];
                }
            }
            if ($myFlow === RESURS_FLOW_TYPES::SIMPLIFIED_FLOW) {
                // Do not forget to pass over $myFlow-overriders to sanitizer as it might be sent from
                // additionalDebitOfPayment rather than a regular bookPayment sometimes
                $this->Payload['orderData'] = [
                    'specLines' => $this->sanitizePaymentSpec($this->SpecLines, $myFlow),
                    'totalAmount' => $paymentSpec['totalAmount'],
                    'totalVatAmount' => $paymentSpec['totalVatAmount'],
                ];
            }
            if ($myFlow === RESURS_FLOW_TYPES::HOSTED_FLOW) {
                // Do not forget to pass over $myFlow-overriders to sanitizer as it might be sent from
                // additionalDebitOfPayment rather than a regular bookPayment sometimes
                $this->Payload['orderData'] = [
                    'orderLines' => $this->sanitizePaymentSpec($this->SpecLines, $myFlow),
                    'totalAmount' => $paymentSpec['totalAmount'],
                    'totalVatAmount' => $paymentSpec['totalVatAmount'],
                ];
            }
            if ($myFlow == RESURS_FLOW_TYPES::RESURS_CHECKOUT) {
                // Do not forget to pass over $myFlow-overriders to sanitizer as it might be sent from
                // additionalDebitOfPayment rather than a regular bookPayment sometimes
                $this->Payload['orderLines'] = $this->sanitizePaymentSpec($this->SpecLines, $myFlow);
            }
        } else {
            // If there are no array for the speclines yet, check if we could update one from the payload
            if (isset($this->Payload['orderLines']) && is_array($this->Payload['orderLines'])) {
                // Do not forget to pass over $myFlow-overriders to sanitizer as it might be sent from
                // additionalDebitOfPayment rather than a regular bookPayment sometimes
                $this->Payload['orderLines'] = $this->sanitizePaymentSpec($this->Payload['orderLines'], $myFlow);
                $this->SpecLines = $this->Payload['orderLines'];
            }
        }

        return $this->Payload;
    }

    /**
     * Make sure that the payment spec only contains the data that each payment flow needs.
     *
     * This function has been created for keeping backwards compatibility from older payment spec renderers. EComPHP is
     * allowing same content in the payment spec for all flows, so to keep this steady, this part of EComPHP will
     * sanitize each spec so it only contains data that it really needs when push out the payload to ecommerce.
     *
     * @param array $specLines
     * @param int $myFlowOverrider
     *
     * @return array
     * @throws Exception
     * @since 1.0.4
     * @since 1.1.4
     */
    public function sanitizePaymentSpec($specLines = [], $myFlowOverrider = RESURS_FLOW_TYPES::NOT_SET)
    {
        $paymentSpecKeys = $this->getPaymentSpecKeyScheme();
        if (is_array($specLines)) {
            $myFlow = $this->getPreferredPaymentFlowService();
            if ($myFlowOverrider !== RESURS_FLOW_TYPES::NOT_SET) {
                $myFlow = $myFlowOverrider;
            }
            $mySpecRules = [];
            if ($myFlow == RESURS_FLOW_TYPES::SIMPLIFIED_FLOW) {
                $mySpecRules = $paymentSpecKeys['simplified'];
            } elseif ($myFlow == RESURS_FLOW_TYPES::HOSTED_FLOW) {
                $mySpecRules = $paymentSpecKeys['hosted'];
            } elseif ($myFlow == RESURS_FLOW_TYPES::RESURS_CHECKOUT) {
                $mySpecRules = $paymentSpecKeys['checkout'];
            } elseif ($myFlow == RESURS_FLOW_TYPES::MINIMALISTIC) {
                $mySpecRules = $paymentSpecKeys['minimalistic'];
            }
            foreach ($specLines as $specIndex => $specArray) {
                foreach ($specArray as $key => $value) {
                    if (!in_array(strtolower($key), array_map('strtolower', $mySpecRules))) {
                        unset($specArray[$key]);
                    }
                }
                if ($myFlow !== RESURS_FLOW_TYPES::MINIMALISTIC) {
                    // Reaching this point, realizing the value IS really there and should not be overwritten...
                    if (!isset($specArray['unitMeasure']) || empty($specArray['unitMeasure'])) {
                        $specArray['unitMeasure'] = $this->defaultUnitMeasure;
                    }
                }
                $specLines[$specIndex] = $specArray;
            }
        }

        return $specLines;
    }

    /**
     * Get paymentSpec key scheme.
     *
     * @param string $key checkout, hosted, simplified, minimalistic, tiny (tiny=what Resurs normally use as keying).
     * @param bool $throwOnFaultyKey
     * @return array
     * @throws Exception
     * @since 1.3.23
     */
    public function getPaymentSpecKeyScheme($key = '', $throwOnFaultyKey = false)
    {
        $return = [
            'checkout' => [
                'artNo',
                'description',
                'quantity',
                'unitMeasure',
                'unitAmountWithoutVat',
                'vatPct',
                'type',
            ],
            'hosted' => [
                'artNo',
                'description',
                'quantity',
                'unitMeasure',
                'unitAmountWithoutVat',
                'vatPct',
                'totalVatAmount',
                'totalAmount',
            ],
            'simplified' => [
                'id',
                'artNo',
                'description',
                'quantity',
                'unitMeasure',
                'unitAmountWithoutVat',
                'vatPct',
                'totalVatAmount',
                'totalAmount',
            ],
            'minimalistic' => [
                'artNo',
                'description',
                'unitAmountWithoutVat',
                'quantity',
            ],
            'tiny' => [
                'artNo',
                'description',
                'unitAMountWithoutVat',
            ],
        ];

        if (!empty($key) && isset($return[$key])) {
            return $return[$key];
        }

        if ($throwOnFaultyKey && !isset($return[$key])) {
            throw new Exception('No such paymentSpec key scheme.', 500);
        }

        return $return;
    }

    /**
     * The new payment creation function (replaces bookPayment)
     *
     * For EComPHP 1.0.2 there is no need for any object conversion (or external parameters). Most of the parameters is
     * about which preferred payment flow that is used, which should be set with the function
     * setPreferredPaymentFlowService() instead. If no preferred are set, we will fall back to the simplified flow.
     *
     * @param string $payment_id_or_method For ResursCheckout the payment id are preferred before the payment method
     * @param array $payload If there are any extra (or full) payload for the chosen payment, it should be placed here
     *
     * @return array
     * @throws Exception
     * @since 1.1.2
     * @since 1.0.2
     */
    public function createPayment($payment_id_or_method = '', $payload = [])
    {
        if (!$this->hasServicesInitialization) {
            $this->InitializeServices();
        }
        $myFlow = $this->getPreferredPaymentFlowService();
        try {
            if ($myFlow !== RESURS_FLOW_TYPES::RESURS_CHECKOUT) {
                $this->desiredPaymentMethod = $payment_id_or_method;
                $paymentMethodInfo = $this->getPaymentMethodSpecific($payment_id_or_method);
                if (isset($paymentMethodInfo->type) && $paymentMethodInfo->type === 'PAYMENT_PROVIDER') {
                    $this->paymentMethodIsPsp = true;
                }
                if (isset($paymentMethodInfo->id)) {
                    $this->PaymentMethod = $paymentMethodInfo;
                }
            }
        } catch (Exception $e) {
        }
        $this->preparePayload($payment_id_or_method, $payload);
        if ($this->paymentMethodIsPsp) {
            $this->clearPspCustomerPayload();
        }

        if ($this->forceExecute) {
            $this->createPaymentExecuteCommand = $payment_id_or_method;

            return ['status' => 'delayed'];
        } else {
            $bookPaymentResult = $this->createPaymentExecute($payment_id_or_method);
        }

        return $bookPaymentResult;
    }

    /////////// LONG LIFE DEPRECATION
    /// Belongs to the deprecated shopFlow emulation, used by the wooCommerce plugin amongst others

    /**
     * Prepare the payload
     *
     * @param string $payment_id_or_method
     * @param array $payload
     *
     * @throws Exception
     * @since 1.0.1
     * @since 1.1.1
     */
    private function preparePayload($payment_id_or_method = '', $payload = [])
    {
        $this->InitializeServices();
        $this->handlePayload($payload);

        $updateStoreIdEvent = $this->event('update_store_id');
        if (!is_null($updateStoreIdEvent)) {
            $this->setStoreId($updateStoreIdEvent);
        }

        if (empty($this->defaultUnitMeasure)) {
            $this->setDefaultUnitMeasure();
        }
        if ($this->getPreferredPaymentFlowService() === RESURS_FLOW_TYPES::NOT_SET) {
            $this->setPreferredPaymentFlowService(RESURS_FLOW_TYPES::SIMPLIFIED_FLOW);
        }

        if ($this->getPreferredPaymentFlowService() === RESURS_FLOW_TYPES::RESURS_CHECKOUT) {
            if (empty($payment_id_or_method) && empty($this->preferredId)) {
                throw new ResursException(
                    'A payment method or payment id must be defined.',
                    RESURS_EXCEPTIONS::CREATEPAYMENT_NO_ID_SET
                );
            }
            $payment_id_or_method = $this->preferredId;
        }
        if (!count($this->Payload) && !$this->isFlag('USE_AFTERSHOP_RENDERING')) {
            throw new ResursException(
                'No payload are set for this payment.',
                RESURS_EXCEPTIONS::BOOKPAYMENT_NO_BOOKDATA
            );
        }

        // Obsolete way to handle multidimensional spec rows.
        if (isset($this->Payload['specLine'])) {
            if (isset($this->Payload['specLine']['artNo'])) {
                $this->SpecLines[] = $this->Payload['specLine'];
            } else {
                if (is_array($this->Payload['specLine'])) {
                    foreach ($this->Payload['specLine'] as $specRow) {
                        $this->SpecLines[] = $specRow;
                    }
                }
            }
            unset($this->Payload['specLine']);
            $this->renderPaymentSpec();
        } elseif (isset($this->Payload['orderLines'])) {
            $this->renderPaymentSpec();
        } elseif (!isset($this->Payload['orderLines']) && count($this->SpecLines)) {
            // Fix desynched order lines.
            $this->Payload['orderLines'] = $this->SpecLines;
            $this->renderPaymentSpec();
        }
        if ($this->getPreferredPaymentFlowService() === RESURS_FLOW_TYPES::HOSTED_FLOW ||
            $this->getPreferredPaymentFlowService() === RESURS_FLOW_TYPES::SIMPLIFIED_FLOW
        ) {
            if (!isset($paymentDataPayload ['paymentData'])) {
                $paymentDataPayload ['paymentData'] = [];
            }
            $paymentDataPayload['paymentData']['paymentMethodId'] = $payment_id_or_method;
            $paymentDataPayload['paymentData']['preferredId'] = $this->getPreferredPaymentId();
            $paymentDataPayload['paymentData']['customerIpAddress'] = $this->getCustomerIp();

            if ($this->getPreferredPaymentFlowService() === RESURS_FLOW_TYPES::SIMPLIFIED_FLOW) {
                if (!isset($this->Payload['storeId']) && !empty($this->storeId)) {
                    $this->Payload['storeId'] = $this->storeId;
                }
            } else {
                // The simplified flag control must run to be backward compatible with older services
                if (isset($this->Payload['paymentData']['waitForFraudControl'])) {
                    $this->Payload['waitForFraudControl'] = $this->Payload['paymentData']['waitForFraudControl'];
                    unset($this->Payload['paymentData']['waitForFraudControl']);
                }
                if (isset($this->Payload['paymentData']['annulIfFrozen'])) {
                    $this->Payload['annulIfFrozen'] = $this->Payload['paymentData']['annulIfFrozen'];
                    unset($this->Payload['paymentData']['annulIfFrozen']);
                }
                if (isset($this->Payload['paymentData']['finalizeIfBooked'])) {
                    $this->Payload['finalizeIfBooked'] = $this->Payload['paymentData']['finalizeIfBooked'];
                    unset($this->Payload['paymentData']['finalizeIfBooked']);
                }
            }
            $this->handlePayload($paymentDataPayload, true);
        }
        if ((
            $this->getPreferredPaymentFlowService() == RESURS_FLOW_TYPES::RESURS_CHECKOUT ||
            $this->getPreferredPaymentFlowService() == RESURS_FLOW_TYPES::HOSTED_FLOW
        )
        ) {
            // Convert signing to checkout urls if exists (not recommended as failUrl might not always be the backUrl)
            // However, those variables will only be replaced in the correct payload if they are not already there.
            if (isset($this->Payload['signing'])) {
                if ($this->getPreferredPaymentFlowService() == RESURS_FLOW_TYPES::HOSTED_FLOW) {
                    if (isset($this->Payload['signing']['forceSigning'])) {
                        $this->Payload['forceSigning'] = $this->Payload['signing']['forceSigning'];
                    }
                    if (!isset($this->Payload['failUrl']) && isset($this->Payload['signing']['failUrl'])) {
                        $this->Payload['failUrl'] = $this->Payload['signing']['failUrl'];
                    }
                    if (!isset($this->Payload['backUrl']) && isset($this->Payload['signing']['backUrl'])) {
                        $this->Payload['backUrl'] = $this->Payload['signing']['backUrl'];
                    }
                }
                if (!isset($this->Payload['successUrl']) && isset($this->Payload['signing']['successUrl'])) {
                    $this->Payload['successUrl'] = $this->Payload['signing']['successUrl'];
                }
                if (!isset($this->Payload['backUrl']) && isset($this->Payload['signing']['failUrl'])) {
                    $this->Payload['backUrl'] = $this->Payload['signing']['failUrl'];
                }
                unset($this->Payload['signing']);
            }
            // Rules for customer only applies to checkout. As this also involves the hosted flow (see above) this
            // must only specifically occur on the checkout
            if ($this->getPreferredPaymentFlowService() == RESURS_FLOW_TYPES::RESURS_CHECKOUT) {
                if (!isset($this->Payload['storeId']) && !empty($this->storeId)) {
                    $this->Payload['storeId'] = $this->storeId;
                }
                if (isset($this->Payload['paymentData'])) {
                    unset($this->Payload['paymentData']);
                }

                // By not removing fields on this kind of exception means that we need to protect the customer object.
                if (isset($this->Payload['customer']['address'])) {
                    $this->checkoutCustomerFieldSupport = true;
                }

                if (isset($this->Payload['customer']['deliveryAddress'])) {
                    $this->checkoutCustomerFieldSupport = true;
                }

                if ($this->checkoutCustomerFieldSupport === false && isset($this->Payload['customer'])) {
                    unset($this->Payload['customer']);
                }

                // Making sure sloppy developers uses shopUrl properly.
                if (!isset($this->Payload['shopUrl'])) {
                    if ($this->validateCheckoutShopUrl) {
                        $shopUrlValidate = $this->NETWORK->getUrlDomain($this->checkoutShopUrl);
                        $this->checkoutShopUrl = $shopUrlValidate[1] . '://' . $shopUrlValidate[0];
                    }
                    $this->Payload['shopUrl'] = $this->checkoutShopUrl;
                }
            }
        }
        // If card data has been included in the payload, make sure that the card data is validated if the payload
        // has been sent by manual hands (deprecated mode)
        if (isset($this->Payload['card'])) {
            /** @noinspection PhpUndefinedFieldInspection */
            if (isset($this->PaymentMethod->specificType)) {
                /** @noinspection PhpUndefinedFieldInspection */
                $this->validateCardData($this->PaymentMethod->specificType);
            }
        }

        $eventReturns = $this->event('update_payload', $this->Payload);
        if (!is_null($eventReturns)) {
            $this->Payload = $eventReturns;
        }
    }

    /**
     * Compile user defined payload with payload that may have been pre-set by other calls
     *
     * @param array $userDefinedPayload
     * @param bool $replacePayload Allow replacements of old payload data
     * @throws Exception
     * @since 1.0.2
     * @since 1.1.2
     */
    private function handlePayload($userDefinedPayload = [], $replacePayload = false)
    {
        $myFlow = $this->getPreferredPaymentFlowService();
        if (is_array($userDefinedPayload) && count($userDefinedPayload)) {
            foreach ($userDefinedPayload as $payloadKey => $payloadContent) {
                if (!isset($this->Payload[$payloadKey]) && !$replacePayload) {
                    $this->Payload[$payloadKey] = $payloadContent;
                } else {
                    // If the payload key already exists, there might be something that wants to share information.
                    // In this case, append more data to the children
                    if (is_array($userDefinedPayload[$payloadKey])) {
                        foreach ($userDefinedPayload[$payloadKey] as $subKey => $subValue) {
                            if (!isset($this->Payload[$payloadKey][$subKey])) {
                                $this->Payload[$payloadKey][$subKey] = $subValue;
                            } elseif ($replacePayload) {
                                $this->Payload[$payloadKey][$subKey] = $subValue;
                            }
                        }
                    } else {
                        if (!isset($this->Payload[$payloadKey])) {
                            $this->Payload[$payloadKey] = $payloadContent;
                        }
                    }
                }
            }
        }
        // Address and deliveryAddress should move to the correct location
        if (isset($this->Payload['address'])) {
            $this->Payload['customer']['address'] = $this->Payload['address'];
            if ($myFlow == RESURS_FLOW_TYPES::HOSTED_FLOW && isset($this->Payload['customer']['address']['country'])) {
                $this->Payload['customer']['address']['countryCode'] = $this->Payload['customer']['address']['country'];
            }
            unset($this->Payload['address']);
        }
        if (isset($this->Payload['deliveryAddress'])) {
            $this->Payload['customer']['deliveryAddress'] = $this->Payload['deliveryAddress'];
            if ($myFlow == RESURS_FLOW_TYPES::HOSTED_FLOW &&
                isset($this->Payload['customer']['deliveryAddress']['country'])
            ) {
                $this->Payload['customer']['deliveryAddress']['countryCode'] =
                    $this->Payload['customer']['deliveryAddress']['country'];
            }
            unset($this->Payload['deliveryAddress']);
        }
        if (isset($this->Payload['customer'])) {
            $noCustomerType = false;
            if ((
                !isset($this->Payload['customer']['type'])) ||
                isset($this->Payload['customer']['type']) &&
                empty($this->Payload['customer']['type'])
            ) {
                $noCustomerType = true;
            }
            if ($noCustomerType) {
                if (!empty($this->desiredPaymentMethod)) {
                    $paymentMethodInfo = $this->getPaymentMethodSpecific($this->desiredPaymentMethod);
                    if (isset($paymentMethodInfo->customerType)) {
                        if (!is_array($paymentMethodInfo->customerType) && !empty($paymentMethodInfo->customerType)) {
                            $this->Payload['customer']['type'] = $paymentMethodInfo->customerType;
                        } else {
                            // At this stage, we have no idea of which customer type it is about, so we will fail over
                            // to NATURAL when it is not set by the customer itself. We could do a getAddress here, but
                            // that may not be safe enough to decide customer types automatically. Also, it is in for
                            // example hosted flow not even necessary to enter a government id here.
                            // Besides this? It lowers the performance of the actions.
                            $this->Payload['customer']['type'] = 'NATURAL';
                        }
                    }
                }
            }
        }
    }

    /**
     * Generates a unique "preferredId" (term from simplified and refers to orderReference) out of a date stamp.
     * Minimum length of maxLength is 14, but in that case only the timestamp will be returned
     *
     * @param int $maxLength Recommended length is currently 25. OK to be shorter
     * @param string $prefix Prefix to prepend at unique id level
     * @param bool $dualUniq Be paranoid and sha1-encrypt the first random uniq id first.
     * @param bool $force Force a new payment id
     * @return string
     * @since 1.0.2
     * @since 1.1.2
     */
    public function getPreferredPaymentId($maxLength = 25, $prefix = '', $dualUniq = true, $force = false)
    {
        if (!empty($this->preferredId) && !$force) {
            return $this->preferredId;
        }
        $timestamp = strftime('%Y%m%d%H%M%S', time());
        if ($dualUniq) {
            $uniq = uniqid(sha1(uniqid(rand(), true)), true);
        } else {
            $uniq = uniqid(rand(), true);
        }
        $uniq = preg_replace('/\D/i', '', $uniq);
        $uniqLength = strlen($uniq);
        if (!empty($prefix)) {
            $uniq = substr($prefix . $uniq, 0, $uniqLength);
        }
        $preferredId = $timestamp . '-' . $uniq;
        $preferredId = substr($preferredId, 0, $maxLength);
        $this->preferredId = $preferredId;

        return $this->preferredId;
    }

    /**
     * Primary method of determining customer ip address
     *
     * @return string
     * @since 1.0.3
     * @since 1.1.3
     */
    private function getCustomerIp()
    {
        $this->isNetWork();
        $primaryAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';

        return $primaryAddress;
    }

    /**
     * Payment card validity check for deprecation layer
     *
     * @param string $specificType
     *
     * @since 1.0.2
     * @since 1.1.2
     */
    private function validateCardData($specificType = '')
    {
        // Keeps compatibility with card data sets
        if (isset($this->Payload['orderData']['totalAmount']) &&
            $this->getPreferredPaymentFlowService() == RESURS_FLOW_TYPES::SIMPLIFIED_FLOW
        ) {
            $cardInfo = isset($this->Payload['card']) ? $this->Payload['card'] : [];
            if ((isset($cardInfo['cardNumber']) && empty($cardInfo['cardNumber'])) || !isset($cardInfo['cardNumber'])) {
                if ((isset($cardInfo['amount']) && empty($cardInfo['amount'])) || !isset($cardInfo['amount'])) {
                    // Adding the exact total amount as we do not rule of exchange rates. For example, adding 500
                    // extra to the total amount in sweden will work, but will on the other hand be devastating for
                    // countries using euro.
                    $this->Payload['card']['amount'] = $this->Payload['orderData']['totalAmount'];
                }
            }
        }

        if (isset($this->Payload['card']['cardNumber'])) {
            if (empty($this->Payload['card']['cardNumber'])) {
                unset($this->Payload['card']['cardNumber']);
            }
        }

        if (isset($this->Payload['customer'])) {
            // CARD + (NEWCARD, REVOLVING_CREDIT)
            $mandatoryExtendedCustomerFields = ['governmentId', 'address', 'phone', 'email', 'type'];
            if ($specificType == 'CARD') {
                $mandatoryExtendedCustomerFields = ['governmentId'];
            } elseif (($specificType == 'REVOLVING_CREDIT' || $specificType == 'NEWCARD')) {
                $mandatoryExtendedCustomerFields = ['governmentId', 'phone', 'email'];
            }
            if (count($mandatoryExtendedCustomerFields)) {
                foreach ($this->Payload['customer'] as $customerKey => $customerValue) {
                    // If the key belongs to extendedCustomer, is mandatory for the specificType and is empty,
                    // this means we can not deliver this data as a null value to ecommerce. Therefore, we have
                    // to remove it. The control being made here will skip the address object as we will only
                    // check the non-recursive data strings.
                    if (is_string($customerValue)) {
                        $trimmedCustomerValue = trim($customerValue);
                    } else {
                        // Do not touch if this is not an array (and consider that something was sent into this part,
                        // that did not belong here?)
                        $trimmedCustomerValue = $customerValue;
                    }
                    if (!is_array($customerValue) && !in_array(
                            $customerKey,
                            $mandatoryExtendedCustomerFields
                        ) && empty($trimmedCustomerValue)) {
                        unset($this->Payload['customer'][$customerKey]);
                    }
                }
            }
        }
    }

    /**
     * Clean up payload fields that should not be there if method is PSP and payload is half empty
     */
    private function clearPspCustomerPayload()
    {
        if (isset($this->Payload['customer']['governmentId']) && empty($this->Payload['customer']['governmentId'])) {
            unset($this->Payload['customer']['governmentId']);
        }
    }

    /**
     * Internal function that is normally used by createPayment. However, if you choose to use createPaymentDelay()
     * you need to be able to execute the creation yourself. From 1.3.32, this function will be open for this.
     *
     * @param string $payment_id_or_method
     * @return array|mixed
     * @throws Exception
     * @since 1.0.2
     * @since 1.1.2
     */
    public function createPaymentExecute($payment_id_or_method = '')
    {
        /**
         * @since 1.0.29
         * @since 1.1.29
         * @since 1.2.2
         * @since 1.3.2
         */
        if ($this->isFlag('PREVENT_EXEC_FLOOD')) {
            $maxTime = (int)$this->getFlag('PREVENT_EXEC_FLOOD_TIME');
            if (!$maxTime) {
                $maxTime = 5;
            }
            $lastPaymentExecute = (int)$this->getSessionVar('lastPaymentExecute');
            $timeDiff = time() - $lastPaymentExecute;
            if ($timeDiff <= $maxTime) {
                if ($this->isFlag('PREVENT_EXEC_FLOOD_EXCEPTIONS')) {
                    throw new ResursException(
                        'You are running createPayment too fast',
                        RESURS_EXCEPTIONS::CREATEPAYMENT_TOO_FAST
                    );
                }

                return false;
            }
            $this->setSessionVar('lastPaymentExecute', time());
        }
        if (trim(strtolower($this->username)) == 'exshop') {
            throw new ResursException(
                'The use of exshop is no longer supported',
                RESURS_EXCEPTIONS::EXSHOP_PROHIBITED
            );
        }
        $error = [];
        $myFlow = $this->getPreferredPaymentFlowService();

        // Using this function to validate that card data info is properly set up
        // during the deprecation state in >= 1.0.2/1.1.1
        if ($myFlow === RESURS_FLOW_TYPES::SIMPLIFIED_FLOW) {
            $paymentMethodInfo = $this->getPaymentMethodSpecific($payment_id_or_method);
            if (isset($paymentMethodInfo) && is_object($paymentMethodInfo)) {
                if (isset($paymentMethodInfo->specificType) &&
                    $paymentMethodInfo->specificType === 'CARD' ||
                    $paymentMethodInfo->specificType === 'NEWCARD' ||
                    $paymentMethodInfo->specificType === 'REVOLVING_CREDIT'
                ) {
                    $this->validateCardData($paymentMethodInfo->specificType);
                }
            }
            $myFlowResponse = $this->postService('bookPayment', $this->Payload);
            $this->resetPayload();

            return $myFlowResponse;
        } elseif ($myFlow === RESURS_FLOW_TYPES::RESURS_CHECKOUT) {
            $checkoutUrl = $this->getCheckoutUrl() . '/checkout/payments/' . $payment_id_or_method;
            try {
                $checkoutResponse = $this->CURL->request(
                    $checkoutUrl,
                    $this->Payload,
                    requestMethod::METHOD_POST,
                    dataType::JSON
                );
                $parsedResponse = $checkoutResponse->getParsed();
                $responseCode = $checkoutResponse->getCode();
                $this->fullCheckoutResponse = $parsedResponse;
                // Do not trust response codes!
                if (isset($parsedResponse->paymentSessionId)) {
                    $this->paymentSessionId = $parsedResponse->paymentSessionId;
                    $this->SpecLines = [];

                    try {
                        if ($this->isFlag('STORE_ORIGIN')) {
                            $this->isNetWork();
                            @preg_match_all('/iframe.*src=\"(http(.*?))\"/', $parsedResponse->html, $matches);
                            if (isset($matches[1]) && isset($matches[1][0])) {
                                $urls = $this->NETWORK->getUrlsFromHtml($parsedResponse->html);
                                if (is_array($urls) && count($urls)) {
                                    $iFrameOriginData = $this->NETWORK->getUrlDomain(
                                        $urls[0]
                                    );
                                    $this->iframeOrigin = sprintf(
                                        '%s://%s',
                                        $iFrameOriginData[1],
                                        $iFrameOriginData[0]
                                    );
                                }
                            }
                        }
                    } catch (Exception $e) {
                        // Ignore on internal errors.
                    }

                    return $parsedResponse->html;
                } else {
                    if (isset($parsedResponse->error)) {
                        $error[] = $parsedResponse->error;
                    }
                    if (isset($parsedResponse->message)) {
                        $error[] = $parsedResponse->message;
                    }
                    throw new ResursException(implode("\n", $error), $responseCode);
                }
            } catch (Exception $e) {
                $this->handlePostErrors($e);
            }

            return isset($parsedResponse) ? $parsedResponse : null;
        } elseif ($myFlow == RESURS_FLOW_TYPES::HOSTED_FLOW) {
            $hostedUrl = $this->getHostedUrl();
            try {
                $hostedResponse = $this->CURL->request(
                    $hostedUrl,
                    $this->Payload,
                    requestMethod::METHOD_POST,
                    dataType::JSON
                );
                $parsedResponse = $hostedResponse->getParsed();
                // Do not trust response codes!
                if (isset($parsedResponse->location)) {
                    $this->resetPayload();

                    return $this->getSecureUrl($parsedResponse->location);
                } else {
                    if (isset($parsedResponse->error)) {
                        $error[] = $parsedResponse->error;
                    }
                    if (isset($parsedResponse->message)) {
                        $error[] = $parsedResponse->message;
                    }
                    $responseCode = $this->CURL->getCode($hostedResponse);
                    throw new ResursException(implode("\n", $error), $responseCode);
                }
            } catch (Exception $e) {
                $this->handlePostErrors($e);
            }
        }

        throw new ResursException(
            sprintf(
                '%s exception: Flow unmatched during execution.',
                __FUNCTION__
            ),
            500
        );
    }


    /////////// PRIMARY INTERNAL SHOP FLOW SECTION
    ////// HELPERS

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
    public function getSessionVar($key = '')
    {
        $this->sessionActivate();
        $returnVar = null;
        if (isset($_SESSION) && isset($_SESSION[$key])) {
            $returnVar = $_SESSION[$key];
        }

        return $returnVar;
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
    public function setSessionVar($key = '', $keyValue = '')
    {
        $this->sessionActivate();
        if (isset($_SESSION)) {
            $_SESSION[$key] = $keyValue;

            return true;
        }

        return false;
    }

    /**
     * Clean up payload after usage.
     *
     * @since 1.1.22
     */
    public function resetPayload()
    {
        $this->PayloadHistory[] = [
            'Payload' => $this->Payload,
            'SpecLines' => $this->SpecLines,
        ];

        // Flags that prevents any reset of payloads. Here, it will be just filled.
        if ($this->getFlag('NO_RESET_PAYLOAD')) {
            return;
        }

        $this->SpecLines = [];
        $this->Payload = [];
    }

    /**
     * Handle post errors and extract eventual errors from a http body
     *
     * @param $e
     *
     * @throws Exception
     * @since 1.0.38
     * @since 1.1.38
     * @since 1.3.11
     * @since 2.0.0
     */
    private function handlePostErrors($e)
    {
        $bodyTest = $this->CURL->getBody();
        if (is_string($bodyTest) && !empty($bodyTest)) {
            $bodyErrTest = json_decode($bodyTest);
            if (is_object($bodyErrTest)) {
                if (isset($bodyErrTest->message) && isset($bodyErrTest->status)) {
                    throw new ResursException(
                        $bodyErrTest->message,
                        $bodyErrTest->status
                    );
                } elseif (isset($bodyErrTest->description)) {
                    throw new ResursException(
                        $bodyErrTest->description,
                        isset($bodyErrTest->errorCode) ? $bodyErrTest->errorCode : 500
                    );
                }
            }
        }
        if (method_exists($e, 'getMessage')) {
            /** @noinspection PhpUndefinedMethodInspection */
            throw new ResursException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @return string
     */
    public function getHostedUrl()
    {
        if ($this->current_environment == RESURS_ENVIRONMENTS::TEST) {
            return $this->env_hosted_test;
        } else {
            return $this->env_hosted_prod;
        }
    }

    /**
     * Returns a possible origin source from the iframe request.
     *
     * @param string $extractFrom
     * @param bool $useOwn
     * @return string
     * @throws Exception
     * @since 1.3.30
     */
    public function getIframeOrigin($extractFrom = '', $useOwn = false)
    {
        $return = $this->iframeOrigin;

        if ((empty($this->iframeOrigin) && !empty($extractFrom)) || (!empty($extractFrom) && $useOwn)) {
            $this->isNetWork();

            $iFrameOriginData = $this->NETWORK->getUrlDomain(
                $extractFrom
            );
            $return = sprintf(
                '%s://%s',
                $iFrameOriginData[1],
                $iFrameOriginData[0]
            );
        }
        return $return;
    }

    /**
     * Get full checkout response from RCO.
     *
     * @return string
     * @since 1.1.30
     */
    public function getFullCheckoutResponse()
    {
        return $this->fullCheckoutResponse;
    }

    ////// MASTER SHOP FLOWS - PRIMARY BOOKING FUNCTIONS

    /**
     * Book signed payment
     *
     * @param string $paymentId
     *
     * @return array|mixed|null
     * @throws Exception
     * @since 1.0.5
     * @since 1.1.5
     */
    public function bookSignedPayment($paymentId = '')
    {
        return $this->postService('bookSignedPayment', ['paymentId' => $paymentId]);
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getOrderLineHash()
    {
        $returnHashed = '';
        $orderLines = $this->sanitizePaymentSpec($this->getOrderLines(), RESURS_FLOW_TYPES::MINIMALISTIC);

        if (is_array($orderLines)) {
            $hashifiedString = '';
            foreach ($orderLines as $idx => $minimalisticArray) {
                $hashifiedString .= sha1($idx . ':' . implode('|', $minimalisticArray));
            }
            // This string has salted itself based on orderline content
            $returnHashed = sha1($hashifiedString);
        }

        // Empty string means fail
        return $returnHashed;
    }

    /**
     * Return added speclines / Order lines
     *
     * @return array
     */
    public function getOrderLines()
    {
        return $this->SpecLines;
    }

    /**
     * Get the payment session id from Resurs Checkout
     *
     * @return string
     * @since 1.0.2
     * @since 1.1.2
     */
    public function getPaymentSessionId()
    {
        return $this->paymentSessionId;
    }

    /**
     * @return array|mixed
     * @throws Exception
     * @since 1.0.3
     * @since 1.1.3
     */
    public function Execute()
    {
        if (!empty($this->createPaymentExecuteCommand)) {
            return $this->createPaymentExecute($this->createPaymentExecuteCommand);
        } else {
            throw new ResursException('createPaymentDelay() must used before you use this function.', 403);
        }
    }

    /**
     * Returns current set unit measure (st, kpl, etc).
     *
     * @return string
     * @since 1.0.26
     * @since 1.1.26
     * @since 1.2.0
     */
    public function getDefaultUnitMeasure()
    {
        return $this->defaultUnitMeasure;
    }

    /**
     * Pre-set a default unit measure if it is missing in the payment spec. Defaults to "st" if nothing is set.
     *
     * If no unit measure are set but setCountry() have been used, this function will try to set a matching string
     * depending on the country.
     *
     * @param null $unitMeasure
     *
     * @since 1.0.2
     * @since 1.1.2
     */
    public function setDefaultUnitMeasure($unitMeasure = null)
    {
        if (is_null($unitMeasure)) {
            if (!empty($this->envCountry)) {
                if ($this->envCountry == RESURS_COUNTRY::DK) {
                    $this->defaultUnitMeasure = 'st';
                } elseif ($this->envCountry == RESURS_COUNTRY::NO) {
                    $this->defaultUnitMeasure = 'st';
                } elseif ($this->envCountry == RESURS_COUNTRY::FI) {
                    $this->defaultUnitMeasure = 'kpl';
                } else {
                    $this->defaultUnitMeasure = 'st';
                }
            } else {
                $this->defaultUnitMeasure = 'st';
            }
        } else {
            $this->defaultUnitMeasure = $unitMeasure;
        }
    }

    /**
     * Set flag annulIfFrozen
     * @param bool $setBoolean
     * @return ResursBank
     * @since 1.1.29
     * @since 1.2.2
     * @since 1.3.2
     * @since 1.0.29
     * @noinspection ParameterDefaultValueIsNotNullInspection
     */
    public function setAnnulIfFrozen($setBoolean = true)
    {
        $this->fixPaymentData();
        $this->Payload['paymentData']['annulIfFrozen'] = $setBoolean;

        return $this;
    }

    private function fixPaymentData()
    {
        if (!isset($this->Payload['paymentData'])) {
            $this->Payload['paymentData'] = [];
        }
    }

    /**
     * Set flag annulIfFrozen
     *
     * @return bool
     * @since 1.0.29
     * @since 1.1.29
     * @since 1.2.2
     * @since 1.3.2
     */
    public function getAnnulIfFrozen()
    {
        $this->fixPaymentData();

        return isset(
            $this->Payload['paymentData']['annulIfFrozen']
        ) ? $this->Payload['paymentData']['annulIfFrozen'] : false;
    }

    /**
     * Set flag waitForFraudControl
     *
     * @param bool $setBoolean
     * @return ResursBank
     * @since 1.0.29
     * @since 1.1.29
     * @since 1.2.2
     * @since 1.3.2
     */
    public function setWaitForFraudControl($setBoolean = true)
    {
        $this->fixPaymentData();
        $this->Payload['paymentData']['waitForFraudControl'] = $setBoolean;

        return $this;
    }

    /**
     * Get flag waitForFraudControl
     *
     * @return bool
     * @since 1.0.29
     * @since 1.1.29
     * @since 1.2.2
     * @since 1.3.2
     */
    public function getWaitForFraudControl()
    {
        $this->fixPaymentData();

        return isset(
            $this->Payload['paymentData']['waitForFraudControl']
        ) ? $this->Payload['paymentData']['waitForFraudControl'] : false;
    }

    /**
     * Set flag finalizeIfBooked
     *
     * @param bool $setBoolean
     * @return ResursBank
     * @since 1.0.29
     * @since 1.1.29
     * @since 1.2.2
     * @since 1.3.2
     */
    public function setFinalizeIfBooked($setBoolean = true)
    {
        $this->fixPaymentData();
        $this->Payload['paymentData']['finalizeIfBooked'] = $setBoolean;

        return $this;
    }

    /**
     * Get flag finalizeIfBooked
     *
     * @return bool
     * @since 1.0.29
     * @since 1.1.29
     * @since 1.2.2
     * @since 1.3.2
     */
    public function getFinalizeIfBooked()
    {
        $this->fixPaymentData();

        return isset(
            $this->Payload['paymentData']['finalizeIfBooked']
        ) ? $this->Payload['paymentData']['finalizeIfBooked'] : false;
    }

    /**
     * Defines if the checkout should honor the customer field array as it is not officially supported by Resurs Bank
     *
     * @param bool $isCustomerSupported
     *
     * @since 1.0.2
     * @since 1.1.2
     */
    public function setCheckoutCustomerSupported($isCustomerSupported = false)
    {
        $this->checkoutCustomerFieldSupport = $isCustomerSupported;
    }

    /**
     * Enable execute()-mode on data passed through createPayment()
     *
     * @param bool $enableExecute
     * @since 1.0.3
     * @since 1.1.3
     * @deprecated Use createPaymentDelay() (making life easier on debugging stage)
     */
    public function setRequiredExecute($enableExecute = false)
    {
        $this->createPaymentDelay($enableExecute);
    }

    /**
     * Enable execute()-mode on data passed through createPayment()
     *
     * If you run createPayment() and do not succeed during the primary function, you can enable this function to not
     * fulfill the whole part of the payment until doing an execute(). In this case EComPHP will only prepare the
     * required parameters for the payment to run. When this function is enabled you can also, before creating the
     * payment do for example a getPayload() to see how it looks before completion.
     *
     * @param bool $enableManualExecution
     * @since 1.0.38
     * @since 1.1.38
     * @since 1.3.11
     * @since 2.0.0
     */
    public function createPaymentDelay($enableManualExecution = false)
    {
        $this->forceExecute = $enableManualExecution;
    }

    /**
     * Payload simplifier: Having data from getAddress, you want to set as billing address, this can be done from here.
     *
     * @param string $getAddressDataOrGovernmentId
     * @param string $customerType
     *
     * @return array
     * @throws Exception
     * @since 1.0.2
     * @since 1.1.2
     */
    public function setBillingByGetAddress($getAddressDataOrGovernmentId, $customerType = 'NATURAL')
    {
        if (is_object($getAddressDataOrGovernmentId)) {
            $this->setAddressPayload('address', $getAddressDataOrGovernmentId);
        } elseif (is_numeric($getAddressDataOrGovernmentId)) {
            $this->Payload['customer']['governmentId'] = $getAddressDataOrGovernmentId;
            $this->setAddressPayload('address', $this->getAddress(
                $getAddressDataOrGovernmentId,
                $customerType,
                isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1'
            ));
        }

        return $this->Payload;
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
    private function setAddressPayload($addressKey = 'address', $addressData = [])
    {
        if (is_object($addressData)) {
            $this->setPayloadArray($addressKey, $this->renderAddress(
                isset($addressData->fullName) && !empty($addressData->fullName) ? $addressData->fullName : '',
                isset($addressData->firstName) && !empty($addressData->firstName) ? $addressData->firstName : '',
                isset($addressData->lastName) && !empty($addressData->lastName) ? $addressData->lastName : '',
                isset($addressData->addressRow1) && !empty($addressData->addressRow1) ? $addressData->addressRow1 : '',
                isset($addressData->addressRow2) && !empty($addressData->addressRow2) ? $addressData->addressRow2 : '',
                isset($addressData->postalArea) && !empty($addressData->postalArea) ? $addressData->postalArea : '',
                isset($addressData->postalCode) && !empty($addressData->postalCode) ? $addressData->postalCode : '',
                isset($addressData->country) && !empty($addressData->country) ? $addressData->country : ''
            ));
        } elseif (is_array($addressData)) {
            // If there is an inbound countryCode here, there is a consideration of hosted flow.
            // In this case we need to normalize the address data first as renderAddress() are rerunning also during
            // setBillingAddress()-process. If we don't do this, EComPHP will drop the countryCode and leave
            // the payload empty  - see ECOMPHP-168.
            if (isset($addressData['countryCode']) && !empty($addressData['countryCode'])) {
                $addressData['country'] = $addressData['countryCode'];
                unset($addressData['countryCode']);
            }
            $this->setPayloadArray($addressKey, $this->renderAddress(
                isset($addressData['fullName']) && !empty($addressData['fullName']) ? $addressData['fullName'] : '',
                isset($addressData['firstName']) && !empty($addressData['firstName']) ? $addressData['firstName'] : '',
                isset($addressData['lastName']) && !empty($addressData['lastName']) ? $addressData['lastName'] : '',
                isset($addressData['addressRow1']) &&
                !empty($addressData['addressRow1']) ? $addressData['addressRow1'] : '',
                isset($addressData['addressRow2']) &&
                !empty($addressData['addressRow2']) ? $addressData['addressRow2'] : '',
                isset($addressData['postalArea']) &&
                !empty($addressData['postalArea']) ? $addressData['postalArea'] : '',
                isset($addressData['postalCode']) &&
                !empty($addressData['postalCode']) ? $addressData['postalCode'] : '',
                isset($addressData['country']) && !empty($addressData['country']) ? $addressData['country'] : ''
            ));
        }
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
    private function setPayloadArray($ArrayKey, $ArrayValue = [])
    {
        if ($ArrayKey == 'address' || $ArrayKey == 'deliveryAddress') {
            if (!isset($this->Payload['customer'])) {
                $this->Payload['customer'] = [];
            }
            $this->Payload['customer'][$ArrayKey] = $ArrayValue;
        } else {
            $this->Payload[$ArrayKey] = $ArrayValue;
        }
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
    private function renderAddress(
        $fullName,
        $firstName,
        $lastName,
        $addressRow1,
        $addressRow2,
        $postalArea,
        $postalCode,
        $country
    ) {
        $ReturnAddress = [
            'fullName' => $fullName,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'addressRow1' => $addressRow1,
            'postalArea' => $postalArea,
            'postalCode' => $postalCode,
        ];

        $trimAddress = trim($addressRow2); // PHP Compatibility
        if (!empty($trimAddress)) {
            $ReturnAddress['addressRow2'] = $addressRow2;
        }

        $targetCountry = $this->getCountry();
        if (empty($country) && !empty($targetCountry)) {
            $country = $targetCountry;
        } elseif (!empty($country) && empty($targetCountry)) {
            // Giving internal country data more influence on this method
            $this->setCountryByCountryCode($targetCountry);
        }

        if ($this->getPreferredPaymentFlowService() === RESURS_FLOW_TYPES::NOT_SET) {
            /**
             * EComPHP might get a bit confused here, if no preferred flow is set. Normally, we don't have to know this,
             * but in this case (since EComPHP actually points at the simplified flow by default) we need to tell it
             * what to use, so correct payload will be used, during automation of the billing.
             *
             * @link https://resursbankplugins.atlassian.net/browse/ECOMPHP-238
             */
            $this->setPreferredPaymentFlowService(RESURS_FLOW_TYPES::SIMPLIFIED_FLOW);
        }

        if ($this->getPreferredPaymentFlowService() === RESURS_FLOW_TYPES::SIMPLIFIED_FLOW) {
            $ReturnAddress['country'] = $country;
        } else {
            $ReturnAddress['countryCode'] = $country;
        }

        return $ReturnAddress;
    }

    /**
     * Returns current set target country
     *
     * @return string
     * @since 1.0.26
     * @since 1.1.26
     * @since 1.2.0
     */
    public function getCountry()
    {
        return $this->envCountry;
    }

    /**
     * Set up a country based on a country code string. Supported countries are SE, DK, NO and FI. Anything else than
     * this defaults to SE
     *
     * @param string $countryCodeString
     */
    public function setCountryByCountryCode($countryCodeString = '')
    {
        if (strtolower($countryCodeString) == 'dk') {
            $this->setCountry(RESURS_COUNTRY::DK);
        } elseif (strtolower($countryCodeString) == 'no') {
            $this->setCountry(RESURS_COUNTRY::NO);
        } elseif (strtolower($countryCodeString) == 'fi') {
            $this->setCountry(RESURS_COUNTRY::FI);
        } else {
            $this->setCountry(RESURS_COUNTRY::SE);
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
    public function setCountry($Country)
    {
        if ($Country === RESURS_COUNTRY::DK) {
            $this->envCountry = 'DK';
        } elseif ($Country === RESURS_COUNTRY::NO) {
            $this->envCountry = 'NO';
        } elseif ($Country === RESURS_COUNTRY::FI) {
            $this->envCountry = 'FI';
        } elseif ($Country === RESURS_COUNTRY::SE) {
            $this->envCountry = 'SE';
        } else {
            $this->envCountry = null;
        }

        return $this->envCountry;
    }

    /**
     * Get customer address by government id.
     *
     * @param string $governmentId
     * @param string $customerType
     * @param string $customerIpAddress
     * @return array|mixed|null
     * @throws Exception
     * @since 1.0.1
     * @since 1.1.1
     */
    public function getAddress($governmentId = '', $customerType = 'NATURAL', $customerIpAddress = '')
    {
        if (!empty($customerIpAddress) && isset($_SERVER['REMOTE_ADDR'])) {
            $customerIpAddress = $_SERVER['REMOTE_ADDR'];
        }

        return $this->postService('getAddress', [
            'governmentId' => $governmentId,
            'customerType' => $customerType,
            'customerIpAddress' => $customerIpAddress,
        ]);
    }

    /**
     * Payload simplifier: Having data from getAddress, you want to set as shipping address, this can be done from here.
     *
     * @param $getAddressData
     *
     * @since 1.0.2
     * @since 1.1.2
     */
    public function setDeliveryByGetAddress($getAddressData)
    {
        $this->setAddressPayload('deliveryAddress', $getAddressData);
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
    public function setBillingAddress(
        $fullName,
        $firstName,
        $lastName,
        $addressRow1,
        $addressRow2,
        $postalArea,
        $postalCode,
        $country
    ) {
        $this->setAddressPayload(
            'address',
            $this->renderAddress(
                $fullName,
                $firstName,
                $lastName,
                $addressRow1,
                $addressRow2,
                $postalArea,
                $postalCode,
                $country
            )
        );
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
    public function setDeliveryAddress(
        $fullName,
        $firstName,
        $lastName,
        $addressRow1,
        $addressRow2,
        $postalArea,
        $postalCode,
        $country
    ) {
        $this->setAddressPayload(
            'deliveryAddress',
            $this->renderAddress(
                $fullName,
                $firstName,
                $lastName,
                $addressRow1,
                $addressRow2,
                $postalArea,
                $postalCode,
                $country
            )
        );
    }

    /**
     * @param string $governmentId
     * @param string $phone
     * @param string $cellphone
     * @param string $email
     * @param string $customerType NATURAL/LEGAL
     * @param string $contactGovernmentId
     *
     * @throws Exception
     * @since 1.0.2
     * @since 1.1.2
     */
    public function setCustomer(
        $governmentId = '',
        $phone = '',
        $cellphone = '',
        $email = '',
        $customerType = '',
        $contactGovernmentId = ''
    ) {
        if (!isset($this->Payload['customer'])) {
            $this->Payload['customer'] = [];
        }
        // Set this if not already set by a getAddress()
        if (!isset($this->Payload['customer']['governmentId'])) {
            $this->Payload['customer']['governmentId'] = !empty($governmentId) ? $governmentId : '';
        }
        $this->Payload['customer']['email'] = $email;
        if (!empty($phone)) {
            $this->Payload['customer']['phone'] = $phone;
        }
        // The field for cellphone in RCO is called mobile.
        if ($this->getPreferredPaymentFlowService() === RESURS_FLOW_TYPES::RESURS_CHECKOUT) {
            if (!empty($cellphone)) {
                $this->Payload['customer']['mobile'] = $cellphone;
            }
        } else {
            if (!empty($cellphone)) {
                $this->Payload['customer']['cellPhone'] = $cellphone;
            }
        }
        if (!empty($customerType)) {
            $this->Payload['customer']['type'] = !empty($customerType) &&
            (strtolower($customerType) == 'natural' ||
                strtolower($customerType) == 'legal'
            ) ? strtoupper($customerType) : 'NATURAL';
        } else {
            // We don't guess on customer types
            throw new ResursException(
                'No customer type has been set. Use NATURAL or LEGAL to proceed',
                RESURS_EXCEPTIONS::BOOK_CUSTOMERTYPE_MISSING
            );
        }
        if (!empty($contactGovernmentId)) {
            $this->Payload['customer']['contactGovernmentId'] = $contactGovernmentId;
        }
    }

    /**
     * Helper function. This actually does what setSigning do, but with lesser confusion.
     *
     * @param string $successUrl
     * @param string $backUrl
     *
     * @throws Exception
     */
    public function setCheckoutUrls($successUrl = '', $backUrl = '')
    {
        $this->setSigning($successUrl, $backUrl);
    }

    /**
     * Configure signing data for the payload. Supports partial url encoding since (1.3.15/1.1.42).
     * Encoding is usually not a problem when using "nice urls".
     *
     * @param string $successUrl Successful payment redirect url
     * @param string $failUrl Payment failures redirect url
     * @param bool $forceSigning Always require signing during payment
     * @param string $backUrl Back url (optional for hosted flow where back !== fail) if anything else than failUrl
     * @param int $encodeType It is NOT recommended to run this on a success url
     * @return mixed
     * @throws Exception
     * @since 1.0.6
     * @since 1.1.6
     * @noinspection PhpParamsInspection
     */
    public function setSigning(
        $successUrl = '',
        $failUrl = '',
        $forceSigning = false,
        $backUrl = null,
        $encodeType = RESURS_URL_ENCODE_TYPES::NONE
    ) {
        $SigningPayload['signing'] = [
            'successUrl' => $this->getEncodedSigningUrl($successUrl, RESURS_URL_ENCODE_TYPES::SUCCESSURL, $encodeType),
            'failUrl' => $this->getEncodedSigningUrl($failUrl, RESURS_URL_ENCODE_TYPES::FAILURL, $encodeType),
            'forceSigning' => $forceSigning,
        ];
        if (!is_null($backUrl)) {
            $SigningPayload['backUrl'] = $this->getEncodedSigningUrl(
                $backUrl,
                RESURS_URL_ENCODE_TYPES::BACKURL,
                $encodeType
            );
        }
        $this->handlePayload($SigningPayload);

        // Return data from this method to confirm output (used with tests) but may help developers
        // check their urls also.
        return $SigningPayload;
    }

    /**
     * @param $currentUrl
     * @param $urlType RESURS_URL_ENCODE_TYPES
     * @param $requestBits RESURS_URL_ENCODE_TYPES
     * @return string
     * @since 1.3.15
     * @since 1.0.42
     * @since 1.1.42
     */
    private function getEncodedSigningUrl($currentUrl, $urlType, $requestBits)
    {
        if ($urlType & $requestBits) {
            $currentUrl = $this->getEncodedUrl($currentUrl, $requestBits);
        }

        return (string)$currentUrl;
    }

    /**
     * @param $url
     * @param $urlType RESURS_URL_ENCODE_TYPES
     * @return string
     * @since 1.3.15
     * @since 1.0.42
     * @since 1.1.42
     * @noinspection PhpDeprecationInspection
     */
    private function getEncodedUrl($url, $urlType)
    {
        try {
            if ($urlType & RESURS_URL_ENCODE_TYPES::PATH_ONLY) {
                $urlParsed = parse_url($url);

                if (is_array($urlParsed)) {
                    $queryStartEncoded = '?';
                    $queryStartDecoded = '';
                    if ($urlType & RESURS_URL_ENCODE_TYPES::LEAVE_FIRST_PART) {
                        $queryStartEncoded = '';
                        $queryStartDecoded = '?';
                    }
                    $encodedQuery = rawurlencode($queryStartEncoded . $urlParsed['query']);
                    if ($urlType & RESURS_URL_ENCODE_TYPES::LEAVE_FIRST_PART) {
                        $encodedQuery = preg_replace('/%3D/', '=', $encodedQuery, 1);
                    }
                    $url = sprintf(
                        '%s://%s%s%s',
                        $urlParsed['scheme'],
                        $urlParsed['host'],
                        isset($urlParsed['path']) ? $urlParsed['path'] : '/',
                        $queryStartDecoded . $encodedQuery
                    );
                }
            } else {
                $url = rawurlencode($url);
            }
        } catch (Exception $e) {
            $url = null;
        }

        return (string)$url;
    }

    /**
     * Returns the final payload
     *
     * @param bool $history
     *
     * @return array
     * @throws Exception
     * @since 1.0.2
     * @since 1.1.2
     * @since 1.2.0
     */
    public function getPayload($history = false)
    {
        if (!$history) {
            if ($this->getPreferredPaymentFlowService() === RESURS_FLOW_TYPES::RESURS_CHECKOUT) {
                try {
                    $this->preparePayload();
                } catch (Exception $e) {
                    /**
                     * @since 1.3.16
                     */
                }
            } else {
                $this->preparePayload();
            }
            // Making sure payloads are returned as they should look
            if (isset($this->Payload)) {
                if (!is_array($this->Payload)) {
                    $this->Payload = [];
                }
            } else {
                $this->Payload = [];
            }

            $return = $this->Payload;
        } else {
            $return = array_pop($this->PayloadHistory);
        }

        return $return;
    }

    /**
     * @return array
     */
    public function getSpecLines()
    {
        return $this->getOrderLines();
    }

    /**
     * Get the iframe resizer URL if requested from a site
     *
     * @return string
     * @throws Exception
     * @since 1.0.2
     * @since 1.1.2
     */
    public function getIframeResizerUrl()
    {
        if (!empty($this->ocShopScript)) {
            return trim($this->ocShopScript);
        }
        throw new ResursException(
            sprintf(
                '%s exception: could not fetch th ocShopScript from iframe.'
            )
        );
    }

    /**
     * Update the Checkout iframe
     *
     * Backwards compatible so the formatting of the orderLines will be accepted in following formats:
     *  - $orderLines is accepted as a json string
     *  - $orderLines can be sent in as array('orderLines' => $yourOrderLines)
     *  - $orderLines can be sent in as array($yourOrderLines)
     *
     * @param string $paymentId
     * @param array $orderLines
     *
     * @return bool
     * @throws Exception
     * @since 1.0.22
     * @since 1.1.22
     * @since 1.2.0
     */
    public function updateCheckoutOrderLines($paymentId = '', $orderLines = [])
    {
        if (empty($paymentId)) {
            throw new ResursException('Payment id not set.');
        }
        if (!$this->hasServicesInitialization) {
            $this->InitializeServices();
        }
        if (empty($this->defaultUnitMeasure)) {
            $this->setDefaultUnitMeasure();
        }
        if (is_string($orderLines)) {
            // If this is a string, it might be an json string from older systems. We need, in that case make sure it
            // is returned as an array. This will destroy the content going to the PUT call, if it is not the case.
            // However, sending a string to this point has no effect in the flow whatsoever.
            $orderLines = $this->objectsIntoArray(json_decode($orderLines));
        }
        // Make sure that the payment spec are clean up and set correctly to a non-recursive array
        if (isset($orderLines['orderLines'])) {
            $outputOrderLines = $orderLines['orderLines'];
        } elseif (isset($orderLines['specLines'])) {
            $outputOrderLines = $orderLines['specLines'];
        } else {
            $outputOrderLines = $orderLines;
        }
        $sanitizedOutputOrderLines = $this->sanitizePaymentSpec(
            $outputOrderLines,
            RESURS_FLOW_TYPES::RESURS_CHECKOUT
        );
        $updateOrderLinesResponse = $this->CURL->request(
            $this->getCheckoutUrl() . '/checkout/payments/' . $paymentId,
            ['orderLines' => $sanitizedOutputOrderLines],
            requestMethod::METHOD_PUT,
            dataType::JSON
        );
        $updateOrderLinesResponseCode = $this->CURL->getCode($updateOrderLinesResponse);
        if ($updateOrderLinesResponseCode >= 400) {
            throw new ResursException(
                'Could not update order lines.',
                $updateOrderLinesResponseCode
            );
        }
        if ($updateOrderLinesResponseCode >= 200 && $updateOrderLinesResponseCode < 300) {
            return true;
        }

        return false;
    }

    //// PAYLOAD HANDLER!

    /**
     * Convert objects to array data - recursive function when casting is not enough
     *
     * @param $arrObjData
     * @param array $arrSkipIndices
     *
     * @return array
     */
    private function objectsIntoArray($arrObjData, $arrSkipIndices = [])
    {
        $arrData = [];
        // if input is object, convert into array
        if (is_object($arrObjData)) {
            $arrObjData = get_object_vars($arrObjData);
        }
        if (is_array($arrObjData)) {
            foreach ($arrObjData as $index => $value) {
                if (is_object($value) || is_array($value)) {
                    $value = $this->objectsIntoArray($value, $arrSkipIndices); // recursive call
                }
                if (@in_array($index, $arrSkipIndices)) {
                    continue;
                }
                $arrData[$index] = $value;
            }
        }

        return $arrData;
    }

    /**
     * Set up payload with simplified card data.
     *
     * Conditions is:
     *   - Cards: Use card number only
     *   - New cards: No data needed, but could be set as (null, cardAmount). If no data set the applied amount will be
     *   the totalAmount.
     *
     * @param null $cardNumber
     * @param null $cardAmount
     *
     * @since 1.0.2
     * @since 1.1.2
     */
    public function setCardData($cardNumber = null, $cardAmount = null)
    {
        if (!isset($this->Payload['card'])) {
            $this->Payload['card'] = [];
        }
        if (!isset($this->Payload['card']['cardNumber'])) {
            $this->Payload['card']['cardNumber'] = trim($cardNumber);
        }
        if ($cardAmount > 0) {
            $this->Payload['card']['amount'] = $cardAmount;
        }
    }

    /**
     * Find out if a payment is creditable
     *
     * @param array|string $paymentArrayOrPaymentId
     *
     * @return bool
     * @throws Exception
     */
    public function canCredit($paymentArrayOrPaymentId = [])
    {
        $status = (array)$this->getPaymentContent($paymentArrayOrPaymentId, 'status');
        // IS_CREDITED - CREDITABLE
        if (in_array('CREDITABLE', $status)) {
            return true;
        }

        return false;
    }

    /**
     * Get the correct key value from a payment (or a payment object directly).
     *
     * @param array $paymentArrayOrPaymentId
     * @param string $paymentKey
     * @return null
     * @throws Exception
     * @since 1.0.2
     * @since 1.1.2
     */
    private function getPaymentContent($paymentArrayOrPaymentId = [], $paymentKey = '')
    {
        $Payment = $this->getCorrectPaymentContent($paymentArrayOrPaymentId);
        if (isset($Payment->$paymentKey)) {
            return $Payment->$paymentKey;
        }

        return null;
    }

    /**
     * Make sure a payment will always be returned correctly. If string, getPayment will run first. If array/object, it
     * will continue to look like one.
     *
     * @param array $paymentArrayOrPaymentId
     *
     * @return array|mixed|null
     * @throws Exception
     * @since 1.0.2
     * @since 1.1.2
     */
    private function getCorrectPaymentContent($paymentArrayOrPaymentId = [])
    {
        if (is_string($paymentArrayOrPaymentId) && !empty($paymentArrayOrPaymentId)) {
            return $this->getPayment($paymentArrayOrPaymentId, false);
        } elseif (is_object($paymentArrayOrPaymentId)) {
            return $paymentArrayOrPaymentId;
        } elseif (is_array($paymentArrayOrPaymentId)) {
            // This is wrong, but we'll return it anyway.
            return $paymentArrayOrPaymentId;
        }

        return null;
    }

    /**
     * A payment is annullable if the payment is debitable
     *
     * @param array $paymentArrayOrPaymentId
     *
     * @return bool
     * @throws Exception
     * @since 1.0.2
     * @since 1.1.2
     */
    public function canAnnul($paymentArrayOrPaymentId = [])
    {
        return $this->canDebit($paymentArrayOrPaymentId);
    }

    /**
     * Find out if a payment is debitable
     *
     * @param array $paymentArrayOrPaymentId
     *
     * @return bool
     * @throws Exception
     */
    public function canDebit($paymentArrayOrPaymentId = [])
    {
        $status = (array)$this->getPaymentContent($paymentArrayOrPaymentId, 'status');
        // IS_DEBITED - DEBITABLE
        if (in_array('DEBITABLE', $status)) {
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
     * @throws Exception
     * @since 1.0.22
     * @since 1.1.22
     * @since 1.2.0
     */
    public function getPaymentSpecCount($paymentIdOrPaymentObject)
    {
        $countObject = $this->getPaymentSpecByStatus($paymentIdOrPaymentObject);
        $returnedCountObject = [];
        foreach ($countObject as $status => $theArray) {
            $returnedCountObject[$status] = is_array($theArray) ? count($theArray) : 0;
        }

        return $returnedCountObject;
    }

    /**
     * Returns a complete payment spec grouped by status. This function does not merge articles, even if there are
     * multiple rows with the same article number. This normally indicates order modifications, so the are returned raw
     * as is.
     *
     * @param $paymentIdOrPaymentObject
     * @param bool $getAsTable
     * @return array
     * @throws Exception
     * @since Ever.
     * @deprecated 1.3.21 Use getPaymentDiffByStatus instead!
     */
    public function getPaymentSpecByStatus($paymentIdOrPaymentObject, $getAsTable = false)
    {
        return $this->getPaymentDiffByStatus($paymentIdOrPaymentObject, $getAsTable);
    }

    /**
     * Get merged payment diff.
     *
     * @param $paymentIdOrPaymentObject
     * @param bool $getAsTable
     * @return array|mixed
     * @throws Exception
     * @since 1.3.21
     */
    public function getPaymentDiffByStatus($paymentIdOrPaymentObject, $getAsTable = false)
    {
        $usePayment = $paymentIdOrPaymentObject;
        // Current specs available: AUTHORIZE, DEBIT, CREDIT, ANNUL
        $orderLinesByStatus = [
            'AUTHORIZE' => [],
            'DEBIT' => [],
            'CREDIT' => [],
            'ANNUL' => [],
        ];
        if (is_string($paymentIdOrPaymentObject)) {
            // Do not cache this moment, as the order statuses may be updated faster than the cache deprecates!
            $usePayment = $this->getPayment($paymentIdOrPaymentObject, false);
        }
        if (is_object($usePayment) && isset($usePayment->id) && isset($usePayment->paymentDiffs)) {
            $paymentDiff = $usePayment->paymentDiffs;
            // Single row diff should be pushed up to a proper array.
            if (isset($paymentDiff->type)) {
                $paymentDiff = [$paymentDiff->type => $paymentDiff];
            }
            if (is_array($paymentDiff) && count($paymentDiff)) {
                // Inspired by DataGert.
                foreach ($paymentDiff as $type => $paymentDiffObject) {
                    $orderLinesByStatus = $this->getMergedPaymentDiff(
                        $paymentDiffObject->paymentSpec->specLines,
                        $orderLinesByStatus,
                        $paymentDiffObject->type
                    );
                }
            }
        }

        // We use this table to collect valuable information, like what's left of each object.
        $asTable = $this->getPaymentDiffAsTable($orderLinesByStatus);

        if ($getAsTable) {
            return $asTable;
        }

        return $orderLinesByStatus;
    }


    ////// HOSTED FLOW

    /**
     * Merge a "getPayment" by each payment diff. This function uses way better keying than the
     * prior method getPaymentByStatuses() which supports "duplicate articles with diffing prices".
     *
     * @param $paymentRows
     * @param $paymentDiff
     * @param $paymentType
     * @return mixed
     * @since 1.3.22
     */
    public function getMergedPaymentDiff($paymentRows, $paymentDiff, $paymentType)
    {
        // Convert to correct row, if only one.
        if (isset($paymentRows->id)) {
            $paymentRows = [$paymentRows];
        }
        if (!isset($paymentDiff[$paymentType])) {
            $paymentDiff[$paymentType] = [];
        }

        foreach ($paymentRows as $row) {
            $isSameArray = [];
            $currentQuantity = $row->quantity;
            $currentId = $row->id;
            // Purge totalVatAmount and totalAmount from this row as the totals are based on the price
            // and quantity in the current object, which will mismatch on comparison. While merging
            // the blocks, we don't need those values - they must be recalculated later on. The same rule
            // is applied to the quantity since either block may have different quantity counts.
            $rowAsArray = $this->getPurgedPaymentRow((array)$row);

            foreach ($paymentDiff[$paymentType] as $diffIndex => $diffData) {
                $isSameArray = array_intersect($rowAsArray, $diffData);
                if (count($isSameArray) === count($rowAsArray)) {
                    $paymentDiff[$paymentType][$diffIndex]['quantity'] += $currentQuantity;
                    break;
                }
            }
            if (!count($isSameArray) || count($isSameArray) !== count($rowAsArray)) {
                // Recreate the quantity and the id, as it was earlier removed to avoid bad keying.
                $rowAsArray['quantity'] = $currentQuantity;
                $rowAsArray['id'] = $currentId;
                $paymentDiff[$paymentType][] = $rowAsArray;
            }
        }

        $paymentDiff[$paymentType] = $this->getRecalculatedPaymentDiff($paymentDiff[$paymentType]);

        return $paymentDiff;
    }

    ////// MASTER SHOP FLOWS - THE OTHER ONES

    /**
     * @param $row
     * @param array $alsoCleanBy Also include this on special needs.
     * @param bool $excludeDefaults
     * @return mixed
     * @since 1.3.23
     */
    private function getPurgedPaymentRow($row, $alsoCleanBy = [], $excludeDefaults = false)
    {
        if (!$excludeDefaults) {
            $cleanBy = array_merge($this->getPaymentDefaultPurge, $alsoCleanBy);
        } else {
            $cleanBy = $alsoCleanBy;
        }

        foreach ($cleanBy as $key) {
            if (isset($row[$key])) {
                unset($row[$key]);
            }
        }
        return $row;
    }

    /**
     * Pick up each order article and recalculate totalAmount-data. Supports half recursion.
     *
     * @param $orderRowArray
     * @param bool $excludeZeroQuantity
     * @return array
     * @since 1.3.21
     */
    private function getRecalculatedPaymentDiff($orderRowArray, $excludeZeroQuantity = false)
    {
        $return = [];

        if (is_array($orderRowArray) && count($orderRowArray)) {
            foreach ($orderRowArray as $idx => $row) {
                if (!$row['quantity'] && $excludeZeroQuantity) {
                    unset($orderRowArray[$idx]);
                    continue;
                }

                if (isset($row['artNo'])) {
                    $orderRowArray[$idx]['totalVatAmount'] = $this->getTotalVatAmount(
                        isset($row['unitAmountWithoutVat']) ? $row['unitAmountWithoutVat'] : 0,
                        isset($row['vatPct']) ? $row['vatPct'] : 0,
                        isset($row['quantity']) ? $row['quantity'] : 0
                    );
                    $orderRowArray[$idx]['totalAmount'] = $this->getTotalAmount(
                        isset($row['unitAmountWithoutVat']) ? $row['unitAmountWithoutVat'] : 0,
                        isset($row['vatPct']) ? $row['vatPct'] : 0,
                        isset($row['quantity']) ? $row['quantity'] : 0
                    );
                }
            }
            // On exclusion we need to resort the indexes.
            if ($excludeZeroQuantity) {
                sort($orderRowArray);
            }
            $return = $orderRowArray;
        }
        return (array)$return;
    }

    /////////// AFTER SHOP ROUTINES

    /**
     * Render a table with completed data about each orderline.
     *
     * @param $orderlineStatuses
     * @return array
     * @throws Exception
     * @since 1.3.21
     */
    public function getPaymentDiffAsTable($orderlineStatuses)
    {
        $tableStatusList = [];

        if (is_array($orderlineStatuses) && count($orderlineStatuses) && isset($orderlineStatuses['AUTHORIZE'])) {
            $authorizeObject = $orderlineStatuses['AUTHORIZE'];
            foreach ($authorizeObject as $artRow) {
                $tableStatusList[] = $this->setPaymentDiffTable($artRow, $orderlineStatuses);
            }
        }

        foreach ($tableStatusList as $idx => $artRow) {
            $tableStatusList[$idx]['ANNULLABLE'] = $artRow['AUTHORIZE'] - $artRow['DEBIT'] - $artRow['ANNUL'];
            $tableStatusList[$idx]['DEBITABLE'] = $artRow['AUTHORIZE'] - $artRow['DEBIT'] - $artRow['ANNUL'];
            $tableStatusList[$idx]['CREDITABLE'] = $artRow['DEBIT'] - $artRow['CREDIT'];
        }

        $tableStatusList = $this->getMissingPaymentDiffRows($orderlineStatuses, $tableStatusList);

        return $tableStatusList;
    }

    /**
     * Compile payment status diffs as a horizontal table.
     *
     * @param $artRow
     * @param $orderlineStatuses
     * @return array
     * @throws Exception
     * @since 1.3.21
     */
    private function setPaymentDiffTable($artRow, $orderlineStatuses)
    {
        if (!is_array($artRow)) {
            throw new Exception(
                sprintf('%s exception: Article row is not an array', __FUNCTION__),
                500
            );
        }

        $debited = $this->getOrderRowMatch($artRow, $orderlineStatuses['DEBIT']);
        $credited = $this->getOrderRowMatch($artRow, $orderlineStatuses['CREDIT']);
        $annulled = $this->getOrderRowMatch($artRow, $orderlineStatuses['ANNUL']);

        $return = [
            'artNo' => $artRow['artNo'],
            'description' => $artRow['description'],
            'unitMeasure' => $artRow['unitMeasure'],
            'unitAmountWithoutVat' => isset($artRow['unitAmountWithoutVat']) ? $artRow['unitAmountWithoutVat'] : 0,
            'vatPct' => isset($artRow['vatPct']) ? $artRow['vatPct'] : 0,
            'AUTHORIZE' => isset($artRow['quantity']) ? $artRow['quantity'] : 0,
            'DEBIT' => isset($debited['quantity']) ? $debited['quantity'] : 0,
            'CREDIT' => isset($credited['quantity']) ? $credited['quantity'] : 0,
            'ANNUL' => isset($annulled['quantity']) ? $annulled['quantity'] : 0,
        ];

        return $return;
    }

    /**
     * Compare two arrays (order rows) and return a full match.
     *
     * @param $artRow
     * @param $matchList
     * @return array|mixed
     * @since 1.3.22
     */
    private function getOrderRowMatch($artRow, $matchList)
    {
        $return = [];

        if (is_array($matchList) && count($matchList)) {
            foreach ($matchList as $matchRow) {
                if (!is_array($artRow)) {
                    // When something went wrong with an expected array.
                    continue;
                }
                $currentArray = array_intersect(
                    $this->getPurgedPaymentRow($artRow),
                    $this->getPurgedPaymentRow($matchRow)
                );
                if (count($currentArray) === count($this->getPurgedPaymentRow($artRow))) {
                    $return = $matchRow;
                    break;
                }
            }
        }

        return $return;
    }

    /**
     * Find the rest of an order that was added after authorization (like "own credited rows).
     *
     * @param $orderlineStatuses
     * @param $tableStatusList
     * @return array
     * @since 1.3.21
     */
    private function getMissingPaymentDiffRows($orderlineStatuses, $tableStatusList)
    {
        foreach ($orderlineStatuses as $type => $contentArray) {
            if ($type === 'AUTHORIZE') {
                continue;
            }
            foreach ($contentArray as $artRow) {
                if (!$this->getIsInAuthorize($artRow, $orderlineStatuses['AUTHORIZE'])) {
                    $setRow = [
                        'artNo' => $artRow['artNo'],
                        'description' => $artRow['description'],
                        'unitMeasure' => $artRow['unitMeasure'],
                        'unitAmountWithoutVat' => $artRow['unitAmountWithoutVat'],
                        'vatPct' => $artRow['vatPct'],
                        'AUTHORIZE' => 0,
                        'DEBIT' => 0,
                        'CREDIT' => 0,
                        'ANNUL' => 0,
                    ];
                    $setRow[strtoupper($type)] += $artRow['quantity'];
                    $tableStatusList[] = $setRow;
                }
            }
        }
        return $tableStatusList;
    }

    /**
     * Check if an article is located in, what we expect, the AUTHORIZE object.
     *
     * @param $paymentDiffArtRow
     * @param $authorizeObject
     * @return bool
     * @since 1.3.22
     */
    private function getIsInAuthorize($paymentDiffArtRow, $authorizeObject)
    {
        $return = false;
        if ($this->getOrderRowMatch($paymentDiffArtRow, $authorizeObject)) {
            $return = true;
        }
        return $return;
    }

    /**
     * Set keys to keep in purger when using aftershop. Setting keys will reverse the way that
     * setPurgeGetPaymentKeys work, by remove the requested keys and keep the rest for the purger.
     *
     * @param array $keepKeys
     * @return array Confirmation return.
     * @throws Exception
     * @since 1.3.23
     */
    public function setGetPaymentMatchKeys($keepKeys = ['title', 'description', 'unitAmountWithoutVat'])
    {
        $return = [];
        if (is_string($keepKeys)) {
            // If this is a string, make sure that the string belongs to a predefined spec.
            $useKeys = $this->getPaymentSpecKeyScheme($keepKeys, true);
        } else {
            if (is_array($keepKeys) && !count($keepKeys)) {
                $useKeys = $this->getPaymentSpecKeyScheme('tiny');
            } else {
                $useKeys = $keepKeys;
            }
        }

        $largest = $this->getPaymentSpecKeyScheme('simplified');

        foreach ($largest as $key) {
            if (!in_array($key, $useKeys)) {
                $return[] = $key;
            }
        }

        // For memory in aftershop.
        $this->getPaymentDefaultUnPurge = $useKeys;
        $this->setPurgeGetPaymentKeys($return);
        return $this->getPaymentDefaultPurge;
    }

    /**
     * @param array $keys
     * @throws Exception
     * @since 1.3.23
     */
    public function setPurgeGetPaymentKeys($keys = ['totalVatAmount', 'totalAmount', 'quantity', 'id'])
    {
        if (is_array($keys)) {
            // Touch on changes only
            if (count($keys)) {
                $this->getPaymentDefaultPurge = $keys;
                $this->getPaymentDefaultPurgeSet = true;
            }
        } else {
            throw new Exception(sprintf('Keys sent to %s must be a function!', __FUNCTION__));
        }
    }

    /**
     * Private functions made public.
     *
     * @return array
     * @since 1.3.23
     */
    public function getPaymentKeysForPurge()
    {
        return $this->getPaymentDefaultPurge;
    }

    /**
     * Private functions made public.
     *
     * @return array
     * @since 1.3.23
     */
    public function getPaymentKeysUnPurgable()
    {
        return $this->getPaymentDefaultUnPurge;
    }

    /**
     * Returns the preferred transaction id if any
     *
     * @return string
     * @since 1.0.22
     * @since 1.1.22
     * @since 1.2.0
     */
    public function getAfterShopPreferredTransactionId()
    {
        return $this->afterShopPreferredTransactionId;
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
    public function setAfterShopPreferredTransactionId($preferredTransactionId)
    {
        if (!empty($preferredTransactionId)) {
            $this->afterShopPreferredTransactionId = $preferredTransactionId;
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
    public function getAfterShopOrderId()
    {
        return $this->afterShopOrderId;
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
    public function setAfterShopOrderId($orderId)
    {
        if (!empty($orderId)) {
            $this->afterShopOrderId = $orderId;
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
    public function getAfterShopInvoiceId()
    {
        return $this->afterShopInvoiceId;
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
    public function setAfterShopInvoiceId($invoiceId)
    {
        if (!empty($invoiceId)) {
            $this->afterShopInvoiceId = $invoiceId;
        }
    }

    /**
     * Returns the current customer id (for aftershop)
     *
     * @return string
     */
    public function getCustomerId()
    {
        return $this->customerId;
    }

    /**
     * Aftershop specific setting to add a customer id to the invoice (can be unset by sending empty value)
     *
     * @param $customerId
     */
    public function setCustomerId($customerId = '')
    {
        $this->customerId = $customerId;
    }

    /**
     * Identical to paymentFinalize but used for testing errors
     *
     * @throws Exception
     */
    public function paymentFinalizeTest()
    {
        if (defined('TEST_OVERRIDE_AFTERSHOP_PAYLOAD') && $this->current_environment == RESURS_ENVIRONMENTS::TEST) {
            $this->postService('finalizePayment', unserialize(TEST_OVERRIDE_AFTERSHOP_PAYLOAD));
        }
    }

    /**
     * Shadow function for paymentFinalize
     *
     * @param string $paymentId
     * @param array $customPayloadItemList
     * @param bool $runOnce
     * @param bool $skipSpecValidation
     * @return bool
     * @throws Exception
     */
    public function finalizePayment(
        $paymentId = '',
        $customPayloadItemList = [],
        $runOnce = false,
        $skipSpecValidation = false
    ) {
        return $this->paymentFinalize($paymentId, $customPayloadItemList, $runOnce, $skipSpecValidation);
    }

    /**
     * Aftershop Payment Finalization (DEBIT)
     *
     * @param $paymentId
     * @param array $customPayloadItemList
     * @param bool $runOnce Only run this once, throw second time
     * @param bool $skipSpecValidation Set to true, you're skipping validation of order rows.
     * @return bool
     * @throws Exception
     * @since 1.0.22
     * @since 1.1.22
     * @since 1.2.0
     */
    public function paymentFinalize(
        $paymentId = '',
        $customPayloadItemList = [],
        $runOnce = false,
        $skipSpecValidation = false
    ) {
        if (!is_array($customPayloadItemList)) {
            $customPayloadItemList = [];
        }

        $this->setAftershopPaymentValidation($skipSpecValidation);

        try {
            $afterShopObject = $this->getAfterShopObjectByPayload(
                $paymentId,
                $customPayloadItemList,
                RESURS_AFTERSHOP_RENDER_TYPES::FINALIZE
            );
        } catch (Exception $afterShopObjectException) {
            // No rows to finalize? Check if this was auto debited by internal rules, or throw back error.
            if ($afterShopObjectException->getCode() === RESURS_EXCEPTIONS::BOOKPAYMENT_NO_BOOKDATA &&
                (
                    $this->getOrderStatusByPayment($paymentId) &
                    RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_AUTOMATICALLY_DEBITED
                )
            ) {
                return true;
            }
            throw $afterShopObjectException;
        }

        $cachedPayment = $this->getPaymentCached();
        if (!is_null($cachedPayment) &&
            is_object($cachedPayment) &&
            $cachedPayment->id === $paymentId) {
            if ($this->isFrozen($cachedPayment)) {
                // Throw it like Resurs Bank one step earlier. Since we do a getPayment
                // before the finalization we do not have make an extra call if payment status
                // is frozen.
                throw new ResursException(
                    'EComPHP can not finalize frozen payments',
                    RESURS_EXCEPTIONS::ECOMMERCEERROR_NOT_ALLOWED_IN_CURRENT_STATE
                );
            }
        }
        $this->aftershopPrepareMetaData($paymentId);
        try {
            $afterShopResponseCode = $this->postService('finalizePayment', $afterShopObject, true);
            if ($afterShopResponseCode >= 200 && $afterShopResponseCode < 300) {
                $this->resetPayload();

                return true;
            }
        } catch (Exception $finalizationException) {
            // Possible invoice error codes:
            // 28 = ECOMMERCEERROR_NOT_ALLOWED_INVOICE_ID
            // 29 ECOMMERCEERROR_ALREADY_EXISTS_INVOICE_ID
            if ((
                    $finalizationException->getCode() === RESURS_EXCEPTIONS::ECOMMERCEERROR_ALREADY_EXISTS_INVOICE_ID ||
                    $finalizationException->getCode() === RESURS_EXCEPTIONS::ECOMMERCEERROR_NOT_ALLOWED_INVOICE_ID
                ) &&
                !$this->isFlag('SKIP_AFTERSHOP_INVOICE_CONTROL')
            ) {
                if (!$runOnce) {
                    $this->getNextInvoiceNumberByDebits(5);

                    return $this->paymentFinalize($paymentId, $customPayloadItemList, true);
                } else {
                    // One time failsafe rescue mode.
                    if ($this->isFlag('AFTERSHOP_RESCUE_INVOICE') && $this->afterShopInvoiceId > 0) {
                        // Reset after once run.
                        $this->getNextInvoiceNumberByDebits(5);

                        return $this->paymentFinalize($paymentId, $customPayloadItemList, true);
                    }
                }
            }

            throw new ResursException(
                $finalizationException->getMessage(),
                $finalizationException->getCode(),
                $finalizationException
            );
        }

        return false;
    }

    /**
     * Get configuration of payment spec validation during aftershop actions.
     *
     * @param bool $skipValidationStatus
     */
    private function setAftershopPaymentValidation($skipValidationStatus = false)
    {
        // Flag overriders.
        if ($this->isFlag('SKIP_AFTERSHOP_VALIDATION')) {
            $skipValidationStatus = true;
        }

        $this->skipAfterShopPaymentValidation = $skipValidationStatus;
    }

    /**
     * Create an afterShopFlow object to use with the afterShop flow
     *
     * @param string $paymentId
     * @param array $customPayloadItemList
     * @param int $payloadType
     *
     * @return array
     * @throws Exception
     * @since 1.0.22
     * @since 1.1.22
     * @since 1.2.0
     */
    private function getAfterShopObjectByPayload(
        $paymentId = '',
        $customPayloadItemList = [],
        $payloadType = RESURS_AFTERSHOP_RENDER_TYPES::NONE
    ) {
        $finalAfterShopSpec = [
            'paymentId' => $paymentId,
        ];

        // getPaymentDiffByStatus, replaces getPaymentSpecByStatus
        $specStatus = $this->getPaymentDiffByStatus($paymentId);
        $specStatusTable = $this->getPaymentDiffAsTable($specStatus);

        if (!is_array($customPayloadItemList)) {
            // Make sure this is correct
            $customPayloadItemList = [];
        }
        if ($this->specLineCustomization && !count($customPayloadItemList)) {
            $customPayloadItemList = $this->SpecLines;
        }
        $storedPayment = $this->getPayment($paymentId);
        $paymentMethod = $storedPayment->paymentMethodId;
        $paymentMethodData = $this->getPaymentMethodSpecific($paymentMethod);
        $paymentSpecificType = strtoupper(
            isset($paymentMethodData->specificType) ? $paymentMethodData->specificType : null
        );
        if ($paymentSpecificType == 'INVOICE') {
            $finalAfterShopSpec['orderDate'] = date('Y-m-d', time());
            $finalAfterShopSpec['invoiceDate'] = date('Y-m-d', time());
            $extRef = $this->getAfterShopInvoiceExtRef();
            $invoiceNumber = $this->getNextInvoiceNumber();

            if ($this->isFlag('TEST_INVOICE') && $this->current_environment === RESURS_ENVIRONMENTS::TEST) {
                // Make us fail intentionally during test. This ID usually exists at our end.
                $invoiceNumber = 1003036;
            }

            if ($this->isFlag('AFTERSHOP_STATIC_INVOICE') || $this->isFirstInvoiceId) {
                $finalAfterShopSpec['invoiceId'] = $invoiceNumber;
            }

            if (!empty($extRef)) {
                $this->addMetaData($paymentId, 'invoiceExtRef', $extRef);
            }
        }

        // Rendered order spec, use when customPayloadItemList is not set, to handle full orders
        $actualEcommerceOrderSpec = $this->sanitizeAfterShopSpec($storedPayment, $payloadType);
        $finalAfterShopSpec['createdBy'] = $this->getCreatedBy();
        $this->renderPaymentSpec(RESURS_FLOW_TYPES::SIMPLIFIED_FLOW);
        try {
            // Try to fetch internal order data.
            /** @noinspection PhpUnusedLocalVariableInspection */
            $this->setFlag('USE_AFTERSHOP_RENDERING', true);
            $orderDataArray = $this->getOrderData();
            $this->deleteFlag('USE_AFTERSHOP_RENDERING');
            if (!count($orderDataArray)) {
                $this->SpecLines += $this->objectsIntoArray($actualEcommerceOrderSpec);
            }
        } catch (Exception $getOrderDataException) {
            // If there is no payload, make sure we'll render this from the current payment
            if ($getOrderDataException->getCode() == RESURS_EXCEPTIONS::BOOKPAYMENT_NO_BOOKDATA &&
                !count($customPayloadItemList)
            ) {
                //array_merge($this->SpecLines, $actualEcommerceOrderSpec);
                $this->SpecLines += $this->objectsIntoArray($actualEcommerceOrderSpec); // Convert objects
            }
        }

        // Still-Empty Indicator.
        if (!count($customPayloadItemList)) {
            // As we currently want to be able to handle partial orders this part tells ecom to use the actual order
            // spec if the custom payload item list is empty.
            if (!count($this->SpecLines) &&
                !count($specStatus['DEBIT']) &&
                !count($specStatus['CREDIT']) &&
                !count($specStatus['ANNUL'])
            ) {
                $customPayloadItemList = $actualEcommerceOrderSpec;
            } else {
                // We should probably give up here and go for the fully merged diff.
                $customPayloadItemList = $specStatus['AUTHORIZE'];
            }
        }

        if (count($customPayloadItemList)) {
            // Is $customPayloadItemList correctly formatted?
            switch ($payloadType) {
                case RESURS_AFTERSHOP_RENDER_TYPES::FINALIZE:
                    $customPayloadItemListValidated = $this->getValidatedAftershopRows(
                        $specStatusTable,
                        $customPayloadItemList,
                        'debit'
                    );
                    break;
                case RESURS_AFTERSHOP_RENDER_TYPES::ANNUL:
                    $customPayloadItemListValidated = $this->getValidatedAftershopRows(
                        $specStatusTable,
                        $customPayloadItemList,
                        'annul'
                    );
                    break;
                case RESURS_AFTERSHOP_RENDER_TYPES::CREDIT:
                    $customPayloadItemListValidated = $this->getValidatedAftershopRows(
                        $specStatusTable,
                        $customPayloadItemList,
                        'credit'
                    );
                    break;
                default:
                    $customPayloadItemListValidated = $customPayloadItemList;
            };

            $this->SpecLines = $customPayloadItemListValidated;
        }
        $this->renderPaymentSpec(RESURS_FLOW_TYPES::SIMPLIFIED_FLOW);
        $this->setFlag('USE_AFTERSHOP_RENDERING', true);
        $orderDataArray = $this->getOrderData();
        $this->deleteFlag('USE_AFTERSHOP_RENDERING');

        if (isset($orderDataArray['specLines'])) {
            $orderDataArray['partPaymentSpec'] = $orderDataArray;
        }

        $finalAfterShopSpec += $orderDataArray;

        return $finalAfterShopSpec;
    }

    /**
     * Return the invoice external reference
     *
     * @return string
     * @since 1.0.22
     * @since 1.1.22
     * @since 1.2.0
     */
    public function getAfterShopInvoiceExtRef()
    {
        return $this->afterShopInvoiceExtRef;
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
    public function setAfterShopInvoiceExtRef($invoiceExtRef)
    {
        if (!empty($invoiceExtRef)) {
            $this->afterShopInvoiceExtRef = $invoiceExtRef;
        }
    }

    /**
     * Get next invoice number - and initialize if not set.
     *
     * @param bool $initInvoice Allow to initialize new invoice number if not set, start with 1
     * @param int $firstInvoiceNumber Initializes invoice number sequence with this value if not set and requested
     *
     * @return int Returns If 0, the set up might have failed
     * @throws Exception
     * @since 1.0.0
     * @since 1.1.0
     */
    public function getNextInvoiceNumber($initInvoice = true, $firstInvoiceNumber = null)
    {
        $this->InitializeServices();
        // Initial invoice number
        $currentInvoiceNumber = 0;
        $invoiceInvokation = false;

        // Get the current from e-commerce
        try {
            $peekSequence = $this->postService('peekInvoiceSequence');
            // Check if nextInvoiceNumber is missing
            if (isset($peekSequence->nextInvoiceNumber)) {
                $currentInvoiceNumber = $peekSequence->nextInvoiceNumber;
            } else {
                $firstInvoiceNumber = 1;
                $this->isFirstInvoiceId = true;
            }
        } catch (Exception $e) {
            if (is_null($firstInvoiceNumber) && $initInvoice) {
                $firstInvoiceNumber = 1;
                $this->isFirstInvoiceId = true;
            }
        }

        // Continue look at initInvoice, but this time take a look at the requested $firstInvoiceNumber
        if ($initInvoice) {
            // If the requested invoice number is a numeric and over 0, set it as next invoice number
            if (!is_null($firstInvoiceNumber) && is_numeric($firstInvoiceNumber) && $firstInvoiceNumber > 0) {
                $this->postService('setInvoiceSequence', ['nextInvoiceNumber' => $firstInvoiceNumber]);
                $invoiceInvokation = true;
            }
        }

        // If $invoiceInvokation is true, we'll know that something happened under this run
        if ($invoiceInvokation) {
            // So in that case, request it again
            try {
                /** @noinspection PhpUndefinedFieldInspection */
                $currentInvoiceNumber = $this->postService('peekInvoiceSequence')->nextInvoiceNumber;
            } catch (Exception $e) {
            }
        }

        return $currentInvoiceNumber;
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
     * @throws Exception
     * @since 1.0.1
     * @since 1.1.1
     */
    public function addMetaData($paymentId = '', $metaDataKey = '', $metaDataValue = '')
    {
        if (empty($paymentId)) {
            throw new ResursException('Payment id is not set.');
        }
        if (empty($metaDataKey) || empty($metaDataValue)) {
            throw new ResursException('Can not have empty meta information.');
        }

        $customErrorMessage = '';
        try {
            $checkPayment = $this->getPayment($paymentId);
        } catch (Exception $e) {
            $customErrorMessage = $e->getMessage();
        }
        if (!isset($checkPayment->id) && !empty($customErrorMessage)) {
            throw new ResursException($customErrorMessage);
        }
        $metaDataArray = [
            'paymentId' => $paymentId,
            'key' => $metaDataKey,
            'value' => $metaDataValue,
        ];
        /** @noinspection PhpUndefinedMethodInspection */
        $metaDataSoapRequest = $this->CURL->request($this->getServiceUrl('addMetaData'));
        $metaDataSoapRequest->addMetaData($metaDataArray);
        // Old request method is bailing out on wrong soapcall.
        $metaDataRequestCode = $metaDataSoapRequest->getCode();
        if ($metaDataRequestCode >= 200 && $metaDataRequestCode <= 250) {
            return true;
        }

        return false;
    }

    /**
     * Sanitize a payment spec from a payment id or a prepared getPayment object and return filtered depending on the
     * requested aftershop type
     *
     * @param string $paymentIdOrPaymentObjectData
     * @param int $renderType RESURS_AFTERSHOP_RENDER_TYPES as unique type or bit mask
     * @return array
     * @throws Exception
     * @since First book of moses.
     */
    public function sanitizeAfterShopSpec(
        $paymentIdOrPaymentObjectData = '',
        $renderType = RESURS_AFTERSHOP_RENDER_TYPES::NONE
    ) {

        $returnSpecObject = [];

        $this->BIT->setBitStructure(
            [
                'FINALIZE' => RESURS_AFTERSHOP_RENDER_TYPES::FINALIZE,
                'CREDIT' => RESURS_AFTERSHOP_RENDER_TYPES::CREDIT,
                'ANNUL' => RESURS_AFTERSHOP_RENDER_TYPES::ANNUL,
                'AUTHORIZE' => RESURS_AFTERSHOP_RENDER_TYPES::AUTHORIZE,
            ]
        );

        $sanitizedPaymentDiff = $this->getPaymentDiffByAbility($paymentIdOrPaymentObjectData);
        $canDebitObject = $sanitizedPaymentDiff['DEBIT'];
        $canCreditObject = $sanitizedPaymentDiff['CREDIT'];
        $canAnnulObject = $sanitizedPaymentDiff['ANNUL'];

        if ($this->BIT->isBit(RESURS_AFTERSHOP_RENDER_TYPES::FINALIZE, $renderType)) {
            $returnSpecObject = $canDebitObject;
        } elseif ($this->BIT->isBit(RESURS_AFTERSHOP_RENDER_TYPES::CREDIT, $renderType)) {
            $returnSpecObject = $canCreditObject;
        } elseif ($this->BIT->isBit(RESURS_AFTERSHOP_RENDER_TYPES::ANNUL, $renderType)) {
            $returnSpecObject = $canAnnulObject;
        }

        return $returnSpecObject;
    }

    /**
     * @param $paymentIdOrPaymentObject
     * @return array
     * @throws Exception
     * @since 1.3.21
     */
    public function getPaymentDiffByAbility($paymentIdOrPaymentObject)
    {
        $paymentDiffTable = $this->getPaymentDiffByStatus($paymentIdOrPaymentObject, true);

        $orderLinesByStatus = [
            'DEBIT' => [],
            'CREDIT' => [],
            'ANNUL' => [],
        ];

        foreach ($paymentDiffTable as $row) {
            $annullable = isset($row['ANNULLABLE']) ? $row['ANNULLABLE'] : 0;
            $debitable = isset($row['DEBITABLE']) ? $row['DEBITABLE'] : 0;
            $creditable = isset($row['CREDITABLE']) ? $row['CREDITABLE'] : 0;

            $newOrderRow = $this->getPurgedPaymentRow(
                $row,
                [
                    'AUTHORIZE',
                    'DEBIT',
                    'CREDIT',
                    'ANNUL',
                    'ANNULLABLE',
                    'DEBITABLE',
                    'CREDITABLE',
                ]
            );

            $orderLinesByStatus['DEBIT'][] = array_merge($newOrderRow, ['quantity' => $debitable]);
            $orderLinesByStatus['CREDIT'][] = array_merge($newOrderRow, ['quantity' => $creditable]);
            $orderLinesByStatus['ANNUL'][] = array_merge($newOrderRow, ['quantity' => $annullable]);
        }

        $orderLinesByStatus['DEBIT'] = $this->getRecalculatedPaymentDiff($orderLinesByStatus['DEBIT'], true);
        $orderLinesByStatus['ANNUL'] = $this->getRecalculatedPaymentDiff($orderLinesByStatus['ANNUL'], true);
        $orderLinesByStatus['CREDIT'] = $this->getRecalculatedPaymentDiff($orderLinesByStatus['CREDIT'], true);

        return $orderLinesByStatus;
    }

    /**
     * Get "Created by" if set (used by aftershop)
     *
     * @return string
     * @since 1.0.0
     * @since 1.1.0
     */
    public function getCreatedBy()
    {
        // Allow clients to skip clientname (if client name is confusing in paymentadmin) by setting
        // flag CREATED_BY_NO_CLIENT_NAME. If unset, ecomphp_decimalVersionNumber will be shown.
        if (!$this->isFlag('CREATED_BY_NO_CLIENT_NAME')) {
            if (!$this->userSetClientName) {
                $createdBy = $this->realClientName . '_' . $this->getVersionNumber(true);
            } else {
                $createdBy = $this->realClientName;
            }

            // If logged in user is set by client or plugin, add this to the createdBy string.
            if (!empty($this->loggedInUser)) {
                $createdBy .= '/' . $this->loggedInUser;
            }
        } else {
            // If client or plugin chose to exclude client name, we'll still look for a logged in user.
            if (!empty($this->loggedInUser)) {
                $createdBy = $this->loggedInUser;
            } else {
                // If no logged in user is set, ecomphp will mark the createdBy-string with an indication
                // that something or someone on the remote has done something to the order. This is
                // done to clarify that this hasn't been done with a regular ResursBank-local interface.
                $createdBy = 'EComPHP-RemoteClientAction';
            }
        }

        return $createdBy;
    }

    /**
     * Return the final payload order data array
     *
     * @return array
     * @throws Exception
     * @since 1.0.22
     * @since 1.1.22
     * @since 1.2.0
     */
    public function getOrderData()
    {
        $this->preparePayload();

        return isset($this->Payload['orderData']) ? $this->Payload['orderData'] : [];
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
    public function deleteFlag($flagKey)
    {
        if ($this->hasFlag($flagKey)) {
            unset($this->internalFlags[$flagKey]);
        }
    }

    /**
     * Validating of requested aftershop order rows.
     *
     * @param $currentPaymentSpecTable
     * @param $currentOrderLines
     * @param $type
     * @return array
     * @throws Exception
     * @since 1.3.21
     */
    private function getValidatedAftershopRows($currentPaymentSpecTable, $currentOrderLines, $type)
    {
        $return = [];
        $id = 0;

        foreach ($currentOrderLines as $idx => $orderRow) {
            // Count unsafe payment objects per row.
            $isUnsafePaymentObject = 0;

            $realQuantity = null;
            $realUnitAmount = null;
            $realVatPct = null;

            $realData = [];
            if ($this->skipAfterShopPaymentValidation) {
                foreach ($this->getPaymentDefaultUnPurge as $field) {
                    if (isset($orderRow[$field])) {
                        $realData[$field] = $orderRow[$field];
                    }
                }
                $realQuantity = $orderRow['quantity'];
                $realUnitAmount = $orderRow['unitAmountWithoutVat'];
                $realVatPct = $orderRow['vatPct'];
            }

            foreach ($currentPaymentSpecTable as $statusRow) {
                if ($type === 'credit') {
                    $quantityMatch = isset($statusRow['CREDITABLE']) ? $statusRow['CREDITABLE'] : 0;
                } elseif ($type === 'annul') {
                    $quantityMatch = isset($statusRow['ANNULLABLE']) ? $statusRow['ANNULLABLE'] : 0;
                } elseif ($type === 'debit') {
                    $quantityMatch = isset($statusRow['DEBITABLE']) ? $statusRow['DEBITABLE'] : 0;
                } elseif ($type === 'authorize') {
                    $quantityMatch = isset($statusRow['AUTHORIZE']) ? $statusRow['AUTHORIZE'] : 0;
                } else {
                    $quantityMatch = 0;
                }

                if (!$quantityMatch) {
                    continue;
                }

                // If the requested quantity is legit (below the maximum matchable amount)
                // we should use the requested quantity.
                if ($orderRow['quantity'] <= $quantityMatch) {
                    $useQuantity = $orderRow['quantity'];
                } else {
                    // If the requested quantity is set too high (over the maximum matchable amount)
                    // we should lower the value to the max-allowed quantity instead.
                    $useQuantity = $quantityMatch;
                }

                $this->checkUnsafePaymentObject($isUnsafePaymentObject);

                if ((!isset($orderRow['unitAmountWithoutVat']) || empty($orderRow['unitAmountWithoutVat'])) &&
                    $orderRow['artNo'] == $statusRow['artNo']
                ) {
                    $orderRow['unitAmountWithoutVat'] = $statusRow['unitAmountWithoutVat'];
                    $isUnsafePaymentObject++;
                }

                if ((!isset($orderRow['description']) || empty($orderRow['description'])) &&
                    $orderRow['artNo'] == $statusRow['artNo']
                ) {
                    $orderRow['description'] = $statusRow['description'];
                    $isUnsafePaymentObject++;
                }

                if ($this->skipAfterShopPaymentValidation) {
                    if (count($realData)) {
                        foreach ($realData as $key => $value) {
                            // Protect a few fields from mistakes that is already considered below in the
                            // validation parts.
                            if (!in_array($key, ['artNo', 'description', 'quantity'])) {
                                $orderRow[$key] = $value;
                            }
                        }
                    }
                    $useQuantity = $realQuantity;
                    $useUnitAmount = $realUnitAmount;
                    $useVatPct = $realVatPct;
                }

                // Validation is based on same article, description and price.
                // Besides this the validation is also
                if ($orderRow['artNo'] == $statusRow['artNo'] &&
                    $orderRow['description'] == $statusRow['description'] &&
                    (
                        $orderRow['unitAmountWithoutVat'] == $statusRow['unitAmountWithoutVat'] ||
                        $this->skipAfterShopPaymentValidation
                    ) &&
                    $useQuantity > 0
                ) {
                    $orderRow = $this->getPurgedPaymentRow(
                        $statusRow,
                        [
                            'AUTHORIZE',
                            'DEBIT',
                            'CREDIT',
                            'ANNUL',
                            'ANNULLABLE',
                            'DEBITABLE',
                            'CREDITABLE',
                        ],
                        $this->getPaymentDefaultPurgeSet ? true : false
                    );

                    if (!$this->skipAfterShopPaymentValidation) {
                        $useUnitAmount = $orderRow['unitAmountWithoutVat'];
                        $useVatPct = $orderRow['vatPct'];
                    }

                    // Make sure we use the correct getPaymentData.
                    $orderRow['id'] = $id;
                    $orderRow['quantity'] = $useQuantity;
                    $orderRow['unitAmountWithoutVat'] = $useUnitAmount;
                    $orderRow['vatPct'] = $useVatPct;

                    $orderRow['totalVatAmount'] = $this->getTotalVatAmount(
                        $useUnitAmount,
                        $useVatPct,
                        $useQuantity
                    );
                    $orderRow['totalAmount'] = $this->getTotalAmount(
                        $useUnitAmount,
                        $useVatPct,
                        $useQuantity
                    );
                    $return[] = $orderRow;
                    $id++;
                }
            }
        }

        return $return;
    }

    /**
     * If more than two fields are missing in the requested payment object, this should be considered
     * an object with missing data.
     *
     * @param $duplicateState
     * @throws Exception
     * @since 1.3.22
     */
    private function checkUnsafePaymentObject($duplicateState)
    {
        if ($duplicateState > 2) {
            throw new Exception(
                'There are more articles in this order that has the same article number, ' .
                'but where other content may differ.',
                400
            );
        }
    }

    /**
     * Return "best practice"-order statuses for a payment.
     *
     * @param string $paymentIdOrPaymentObject
     * @param int $byCallbackEvent Compare the order status with a potential inbound callback type
     * @param array|string $callbackEventDataArrayOrString Content from the callback event sent by Resurs Bank
     * @param null $paymentMethodObject
     * @return int
     * @throws Exception
     * @since 1.0.26
     * @since 1.1.26
     * @since 1.2.0
     */
    public function getOrderStatusByPayment(
        $paymentIdOrPaymentObject = '',
        $byCallbackEvent = RESURS_CALLBACK_TYPES::NOT_SET,
        $callbackEventDataArrayOrString = [],
        $paymentMethodObject = null
    ) {

        if (is_string($paymentIdOrPaymentObject)) {
            $paymentData = $this->getPayment($paymentIdOrPaymentObject, false);
        } elseif (is_object($paymentIdOrPaymentObject)) {
            $paymentData = $paymentIdOrPaymentObject;
        } else {
            throw new ResursException('Payment data object or id is not valid.', 500);
        }

        // If nothing else suits us, this will be used
        $preAnalyzePayment = $this->getOrderStatusByPaymentStatuses($paymentData);

        // Analyzed during a callback event, which have higher priority than a regular control
        switch (true) {
            case $byCallbackEvent & (RESURS_CALLBACK_TYPES::ANNULMENT):
                return RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_ANNULLED;
            case $byCallbackEvent & (RESURS_CALLBACK_TYPES::AUTOMATIC_FRAUD_CONTROL):
                if (is_string($callbackEventDataArrayOrString)) {
                    if ($callbackEventDataArrayOrString === 'THAWED') {
                        return RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_PROCESSING;
                    }
                }

                return RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_PENDING;
            case $byCallbackEvent & (RESURS_CALLBACK_TYPES::BOOKED):
                // Frozen set, but not true OR frozen not set at all - Go processing
                if ($this->isFrozen()) {
                    return RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_PENDING;
                }
                // Running in synchronous mode (finalizeIfBooked) might disturb the normal way to handle the booked
                // callback, so we'll continue checking the order by statuses if this order is not frozen
                return $preAnalyzePayment;
            case $byCallbackEvent & (RESURS_CALLBACK_TYPES::FINALIZATION):
                $return = (
                    RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_COMPLETED |
                    $this->getInstantFinalizationStatus($paymentData, $paymentMethodObject)
                );

                return $this->resetFailBit($return);
            case $byCallbackEvent & (RESURS_CALLBACK_TYPES::UNFREEZE):
                return RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_PROCESSING;
            case $byCallbackEvent & (RESURS_CALLBACK_TYPES::UPDATE):
                return $preAnalyzePayment;
            default:
                break;
        }

        // If nothing was hit in the above check, use the suggested pre analyze status.
        $returnThisAfterAll = $preAnalyzePayment;

        return $returnThisAfterAll;
    }

    /**
     * Generic order status content information that checks payment statuses instead of callback input and decides what
     * has happened to the payment.
     *
     * Second argument can be passed (if necessary) to this method as a performance saver (= to avoid making an extra
     * getPaymentMethods during this process when checking instant finalizations).
     *
     * @param array $paymentData
     * @param null $paymentMethodObject A single getPaymentMethods() payment object in stdClass-format should pass here
     * @return int
     * @throws Exception
     * @since 1.0.26
     * @since 1.1.26
     * @since 1.2.0
     */
    private function getOrderStatusByPaymentStatuses($paymentData = [], $paymentMethodObject = null)
    {
        $return = RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_STATUS_COULD_NOT_BE_SET;

        if ($this->isFrozen($paymentData)) {
            $return = RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_PENDING;
        }

        /** @noinspection PhpUndefinedFieldInspection */
        $resursTotalAmount = $paymentData->totalAmount;
        if ($this->canDebit($paymentData)) {
            $return = RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_PROCESSING;
        }

        if (!$this->canDebit($paymentData) && $this->getIsDebited($paymentData) && $resursTotalAmount > 0) {
            // If payment is flagged debitable, also make sure that the "instant finalization"-flag is present on this
            // payment if necessary, so that we can indicate for developers that is has both been debited and probably
            // instantly (based on the payment method).
            $return = (RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_COMPLETED |
                $this->getInstantFinalizationStatus($paymentData, $paymentMethodObject)
            );
        }

        if ($this->getIsAnnulled($paymentData) && !$this->getIsCredited($paymentData) && $resursTotalAmount == 0) {
            // ANNULLED or CANCELLED is the same for us
            $return = RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_ANNULLED;
        }

        if ($this->getIsCredited($paymentData) && $resursTotalAmount == 0) {
            // CREDITED or REFUND is the same for us
            $return = RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_CREDITED;
        }

        if ($this->getFraudFlagStatus() && $this->isFraud($paymentData)) {
            if ($return & RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_STATUS_COULD_NOT_BE_SET) {
                $return = RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_MANUAL_INSPECTION;
            } else {
                $return += RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_MANUAL_INSPECTION;
            }
        }

        return $this->resetFailBit($return);
    }

    /**
     * @param array $paymentArrayOrPaymentId
     * @return bool
     * @throws Exception
     * @since 1.0.40
     * @since 1.1.40
     * @since 1.3.13
     */
    public function isFrozen($paymentArrayOrPaymentId = [])
    {
        return (bool)$this->getPaymentContent($paymentArrayOrPaymentId, 'frozen');
    }

    /**
     * Return true if order is debited
     *
     * @param array $paymentArrayOrPaymentId
     *
     * @return bool
     * @throws Exception
     * @since 1.0.13
     * @since 1.1.13
     * @since 1.2.0
     */
    public function getIsDebited($paymentArrayOrPaymentId = [])
    {
        $Status = (array)$this->getPaymentContent($paymentArrayOrPaymentId, 'status');
        if (in_array('IS_DEBITED', $Status)) {
            return true;
        }

        return false;
    }

    /**
     * Get, if exists, the status code for automatically debited on matching payments.
     * Can be used "out of the box" if you know how.
     *
     * This method only returns the current status code for automatically finalized payments, if the payment method
     * is matched with an "instant finalization"-type (like SWISH). If not, PAYMENT_STATUS_COULD_NOT_BE_SET (0) will
     * be used, which also (if you so wish) matches with false. If this method returns false, you might consider
     * the payment not instantly finalized.
     *
     * To save performance (= to avoid making getPaymentMethods during this process), you can pass over a cache stored
     * payment method object to this method (it has to contain information about type and specificType). For testing
     * purposes (or production environments where payment methods are stored locally) you might find this useful.
     *
     * @param array $paymentData
     * @param null $paymentMethodObject A single getPaymentMethods() payment object in stdClass-format should pass here
     * @return int
     * @throws Exception
     * @since 1.0.41
     * @since 1.1.41
     * @since 1.3.14
     */
    public function getInstantFinalizationStatus($paymentData = [], $paymentMethodObject = null)
    {
        $return = RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_STATUS_COULD_NOT_BE_SET;

        // Make this cached on reruns so we don't have to fetch information twice if there's chained procedures.
        if (!is_object($this->autoDebitablePaymentMethod)) {
            if (is_object($paymentMethodObject)) {
                $this->autoDebitablePaymentMethod = $paymentMethodObject;
            }
            try {
                $this->autoDebitablePaymentMethod = $this->getPaymentMethodSpecific($paymentData);
            } catch (\Exception $e) {
                throw new ResursException(
                    'getPaymentMethods Problem',
                    RESURS_EXCEPTIONS::PAYMENT_METHODS_ERROR,
                    $e
                );
            }
        }

        // Check if feature is enabled, the type contains PAYMENT_PROVIDER and the specificType matches a payment
        // provider that tend to finalize payments instantly after orders has been created.
        if ($this->getAutoDebitableTypeState() && $this->autoDebitablePaymentMethod->type === 'PAYMENT_PROVIDER' &&
            $this->isAutoDebitableType($this->autoDebitablePaymentMethod->specificType)
        ) {
            $return = RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_AUTOMATICALLY_DEBITED;
        }

        return $return;
    }

    /**
     * Returns true if the auto discovery of automatically debited payments is active
     *
     *
     * @return bool
     * @since 1.0.41
     * @since 1.1.41
     * @since 1.3.14
     */
    public function getAutoDebitableTypeState()
    {
        return $this->autoDebitableTypesActive;
    }

    /**
     * Returns true if the payment method type tend to auto debit themselves.
     *
     * @param string $type
     * @return bool
     * @since 1.0.41
     * @since 1.1.41
     * @since 1.3.14
     */
    public function isAutoDebitableType($type = '')
    {
        $return = false;

        $this->prepareAutoDebitableTypes();
        if (in_array($type, $this->autoDebitableTypes)) {
            return true;
        }

        return $return;
    }

    /**
     * Return true if order is annulled
     *
     * @param array $paymentArrayOrPaymentId
     *
     * @return bool
     * @throws Exception
     * @since 1.0.13
     * @since 1.1.13
     * @since 1.2.0
     */
    public function getIsAnnulled($paymentArrayOrPaymentId = [])
    {
        $Status = (array)$this->getPaymentContent($paymentArrayOrPaymentId, 'status');
        if (in_array('IS_ANNULLED', $Status)) {
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
     * @throws Exception
     * @since 1.0.13
     * @since 1.1.13
     * @since 1.2.0
     */
    public function getIsCredited($paymentArrayOrPaymentId = [])
    {
        $Status = (array)$this->getPaymentContent($paymentArrayOrPaymentId, 'status');
        if (in_array('IS_CREDITED', $Status)) {
            return true;
        }

        return false;
    }

    /**
     * @return mixed
     * @since 1.0.40
     * @since 1.1.40
     * @since 1.3.13
     */
    public function getFraudFlagStatus()
    {
        return $this->fraudStatusAllowed;
    }

    /**
     * @param array $paymentArrayOrPaymentId
     * @return bool
     * @throws Exception
     * @since 1.0.40
     * @since 1.1.40
     * @since 1.3.13
     */
    public function isFraud($paymentArrayOrPaymentId = [])
    {
        return (bool)$this->getPaymentContent($paymentArrayOrPaymentId, 'fraud');
    }

    /**
     * Is status set but Ecom claims that it could not set it?
     *
     * @param $return
     * @return int
     * @since 1.3.28
     */
    private function resetFailBit($return)
    {
        // Occurs when PAYMENT_COMPLETED and finalization status falsely returns with PAYMENT_STATUS_COULD_NOT_BE_SET.
        if (($return & RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_STATUS_COULD_NOT_BE_SET) &&
            $return !== RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_STATUS_COULD_NOT_BE_SET &&
            $return > 0
        ) {
            $return -= RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_STATUS_COULD_NOT_BE_SET;
        }

        return $return;
    }

    /**
     * @return array
     */
    public function getPaymentCached()
    {
        return $this->lastPaymentStored;
    }

    /**
     * Split function for aftershop: This was included in each of the deprecated function instead of running from a
     * central place
     *
     * @param $paymentId
     *
     * @return bool
     */
    private function aftershopPrepareMetaData($paymentId)
    {
        try {
            if (empty($this->customerId)) {
                $this->customerId = '-';
            }
            $this->addMetaData($paymentId, 'CustomerId', $this->customerId);
        } catch (Exception $metaResponseException) {
        }

        return true;
    }

    /**
     * Invoice sequence number rescuer/scanner (This function replaces old sequence numbers if there is a higher value
     * found in the last X payments)
     *
     * @param $scanDebitCount
     * @return int
     * @throws Exception
     * @since 1.0.27
     * @since 1.1.27
     */
    public function getNextInvoiceNumberByDebits($scanDebitCount = 10)
    {
        /**
         * @since 1.3.7
         */
        $currentInvoiceTest = null;
        // Check if there is a "current" invoice ID before searching for them.
        // This prevents errors like "Setting a invoice number lower than last number is not allowed (1)"
        try {
            $currentInvoiceTest = $this->getNextInvoiceNumber();
        } catch (Exception $e) {
        }
        $paymentScanTypes = ['IS_DEBITED', 'IS_CREDITED', 'IS_ANNULLED'];

        $lastHighestInvoice = 0;
        foreach ($paymentScanTypes as $paymentType) {
            $paymentScanList = $this->findPayments(
                ['statusSet' => [$paymentType]],
                1,
                $scanDebitCount,
                [
                    'ascending' => false,
                    'sortColumns' => ['FINALIZED_TIME', 'MODIFIED_TIME', 'BOOKED_TIME'],
                ]
            );
            $lastHighestInvoice = $this->getHighestValueFromPaymentList($paymentScanList, $lastHighestInvoice);
        }

        // Set invoice id to the highest scanned id automatically.
        $properInvoiceNumber = intval($lastHighestInvoice) + 1;

        // If the peeked number is higher than the scanned, use that number instead.
        if (intval($currentInvoiceTest) > 0 && $currentInvoiceTest > $properInvoiceNumber) {
            $properInvoiceNumber = $currentInvoiceTest;
        }
        $this->getNextInvoiceNumber(true, $properInvoiceNumber);

        // Make sure this isn't a special test.
        $testInvoiceNumber = $this->getTestInvoiceNumber();
        if (!is_null($testInvoiceNumber)) {
            $properInvoiceNumber = $testInvoiceNumber;
        }

        // Paranoid mode. This is where we try to increment invoices furthermore on failures.
        // On demand only; AFTERSHOP_RESCUE_INVOICE must be active, and a prior invoice id must be set.
        // See below. The afterShopInvoiceId value indicates that there has been one prior scan for invoice
        // id's which means the first try failed.
        if (!empty($this->afterShopInvoiceId) &&
            intval($this->afterShopInvoiceId) > 0 &&
            $this->isFlag('AFTERSHOP_RESCUE_INVOICE')
        ) {
            // Rescue once only.
            $this->deleteFlag('AFTERSHOP_RESCUE_INVOICE');
            if ((int)$this->getFlag('AFTERSHOP_RESCUE_INCREMENT') > 0) {
                $incrementValue = $this->getFlag('AFTERSHOP_RESCUE_INCREMENT');
                $properInvoiceNumber = $properInvoiceNumber + intval($incrementValue);
            } else {
                $properInvoiceNumber++;
            }

            // Try to replace the prior number with the incremented number.
            $this->getNextInvoiceNumber(true, $properInvoiceNumber);
        }

        $this->afterShopInvoiceId = $properInvoiceNumber;

        return $properInvoiceNumber;
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
     * @throws Exception
     * @link https://test.resurs.com/docs/x/loEW
     * @since 1.0.1
     * @since 1.1.1
     */
    public function findPayments($searchCriteria = [], $pageNumber = 1, $itemsPerPage = 10, $sortBy = null)
    {
        $searchParameters = [
            'searchCriteria' => $searchCriteria,
            'pageNumber' => $pageNumber,
            'itemsPerPage' => $itemsPerPage,
        ];
        if (!empty($sortBy)) {
            $searchParameters['sortBy'] = $sortBy;
        }

        return $this->postService('findPayments', $searchParameters);
    }

    /**
     * Get the highest invoice value from a list of payments
     *
     * @param array $paymentList
     * @param int $lastHighestInvoice
     *
     * @return int|mixed
     * @throws Exception
     */
    private function getHighestValueFromPaymentList($paymentList = [], $lastHighestInvoice = 0)
    {
        if (is_object($paymentList)) {
            $paymentList = [$paymentList];
        }
        if (is_array($paymentList)) {
            foreach ($paymentList as $payments) {
                if (isset($payments->paymentId)) {
                    $id = $payments->paymentId;
                    $invoices = $this->getPaymentInvoices($id);
                    foreach ($invoices as $multipleDebitCheck) {
                        if ($multipleDebitCheck > $lastHighestInvoice) {
                            $lastHighestInvoice = $multipleDebitCheck;
                        }
                    }
                }
            }
        }

        return $lastHighestInvoice;
    }

    /**
     * Returns all invoice numbers for a specific payment
     *
     * @param string $paymentIdOrPaymentObject
     *
     * @return array
     * @throws Exception
     * @since 1.0.11
     * @since 1.1.11
     * @since 1.2.0
     */
    public function getPaymentInvoices($paymentIdOrPaymentObject = '')
    {
        $invoices = [];
        if (is_string($paymentIdOrPaymentObject)) {
            $paymentData = $this->getPayment($paymentIdOrPaymentObject);
        } elseif (is_object($paymentIdOrPaymentObject)) {
            $paymentData = $paymentIdOrPaymentObject;
        } else {
            return [];
        }
        if (!empty($paymentData) && isset($paymentData->paymentDiffs)) {
            foreach ($paymentData->paymentDiffs as $paymentRow) {
                if (isset($paymentRow->type) && isset($paymentRow->invoiceId)) {
                    $invoices[] = $paymentRow->invoiceId;
                }
            }
        }

        return $invoices;
    }

    /**
     * getPayment - Retrieves detailed information about a payment
     *
     * As of 1.3.13, SOAP has higher priority than REST. This might be a breaking change, since
     * there will from now in (again) be a dependency of SoapClient. The flag GET_PAYMENT_BY_REST is
     * obsolete and has no longer any effect.
     *
     * Exceptions thrown:
     *      3/REST=>Order does not exist,
     *      8/SOAP=>Reference does not exist,
     *      404 is thrown when errors could not be fetched
     *
     * @param string $paymentId
     * @param bool $requestCached Try fetch cached data before going for live data.
     * @return mixed
     * @throws ResursException
     * @since 1.0.1
     * @since 1.1.1
     * @since 1.3.4 Refactored from this version
     */
    public function getPayment($paymentId = '', $requestCached = true)
    {
        $this->InitializeServices();
        $rested = false;

        $this->getPaymentRequests++;
        if ($requestCached && isset($this->lastPaymentStored[$paymentId]->cached)) {
            $lastRequest = time() - $this->lastPaymentStored[$paymentId]->cached;
            if ($lastRequest <= $this->lastGetPaymentMaxCacheTime) {
                $this->getPaymentCachedRequests++;
                return $this->lastPaymentStored[$paymentId];
            }
        }

        /**
         * As REST based exceptions is more unsafe than the SOAP responses we use the SOAP as default method to get
         * the payment data. REST throws a 404 exception with an extended body with errors when a payment does not
         * exist. This behaviour is partially only half safe, since we don't know from moment to moment when this
         * error body is present.
         *
         * @since 1.3.13
         */
        if ($this->isFlag('GET_PAYMENT_BY_REST') || !$this->SOAP_AVAILABLE) {
            // This will ALWAYS run if SOAP is unavailable
            try {
                $rested = true;
                $this->lastPaymentStored[$paymentId] = $this->getPaymentByRest($paymentId);
                $this->getPaymentRequestMethod = RESURS_GETPAYMENT_REQUESTTYPE::REST;
                $this->lastPaymentStored[$paymentId]->cached = time();
                $this->lastPaymentStored[$paymentId]->requestMethod = $this->getPaymentRequestMethod;
                $return = $this->lastPaymentStored[$paymentId];
            } catch (ResursException $e) {
                // 3 = The order does not exist, default REST error.
                // If we for some reason get 404 errors here, the error should be rethrown as 3.
                // If we for some unknown reason get 500+ errors, we can almost be sure that something else went wrong.
                if ($e->getCode() === 404) {
                    throw new ResursException($e->getMessage(), 3, $e);
                }
                if (!$this->SOAP_AVAILABLE && $e->getCode() === 51) {
                    // Fail over on SSL certificate errors (first) as the domain for soap is different than RCO-rest.
                    $rested = false;
                } else {
                    throw $e;
                }
            }
        }

        if (!$rested) {
            try {
                $this->lastPaymentStored[$paymentId] = $this->getPaymentBySoap($paymentId);
                $this->getPaymentRequestMethod = RESURS_GETPAYMENT_REQUESTTYPE::SOAP;
                $this->lastPaymentStored[$paymentId]->cached = time();
                $this->lastPaymentStored[$paymentId]->requestMethod = $this->getPaymentRequestMethod;
                $return = $this->lastPaymentStored[$paymentId];
            } catch (Exception $e) {
                // 8 = REFERENCED_DATA_DONT_EXISTS
                throw $e;
            }
        }

        return $return;
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
    public function isFlag($flagKey = '')
    {
        if ($this->hasFlag($flagKey)) {
            return ((bool)$this->getFlag($flagKey) ? true : false);
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
    public function hasFlag($flagKey = '')
    {
        if (!is_null($this->getFlag($flagKey))) {
            return true;
        }

        return false;
    }

    /**
     * @param string $paymentId
     * @return stdClass
     * @throws ResursException
     * @throws ExceptionHandler
     * @since 1.3.13
     * @since 1.1.40
     * @since 1.0.40
     * @deprecated Since 1.3.45, use the auto selective method (getPayment) instead.
     */
    public function getPaymentByRest($paymentId = '')
    {
        try {
            // The look of this call makes it compatible to PHP 5.3 (without chaining)
            return $this->CURL->request($this->getCheckoutUrl() . '/checkout/payments/' . $paymentId)->getParsed();
        } catch (Exception $e) {
            // Get internal exceptions before http responses
            $exceptionTestBody = @json_decode($this->CURL->getBody());
            if (isset($exceptionTestBody->errorCode) && isset($exceptionTestBody->description)) {
                throw new ResursException($exceptionTestBody->description, $exceptionTestBody->errorCode, $e);
            }
            throw new ResursException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Retrieves detailed information about a payment.
     *
     * @param string $paymentId
     *
     * @return array|mixed|null
     * @throws Exception
     * @link  https://test.resurs.com/docs/x/moEW getPayment() documentation
     * @since 1.0.31
     * @since 1.1.31
     * @since 1.2.4
     * @since 1.3.4
     */
    public function getPaymentBySoap($paymentId = '')
    {
        return $this->postService('getPayment', ['paymentId' => $paymentId]);
    }

    /**
     * For test purposes only.
     *
     * @return int|null
     * @throws Exception
     * @since 1.3.27
     */
    private function getTestInvoiceNumber()
    {
        $return = null;

        if ($this->isFlag('DELETE_TEST_INVOICE')) {
            $this->deleteFlag('TEST_INVOICE');
        }
        if ($this->isFlag('TEST_INVOICE') && $this->current_environment === RESURS_ENVIRONMENTS::TEST) {
            $this->setFlag('DELETE_TEST_INVOICE');
            $return = 1003036;
        }

        return $return;
    }

    /**
     * Shadow function for paymentAnnul
     *
     * @param string $paymentId
     * @param array $customPayloadItemList
     * @param bool $runOnce
     * @param bool $skipSpecValidation
     * @return bool
     * @throws Exception
     */
    public function annulPayment(
        $paymentId = '',
        $customPayloadItemList = [],
        $runOnce = false,
        $skipSpecValidation = false
    ) {
        return $this->paymentAnnul($paymentId, $customPayloadItemList, $runOnce, $skipSpecValidation);
    }

    /**
     * Aftershop Payment Annulling (ANNUL)
     *
     * @param $paymentId
     * @param array $customPayloadItemList
     * @param bool $runOnce Only run this once, throw second time
     * @param bool $skipSpecValidation Set to true, you're skipping validation of order rows.
     * @return bool
     * @throws Exception
     * @since 1.0.22
     * @since 1.1.22
     * @since 1.2.0
     * @noinspection PhpUnusedParameterInspection
     */
    public function paymentAnnul(
        $paymentId = '',
        $customPayloadItemList = [],
        $runOnce = false,
        $skipSpecValidation = false
    ) {
        if (!is_array($customPayloadItemList)) {
            $customPayloadItemList = [];
        }

        $this->setAftershopPaymentValidation($skipSpecValidation);

        $afterShopObject = $this->getAfterShopObjectByPayload(
            $paymentId,
            $customPayloadItemList,
            RESURS_AFTERSHOP_RENDER_TYPES::ANNUL
        );
        $this->aftershopPrepareMetaData($paymentId);
        // We did nothing here since there was no order lines.
        if (!isset($afterShopObject['specLines']) ||
            (
                is_array($afterShopObject['specLines']) &&
                !count($afterShopObject['specLines'])
            )
        ) {
            return false;
        }
        $afterShopResponseCode = $this->postService('annulPayment', $afterShopObject, true);
        if ($afterShopResponseCode >= 200 && $afterShopResponseCode < 300) {
            $this->resetPayload();

            return true;
        }

        return false;
    }

    /**
     * Shadow function for paymentCredit
     *
     * @param string $paymentId
     * @param array $customPayloadItemList
     * @param bool $runOnce
     * @param bool $skipSpecValidation
     * @return bool
     * @throws Exception
     */
    public function creditPayment(
        $paymentId = '',
        $customPayloadItemList = [],
        $runOnce = false,
        $skipSpecValidation = false
    ) {
        return $this->paymentCredit($paymentId, $customPayloadItemList, $runOnce, $skipSpecValidation);
    }

    /**
     * Aftershop Payment Crediting (CREDIT)
     *
     * Make sure that you are running this with try-catches in cases where failures may occur.
     *
     * @param $paymentId
     * @param array $customPayloadItemList
     * @param bool $runOnce Only run this once, throw second time
     * @param bool $skipSpecValidation Set to true, you're skipping validation of order rows.
     * @return bool
     * @throws Exception
     * @since 1.0.22
     * @since 1.1.22
     * @since 1.2.0
     */
    public function paymentCredit(
        $paymentId = '',
        $customPayloadItemList = [],
        $runOnce = false,
        $skipSpecValidation = false
    ) {
        if (!is_array($customPayloadItemList)) {
            $customPayloadItemList = [];
        }

        $this->setAftershopPaymentValidation($skipSpecValidation);

        $afterShopObject = $this->getAfterShopObjectByPayload(
            $paymentId,
            $customPayloadItemList,
            RESURS_AFTERSHOP_RENDER_TYPES::CREDIT
        );
        $this->aftershopPrepareMetaData($paymentId);
        try {
            // We did nothing here since there was no order lines.
            if (!isset($afterShopObject['specLines']) ||
                (
                    is_array($afterShopObject['specLines']) &&
                    !count($afterShopObject['specLines'])
                )
            ) {
                return false;
            }
            $afterShopResponseCode = $this->postService('creditPayment', $afterShopObject, true);
            if ($afterShopResponseCode >= 200 && $afterShopResponseCode < 300) {
                $this->resetPayload();

                return true;
            }
        } catch (Exception $creditException) {
            // Possible invoice error codes:
            // 28 = ECOMMERCEERROR_NOT_ALLOWED_INVOICE_ID
            // 29 ECOMMERCEERROR_ALREADY_EXISTS_INVOICE_ID
            if ((
                    (int)$creditException->getCode() === RESURS_EXCEPTIONS::ECOMMERCEERROR_ALREADY_EXISTS_INVOICE_ID ||
                    (int)$creditException->getCode() === RESURS_EXCEPTIONS::ECOMMERCEERROR_NOT_ALLOWED_INVOICE_ID
                ) &&
                !$this->isFlag('SKIP_AFTERSHOP_INVOICE_CONTROL')
            ) {
                if (!$runOnce) {
                    $this->getNextInvoiceNumberByDebits(5);

                    return $this->paymentCredit($paymentId, $customPayloadItemList, true);
                } else {
                    // One time failsafe rescue mode.
                    if ($this->isFlag('AFTERSHOP_RESCUE_INVOICE') && $this->afterShopInvoiceId > 0) {
                        // Reset after once run.
                        $this->getNextInvoiceNumberByDebits(5);

                        return $this->paymentCredit($paymentId, $customPayloadItemList, true);
                    }
                }
            }

            throw new ResursException(
                $creditException->getMessage(),
                $creditException->getCode(),
                $creditException
            );
        }

        return false;
    }

    /**
     * Shadow function for paymentCancel
     *
     * @param string $paymentId
     * @param array $customPayloadItemList
     * @param bool $skipSpecValidation
     * @return bool
     * @throws Exception
     * @since Forever.
     */
    public function cancelPayment($paymentId = '', $customPayloadItemList = [], $skipSpecValidation = false)
    {
        return $this->paymentCancel($paymentId, $customPayloadItemList, $skipSpecValidation);
    }

    /**
     * Aftershop Payment Cancellation (ANNUL+CREDIT)
     *
     * This function cancels a full order depending on the order content. Payloads MAY be customized but on your own
     * risk!
     *
     * @param string $paymentId
     * @param array $customPayloadItemList
     * @param bool $skipSpecValidation Set to true, you're skipping validation of order rows.
     * @return bool
     * @throws Exception
     * @since 1.0.22
     * @since 1.1.22
     * @since 1.2.0
     */
    public function paymentCancel($paymentId = '', $customPayloadItemList = [], $skipSpecValidation = false)
    {
        if (!is_array($customPayloadItemList)) {
            $customPayloadItemList = [];
        }

        $this->setAftershopPaymentValidation($skipSpecValidation);

        $paymentData = $this->getPayment($paymentId);
        // Collect the payment sorted by status
        $currentPaymentTable = $this->getPaymentDiffByStatus($paymentData, true);

        // Sanitized payment spec based on what CAN be fully ANNULLED (and actually
        // also debited) wit no custom payment load.
        $fullAnnulObject = $this->sanitizeAfterShopSpec(
            $this->getPayment($paymentId),
            RESURS_AFTERSHOP_RENDER_TYPES::ANNUL
        );
        // Sanitized payment spec based on what CAN be fully CREDITED with no custom payment load.
        $fullCreditObject = $this->sanitizeAfterShopSpec(
            $this->getPayment($paymentId),
            RESURS_AFTERSHOP_RENDER_TYPES::CREDIT
        );

        if (is_array($customPayloadItemList) && count($customPayloadItemList)) {
            $this->SpecLines = array_merge($this->SpecLines, $customPayloadItemList);
        }
        $this->renderPaymentSpec(RESURS_FLOW_TYPES::SIMPLIFIED_FLOW);
        $this->aftershopPrepareMetaData($paymentId);
        // Render and current order lines. This may contain customized order lines.
        $currentOrderLines = $this->getOrderLines();

        try {
            if (is_array($currentOrderLines) && count($currentOrderLines)) {
                // If the order rows are custom (addOrderLine or "deprecated array mode") we actually don't
                // need the old section anymore as the "GertFormula" was highly effective, when it came
                // to recalculate "what's left". However, we should probably leave some kind of validation
                // to make the requested object legit. For example, if developers are sending other
                // stuff in that does not cover what's already in the order, such rows should not be
                // able to pass trough. Also, the formula made this section broken.

                $newCreditObject = $this->getValidatedAftershopRows($currentPaymentTable, $currentOrderLines, 'credit');
                $newAnnulObject = $this->getValidatedAftershopRows($currentPaymentTable, $currentOrderLines, 'annul');

                if (is_array($newCreditObject) && count($newCreditObject)) {
                    $this->paymentCredit($paymentId, $newCreditObject, $this->skipAfterShopPaymentValidation);
                }
                if (is_array($newAnnulObject) && count($newAnnulObject)) {
                    $this->paymentAnnul($paymentId, $newAnnulObject, false, $this->skipAfterShopPaymentValidation);
                }
            } else {
                if (is_array($fullAnnulObject) && count($fullAnnulObject)) {
                    $this->paymentAnnul($paymentId, $fullAnnulObject, $this->skipAfterShopPaymentValidation);
                }
                if (is_array($fullCreditObject) && count($fullCreditObject)) {
                    $this->paymentCredit($paymentId, $fullCreditObject, $this->skipAfterShopPaymentValidation);
                }
            }
        } catch (Exception $cancelException) {
            // Last catched exception will be thrown back to the plugin/developer.
            throw new ResursException(
                $cancelException->getMessage(),
                $cancelException->getCode(),
                $cancelException
            );
        }
        $this->resetPayload();

        return true;
    }

    /**
     * Add an additional orderline to a payment
     *
     * With setLoggedInUser() you can also set up a user identification for the createdBy-parameter sent with the
     * additional debug. If not set, EComPHP paymentCancel($paymentId = "", $customPayloadItemList will use
     * the merchant credentials.
     *
     * @param string $paymentId
     *
     * @return bool
     * @throws Exception
     * @since 1.0.3
     * @since 1.1.3
     */
    public function setAdditionalDebitOfPayment($paymentId = '')
    {
        $createdBy = $this->username;
        if (!empty($this->loggedInUser)) {
            $createdBy = $this->loggedInUser;
        }
        $this->renderPaymentSpec(RESURS_FLOW_TYPES::SIMPLIFIED_FLOW);
        $additionalDataArray = [
            'paymentId' => $paymentId,
            'paymentSpec' => $this->Payload['orderData'],
            'createdBy' => $createdBy,
        ];
        $Result = $this->postService('additionalDebitOfPayment', $additionalDataArray, true);
        if ($Result >= 200 && $Result <= 250) {
            // Reset orderData for each addition
            $this->resetPayload();

            return true;
        } else {
            return false;
        }
    }

    /**
     * @param bool $enable
     * @since 1.0.40
     * @since 1.1.40
     * @since 1.3.13
     */
    public function setFraudFlagStatus($enable = true)
    {
        $this->fraudStatusAllowed = $enable;
    }

    ///////////// INTERNAL MEMORY LIMIT HANDLER BEGIN

    /**
     * @param $returnCode
     *
     * @return string
     * @since 1.0.26
     * @since 1.1.26
     * @since 1.2.0
     */
    public function getOrderStatusStringByReturnCode(
        $returnCode = RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_STATUS_COULD_NOT_BE_SET
    ) {
        $returnValue = '';

        switch (true) {
            case $returnCode & (RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_PENDING):
                $returnValue = 'pending';
                break;
            case $returnCode & (RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_PROCESSING):
                $returnValue = 'processing';
                break;
            case $returnCode & (
                    RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_COMPLETED |
                    RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_AUTOMATICALLY_DEBITED
                );
                // Return completed by default here, regardless of what actually has happened to the order
                // to maintain compatibility. If the payment has been finalized instantly, it is not here you'd
                // like to use another status. It's in your own code.
                $returnValue = 'completed';
                break;
            case $returnCode & (RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_ANNULLED);
                $returnValue = 'annul';
                break;
            case $returnCode & (RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_CREDITED);
                $returnValue = 'credit';
                break;
            default:
                break;
        }

        return $returnValue;
    }

    /**
     * Callback digest validator
     *
     * @param string $callbackPaymentId Requested payment id to check.
     * @param string $saltKey Current salt key used for the digest.
     * @param string $inboundDigest Digest received from Resurs Bank.
     * @param null $callbackResult Optional for AUTOMATIC_FRAUD_CONTROL.
     *
     * @return bool
     * @since 1.0.33
     * @since 1.1.33
     * @since 1.2.6
     * @since 1.3.6
     */
    public function getValidatedCallbackDigest(
        $callbackPaymentId = '',
        $saltKey = '',
        $inboundDigest = '',
        $callbackResult = null
    ) {
        $digestCompiled = $callbackPaymentId . (!is_null($callbackResult) ? $callbackResult : null) . $saltKey;
        $digestMd5 = strtoupper(md5($digestCompiled));
        $digestSha = strtoupper(sha1($digestCompiled));
        $realInboundDigest = strtoupper($inboundDigest);
        if ($realInboundDigest == $digestMd5 || $realInboundDigest == $digestSha) {
            return true;
        }

        return false;
    }

    /**
     * Get the current list of auto debitable types
     *
     * @return array
     * @since 1.0.41
     * @since 1.1.41
     * @since 1.3.14
     */
    public function getAutoDebitableTypes()
    {
        $this->prepareAutoDebitableTypes();
        return $this->autoDebitableTypes;
    }

    /**
     * @param $url
     * @return string|string[]|null
     * @since 1.3.47
     */
    public function getSecureUrl($url)
    {
        $return = $url;

        if ($this->isFlag('HEAL_URL')) {
            $return = preg_replace('/^http:/', 'https:', $return);
        }

        return $return;
    }

    /**
     * Activates or disables the auto debited payments discovery. Default enables this function.
     *
     * @param bool $activation
     * @since 1.0.41
     * @since 1.1.41
     * @since 1.3.14
     */
    public function setAutoDebitableTypes($activation = true)
    {
        $this->autoDebitableTypesActive = $activation;
    }
    ///////////// INTERNAL MEMORY LIMIT HANDLER END

    /**
     * Magic function that will help us clean up unnecessary content. Future prepared.
     *
     * @param $name
     * @return mixed
     * @throws Exception
     */
    /*function __get($name)
    {
        $requestedVariableProperties = get_class_vars(__CLASS__);

        switch ($name) {
            case 'test';
                $return = true;
                break;

            default:
                if (isset($this->$name)) {

                    if (!isset($requestedVariableProperties->$name)) {
                        throw new \ResursException(sprintf('Requested variable is not reachable: "%s"', $name), 400);
                    }
                    $return = $this->$name;
                } else {
                    throw new \ResursException(sprintf('Requested variable is not defined: "%s"', $name));
                }

        }

        return $return;
    }*/

    /**
     * v1.1 method compatibility
     *
     * @param null $func
     * @param array $args
     * @return mixed
     * @throws Exception
     */
    public function __call($func = null, $args = [])
    {
        $runCall = false;
        if (class_exists(
            'Resursbank_Obsolete_Functions',
            ECOM_CLASS_EXISTS_AUTOLOAD
        )) {
            $runCall = true;
        }

        if (class_exists(
            '\Resursbank\RBEcomPHP\Resursbank_Obsolete_Functions',
            ECOM_CLASS_EXISTS_AUTOLOAD
        )) {
            $runCall = true;
        }

        if ($runCall) {
            /** @noinspection PhpUndefinedClassInspection */
            $obsoleteCaller = new Resursbank_Obsolete_Functions($this);
            if (method_exists($obsoleteCaller, $func)) {
                $this->hasDeprecatedCall = true;
                return call_user_func_array([$obsoleteCaller, $func], $args);
            }
        }
        // 501 NOT IMPLEMENTED
        throw new ResursException(
            sprintf(
                'Method "%s" not found in ECom Library, neither in the current release nor in the deprecation library.',
                $func
            ),
            501
        );
    }
}
