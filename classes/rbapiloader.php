<?php

/**
 * Resurs Bank Passthrough API - A pretty silent ShopFlowSimplifier for Resurs Bank.
 * Last update: See the lastUpdate variable
 * @package RBEcomPHP
 * @author Resurs Bank Ecommerce <ecommerce.support@resurs.se>
 * @version 1.0-beta
 * @branch 1.0
 * @link https://test.resurs.com/docs/x/KYM0 Get started - PHP Section
 * @link https://test.resurs.com/docs/x/TYNM EComPHP Usage
 * @license Not set
 */

/**
 * Location of RBEcomPHP class files.
 */
if (!defined('RB_API_PATH')) {
    define('RB_API_PATH', __DIR__);
}
require_once('rbapi_exceptions.php');
require_once('networklib.php');

/**
 * Class ResursBank
 */
class ResursBank
{
    /** @var string This. */
    private $clientName = "RB-EcomBridge";
    /** @var string Replacing $clientName on usage of setClientNAme */
    private $realClientName = "RB-EcomBridge";
    /** @var string The version of this gateway */
    private $version = "1.0.0";
    /** @var string Identify current version release (as long as we are located in v1.0.0beta this is necessary */
    private $lastUpdate = "20170116";
    private $preferredId = null;
    private $ocShopScript = null;
    private $formTemplateRuleArray = array();

    /**
     * Resurs Bank API Client Gateway. Works with dynamic data arrays. By default, the API-gateway will connect to Resurs Bank test environment, so to use production mode this must be configured at runtime.
     *
     * @subpackage RBEcomPHPClient
     */

    /**
     * Constant variable for using ecommerce production mode
     */
    const ENVIRONMENT_PRODUCTION = 0;
    /**
     * Constant variable for using ecommerce in test mode
     */
    const ENVIRONMENT_TEST = 1;

    /**
     * @var array Standard options for the SOAP-interface.
     */
    var $soapOptions = array(
        'exceptions' => 1,
        'connection_timeout' => 60,
        'login' => '',
        'password' => '',
        'trace' => 1
    );

    /**
     * @var array Configuration.
     */
    public $config;

    /** @var bool Set to true if you want to try to use internally cached data. This is disabled by default since it may, in a addition to performance, also be a security issue since the configuration file needs read- and write permissions */
    public $configurationInternal = false;
    /** @var string For internal handling of cache, etc */
    private $configurationSystem = "configuration";
    /** @var string Configuration file */
    private $configurationStorage = "";
    /** @var array Configuration, settings, payment methods etc */
    private $configurationArray = array();
    /** @var int Time in seconds when cache should be considered outdated and needs to get updated with new fresh data */
    public $configurationCacheTimeout = 3600;

    /** @var string Default URL to test environment */
    private $env_test = "https://test.resurs.com/ecommerce-test/ws/V4/";
    /** @var string Default URL to production environment */
    private $env_prod = "https://ecommerce.resurs.com/ws/V4/";

    /** @var string Default URL to hostedflow test */
    private $env_hosted_test = "https://test.resurs.com/ecommerce-test/hostedflow/back-channel";
    /** @var string Default URL to hosted flow production */
    private $env_hosted_prod = "https://ecommerce-hosted.resurs.com/back-channel";
    /** @var string The current chosen URL for hosted flow after initiation */
    private $env_hosted_current = "";
    private $jsonHosted = "";
    private $jsonOmni = "";

    /** @var string Default URL to omnicheckout test */
    private $env_omni_test = "https://omnitest.resurs.com";
    /** @var string Default URL to omnicheckout production */
    private $env_omni_prod = "https://checkout.resurs.com";
    /** @var string The current chosen URL for omnicheckout after initiation */
    private $env_omni_current = "";

    /** @var bool Internal "handshake" control */
    private $hasinit = false;
    /** @var bool Activation of debug mode */
    public $debug = false;

    /** @var string The current directory of RB Classes */
    private $classPath = "";

    /** @var array Files to look for in class directories, to find RB */
    private $classPathFiles = array('/simplifiedshopflowservice-client/Resurs_SimplifiedShopFlowService.php', '/configurationservice-client/Resurs_ConfigurationService.php', '/aftershopflowservice-client/Resurs_AfterShopFlowService.php', '/shopflowservice-client/Resurs_ShopFlowService.php');

    /** @var null The chosen environment */
    private $environment = null;
    /** @var int Default current environment. Always set to test (security reasons) */
    public $current_environment = self::ENVIRONMENT_TEST;
    private $current_environment_updated = false;

    /** Web Services section */
    /** @var null Object configurationService */
    public $configurationService = null;
    /** @var null Object developerWebService */
    public $developerWebService = null;
    /** @var null Object simplifiedShopFlowService (this is what is primary used by this gateway) */
    public $simplifiedShopFlowService = null;
    /** @var null Object afterShopFlowService */
    public $afterShopFlowService = null;
    /** @var null Object shopwFlowService (Deprecated "long flow") */
    public $shopFlowService = null;
    /** @var null What the service has returned (debug) */
    public $serviceReturn = null;
    /** @var null Last error received */
    public $lastError = null;

    /** @var null When we know what to use from beginning */
    private $enforceService = null;

    private $paymentMethodNames = array();
    /** @var bool Always append amount data and ending urls (cost examples) */
    public $alwaysAppendPriceLast = false;

    /** @var array Stored array for booked payments */
    private $bookData = array();

    /** @var string bookedCallbackUrl that may be set in runtime on bookpayments - has to be null or a string with 1 character or more */
    private $_bookedCallbackUrl = null;
    /** @var bool If set to true, EComPHP will ignore totalVatAmounts in specrows, and recalculate the rows by itself */
    public $bookPaymentInternalCalculate = true;
    /** @var int How many decimals to use with round */
    public $bookPaymentRoundDecimals = 2;
    /** @var bool Is set to true, if EComPHP has interfered with your specrows */
    private $bookPaymentCartFixed = false;
    /** @var null Last booked payment state */
    private $lastBookPayment = null;

    /** @var null Simple web engine built on CURL, used for hosted flow */
    private $simpleWebEngine = null;

    /** @var bool Defines wheter we have detected a hosted flow request or not */
    private $isHostedFlow = false;
    private $isOmniFlow = false;

    /** @var null Omnicheckout payment data container */
    private $omniFrame = null;

    /**
     * Configuration loader
     *
     * This API is set to primary use Resurs simplified shopflow. By default, this service is loaded automatically, together with the configurationservice which is used for setting up callbacks, etc. If you need other services, like aftershop, you should add it when your API is loading like this for example:<br>
     * $API->Include[] = 'AfterShopFlowService';
     *
     * @var array Simple array with a list of which interfaces that should be automatically loaded on init. Default: ConfigurationService, SimplifiedShopFlowService
     */
    public $Include = array('ConfigurationService', 'SimplifiedShopFlowService', 'AfterShopFlowService');

    /** @var bool If set to true, EComPHP will throw on wsdl initialization (default is false, since snapshot 20160405, when Omni got implemented ) */
    public $throwOnInit = false;

    /** @var null The username used with the webservices */
    public $username = null;
    /** @var null The password used with the webservices */
    public $password = null;
    /** @var bool Enforcing of SSL/HTTPS */
    public $HTTPS = true;         /* Always require SSL */

    /** @var string Eventually a logged in user on the platform using EComPHP (used in aftershopFlow) */
    private $loggedInuser = "";

    /** Section: BookPayment. Objects automatically used to store data, while preparing the booking object */

    /** @var string Default unit measure. "st" or styck for Sweden. If your plugin is not used for Sweden, use the proper unit for your country. */
    public $defaultUnitMeasure = "st";
    /** @var null Payment data object */
    private $_paymentData = null;
    /** @var null Object for speclines/specrows */
    private $_paymentSpeclines = null;
    /** @var null Counter for a specline */
    private $_specLineID = null;

    /** @var null Order data for the payment */
    private $_paymentOrderData = null;
    /** @var null Address data for the payment */
    private $_paymentAddress = null;
    /** @var null Normally used if billing and delivery differs (Sent to the gateway clientside) */
    private $_paymentDeliveryAddress = null;
    /** @var null Customer data for the payment */
    private $_paymentCustomer = null;
    /** @var null Customer data, extended, for the payment. For example when delivery address is set */
    private $_paymentExtendedCustomer = null;
    /** @var null Card data for the payment */
    private $_paymentCardData = null;

    /** @var null Card data object: Card number */
    private $cardDataCardNumber = null;
    /** @var null Card data object: The amount applied for the customer */
    private $cardDataUseAmount = false;
    /** @var null Card data object: If set, you can set up your own amount to apply for */
    private $cardDataOwnAmount = null;

    /** @var string Customer id used at afterShopFlow */
    public $customerId = "";

    /**
     * Autodetecting of SSL capabilities section
     *
     * Default settings: Always disabled, to let the system handle this automatically.
     * If there are problems reaching wsdl or connecting to https://test.resurs.com, set $testssl to true
     *
     */

    /** @var bool PHP 5.6.0 or above only: If defined, try to guess if there is valid certificate bundles when using for example https links (used with openssl). This function tries to detect whether sslVerify should be used or not. The default value of this setting is normally false, since there should be no problems in a correctly installed environment. */
    public $testssl = false;
    /** @var bool Sets "verify SSL certificate in production required" if true (and if true, unverified SSL certificates will throw an error in production) - for auto-testing certificates only */
    public $sslVerifyProduction = true;
    /** @var bool Do not test certificates on older PHP-version (< 5.6.0) if this is false */
    public $testssldeprecated = false;
    /** @var array Default paths to the certificates we are looking for */
    public $sslPemLocations = array('/etc/ssl/certs/cacert.pem', '/etc/ssl/certs/ca-certificates.crt', '/usr/local/ssl/certs/cacert.pem');
    /** @var bool During tests this will be set to true if certificate files is found */
    private $hasCertFile = false;
    private $useCertFile = "";
    private $hasDefaultCertFile = false;
    private $openSslGuessed = false;

    /** @var bool During tests this will be set to true if certificate directory is found */
    private $hasCertDir = false;

    /** @var bool SSL Certificate verification setting. Setting this to false, we will ignore certificate errors */
    private $sslVerify = true;

    /** @var null Get Cost of Purchase Custom HTML - Before html code received from webservices */
    private $getcost_html_before;
    /** @var null Get Cost of Purchase Custom HTML - AFter html code received from webservices */
    private $getcost_html_after;

    /* Callback related variables */
    private $digestKey = array();

    /** @var string Globally set digestive key */
    private $globalDigestKey = "";

    /**
     * If set to true, we're trying to convert received object data to standard object classes so they don't get incomplete on serialization.
     *
     * Only a few calls are dependent on this since most of the objects don't need this.
     * Related to issue #63127
     *
     * @var bool
     */
    public $convertObjects = false;

    /**
     * @var bool Converting objects when a getMethod is used with ecommerce. This is only activated when convertObjects are active
     */
    public $convertObjectsOnGet = true;

    /**
     * Array rules set, baed by getTemplateFieldsByMethodType()
     * @var array
     */
    private $templateFieldsByMethodResponse = array();


    //private $_paymentCart = null;

    /**
     * Constructor method for Resurs Bank WorkFlows
     *
     * This method prepares initial variables for the workflow. No connections are being made from this point.
     *
     * @param string $login
     * @param string $password
     * @param int $targetEnvironment
     * @throws ResursException
     */
    function __construct($login = '', $password = '', $targetEnvironment = ResursEnvironments::ENVIRONMENT_NOT_SET)
    {
        if (defined('RB_API_PATH')) {
            $this->classPath = RB_API_PATH;
        }

        if (!class_exists('ReflectionClass')) {
            throw new ResursException("ReflectionClass can not be found", ResursExceptions::CLASS_REFLECTION_MISSING, __FUNCTION__);
        }
        $this->soapOptions['cache_wsdl'] = (defined('WSDL_CACHE_BOTH') ? WSDL_CACHE_BOTH : true);
        $this->soapOptions['ssl_method'] = (defined('SOAP_SSL_METHOD_TLS') ? SOAP_SSL_METHOD_TLS : false);
        if (!is_null($login)) {
            $this->soapOptions['login'] = $login;
            $this->username = $login; // For use with initwsdl
        }
        if (!is_null($password)) {
            $this->soapOptions['password'] = $password;
            $this->password = $password; // For use with initwsdl
        }
        // PreSelect environment when creating the class
        if ($targetEnvironment != ResursEnvironments::ENVIRONMENT_NOT_SET) {
            $this->setEnvironment($targetEnvironment);
        }
    }


    /**
     * Set a new url to the chosen test flow (this is prohibited in production sets)
     *
     * @param string $newUrl
     * @param int $FlowType
     * @return string
     */
    public function setTestUrl($newUrl = '', $FlowType = ResursMethodTypes::METHOD_UNDEFINED)
    {
        if (!preg_match("/^http/i", $newUrl)) {
            /*
             * Automatically base64-decode if encoded
             */
            $testDecoded = $this->base64url_decode($newUrl);
            if (preg_match("/^http/i", $testDecoded)) {
                $newUrl = $testDecoded;
            } else {
                $newUrl = "https://" . $newUrl;
            }
        }
        if ($FlowType == ResursMethodTypes::METHOD_SIMPLIFIED) {
            $this->env_test = $newUrl;
        } else if ($FlowType == ResursMethodTypes::METHOD_HOSTED) {
            $this->env_hosted_test = $newUrl;
        } else if ($FlowType == ResursMethodTypes::METHOD_OMNI) {
            $this->env_omni_test = $newUrl;
        } else {
            /*
             * If this developer wasn't sure of what to change, we'd change all.
             */
            $this->env_test = $newUrl;
            $this->env_hosted_test = $newUrl;
            $this->env_omni_test = $newUrl;
        }
        return $newUrl;
    }

    /**
     * base64_encode
     *
     * @param $data
     * @return string
     */
    public function base64url_encode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * base64_decode
     *
     * @param $data
     * @return string
     */
    public function base64url_decode($data)
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }

    /**
     * Convert array to json
     * @param array $jsonData
     * @return array|mixed|string|void
     * @throws ResursException
     */
    private function toJson($jsonData = array())
    {
        if (is_array($jsonData) || is_object($jsonData)) {
            $jsonData = json_encode($jsonData);
            if (json_last_error()) {
                throw new ResursException(json_last_error_msg(), json_last_error());
            }
        }
        return $jsonData;
    }

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
     * @return array|mixed|string|void
     */
    public function toJsonByType($dataContainer = array(), $paymentMethodType = ResursMethodTypes::METHOD_SIMPLIFIED, $updateCart = false)
    {
        // We need the content as is at this point since this part normally should be received as arrays
        $newDataContainer = $this->getDataObject($dataContainer, false, true);
        if (!isset($newDataContainer['type']) || empty($newDataContainer['type'])) {
            if ($paymentMethodType == ResursMethodTypes::METHOD_HOSTED) {
                $newDataContainer['type'] = 'hosted';
            } else if ($paymentMethodType == ResursMethodTypes::METHOD_OMNI) {
                $newDataContainer['type'] = 'omni';
            }
        }
        if (isset($newDataContainer['type']) && !empty($newDataContainer['type'])) {
            /**
             * Hosted flow ruleset
             */
            if (strtolower($newDataContainer['type']) == "hosted") {
                /* If the specLines are defined as simplifiedFlowSpecrows, we need to convert them to hosted speclines */
                $hasSpecLines = false;
                /* If there is an old array containing specLines, this has to be renamed to orderLines */
                if (isset($newDataContainer['orderData']['specLines'])) {
                    $newDataContainer['orderData']['orderLines'] = $newDataContainer['orderData']['specLines'];
                    unset($newDataContainer['orderData']['specLines']);
                    $hasSpecLines = true;
                }
                /* If there is a specLine defined in the parent array ... */
                if (isset($newDataContainer['specLine'])) {
                    /* ... then check if we miss orderLines ... */
                    if (!$hasSpecLines) {
                        /* ... and add them on demand */
                        $newDataContainer['orderData']['orderLines'] = $newDataContainer['specLine'];
                    }
                    /* Then unset the old array */
                    unset($newDataContainer['specLine']);
                }
                /* If there is an address array on first level, we need to move the array to the customerArray*/
                if (isset($newDataContainer['address'])) {
                    $newDataContainer['customer']['address'] = $newDataContainer['address'];
                    unset($newDataContainer['address']);
                }
                /* The same rule as in the address case applies to the deliveryAddress */
                if (isset($newDataContainer['deliveryAddress'])) {
                    $newDataContainer['customer']['deliveryAddress'] = $newDataContainer['deliveryAddress'];
                    unset($newDataContainer['deliveryAddress']);
                }

                /* Now, let's see if there is a simplifiedFlow country applied to the customer data. In that case, we need to convert it to at countryCode. */
                if (isset($newDataContainer['customer']['address']['country'])) {
                    $newDataContainer['customer']['address']['countryCode'] = $newDataContainer['customer']['address']['country'];
                    unset($newDataContainer['customer']['address']['country']);
                }
                /* The same rule applied to the deliveryAddress */
                if (isset($newDataContainer['customer']['deliveryAddress']['country'])) {
                    $newDataContainer['customer']['deliveryAddress']['countryCode'] = $newDataContainer['customer']['deliveryAddress']['country'];
                    unset($newDataContainer['customer']['deliveryAddress']['country']);
                }

                if (isset($newDataContainer['signing'])) {
                    if (!isset($newDataContainer['successUrl']) && isset($newDataContainer['signing']['successUrl'])) {
                        $newDataContainer['successUrl'] = $newDataContainer['signing']['successUrl'];
                    }
                    if (!isset($newDataContainer['failUrl']) && isset($newDataContainer['signing']['failUrl'])) {
                        $newDataContainer['failUrl'] = $newDataContainer['signing']['failUrl'];
                    }
                    if (!isset($newDataContainer['forceSigning']) && isset($newDataContainer['signing']['forceSigning'])) {
                        $newDataContainer['forceSigning'] = $newDataContainer['signing']['forceSigning'];
                    }
                    unset($newDataContainer['signing']);
                }
                $this->jsonHosted = $this->getDataObject($newDataContainer, true);
            }

            /**
             * OmniCheckout Ruleset
             */
            if (strtolower($newDataContainer['type']) == "omni") {
                if (isset($newDataContainer['specLine'])) {
                    $newDataContainer['orderLines'] = $newDataContainer['specLine'];
                    unset($newDataContainer['specLine']);
                }
                if (isset($newDataContainer['specLines'])) {
                    $newDataContainer['orderLines'] = $newDataContainer['specLines'];
                    unset($newDataContainer['specLines']);
                }
                /*
                 * OmniFrameJS helper.
                 */
                if (!isset($newDataContainer['shopUrl'])) {
                    $newDataContainer['shopUrl'] = "http" . (isset($_SERVER['HTTPS']) ? "s" : "") . "://" . $_SERVER['HTTP_HOST'];
                }


                $orderlineProps = array("artNo", "vatPcs", "vatPct", "unitMeasure", "quantity", "description", "unitAmountWithoutVat");
                /**
                 * Sanitizing orderlines in case it's an orderline conversion from a simplified shopflow.
                 */
                if (isset($newDataContainer['orderLines']) && is_array($newDataContainer['orderLines'])) {
                    $orderLineClean = array();
                    foreach ($newDataContainer['orderLines'] as $orderLineId => $orderLineArray) {
                        foreach ($orderLineArray as $orderLineArrayKey => $orderLineArrayValue) {
                            if (!in_array($orderLineArrayKey, $orderlineProps)) {
                                unset($orderLineArray[$orderLineArrayKey]);
                            }
                        }
                        $orderLineClean[] = $orderLineArray;
                    }
                    $newDataContainer['orderLines'] = $orderLineClean;
                }
                if (isset($newDataContainer['address'])) {
                    unset($newDataContainer['address']);
                }
                if (isset($newDataContainer['uniqueId'])) {
                    unset($newDataContainer['uniqueId']);
                }
                if (isset($newDataContainer['signing'])) {
                    if (!isset($newDataContainer['successUrl']) && isset($newDataContainer['signing']['successUrl'])) {
                        $newDataContainer['successUrl'] = $newDataContainer['signing']['successUrl'];
                    }
                    if (!isset($newDataContainer['backUrl']) && isset($newDataContainer['signing']['failUrl'])) {
                        $newDataContainer['backUrl'] = $newDataContainer['signing']['failUrl'];
                    }
                    unset($newDataContainer['signing']);
                }
                if (isset($newDataContainer['customer']['phone'])) {
                    if (!isset($newDataContainer['customer']['mobile']) || (isset($newDataContainer['customer']['mobile']) && empty($newDataContainer['customer']['mobile']))) {
                        $newDataContainer['customer']['mobile'] = $newDataContainer['customer']['phone'];
                    }
                    unset($newDataContainer['customer']['phone']);
                }
                if ($updateCart) {
                    /*
                     * Return orderLines only, if this function is called as an updateCart.
                     */
                    $newDataContainer = array(
                        'orderLines' => is_array($newDataContainer['orderLines']) ? $newDataContainer['orderLines'] : array()
                    );
                }
                $this->jsonOmni = $newDataContainer;
            }
        }
        if (isset($newDataContainer['type'])) {
            unset($newDataContainer['type']);
        }
        if (isset($newDataContainer['uniqueId'])) {
            unset($newDataContainer['uniqueId']);
        }
        $returnJson = $this->toJson($newDataContainer);
        return $returnJson;
    }

    public function getBookedJsonObject($method = ResursMethodTypes::METHOD_UNDEFINED)
    {
        $returnObject = new stdClass();
        if ($method == ResursMethodTypes::METHOD_SIMPLIFIED) {
            return $returnObject;
        } elseif ($method == ResursMethodTypes::METHOD_HOSTED) {
            return $this->jsonHosted;
        } else {
            return $this->jsonOmni;
        }
    }

    /**
     * Create a simple engine for cURL, for use with for example hosted flow.
     *
     * @param string $url
     * @param string $jsonData
     * @param int $curlMethod POST, GET, DELETE, etc
     * @return mixed
     * @throws Exception
     */
    private function createJsonEngine($url = '', $jsonData = "", $curlMethod = ResursCurlMethods::METHOD_POST)
    {
        $CurlLibResponse = null;
        $CURL = new \TorneLIB\Tornevall_cURL();
        $CURL->setAuthentication($this->username, $this->password);
        $CURL->setUserAgent("EComPHP " . $this->version);
        if ($curlMethod == ResursCurlMethods::METHOD_POST) {
            $CurlLibResponse = $CURL->doPost($url, $jsonData, \TorneLIB\CURL_POST_AS::POST_AS_JSON);
        } else if ($curlMethod == ResursCurlMethods::METHOD_PUT) {
            $CurlLibResponse = $CURL->doPut($url, $jsonData, \TorneLIB\CURL_POST_AS::POST_AS_JSON);
        } else {
            $CurlLibResponse = $CURL->doGet($url, \TorneLIB\CURL_POST_AS::POST_AS_JSON);
        }
        if ($CurlLibResponse['code'] >= 400) {
            $useResponseCode = $CurlLibResponse['code'];
            if (is_object($CurlLibResponse['parsed'])) {
                $ResursResponse = $CurlLibResponse['parsed'];
                if (isset($ResursResponse->error)) {
                    if (isset($ResursResponse->status)) {
                        $useResponseCode = $ResursResponse->status;
                    }
                    throw new Exception($ResursResponse->error, $useResponseCode);
                }
                /*
                 * Must handle ecommerce errors too.
                 */
                if (isset($ResursResponse->errorCode) && $ResursResponse->errorCode > 0) {
                    throw new Exception(isset($ResursResponse->description) && !empty($ResursResponse->description) ? $ResursResponse->description : "Unknown error in " . __FUNCTION__, $ResursResponse->errorCode);
                }
            }
        } else {
            /*
             * Receiving code 200 here is flawless
             */
            return $CurlLibResponse;
        }
        //return $result;
    }

    /**
     * Function to enable/disabled SSL Peer/Host verification, if problems occur with certificates
     * @param bool|true $enabledFlag
     */
    public function setSslVerify($enabledFlag = true)
    {
        $this->sslVerify = $enabledFlag;
    }

    /**
     * Define current environment
     * @param int $environmentType
     */
    public function setEnvironment($environmentType = ResursEnvironments::ENVIRONMENT_TEST)
    {
        $this->current_environment = $environmentType;
        $this->current_environment_updated = true;
    }

    /**
     * Find out if we have internal configuration enabled. The config file supports serialized (php) data and json-encoded content, but saves all data serialized.
     * @return bool
     */
    private function hasConfiguration()
    {
        /* Internally stored configuration - has to be activated on use */
        if ($this->configurationInternal) {
            if (defined('RB_API_CONFIG') && file_exists(RB_API_CONFIG . "/" . $this->configurationSystem)) {
                $this->configurationStorage = RB_API_CONFIG . "/" . $this->configurationSystem . "/config.data";
            } elseif (file_exists(__DIR__ . "/" . $this->configurationSystem)) {
                $this->configurationStorage = __DIR__ . "/" . $this->configurationSystem . "/config.data";
            }
            /* Initialize configuration storage if exists */
            if (!empty($this->configurationStorage) && !file_exists($this->configurationStorage)) {
                $defaults = array(
                    'system' => array(
                        'representative' => $this->username
                    )
                );

                @file_put_contents($this->configurationStorage, serialize($defaults), LOCK_EX);
                if (!file_exists($this->configurationStorage)) {
                    /* Disable internal configuration during this call, if no file has been found after initialization */
                    $this->configurationInternal = false;
                    return false;
                }
            }
        }
        if ($this->configurationInternal) {
            $this->config = file_get_contents($this->configurationStorage);
            $getArray = @unserialize($this->config);
            if (!is_array($getArray)) {
                $getArray = @json_decode($this->config, true);
            }
            if (!is_array($getArray)) {
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
     * TestCerts - Test if your webclient has certificates available (make sure the $testssldeprecated are enabled if you want to test older PHP-versions - meaning older than 5.6.0)
     * @return bool
     */
    public function TestCerts()
    {
        return $this->openssl_guess();
    }

    /**
     * Convert a object to a data object (Issue #63127)
     * @param array $d
     * @param bool $forceConversion
     * @param bool $preventConversion
     * @return array|mixed|null
     */
    private function getDataObject($d = array(), $forceConversion = false, $preventConversion = false)
    {
        if ($preventConversion) {
            return $d;
        }
        if ($this->convertObjects || $forceConversion) {
            /**
             * If json_decode and json_encode exists as function, do it the simple way.
             * http://php.net/manual/en/function.json-encode.php
             */
            if (function_exists('json_decode') && function_exists('json_encode')) {
                return json_decode(json_encode($d));
            }
            $newArray = array();
            if (is_array($d) || is_object($d)) {
                foreach ($d as $itemKey => $itemValue) {
                    if (is_array($itemValue)) {
                        $newArray[$itemKey] = (array)$this->getDataObject($itemValue);
                    } elseif (is_object($itemValue)) {
                        $newArray[$itemKey] = (object)(array)$this->getDataObject($itemValue);
                    } else {
                        $newArray[$itemKey] = $itemValue;
                    }
                }
            }
        } else {
            return $d;
        }
        return $newArray;
    }


    /**
     * Wsdl initializer - Everything communicating with RB Webservices are recommended to pass through here to generate a communication link
     * @return bool
     * @throws Exception
     */
    private function InitializeWsdl()
    {
        $throwable = true;
        /**
         * Looking for certs on request here, instead of constructor level.
         */
        if ($this->testssl) {
            $this->openssl_guess();
        }
        try {
            if (!$this->hasinit) {
                /**
                 * Marking this part as performed, even if it's currently empty
                 */
                if (!count($this->Include)) {
                    $this->hasinit = true;
                    return $this->hasinit;
                }
                try {
                    $this->hasinit = $this->initWsdl();
                    return $this->hasinit;
                } catch (\Exception $e) {
                    $this->hasinit = false;
                    throw new ResursException($e->getMessage(), $e->getCode(), __FUNCTION__);
                }
            }
        } catch (\Exception $initWsdlException) {
            /**
             * If there is an exception here, that comes from the initializer with errcode NO_SERVICE_CLASSES_LOADED and the Includer is empty
             * we should consider the exception as unthrowable. This exception guard is placed here if some exceptions slipped through the above
             * check of Include. If throws are breaking new flows there is however a toggler that disables throws completely on initialization.
             */
            if (($initWsdlException->getCode() == ResursExceptions::NO_SERVICE_CLASSES_LOADED || $initWsdlException->getCode() == ResursExceptions::NO_SERVICE_API_HANDLED) && !count($this->Include)) {
                $throwable = false;
            }
            if ($this->throwOnInit && $throwable) {
                throw new ResursException($initWsdlException->getMessage(), $initWsdlException->getCode(), __FUNCTION__);
            }
        }
        return $this->hasinit;
    }

    /**
     * If configuration file exists, this is the place where we're updating the content of it.
     *
     * @param string $arrayName The name of the array we're going to save
     * @param array $arrayContent The content of the array
     * @return bool If save is successful, we return true
     */
    private function updateConfig($arrayName, $arrayContent = array())
    {
        /* Make sure that the received array is really an array, since objects from ecom may be trashed during serialziation */
        $arrayContent = $this->objectsIntoArray($arrayContent);
        if ($this->configurationInternal && !empty($arrayName)) {
            $this->configurationArray[$arrayName] = $arrayContent;
            $this->configurationArray['lastUpdate'][$arrayName] = time();
            $serialized = @serialize($this->configurationArray);
            if (file_exists($this->configurationStorage)) {
                @file_put_contents($this->configurationStorage, $serialized, LOCK_EX);
                return true;
            }
        }
        return false;
    }

    /**
     * Returns an array with stored configuration (if stored configuration are enabled)
     * @return array
     */
    public function getConfigurationCache()
    {
        return $this->configurationArray;
    }

    /**
     * Get a timestamp for when the last cache of a call was requested and saved from Ecommerce
     * @param $cachedArrayName
     * @return int
     */
    public function getLastCall($cachedArrayName)
    {
        if (isset($this->configurationArray['lastUpdate']) && isset($this->configurationArray['lastUpdate'][$cachedArrayName])) {
            return time() - intval($this->configurationArray['lastUpdate'][$cachedArrayName]);
        }
        return time(); /* If nothing is set, indicate that it has been a long time since last call was made... */
    }

    /**
     * Find classes
     * @param string $path
     * @return bool
     */
    private function classes($path = '')
    {
        foreach ($this->classPathFiles as $file) {
            if (file_exists($path . "/" . $file)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get current client name and version
     * @param bool $getDecimals (Get it as decimals, simple mode)
     * @return string
     */
    protected function getVersionFull($getDecimals = false)
    {
        if (!$getDecimals) {
            return $this->clientName . " v" . $this->version . "-" . $this->lastUpdate;
        }
        return $this->clientName . "_" . $this->versionToDecimals();
    }

    /**
     * Get current client version only
     * @param bool $getDecimals
     * @return string
     */
    protected function getVersionNumber($getDecimals = false)
    {
        if (!$getDecimals) {
            return $this->version . "-" . $this->lastUpdate;
        } else {
            return $this->versionToDecimals();
        }
    }

    /**
     * Get "Created by" if set (used by aftershop)
     * @return string
     */
    protected function getCreatedBy()
    {
        $createdBy = $this->realClientName . "_" . $this->getVersionNumber(true);
        if (!empty($this->loggedInuser)) {
            $createdBy .= "/" . $this->loggedInuser;
        }
        return $createdBy;
    }

    /**
     * Adds your client name to the current client name string which is used as User-Agent when communicating with ecommerce.
     * @param string $clientNameString
     */
    public function setClientName($clientNameString = "")
    {
        $this->clientName = $clientNameString . "/" . $this->realClientName;
    }

    /**
     * Initializer for WSDL before calling services. Decides what username and environment to use. Default is always test.
     *
     * @throws ResursException
     */
    private function initWsdl()
    {
        $this->hasConfiguration();
        $this->testWrappers();
        /*
         * Make sure that the correct webservice is loading first. The topmost service has the highest priority and will not be overwritten once loaded.
         * For example, if ShopFlowService is loaded before the SimplifiedShopFlowService, you won't be able to use the SimplifiedShopFlowService at all.
         *
         */
        $apiFileLoads = 0;
        $currentService = "";

        /* Try to autodetect wsdl location and set up new path for where they can be loaded */
        if (!$this->classes($this->classPath)) {
            if ($this->classes($this->classPath . "/classes")) {
                $this->classPath = $this->classPath . "/classes";
            }
            if ($this->classes($this->classPath . "/classes/rbwsdl")) {
                $this->classPath = $this->classPath . "/classes/rbwsdl";
            }
            if ($this->classes($this->classPath . "/rbwsdl")) {
                $this->classPath = $this->classPath . "/rbwsdl";
            }
            if ($this->classes($this->classPath . "/../rbwsdl")) {
                $this->classPath = realpath($this->classPath . "/../rbwsdl");
            }
        }

        if (in_array('simplifiedshopflowservice', array_map("strtolower", $this->Include)) && file_exists($this->classPath . '/simplifiedshopflowservice-client/Resurs_SimplifiedShopFlowService.php')) {
            /** @noinspection PhpIncludeInspection */
            require $this->classPath . '/simplifiedshopflowservice-client/Resurs_SimplifiedShopFlowService.php';
            $apiFileLoads++;
        }
        if (in_array('configurationservice', array_map("strtolower", $this->Include)) && file_exists($this->classPath . '/configurationservice-client/Resurs_ConfigurationService.php')) {
            /** @noinspection PhpIncludeInspection */
            require $this->classPath . '/configurationservice-client/Resurs_ConfigurationService.php';
            $apiFileLoads++;
        }
        if (in_array('aftershopflowservice', array_map("strtolower", $this->Include)) && file_exists($this->classPath . '/aftershopflowservice-client/Resurs_AfterShopFlowService.php')) {
            /** @noinspection PhpIncludeInspection */
            require $this->classPath . '/aftershopflowservice-client/Resurs_AfterShopFlowService.php';
            $apiFileLoads++;
        }
        /**
         * Loads the deprecated flow as the last class, if found in our library. However, we normally don't deliver this setup in our EComPHP-package, so if we find this
         * the developer may have added it him/herself.
         */
        if (in_array('shopflowservice', array_map("strtolower", $this->Include)) && file_exists($this->classPath . '/shopflowservice-client/Resurs_ShopFlowService.php')) {
            /** @noinspection PhpIncludeInspection */
            require $this->classPath . '/shopflowservice-client/Resurs_ShopFlowService.php';
            $apiFileLoads++;
        }
        if (!$apiFileLoads && count($this->Include)) {
            throw new ResursException("No service classes found", ResursExceptions::NO_SERVICE_CLASSES_LOADED, __FUNCTION__);
        }

        // Requiring that SSL is available on the current server, will throw an exception if no HTTPS-wrapper is found.
        if ($this->username != null) $this->soapOptions['login'] = $this->username;
        if ($this->password != null) $this->soapOptions['password'] = $this->password;
        if ($this->current_environment == self::ENVIRONMENT_TEST) {
            $this->environment = $this->env_test;
        } else {
            $this->environment = $this->env_prod;
        }
        $this->hasCertFile = false;

        $this->soapOptions = $this->sslGetOptionsStream($this->soapOptions, array('http' => array("user_agent" => $this->getVersionFull())));
        try {
            if (class_exists('Resurs_SimplifiedShopFlowService')) {
                $currentService = "simplifiedShopFlowService";
                $this->simplifiedShopFlowService = new Resurs_SimplifiedShopFlowService($this->soapOptions, $this->environment . "SimplifiedShopFlowService?wsdl");
            }
            if (class_exists('Resurs_ConfigurationService')) {
                $currentService = "configurationService";
                $this->configurationService = new Resurs_ConfigurationService($this->soapOptions, $this->environment . "ConfigurationService?wsdl");
            }
            if (class_exists('Resurs_AfterShopFlowService')) {
                $currentService = "afterShopFlowService";
                $this->afterShopFlowService = new Resurs_AfterShopFlowService($this->soapOptions, $this->environment . "AfterShopFlowService?wsdl");
            }
            if (class_exists('Resurs_ShopFlowService')) {
                /** @noinspection PhpUnusedLocalVariableInspection */
                $currentService = "shopFlowService";
                $this->shopFlowService = new Resurs_ShopFlowService($this->soapOptions, $this->environment . "ShopFlowService?wsdl");
            }
            //if (class_exists('DeveloperWebService')) {$this->developerWebService = new DeveloperWebService($this->soapOptions, $this->environment . "DeveloperWebService?wsdl");}
        } catch (\Exception $e) {
            /** Adds the $currentService to the message, to show which service that failed */
            throw new ResursException($e->getMessage() . "\nStuck on service: " . $currentService, ResursExceptions::WSDL_APILOAD_EXCEPTION, __FUNCTION__, $e);
        }
        /* count($Include)? */
        if (!$apiFileLoads) {
            throw new ResursException("No services was loaded", ResursExceptions::NO_SERVICE_API_HANDLED, __FUNCTION__);
        }
        return true;
    }


    /**
     * Generate a correctified stream context depending on what happened in openssl_guess(), which also is running in this operation.
     *
     * Function created for moments when ini_set() fails in openssl_guess() and you don't want to "recalculate" the location of a valid certificates.
     * This normally occurs in improper configured environments (where this bulk of functions actually also has been tested in).
     * Recommendation of Usage: Do not copy only those functions, use the full version of tornevall_curl.php since there may be dependencies in it.
     *
     * @return array
     * @link http://developer.tornevall.net/apigen/TorneLIB-5.0/class-TorneLIB.Tornevall_cURL.html sslStreamContextCorrection() is a part of TorneLIB 5.0, described here
     */
    public function sslStreamContextCorrection()
    {
        if (!$this->openSslGuessed) {
            $this->openssl_guess(true);
        }
        $caCert = $this->getCertFile();
        $sslVerify = true;
        $sslSetup = array();
        if (isset($this->sslVerify)) {
            $sslVerify = $this->sslVerify;
        }
        if (!empty($caCert)) {
            $sslSetup = array(
                'cafile' => $caCert,
                'verify_peer' => $sslVerify,
                'verify_peer_name' => $sslVerify,
                'verify_host' => $sslVerify,
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
     * @return array
     * @link http://developer.tornevall.net/apigen/TorneLIB-5.0/class-TorneLIB.Tornevall_cURL.html sslGetOptionsStream() is a part of TorneLIB 5.0, described here
     */
    public function sslGetOptionsStream($optionsArray = array(), $selfContext = array())
    {
        $streamContextOptions = array();
        $sslCorrection = $this->sslStreamContextCorrection();
        if (count($sslCorrection)) {
            $streamContextOptions['ssl'] = $this->sslStreamContextCorrection();
        }
        foreach ($selfContext as $contextKey => $contextValue) {
            $streamContextOptions[$contextKey] = $contextValue;
        }
        $optionsArray['stream_context'] = stream_context_create($streamContextOptions);
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
     * @link http://developer.tornevall.net/apigen/TorneLIB-5.0/class-TorneLIB.Tornevall_cURL.html openssl_guess() is a part of TorneLIB 5.0, described here
     * @return bool
     */
    private function openssl_guess($forceTesting = false)
    {
        $pemLocation = "";
        if ($this->testssl || $forceTesting) {
            $this->openSslGuessed = true;
            if (version_compare(PHP_VERSION, "5.6.0", ">=") && function_exists("openssl_get_cert_locations")) {
                $locations = openssl_get_cert_locations();
                if (is_array($locations)) {
                    if (isset($locations['default_cert_file'])) {
                        /* If it exists don't bother */
                        if (file_exists($locations['default_cert_file'])) {
                            $this->hasCertFile = true;
                            $this->useCertFile = $locations['default_cert_file'];
                            $this->hasDefaultCertFile = true;
                        }
                        if (file_exists($locations['default_cert_dir'])) {
                            $this->hasCertDir = true;
                        }
                        /* Sometimes certificates are located in a default location, which is /etc/ssl/certs - this part scans through such directories for a proper cert-file */
                        if (!$this->hasCertFile && is_array($this->sslPemLocations) && count($this->sslPemLocations)) {
                            /* Loop through suggested locations and set a cafile if found */
                            foreach ($this->sslPemLocations as $pemLocation) {
                                if (file_exists($pemLocation)) {
                                    ini_set('openssl.cafile', $pemLocation);
                                    $this->useCertFile = $pemLocation;
                                    $this->hasCertFile = true;
                                }
                            }
                        }
                    }
                }
                /* On guess, disable verification if failed */
                if (!$this->hasCertFile) {
                    $this->setSslVerify(false);
                }
            } else {
                /* If we run on other PHP versions than 5.6.0 or higher, try to fall back into a known directory */
                if ($this->testssldeprecated) {
                    if (!$this->hasCertFile && is_array($this->sslPemLocations) && count($this->sslPemLocations)) {
                        /* Loop through suggested locations and set a cafile if found */
                        foreach ($this->sslPemLocations as $pemLocation) {
                            if (file_exists($pemLocation)) {
                                ini_set('openssl.cafile', $pemLocation);
                                $this->useCertFile = $pemLocation;
                                $this->hasCertFile = true;
                            }
                        }
                    }
                    if (!$this->hasCertFile) {
                        $this->setSslVerify(false);
                    }
                }
            }
        }
        return $this->hasCertFile;
    }

    /**
     * Return the current certificate bundle file, chosen by autodetection
     * @return string
     */
    public function getCertFile()
    {
        return $this->useCertFile;
    }


    /**
     * Special set up for internal tests putting tests outside mock but still in test
     */
    public function setNonMock()
    {
        $this->env_test = "https://test.resurs.com/ecommerce/ws/V4/";
    }

    /**
     * Check HTTPS-requirements, if they pass.
     *
     * Resurs Bank requires secure connection to the webservices, so your PHP-version must support SSL. Normally this is not a problem, but since there are server- and hosting providers that is actually having this disabled, the decision has been made to do this check.
     * @throws Exception
     */
    private function testWrappers()
    {
        if ($this->HTTPS === true) {
            if (!in_array('https', @stream_get_wrappers())) {
                throw new ResursException("HTTPS wrapper can not be found", ResursExceptions::SSL_WRAPPER_MISSING, __FUNCTION__);
            }
        }
    }

    /**
     * ResponseObjectArrayParser. Translates a return-object to a clean array
     * @param null $returnObject
     * @return array
     */
    public function parseReturn($returnObject = null)
    {
        $hasGet = false;
        if (is_array($returnObject)) {
            $parsedArray = array();
            foreach ($returnObject as $arrayName => $objectArray) {
                $classMethods = get_class_methods($objectArray);
                if (is_array($classMethods)) {
                    foreach ($classMethods as $classMethodId => $classMethod) {
                        if (preg_match("/^get/i", $classMethod)) {
                            $hasGet = true;
                            $field = lcfirst(preg_replace("/^get/i", '', $classMethod));
                            $objectContent = $objectArray->$classMethod();
                            if (is_array($objectContent)) {
                                $parsedArray[$arrayName][$field] = $this->parseReturn($objectContent);
                            } else {
                                $parsedArray[$arrayName][$field] = $objectContent;
                            }
                        }
                    }
                }
            }
            /* Failver test */
            if (!$hasGet && !count($parsedArray)) {
                return $this->objectsIntoArray($returnObject);
            }
            return $parsedArray;
        }
        return array(); /* Fail with empty array, if there is no recursive array  */
    }

    /**
     * Convert objects to array data
     * @param $arrObjData
     * @param array $arrSkipIndices
     * @return array
     */
    function objectsIntoArray($arrObjData, $arrSkipIndices = array())
    {
        $arrData = array();
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
     * Method calls that should be passed directly to a webservice
     *
     * Unknown calls passed through __call(), so that we may cover functions unsupported by the gateway.
     * This stub-gateway processing is also checking if the methods really exist in the stubs and passing them over is they do.<br>
     * <br>
     * This method also takes control of responses and returns the object "return" if it exists.<br>
     * The function also supports array, by adding "Array" to the end of the method.<br>
     *
     * @param null $func
     * @param array $args
     * @return array|null
     * @throws Exception
     *
     */
    public function __call($func = null, $args = array())
    {
        /* Initializing wsdl if not done is required here */
        $this->InitializeWsdl();

        $returnObject = null;
        $this->serviceReturn = null;
        $returnAsArray = false;
        $classfunc = null;
        $funcArgs = null;
        $returnContent = null;
        //if (isset($args[0]) && is_array($args[0])) {}
        $classfunc = "resurs_" . $func;
        if (preg_match("/Array$/", $func)) {
            $func = preg_replace("/Array$/", '', $func);
            $classfunc = preg_replace("/Array$/", '', $classfunc);
            $returnAsArray = true;
        }

        try {
            $reflection = new ReflectionClass($classfunc);
            $instance = $reflection->newInstanceArgs($args);
            // Check availability, fetch and stop on first match
            if (!isset($returnObject) && in_array($func, get_class_methods("Resurs_SimplifiedShopFlowService"))) {
                $this->serviceReturn = "SimplifiedShopFlowService";
                $returnObject = $this->simplifiedShopFlowService->$func($instance);
            }
            if (!isset($returnObject) && in_array($func, get_class_methods("Resurs_ConfigurationService"))) {
                $this->serviceReturn = "ConfigurationService";
                $returnObject = $this->configurationService->$func($instance);
            }
            if (!isset($returnObject) && in_array($func, get_class_methods("Resurs_AfterShopFlowService"))) {
                $this->serviceReturn = "AfterShopFlowService";
                $returnObject = $this->afterShopFlowService->$func($instance);
            }
            if (!isset($returnObject) && in_array($func, get_class_methods("Resurs_ShopFlowService"))) {
                $this->serviceReturn = "ShopFlowService";
                $returnObject = $this->shopFlowService->$func($instance);
            }
            //if (!isset($returnObject) && in_array($func, get_class_methods("DeveloperService"))) {$this->serviceReturn = "DeveloperService";$returnObject = $this->developerService->$func($instance);}
        } catch (Exception $e) {
            throw new ResursException($e->getMessage(), ResursExceptions::WSDL_PASSTHROUGH_EXCEPTION, __FUNCTION__ . "/" . $func . "/" . $classfunc);
        }
        try {
            if (isset($returnObject) && !empty($returnObject) && isset($returnObject->return) && !empty($returnObject->return)) {
                /* Issue #63127 - make some dataobjects storable */
                if ($this->convertObjectsOnGet && preg_match("/^get/i", $func)) {
                    $returnContent = $this->getDataObject($returnObject->return);
                } else {
                    $returnContent = $returnObject->return;
                }
                if ($returnAsArray) {
                    return $this->parseReturn($returnContent);
                }
            } else {
                /* Issue #62975: Fixes empty responses from requests not containing a return-object */
                if (empty($returnObject)) {
                    if ($returnAsArray) {
                        return array();
                    }
                } else {
                    if ($returnAsArray) {
                        return $this->parseReturn($returnContent);
                    } else {
                        return $returnObject;
                    }
                }
            }
            return $returnContent;
        } catch (Exception $returnObjectException) {
        }
        if ($returnAsArray) {
            return $this->parseReturn($returnObject);
        }
        return $returnObject;
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
     * @param null $setmax
     * @return string
     */
    private function getSaltKey($complexity = 1, $setmax = null)
    {
        $retp = null;
        $characterListArray = array(
            'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'abcdefghijklmnopqrstuvwxyz',
            '0123456789',
            '!@#$%*?'
        );

        // Set complexity to no limit if type 5 is requested
        if ($complexity == 5) {
            $characterListArray = array();
            for ($unlim = 0; $unlim <= 255; $unlim++) {
                $characterListArray[0] .= chr($unlim);
            }
            if ($setmax == null) {
                $setmax = 15;
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
        if ($setmax > 0) {
            $max = $setmax;
        }
        $chars = array();
        $numchars = array();
        //$equalityPart = ceil($max / count($characterListArray));
        for ($i = 0; $i < $max; $i++) {
            $charListId = rand(0, count($characterListArray) - 1);
            $numchars[$charListId]++;
            $chars[] = $characterListArray[$charListId]{mt_rand(0, (strlen($characterListArray[$charListId]) - 1))};
        }
        shuffle($chars);
        $retp = implode("", $chars);
        return $retp;
    }


    /**
     * Convert callback types to string names
     * @param int $callbackType
     * @return null|string
     */
    private function getCallbackTypeString($callbackType = ResursCallbackTypes::UNDEFINED)
    {
        if ($callbackType == ResursCallbackTypes::UNDEFINED) {
            return null;
        }
        if ($callbackType == ResursCallbackTypes::ANNULMENT) {
            return "ANNULMENT";
        }
        if ($callbackType == ResursCallbackTypes::AUTOMATIC_FRAUD_CONTROL) {
            return "AUTOMATIC_FRAUD_CONTROL";
        }
        if ($callbackType == ResursCallbackTypes::FINALIZATION) {
            return "FINALIZATION";
        }
        if ($callbackType == ResursCallbackTypes::TEST) {
            return "TEST";
        }
        if ($callbackType == ResursCallbackTypes::UNFREEZE) {
            return "UNFREEZE";
        }
        if ($callbackType == ResursCallbackTypes::UPDATE) {
            return "UPDATE";
        }
        if ($callbackType == ResursCallbackTypes::BOOKED) {
            return "BOOKED";
        }
        return null;
    }

    /**
     * @param string $callbackTypeString
     * @return int
     */
    public function getCallbackTypeByString($callbackTypeString = "")
    {
        if (strtoupper($callbackTypeString) == "ANNULMENT") {
            return ResursCallbackTypes::ANNULMENT;
        }
        if (strtoupper($callbackTypeString) == "UPDATE") {
            return ResursCallbackTypes::UPDATE;
        }
        if (strtoupper($callbackTypeString) == "TEST") {
            return ResursCallbackTypes::TEST;
        }
        if (strtoupper($callbackTypeString) == "FINALIZATION") {
            return ResursCallbackTypes::FINALIZATION;
        }
        if (strtoupper($callbackTypeString) == "AUTOMATIC_FRAUD_CONTROL") {
            return ResursCallbackTypes::AUTOMATIC_FRAUD_CONTROL;
        }
        if (strtoupper($callbackTypeString) == "UNFREEZE") {
            return ResursCallbackTypes::UNFREEZE;
        }
        if (strtoupper($callbackTypeString) == "BOOKED") {
            return ResursCallbackTypes::BOOKED;
        }
        return ResursCallbackTypes::UNDEFINED;
    }

    /**
     * Set up digestive parameters baed on requested callback type
     * @param int $callbackType
     * @return array
     */
    private function getCallbackTypeParameters($callbackType = ResursCallbackTypes::UNDEFINED)
    {
        if ($callbackType == ResursCallbackTypes::ANNULMENT) {
            return array('paymentId');
        }
        if ($callbackType == ResursCallbackTypes::AUTOMATIC_FRAUD_CONTROL) {
            return array('paymentId', 'result');
        }
        if ($callbackType == ResursCallbackTypes::FINALIZATION) {
            return array('paymentId');
        }
        if ($callbackType == ResursCallbackTypes::TEST) {
            return array('param1', 'param2', 'param3', 'param4', 'param5');
        }
        if ($callbackType == ResursCallbackTypes::UNFREEZE) {
            return array('paymentId');
        }
        if ($callbackType == ResursCallbackTypes::UPDATE) {
            return array('paymentId');
        }
        if ($callbackType == ResursCallbackTypes::BOOKED) {
            return array('paymentId');
        }
        return array();
    }

    /**
     * Callback digest helper - sets a simple digest key before calling setCallback
     * @param string $digestSaltString If empty, $digestSaltString is randomized
     * @param int $callbackType
     * @return string
     */
    public function setCallbackDigest($digestSaltString = '', $callbackType = ResursCallbackTypes::UNDEFINED)
    {
        /* Make sure the digestSaltString is never empty */
        if (!empty($digestSaltString)) {
            $currentDigest = $digestSaltString;
        } else {
            $currentDigest = $this->getSaltKey(4, 10);
        }
        if ($callbackType !== ResursCallbackTypes::UNDEFINED) {
            $callbackTypeString = $this->getCallbackTypeString(!is_null($callbackType) ? $callbackType : ResursCallbackTypes::UNDEFINED);
            $this->digestKey[$callbackTypeString] = $currentDigest;
        } else {
            $this->globalDigestKey = $currentDigest;
        }
        // Confirm the set up
        return $currentDigest;
    }

    /**
     * Simplifed callback registrator. Also handles re-registering of callbacks in case of already found in system.
     *
     * @param int $callbackType
     * @param string $callbackUriTemplate
     * @param array $callbackDigest If empty or null, this is automatically handled
     * @param null $basicAuthUserName
     * @param null $basicAuthPassword
     * @return bool
     * @throws Exception
     */
    public function setCallback($callbackType = ResursCallbackTypes::UNDEFINED, $callbackUriTemplate = "", $callbackDigest = array(), $basicAuthUserName = null, $basicAuthPassword = null)
    {
        $this->InitializeWsdl();
        $renderCallback = array();
        $digestParameters = array();
        $regCallBackResult = false;     // CodeInspection - Set to FunctionGlobal
        $renderCallback['eventType'] = $this->getCallbackTypeString($callbackType);

        if (empty($renderCallback['eventType'])) {
            throw new ResursException("The callback type you are trying to register is not supported by EComPHP", ResursExceptions::CALLBACK_TYPE_UNSUPPORTED, __FUNCTION__);
        }
        $confirmCallbackResult = false;

        if (count($renderCallback) && $renderCallback['eventType'] != "" && !empty($callbackUriTemplate)) {
            /** @noinspection PhpParamsInspection */
            $registerCallbackClass = new resurs_registerEventCallback($renderCallback['eventType'], $callbackUriTemplate);
            $digestAlgorithm = resurs_digestAlgorithm::SHA1;

            /*
             * Look for parameters in the request. Algorithms are set to SHA1 by default.
             * If no digestsalts are set, we will throw you an exception, since empty salts is normally not a good idea
             */
            if (is_array($callbackDigest)) {
                if (isset($callbackDigest['digestAlgorithm']) && strtolower($callbackDigest['digestAlgorithm']) != "sha1" && strtolower($callbackDigest['digestAlgorithm']) != "md5") {
                    $callbackDigest['digestAlgorithm'] = "sha1";
                } elseif (!isset($callbackDigest['digestAlgorithm'])) {
                    $callbackDigest['digestAlgorithm'] = "sha1";
                }
                /* If requested algorithm is not sha1, use md5 as the other option. */
                if ($callbackDigest['digestAlgorithm'] != "sha1") {
                    $digestAlgorithm = digestAlgorithm::MD5;
                }

                /* Start collect the parameters needed for the callback (manually if necessary - otherwise, we'll catch the parameters from our defaults as described at https://test.resurs.com/docs/x/LAAF) */
                $parameterArray = array();

                if ((is_array($callbackDigest['digestParameters']) && !count($callbackDigest['digestParameters'])) || empty($callbackDigest['digestParameters'])) {
                    $callbackDigest['digestParameters'] = $this->getCallbackTypeParameters($callbackType);
                }

                if (isset($callbackDigest['digestParameters']) && is_array($callbackDigest['digestParameters'])) {
                    if (count($callbackDigest['digestParameters'])) {
                        foreach ($callbackDigest['digestParameters'] as $parameter) {
                            array_push($parameterArray, $parameter);
                        }
                    }
                }

                /*
                 * Check if the helper received a salt key. To now interfere with the array of digestKey, we are preparing with globalDigestKey.
                 */
                if (!count($this->digestKey) && !empty($this->globalDigestKey)) {
                    /* Only set up the digesSalt if not already set */
                    if (empty($callbackDigest['digestSalt'])) {
                        $callbackDigest['digestSalt'] = $this->globalDigestKey;
                    }
                } else {
                    if (isset($this->digestKey[$renderCallback['eventType']])) {
                        $callbackDigest['digestSalt'] = $this->digestKey['eventType'];
                    }
                }
                /* Make sure there is a saltkey or throw */
                if (isset($callbackDigest['digestSalt']) && !empty($callbackDigest['digestSalt'])) {
                    $digestParameters['digestSalt'] = $callbackDigest['digestSalt'];
                } else {
                    throw new ResursException("No salt key for digest found", ResursExceptions::CALLBACK_SALTDIGEST_MISSING, __FUNCTION__);
                }
                $digestParameters['digestParameters'] = (is_array($parameterArray) ? $parameterArray : array());
            }
            /* Generate a digest configuration for the services. */
            /** @noinspection PhpParamsInspection */
            $digestConfiguration = new resurs_digestConfiguration($digestAlgorithm, $digestParameters['digestParameters']);
            $digestConfiguration->digestSalt = $digestParameters['digestSalt'];

            /* Unregister any old callbacks if found. */
            $this->unSetCallback($callbackType);

            /* If your site needs authentication to reach callbacks, this sets up a basic authentication for it */
            if (!empty($basicAuthUserName)) {
                $registerCallbackClass->setBasicAuthUserName($basicAuthUserName);
            }
            if (!empty($basicAuthPassword)) {
                $registerCallbackClass->setBasicAuthPassword($basicAuthPassword);
            }

            /* Prepare for the primary digestive data. */
            $registerCallbackClass->digestConfiguration = $digestConfiguration;
            try {
                /* And register the rendered callback at the service. Make sure that configurationService is really there before doing this. */
                $regCallBackResult = $this->configurationService->registerEventCallback($registerCallbackClass);
                if (is_object($regCallBackResult)) {
                    $confirmCallbackResult = true;
                }
            } catch (Exception $rbCallbackEx) {
                /* Set up a silent. failover, and return false if the callback registration failed. Set the error into the lastError-variable. */
                $regCallBackResult = false;
                $this->lastError = $rbCallbackEx->getMessage();
            }

            /* If the answer, received from the registerEventCallback, is an empty object we should also secure that everything is all right with the requested URL */
            if ($confirmCallbackResult) {
                $getRegisteredCallbackConfirmation = $this->getRegisteredEventCallback($renderCallback['eventType']);
                if (isset($getRegisteredCallbackConfirmation->uriTemplate)) {
                    if (strtolower($getRegisteredCallbackConfirmation->uriTemplate) == strtolower($callbackUriTemplate)) {
                        return true;
                    } else {
                        // Mismatching urls fails
                        throw new ResursException("registerEventCallback returns a different callback URL than expected", ResursExceptions::CALLBACK_URL_MISMATCH, __FUNCTION__);
                    }
                }
            }
            return $regCallBackResult;
        } else {
            throw new ResursException("Insufficient data for callback registration", ResursExceptions::CALLBACK_UNSUFFICIENT_DATA, __FUNCTION__);
        }
    }

    /**
     * Simplifies removal of callbacks even when they does not exist at first.
     *
     * @param int $callbackType
     * @return bool
     * @throws ResursException
     */
    public function unSetCallback($callbackType = ResursCallbackTypes::UNDEFINED)
    {
        $renderCallback['eventType'] = $this->getCallbackTypeString($callbackType);
        if (empty($renderCallback['eventType'])) {
            throw new ResursException("The callback type you are trying to unregister is not supported by EComPHP", ResursExceptions::CALLBACK_TYPE_UNSUPPORTED);
        }
        try {
            $this->unregisterEventCallback($renderCallback['eventType']);
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    /**
     * Trigger the registered callback event TEST if set. Returns true if trigger call was successful, otherwise false (Observe that this not necessarily is a successful completion of the callback)
     *
     * @return bool
     */
    public function testCallback()
    {
        $serviceUrl = $this->env_test . "DeveloperWebService?wsdl";
        $CURL = new \TorneLIB\Tornevall_cURL();
        $CURL->setAuthentication($this->username, $this->password);
        $CURL->setUserAgent("EComPHP " . $this->version);
        $eventRequest = $CURL->doGet($serviceUrl);
        $eventParameters = array(
            'eventType' => 'TEST',
            'param' => array(
                rand(10000, 30000),
                rand(10000, 30000),
                rand(10000, 30000),
                rand(10000, 30000),
                rand(10000, 30000)
            )
        );
        try {
            $eventRequest->triggerEvent($eventParameters);
        } catch (Exception $e) {
            return false;
        }
        return true;
    }


    /**
     * List payment methods
     *
     * Retrieves detailed information on the payment methods available to the representative. Parameters (customerType, language and purchaseAmount) are optional.
     * @link https://test.resurs.com/docs/display/ecom/Get+Payment+Methods
     *
     * @param array $parameters
     * @return mixed
     * @throws Exception
     */
    public function getPaymentMethods($parameters = array())
    {
        $this->InitializeWsdl();

        /** @noinspection PhpUnusedLocalVariableInspection */
        $return = $this->getDataObject(array());
        /* 2016-07-13: Redmine issue #66819 - Call to a member function getPaymentMethods() on null - probably fails when session times out */
        if (class_exists("resurs_getPaymentMethods") && isset($this->simplifiedShopFlowService) && !empty($this->simplifiedShopFlowService)) {
            $paymentMethodParameters = new resurs_getPaymentMethods();
            if (isset($parameters['customerType'])) {
                $paymentMethodParameters->customerType = $parameters['customerType'];
            }
            if (isset($parameters['language']) && !empty($parameters['language'])) {
                $paymentMethodParameters->language = $parameters['language'];
            }
            if (isset($parameters['purchaseAmount'])) {
                $paymentMethodParameters->purchaseAmount = $parameters['purchaseAmount'];
            }
            /*
             * Decide how data objects should be returned.
             */
            $return = $this->getDataObject(isset($this->simplifiedShopFlowService->getPaymentMethods($paymentMethodParameters)->return) ? $this->simplifiedShopFlowService->getPaymentMethods($paymentMethodParameters)->return : array());

            /*
			 * If the return value is objectified instead of arrayed, we should be aware of that the response may only contain one payment method and surround the returned response as
			 * that missing part to keep the expected behaviour in the core of EComPHP. This issue should have been fixed for a long time ago.
			 *
			 * Note: Nothing of this may work properly in ResursLIB(-PHP) so the method has to be rewritten based on a newer wsdl generator.
			 *
			 */
            if (!is_array($return) && is_object($return)) {
                $newReturn = array($return);
                return $newReturn;
            }
            $this->updateConfig('getPaymentMethods', $return);

        } else {
            $return = array(
                'error' => 'Can not load class for getPaymentMethods'
            );
        }
        return $return;
    }


    /**
     * Get the list of Resurs Bank payment methods from cache, instead of live (Cache function needs to be active)
     * @return array
     * @throws ResursException
     */
    public function getPaymentMethodsCache()
    {
        if ($this->hasConfiguration()) {
            if (isset($this->configurationArray['getPaymentMethods']) && is_array($this->configurationArray['getPaymentMethods']) && count($this->configurationArray) && !$this->cacheExpired('getPaymentMethods')) {
                return $this->configurationArray['getPaymentMethods'];
            } else {
                return $this->objectsIntoArray($this->getPaymentMethods());
            }
        }
        throw new ResursException("Can not fetch payment methods from cache. You must enable internal caching first.", ResursExceptions::PAYMENT_METHODS_CACHE_DISABLED, __FUNCTION__);
    }

    /**
     * Get a list of current available payment methods, in the form of an arraylist with id's
     * @return array
     */
    public function getPaymentMethodNames()
    {
        $methods = $this->getPaymentMethods();
        if (is_array($methods)) {
            $this->paymentMethodNames = array();
            foreach ($methods as $objectMethod) {
                if (isset($objectMethod->id) && !empty($objectMethod->id) && !in_array($objectMethod->id, $this->paymentMethodNames)) {
                    $this->paymentMethodNames[$objectMethod->id] = $objectMethod->id;
                }
            }
        }
        return $this->paymentMethodNames;
    }

    /**
     * Fetch one specific payment method only, from Resurs Bank
     * @param string $specificMethodName
     * @return array If not found, array will be empty
     */
    public function getPaymentMethodSpecific($specificMethodName = '')
    {
        $methods = $this->getPaymentMethods();
        $methodArray = array();
        if (is_array($methods)) {
            foreach ($methods as $objectMethod) {
                if (isset($objectMethod->id) && strtolower($objectMethod->id) == strtolower($specificMethodName)) {
                    $methodArray = $objectMethod;
                }
            }
        }
        return $methodArray;
    }

    /**
     * Test if a stored configuration (cache) has expired and needs to be renewed.
     * @param $cachedArrayName
     * @return bool
     */
    private function cacheExpired($cachedArrayName)
    {
        if ($this->getLastCall($cachedArrayName) >= $this->configurationCacheTimeout) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get annuityfactors from payment method through cache, instead of live (Cache function needs to be active)
     * @param string $paymentMethod Given payment method
     * @return array
     * @throws ResursException
     */
    public function getAnnuityFactorsCache($paymentMethod)
    {
        if ($this->hasConfiguration()) {
            if (isset($this->configurationArray['getAnnuityFactors']) && isset($this->configurationArray['getAnnuityFactors'][$paymentMethod]) && is_array($this->configurationArray['getAnnuityFactors']) && is_array($this->configurationArray['getAnnuityFactors'][$paymentMethod]) && count($this->configurationArray['getAnnuityFactors'][$paymentMethod]) && !$this->cacheExpired('getPaymentMethods')) {
                return $this->configurationArray['getAnnuityFactors'][$paymentMethod];
            } else {
                return $this->objectsIntoArray($this->getAnnuityFactors($paymentMethod));
            }
        } else {
            throw new ResursException("Can not fetch annuity factors from cache. You must enable internal caching first.", ResursExceptions::ANNUITY_FACTORS_CACHE_DISABLED, __FUNCTION__);
        }
    }

    /**
     * getAnnuityFactors Displays
     *
     * Retrieves the annuity factors for a given payment method. The duration is given is months. While this makes most sense for payment methods that consist of part payments (i.e. new account), it is possible to use for all types. It returns a list of with one annuity factor per payment plan of the payment method. There are typically between three and six payment plans per payment method. If no payment method are given to this function, the first available method will be used (meaning this function will also make a getPaymentMethods()-request which will delay the primary call a bit).
     * @link https://test.resurs.com/docs/display/ecom/Get+Annuity+Factors Ecommerce Docs for getAnnuityFactors
     *
     * @param string $paymentMethodId
     * @return mixed
     * @throws ResursException
     */
    public function getAnnuityFactors($paymentMethodId = '')
    {
        $this->InitializeWsdl();
        $firstMethod = array();
        if (empty($paymentMethodId) || is_null($paymentMethodId)) {
            $methodsAvailable = $this->getPaymentMethods();
            if (is_array($methodsAvailable) && count($methodsAvailable)) {
                $firstMethod = array_pop($methodsAvailable);
                $paymentMethodId = isset($firstMethod->id) ? $firstMethod->id : null;
                if (empty($paymentMethodId)) {
                    throw new ResursException("getAnnuityFactorsException: No available payment method", ResursExceptions::ANNUITY_FACTORS_METHOD_UNAVAILABLE, __FUNCTION__);
                }
            }
        }
        /** @noinspection PhpParamsInspection */
        $annuityParameters = new resurs_getAnnuityFactors($paymentMethodId);
        /* Issue #63127 */
        $return = $this->getDataObject($this->simplifiedShopFlowService->getAnnuityFactors($annuityParameters)->return);
        if ($this->configurationInternal) {
            $CurrentAnnuityFactors = isset($this->configurationArray['getAnnuityFactors']) ? $this->configurationArray['getAnnuityFactors'] : array();
            $CurrentAnnuityFactors[$paymentMethodId] = $return;
            $this->updateConfig('getAnnuityFactors', $CurrentAnnuityFactors);
        }
        return $return;
    }

    /**
     * Override formTemplateFieldsetRules in case of important needs or unexpected changes
     *
     * @param $customerType
     * @param $methodType
     * @param $fieldArray
     * @return array
     */
    public function setFormTemplateRules($customerType, $methodType, $fieldArray)
    {
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
     */
    private function getFormTemplateRules()
    {
        $formTemplateRules = array(
            'NATURAL' => array(
                'fields' => array(
                    'INVOICE' => array('applicant-government-id', 'applicant-telephone-number', 'applicant-mobile-number', 'applicant-email-address'),
                    'CARD' => array('applicant-government-id', 'card-number'),
                    'REVOLVING_CREDIT' => array('applicant-government-id', 'applicant-telephone-number', 'applicant-mobile-number', 'applicant-email-address'),
                    'PART_PAYMENT' => array('applicant-government-id', 'applicant-telephone-number', 'applicant-mobile-number', 'applicant-email-address')
                )
            ),
            'LEGAL' => array(
                'fields' => array(
                    'INVOICE' => array('applicant-government-id', 'applicant-telephone-number', 'applicant-mobile-number', 'applicant-email-address', 'applicant-full-name', 'contact-government-id'),
                )
            ),
            'display' => array('applicant-government-id', 'card-number', 'applicant-full-name', 'contact-government-id'),
            'regexp' => array(
                'SE' => array(
                    'NATURAL' => array(
                        'applicant-government-id' => '^(18\d{2}|19\d{2}|20\d{2}|\d{2})(0[1-9]|1[0-2])([0][1-9]|[1-2][0-9]|3[0-1])(\-|\+)?([\d]{4})$',
                        'applicant-telephone-number' => '^(0|\+46|0046)[ |-]?(200|20|70|73|76|74|[1-9][0-9]{0,2})([ |-]?[0-9]){5,8}$',
                        'applicant-mobile-number' => '^(0|\+46|0046)[ |-]?(200|20|70|73|76|74|[1-9][0-9]{0,2})([ |-]?[0-9]){5,8}$',
                        'applicant-email-address' => '^[A-Za-z0-9!#%&\'*+/=?^_`~-]+(\.[A-Za-z0-9!#%&\'*+/=?^_`~-]+)*@([A-Za-z0-9]+)(([\.\-]?[a-zA-Z0-9]+)*)\.([A-Za-z]{2,})$',
                        'card-number' => '^([1-9][0-9]{3}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4})$'
                    ),
                    'LEGAL' => array(
                        'applicant-government-id' => '^(16\d{2}|18\d{2}|19\d{2}|20\d{2}|\d{2})(\d{2})(\d{2})(\-|\+)?([\d]{4})$',
                        'applicant-telephone-number' => '^(0|\+46|0046)[ |-]?(200|20|70|73|76|74|[1-9][0-9]{0,2})([ |-]?[0-9]){5,8}$',
                        'applicant-mobile-number' => '^(0|\+46|0046)[ |-]?(200|20|70|73|76|74|[1-9][0-9]{0,2})([ |-]?[0-9]){5,8}$',
                        'applicant-email-address' => '^[A-Za-z0-9!#%&\'*+/=?^_`~-]+(\.[A-Za-z0-9!#%&\'*+/=?^_`~-]+)*@([A-Za-z0-9]+)(([\.\-]?[a-zA-Z0-9]+)*)\.([A-Za-z]{2,})$',
                        'card-number' => '^([1-9][0-9]{3}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4})$'
                    )
                ),
                'DK' => array(
                    'NATURAL' => array(
                        'applicant-government-id' => '^((3[0-1])|([1-2][0-9])|(0[1-9]))((1[0-2])|(0[1-9]))(\d{2})(\-)?([\d]{4})$',
                        'applicant-telephone-number' => '^(\+45|0045|)?[ |-]?[2-9]([ |-]?[0-9]){7,7}$',
                        'applicant-mobile-number' => '^(\+45|0045|)?[ |-]?[2-9]([ |-]?[0-9]){7,7}$',
                        'applicant-email-address' => '^[A-Za-z0-9!#%&\'*+/=?^_`~-]+(\.[A-Za-z0-9!#%&\'*+/=?^_`~-]+)*@([A-Za-z0-9]+)(([\.\-]?[a-zA-Z0-9]+)*)\.([A-Za-z]{2,})$',
                        'card-number' => '^([1-9][0-9]{3}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4})$'
                    ),
                    'LEGAL' => array(
                        'applicant-government-id' => null,
                        'applicant-telephone-number' => '^(\+45|0045|)?[ |-]?[2-9]([ |-]?[0-9]){7,7}$',
                        'applicant-mobile-number' => '^(\+45|0045|)?[ |-]?[2-9]([ |-]?[0-9]){7,7}$',
                        'applicant-email-address' => '^[A-Za-z0-9!#%&\'*+/=?^_`~-]+(\.[A-Za-z0-9!#%&\'*+/=?^_`~-]+)*@([A-Za-z0-9]+)(([\.\-]?[a-zA-Z0-9]+)*)\.([A-Za-z]{2,})$',
                        'card-number' => '^([1-9][0-9]{3}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4})$'
                    )
                ),
                'NO' => array(
                    'NATURAL' => array(
                        'applicant-government-id' => '^([0][1-9]|[1-2][0-9]|3[0-1])(0[1-9]|1[0-2])(\d{2})(\-)?([\d]{5})$',
                        'applicant-telephone-number' => '^(\+47|0047|)?[ |-]?[2-9]([ |-]?[0-9]){7,7}$',
                        'applicant-mobile-number' => '^(\+47|0047|)?[ |-]?[2-9]([ |-]?[0-9]){7,7}$',
                        'applicant-email-address' => '^[A-Za-z0-9!#%&\'*+/=?^_`~-]+(\.[A-Za-z0-9!#%&\'*+/=?^_`~-]+)*@([A-Za-z0-9]+)(([\.\-]?[a-zA-Z0-9]+)*)\.([A-Za-z]{2,})$',
                        'card-number' => '^([1-9][0-9]{3}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4})$'
                    ),
                    'LEGAL' => array(
                        'applicant-government-id' => '^([89]([ |-]?[0-9]){8})$',
                        'applicant-telephone-number' => '^(\+47|0047|)?[ |-]?[2-9]([ |-]?[0-9]){7,7}$',
                        'applicant-mobile-number' => '^(\+47|0047|)?[ |-]?[2-9]([ |-]?[0-9]){7,7}$',
                        'applicant-email-address' => '^[A-Za-z0-9!#%&\'*+/=?^_`~-]+(\.[A-Za-z0-9!#%&\'*+/=?^_`~-]+)*@([A-Za-z0-9]+)(([\.\-]?[a-zA-Z0-9]+)*)\.([A-Za-z]{2,})$',
                        'card-number' => '^([1-9][0-9]{3}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4})$'
                    )
                ),
                'FI' => array(
                    'NATURAL' => array(
                        'applicant-government-id' => '^([\d]{6})[\+\-A]([\d]{3})([0123456789ABCDEFHJKLMNPRSTUVWXY])$',
                        'applicant-telephone-number' => '^((\+358|00358|0)[-| ]?(1[1-9]|[2-9]|[1][0][1-9]|201|2021|[2][0][2][4-9]|[2][0][3-8]|29|[3][0][1-9]|71|73|[7][5][0][0][3-9]|[7][5][3][0][3-9]|[7][5][3][2][3-9]|[7][5][7][5][3-9]|[7][5][9][8][3-9]|[5][0][0-9]{0,2}|[4][0-9]{1,3})([-| ]?[0-9]){3,10})?$',
                        'applicant-mobile-number' => '^((\+358|00358|0)[-| ]?(1[1-9]|[2-9]|[1][0][1-9]|201|2021|[2][0][2][4-9]|[2][0][3-8]|29|[3][0][1-9]|71|73|[7][5][0][0][3-9]|[7][5][3][0][3-9]|[7][5][3][2][3-9]|[7][5][7][5][3-9]|[7][5][9][8][3-9]|[5][0][0-9]{0,2}|[4][0-9]{1,3})([-| ]?[0-9]){3,10})?$',
                        'applicant-email-address' => '^[A-Za-z0-9!#%&\'*+/=?^_`~-]+(\.[A-Za-z0-9!#%&\'*+/=?^_`~-]+)*@([A-Za-z0-9]+)(([\.\-]?[a-zA-Z0-9]+)*)\.([A-Za-z]{2,})$',
                        'card-number' => '^([1-9][0-9]{3}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4})$'
                    ),
                    'LEGAL' => array(
                        'applicant-government-id' => '^((\d{7})(\-)?\d)$',
                        'applicant-telephone-number' => '^((\+358|00358|0)[-| ]?(1[1-9]|[2-9]|[1][0][1-9]|201|2021|[2][0][2][4-9]|[2][0][3-8]|29|[3][0][1-9]|71|73|[7][5][0][0][3-9]|[7][5][3][0][3-9]|[7][5][3][2][3-9]|[7][5][7][5][3-9]|[7][5][9][8][3-9]|[5][0][0-9]{0,2}|[4][0-9]{1,3})([-| ]?[0-9]){3,10})?$',
                        'applicant-mobile-number' => '^((\+358|00358|0)[-| ]?(1[1-9]|[2-9]|[1][0][1-9]|201|2021|[2][0][2][4-9]|[2][0][3-8]|29|[3][0][1-9]|71|73|[7][5][0][0][3-9]|[7][5][3][0][3-9]|[7][5][3][2][3-9]|[7][5][7][5][3-9]|[7][5][9][8][3-9]|[5][0][0-9]{0,2}|[4][0-9]{1,3})([-| ]?[0-9]){3,10})?$',
                        'applicant-email-address' => '^[A-Za-z0-9!#%&\'*+/=?^_`~-]+(\.[A-Za-z0-9!#%&\'*+/=?^_`~-]+)*@([A-Za-z0-9]+)(([\.\-]?[a-zA-Z0-9]+)*)\.([A-Za-z]{2,})$',
                        'card-number' => '^([1-9][0-9]{3}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4})$'
                    )
                ),
            )
        );

        if (isset($this->formTemplateRuleArray) && is_array($this->formTemplateRuleArray) && count($this->formTemplateRuleArray)) {
            foreach ($this->formTemplateRuleArray as $cType => $cArray) {
                $formTemplateRules[$cType] = $cArray;
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
     * @return array
     * @throws ResursException
     */
    public function getRegEx($formFieldName = '', $countryCode, $customerType)
    {
        $returnRegEx = array();

        $templateRule = $this->getFormTemplateRules();
        $returnRegEx = $templateRule['regexp'];

        if (empty($countryCode)) {
            throw new ResursException("Country code is missing in getRegEx-request for form fields", ResursExceptions::REGEX_COUNTRYCODE_MISSING);
        }
        if (empty($customerType)) {
            throw new ResursException("Customer type is missing in getRegEx-request for form fields", ResursExceptions::REGEX_CUSTOMERTYPE_MISSING);
        }

        if (!empty($countryCode) && isset($returnRegEx[strtoupper($countryCode)])) {
            $returnRegEx = $returnRegEx[strtoupper($countryCode)];
            if (!empty($customerType)) {
                if (!is_array($customerType)) {
                    if (isset($returnRegEx[strtoupper($customerType)])) {
                        $returnRegEx = $returnRegEx[strtoupper($customerType)];
                        if (isset($returnRegEx[strtolower($formFieldName)])) {
                            $returnRegEx = $returnRegEx[strtolower($formFieldName)];
                        }
                    }
                } else {
                    foreach ($customerType as $cType) {
                        if (isset($returnRegEx[strtoupper($cType)])) {
                            $returnRegEx = $returnRegEx[strtoupper($cType)];
                            if (isset($returnRegEx[strtolower($formFieldName)])) {
                                $returnRegEx = $returnRegEx[strtolower($formFieldName)];
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
     * @return bool Returns false if you should NOT hide the field
     * @throws ResursException
     */
    public function canHideFormField($formField = "", $canThrow = false)
    {
        $canHideSet = false;

        if (is_array($this->templateFieldsByMethodResponse) && count($this->templateFieldsByMethodResponse) && isset($this->templateFieldsByMethodResponse['fields']) && isset($this->templateFieldsByMethodResponse['display'])) {
            $currentDisplay = $this->templateFieldsByMethodResponse['display'];
            if (in_array($formField, $currentDisplay)) {
                $canHideSet = false;
            } else {
                $canHideSet = true;
            }
        } else {
            /* Make sure that we don't hide things that does not exists in our configuration */
            $canHideSet = false;
        }

        if ($canThrow && !$canHideSet) {
            throw new ResursException("templateFieldsByMethodResponse is empty. You have to run getTemplateFieldsByMethodType first", ResursExceptions::FORMFIELD_CANHIDE_EXCEPTION, __FUNCTION__);
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
     * @return array
     */
    public function getTemplateFieldsByMethodType($paymentMethodName = "", $customerType = "", $specificType = "")
    {
        $templateRules = $this->getFormTemplateRules();
        $returnedRules = array();
        $returnedRuleArray = array();

        /* If the client is requesting a getPaymentMethod-object we'll try to handle that information instead */
        if (is_object($paymentMethodName) || is_array($paymentMethodName)) {
            /** @noinspection PhpUndefinedFieldInspection */
            if (isset($templateRules[strtoupper($customerType)]) && isset($templateRules[strtoupper($customerType)]['fields'][strtoupper($paymentMethodName->specificType)])) {
                //$returnedRuleArray = $templateRules[strtoupper($paymentMethodType->customerType)]['fields'][strtoupper($paymentMethodType->specificType)];
                /** @noinspection PhpUndefinedFieldInspection */
                $returnedRuleArray = $templateRules[strtoupper($customerType)]['fields'][strtoupper($paymentMethodName->specificType)];
            }
        } else {
            if (isset($templateRules[strtoupper($customerType)]) && isset($templateRules[strtoupper($customerType)]['fields'][strtoupper($paymentMethodName)])) {
                //$returnedRuleArray = $templateRules[strtoupper($customerType)]['fields'][strtoupper($paymentMethodType)];
                $returnedRuleArray = $templateRules[strtoupper($customerType)]['fields'][strtoupper($specificType)];
            }
        }

        $returnedRules = array(
            'fields' => $returnedRuleArray,
            'display' => $templateRules['display'],
            'regexp' => $templateRules['regexp']
        );
        $this->templateFieldsByMethodResponse = $returnedRules;
        return $returnedRules;
    }

    /**
     * Get template fields by a specific payment method. This function retrieves the payment method in real time.
     *
     * @param string $paymentMethodName
     * @return array
     */
    public function getTemplateFieldsByMethod($paymentMethodName = "")
    {
        return $this->getTemplateFieldsByMethodType($this->getPaymentMethodSpecific($paymentMethodName));
    }

    /**
     * Get form fields by a specific payment method. This function retrieves the payment method in real time.
     *
     * @param string $paymentMethodName
     * @return array
     */
    public function getFormFieldsByMethod($paymentMethodName = "")
    {
        return $this->getTemplateFieldsByMethod($paymentMethodName);
    }


    /**
     * Prepare a payment by setting it up
     *
     * customerIpAddress has a failover: If we don't receive a proper customer ip, we will try to check if there is a REMOTE_ADDR set by the server. If neither of those values are set, we will finally fail over to 127.0.0.1
     * preferredId is set to a internally generated id instead of null, unless you apply your own (if set to null, Resurs Bank decides what order number to be used)
     *
     * @param $paymentMethodId
     * @param array $paymentDataArray
     * @throws ResursException
     */
    public function updatePaymentdata($paymentMethodId, $paymentDataArray = array())
    {
        $this->InitializeWsdl();
        $this->preferredId = $this->generatePreferredId();
        if (!is_object($this->_paymentData) && class_exists('resurs_paymentData')) {
            $this->_paymentData = new resurs_paymentData($paymentMethodId);
        } else {
            // If there are no wsdl-classes loaded, we should consider a default stdClass as object
            $this->_paymentData = new stdClass();
        }

        $this->_paymentData->preferredId = isset($paymentDataArray['preferredId']) && !empty($paymentDataArray['preferredId']) ? $paymentDataArray['preferredId'] : $this->preferredId;
        $this->_paymentData->paymentMethodId = $paymentMethodId;
        $this->_paymentData->customerIpAddress = (isset($paymentDataArray['customerIpAddress']) && !empty($paymentDataArray['customerIpAddress']) ? $paymentDataArray['customerIpAddress'] : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1'));
        $this->_paymentData->waitForFraudControl = isset($paymentDataArray['waitForFraudControl']) && !empty($paymentDataArray['waitForFraudControl']) ? $paymentDataArray['waitForFraudControl'] : false;
        $this->_paymentData->annulIfFrozen = isset($paymentDataArray['annulIfFrozen']) && !empty($paymentDataArray['annulIfFrozen']) ? $paymentDataArray['annulIfFrozen'] : false;
        $this->_paymentData->finalizeIfBooked = isset($paymentDataArray['finalizeIfBooked']) && !empty($paymentDataArray['finalizeIfBooked']) ? $paymentDataArray['finalizeIfBooked'] : false;
    }

    /**
     * See generatePreferredId
     *
     * @param int $maxLength The maximum recommended length of a preferred id is currently 25. The order numbers may be shorter (the minimum length is 14, but in that case only the timestamp will be returned)
     * @param string $prefix Prefix to prepend at unique id level
     * @param bool $dualUniq Be paranoid and sha1-encrypt the first random uniq id first.
     * @return string
     */
    public function getPreferredId($maxLength = 25, $prefix = "", $dualUniq = true)
    {
        return $this->generatePreferredId($maxLength, $prefix, $dualUniq);
    }

    public function getPreferredPaymentId()
    {
        return $this->preferredId;
    }

    /**
     * Generates a unique "preferredId" out of a datestamp
     *
     * @param int $maxLength The maximum recommended length of a preferred id is currently 25. The order numbers may be shorter (the minimum length is 14, but in that case only the timestamp will be returned)
     * @param string $prefix Prefix to prepend at unique id level
     * @param bool $dualUniq Be paranoid and sha1-encrypt the first random uniq id first.
     * @return string
     */
    public function generatePreferredId($maxLength = 25, $prefix = "", $dualUniq = true)
    {
        $timestamp = strftime("%Y%m%d%H%M%S", time());
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

        $preferredId = $timestamp . "-" . $uniq;
        $preferredId = substr($preferredId, 0, $maxLength);
        $this->preferredId = $preferredId;
        return $this->preferredId;
    }

    /**
     * Creation of specrows lands here
     *
     * @param array $speclineArray
     * @return null
     * @throws ResursException
     */
    public function updateCart($speclineArray = array())
    {
        if (!$this->isOmniFlow && !$this->isHostedFlow) {
            if (!class_exists('resurs_specLine')) {
                throw new ResursException("Class specLine does not exist", ResursExceptions::UPDATECART_NOCLASS_EXCEPTION, __FUNCTION__);
            }
        }
        $this->InitializeWsdl();
        $realSpecArray = array();
        if (isset($speclineArray['artNo'])) {
            // If this require parameter is found first in the array, it's a single specrow.
            // In that case, push it out to be a multiple.
            array_push($realSpecArray, $speclineArray);
        } else {
            $realSpecArray = $speclineArray;
        }

        // Handle the specrows as they were many.
        foreach ($realSpecArray as $specIndex => $speclineArray) {
            $quantity = (isset($speclineArray['quantity']) && !empty($speclineArray['quantity']) ? $speclineArray['quantity'] : 1);
            $unitAmountWithoutVat = (is_numeric(floatval($speclineArray['unitAmountWithoutVat'])) ? $speclineArray['unitAmountWithoutVat'] : 0);
            $vatPct = (isset($speclineArray['vatPct']) && !empty($speclineArray['vatPct']) ? $speclineArray['vatPct'] : 0);
            $totalVatAmountInternal = ($unitAmountWithoutVat * ($vatPct / 100)) * $quantity;
            $totalAmountInclTax = round(($unitAmountWithoutVat * $quantity) + $totalVatAmountInternal, $this->bookPaymentRoundDecimals);
            $totalAmountInclTaxInternal = $totalAmountInclTax;

            if (!$this->bookPaymentInternalCalculate) {
                if (isset($speclineArray['totalVatAmount']) && !empty($speclineArray['totalVatAmount'])) {
                    $totalVatAmount = $speclineArray['totalVatAmount'];
                    // Controls the totalVatAmount
                    if ($totalVatAmount != $totalVatAmountInternal) {
                        $totalVatAmount = $totalVatAmountInternal;
                        $this->bookPaymentCartFixed = true;
                    }
                    if ($totalAmountInclTax != $totalAmountInclTaxInternal) {
                        $this->bookPaymentCartFixed = true;
                        $totalAmountInclTax = $totalAmountInclTaxInternal;
                    }
                    $totalAmountInclTax = ($unitAmountWithoutVat * $quantity) + $totalVatAmount;
                } else {
                    $totalVatAmount = $totalVatAmountInternal;
                }
            } else {
                $totalVatAmount = $totalVatAmountInternal;
            }
            $this->_specLineID++;

            /*
             * When the class for resurs_SpecLine is missing (e.g. during omni/hosted), those this must be added differently:
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

            if (class_exists('resurs_specLine')) {
                $this->_paymentSpeclines[] = new resurs_specLine(
                    $this->_specLineID,
                    $speclineArray['artNo'],
                    $speclineArray['description'],
                    $speclineArray['quantity'],
                    (isset($speclineArray['unitMeasure']) && !empty($speclineArray['unitMeasure']) ? $speclineArray['unitMeasure'] : $this->defaultUnitMeasure),
                    $unitAmountWithoutVat,
                    $vatPct,
                    $totalVatAmount,
                    $totalAmountInclTax
                );
            } else {
                if (is_array($speclineArray)) {
                    $this->_paymentSpeclines[] = $speclineArray;
                }
            }
        }
        return $this->_paymentSpeclines;
    }

    /**
     * Returns true if updateCart has interfered with the specRows (this is a good way to indicate if something went wrong with the handling)
     *
     * @return bool
     */
    public function isCartFixed()
    {
        return $this->bookPaymentCartFixed;
    }

    /**
     * Update payment specs and prepeare specrows
     *
     * @param array $specLineArray
     * @throws ResursException
     */
    public function updatePaymentSpec($specLineArray = array())
    {
        $this->InitializeWsdl();
        if (class_exists('resurs_paymentSpec')) {
            $totalAmount = 0;
            $totalVatAmount = 0;
            if (is_array($specLineArray) && count($specLineArray)) {
                foreach ($specLineArray as $specRow => $specRowArray) {
                    $totalAmount += (isset($specRowArray->totalAmount) ? $specRowArray->totalAmount : 0);
                    $totalVatAmount += (isset($specRowArray->totalVatAmount) ? $specRowArray->totalVatAmount : 0);
                }
            }
            $this->_paymentOrderData = new resurs_paymentSpec($specLineArray, $totalAmount, 0);
            $this->_paymentOrderData->totalVatAmount = floatval($totalVatAmount);
        }
    }

    /**
     * Prepare customer address data
     *
     * Note: Customer types LEGAL needs to be defined as $custeromArray['type'] = "LEGAL", if the booking is about LEGAL customers, since we need to extend the address data for such customers.
     *
     * @param array $addressArray
     * @param array $customerArray
     * @throws ResursException
     */
    public function updateAddress($addressArray = array(), $customerArray = array())
    {
        $this->InitializeWsdl();
        $address = null;
        $resursDeliveryAddress = null;
        if (count($addressArray)) {
            if (isset($addressArray['address'])) {
                $address = new resurs_address($addressArray['address']['fullName'], $addressArray['address']['firstName'], $addressArray['address']['lastName'], (isset($addressArray['address']['addressRow1']) ? $addressArray['address']['addressRow1'] : null), (isset($addressArray['address']['addressRow2']) ? $addressArray['address']['addressRow2'] : null), $addressArray['address']['postalArea'], $addressArray['address']['postalCode'], $addressArray['address']['country']);
                if (isset($addressArray['deliveryAddress'])) {
                    $resursDeliveryAddress = new resurs_address($addressArray['deliveryAddress']['fullName'], $addressArray['deliveryAddress']['firstName'], $addressArray['deliveryAddress']['lastName'], (isset($addressArray['deliveryAddress']['addressRow1']) ? $addressArray['deliveryAddress']['addressRow1'] : null), (isset($addressArray['deliveryAddress']['addressRow2']) ? $addressArray['deliveryAddress']['addressRow2'] : null), $addressArray['deliveryAddress']['postalArea'], $addressArray['deliveryAddress']['postalCode'], $addressArray['deliveryAddress']['country']);
                }
            } else {
                $address = new resurs_address($addressArray['fullName'], $addressArray['firstName'], $addressArray['lastName'], (isset($addressArray['addressRow1']) ? $addressArray['addressRow1'] : null), (isset($addressArray['addressRow2']) ? $addressArray['addressRow2'] : null), $addressArray['postalArea'], $addressArray['postalCode'], $addressArray['country']);
            }
        }
        if (count($customerArray)) {
            $customer = new resurs_customer($customerArray['governmentId'], $address, $customerArray['phone'], $customerArray['email'], $customerArray['type']);
            $this->_paymentAddress = $address;
            if (!empty($resursDeliveryAddress) || $customerArray['type'] == "LEGAL") {
                if (isset($resursDeliveryAddress) && is_array($resursDeliveryAddress)) {
                    $this->_paymentDeliveryAddress = $resursDeliveryAddress;
                }
                /** @noinspection PhpParamsInspection */
                $extendedCustomer = new resurs_extendedCustomer($customerArray['governmentId'], $resursDeliveryAddress, $customerArray['phone'], $customerArray['email'], $customerArray['type']);
                $this->_paymentExtendedCustomer = $extendedCustomer;
                /* #59042 => #59046 (Additionaldata should be empty) */
                if (empty($this->_paymentExtendedCustomer->additionalData)) {
                    unset($this->_paymentExtendedCustomer->additionalData);
                }
                if ($customerArray['type'] == "LEGAL") {
                    $extendedCustomer->contactGovernmentId = $customerArray['contactGovernmentId'];
                }
                if (!empty($customerArray['cellPhone'])) {
                    $extendedCustomer->cellPhone = $customerArray['cellPhone'];
                }
            }
            $this->_paymentCustomer = $customer;
            if (isset($extendedCustomer)) {
                $extendedCustomer->address = $address;
                $this->_paymentCustomer = $extendedCustomer;
            }
        }
    }

    /**
     * Internal handler for carddata
     * @throws ResursException
     */
    private function updateCardData()
    {
        $amount = null;
        $this->_paymentCardData = new resurs_cardData();
        if (!isset($this->cardDataCardNumber)) {
            if ($this->cardDataUseAmount && $this->cardDataOwnAmount) {
                $this->_paymentCardData->amount = $this->cardDataOwnAmount;
            } else {
                /** @noinspection PhpUndefinedFieldInspection */
                $this->_paymentCardData->amount = $this->_paymentOrderData->totalAmount;
            }
        } else {
            if (isset($this->cardDataCardNumber) && !empty($this->cardDataCardNumber)) {
                $this->_paymentCardData->cardNumber = $this->cardDataCardNumber;
            }
        }
        if (!empty($this->cardDataCardNumber) && !empty($this->cardDataUseAmount)) {
            throw new ResursException("Card number and amount can not be set at the same time", ResursExceptions::UPDATECARD_DOUBLE_DATA_EXCEPTION, __FUNCTION__);
        }
        return $this->_paymentCardData;
    }

    /**
     * Prepare API for cards. Make sure only one of the parameters are used. Cardnumber cannot be combinad with amount.
     *
     * @param null $cardNumber
     * @param bool|false $useAmount Set to true when using new cards
     * @param bool|false $setOwnAmount If customer applies for a new card specify the credit amount that is applied for. If $setOwnAmount is not null, this amount will be used instead of the specrow data
     * @throws ResursException
     */
    public function prepareCardData($cardNumber = null, $useAmount = false, $setOwnAmount = null)
    {
        if (!is_null($cardNumber)) {
            if (is_numeric($cardNumber)) {
                $this->cardDataCardNumber = $cardNumber;
            } else {
                throw new ResursException("Card number must be numeric", ResursExceptions::PREPARECARD_NUMERIC_EXCEPTION, __FUNCTION__);
            }
        }
        if ($useAmount) {
            $this->cardDataUseAmount = true;
            if (!is_null($setOwnAmount) && is_numeric($setOwnAmount)) {
                $this->cardDataOwnAmount = $setOwnAmount;
            }
        }
    }

    /**
     * Prepare bookedCallbackUrl (Omni)
     * @param string $bookedCallbackUrl
     */
    public function setBookedCallbackUrl($bookedCallbackUrl = "")
    {
        if (!empty($bookedCallbackUrl)) {
            $this->_bookedCallbackUrl = $bookedCallbackUrl;
        }
    }


    /**
     * bookPayment - Compiler for bookPayment.
     *
     * This is the entry point of the simplified version of bookPayment. The normal action here is to send a bulked array with settings for how the payment should be handled (see https://test.resurs.com/docs/x/cIZM)
     * Minor notice: We are currently preparing support for hosted flow by sending array('type' => 'hosted'). It is however not ready to run yet.
     *
     * @param string $paymentMethodId
     * @param array $bookData
     * @param bool $getReturnedObjectAsStd Returning a stdClass instead of a Resurs class
     * @param bool $keepReturnObject Making EComPHP backwards compatible when a webshop still needs the complete object, not only $bookPaymentResult->return
     * @param array $externalParameters External parameters
     * @return object
     * @throws ResursException
     * @link https://test.resurs.com/docs/x/cIZM bookPayment EComPHP Reference
     * @link https://test.resurs.com/docs/display/ecom/bookPayment bookPayment reference
     */
    public function bookPayment($paymentMethodId = '', $bookData = array(), $getReturnedObjectAsStd = true, $keepReturnObject = false, $externalParameters = array())
    {
        /* If the bookData-array seems empty, we'll try to import the internally set bookData */
        if (is_array($bookData) && !count($bookData)) {
            if (is_array($this->bookData) && count($this->bookData)) {
                $bookData = $this->bookData;
            } else {
                throw new ResursException("There is no bookData available for the booking", ResursExceptions::BOOKPAYMENT_NO_BOOKDATA);
            }
        }
        return $this->bookPaymentBulk($paymentMethodId, $bookData, $getReturnedObjectAsStd, $keepReturnObject, $externalParameters);
    }

    /**
     * Check if there is a parameter send through externals, during a bookPayment
     * @param string $parameter
     * @param array $externalParameters
     * @param bool $getValue
     * @return bool|null
     */
    private function bookHasParameter($parameter = '', $externalParameters = array(), $getValue = false)
    {
        if (is_array($externalParameters)) {
            if (isset($externalParameters[$parameter])) {
                if ($getValue) {
                    return $externalParameters[$parameter];
                } else {
                    return true;
                }
            }
        }
        if ($getValue) {
            return null;
        }
        return false;
    }

    /**
     * Get extra parameters during a bookPayment
     * @param string $parameter
     * @param array $externalParameters
     * @return bool|null
     */
    private function getBookParameter($parameter = '', $externalParameters = array())
    {
        return $this->bookHasParameter($parameter, $externalParameters);
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
     * @param string $paymentMethodId
     * @param array $bookData
     * @param bool $getReturnedObjectAsStd Returning a stdClass instead of a Resurs class
     * @param bool $keepReturnObject Making EComPHP backwards compatible when a webshop still needs the complete object, not only $bookPaymentResult->return
     * @param array $externalParameters External parameters
     * @return array|mixed|null This normally returns an object depending on your platform request
     * @throws ResursException
     */
    private function bookPaymentBulk($paymentMethodId = '', $bookData = array(), $getReturnedObjectAsStd = true, $keepReturnObject = false, $externalParameters = array())
    {
        if (empty($paymentMethodId)) {
            return new stdClass();
        }
        if ($this->enforceService == ResursMethodTypes::METHOD_OMNI) {
            $bookData['type'] = "omni";
        } else {
            if (isset($bookData['type']) == "omni") {
                $this->enforceService = ResursMethodTypes::METHOD_OMNI;
                $this->isOmniFlow = true;
            }
        }
        if ($this->enforceService == ResursMethodTypes::METHOD_HOSTED) {
            $bookData['type'] = "hosted";
        } else {
            if (isset($bookData['type']) == "hosted") {
                $this->enforceService = ResursMethodTypes::METHOD_HOSTED;
                $this->isHostedFlow = true;
            }
        }

        $skipSteps = array();
        /* Special rule preparation for Resurs Bank hosted flow */
        if ($this->getBookParameter('type', $externalParameters) == "hosted" || (isset($bookData['type']) && $bookData['type'] == "hosted")) {
            $this->isHostedFlow = true;
        }
        /* Special rule preparation for Resurs Bank Omnicheckout */
        if ($this->getBookParameter('type', $externalParameters) == "omni" || (isset($bookData['type']) && $bookData['type'] == "omni")) {
            $this->isOmniFlow = true;
        }
        /* Make EComPHP ignore some steps that is not required in an omni checkout */
        if ($this->isOmniFlow) {
            $skipSteps['address'] = true;
        }

        /* Prepare for a simplified flow */
        $this->InitializeWsdl();
        $this->updatePaymentdata($paymentMethodId, isset($bookData['paymentData']) && is_array($bookData['paymentData']) && count($bookData['paymentData']) ? $bookData['paymentData'] : array());
        if (isset($bookData['specLine']) && is_array($bookData['specLine'])) {
            $this->updateCart(isset($bookData['specLine']) ? $bookData['specLine'] : array());
        } else {
            // For omni and hosted flow, if specLine is not set
            if (isset($bookData['orderLines']) && is_array($bookData['orderLines'])) {
                $this->updateCart(isset($bookData['orderLines']) ? $bookData['orderLines'] : array());
            }
        }
        $this->updatePaymentSpec($this->_paymentSpeclines);

        /* Prepare address data for hosted flow and simplified, ignore if we're on omni, where this data is not required */
        if (!isset($skipSteps['address'])) {
            if (isset($bookData['deliveryAddress'])) {
                $addressArray = array(
                    'address' => $bookData['address'],
                    'deliveryAddress' => $bookData['deliveryAddress']
                );
                $this->updateAddress(isset($addressArray) ? $addressArray : array(), isset($bookData['customer']) ? $bookData['customer'] : array());
            } else {
                $this->updateAddress(isset($bookData['address']) ? $bookData['address'] : array(), isset($bookData['customer']) ? $bookData['customer'] : array());
            }
        }

        /* Prepare and collect data for a bookpayment */
        if (class_exists('resurs_bookPayment')) {
            /* Only run this if it exists, and the plans is to go through simplified flow */
            if (!$this->isOmniFlow && !$this->isHostedFlow) {
                /** @noinspection PhpParamsInspection */
                $bookPaymentInit = new resurs_bookPayment($this->_paymentData, $this->_paymentOrderData, $this->_paymentCustomer, $this->_bookedCallbackUrl);
            }
        } else {
            /*
             * If no "new flow" are detected during the handle of payment here, and the class also exists so no booking will be possible, we should
             * throw an execption here.
             */
            if (!$this->isOmniFlow && !$this->isHostedFlow) {
                throw new ResursException("bookPaymentClass not found, and this is neither an omni nor hosted flow", ResursExceptions::BOOKPAYMENT_NO_BOOKPAYMENT_CLASS, __FUNCTION__);
            }
        }
        if (!empty($this->cardDataCardNumber) || $this->cardDataUseAmount) {
            $bookPaymentInit->card = $this->updateCardData();
        }
        if (!empty($this->_paymentDeliveryAddress) && is_object($this->_paymentDeliveryAddress)) {
            /** @noinspection PhpUndefinedFieldInspection */
            $bookPaymentInit->customer->deliveryAddress = $this->_paymentDeliveryAddress;
        }

        /* If the preferredId is set, check if there is a request for this varaible in the signing urls */
        /** @noinspection PhpUndefinedFieldInspection */
        if (isset($this->_paymentData->preferredId)) {
            // Make sure that the search and replace really works for unique id's
            if (!isset($bookData['uniqueId'])) {
                $bookData['uniqueId'] = "";
            }
            if (isset($bookData['signing']['successUrl'])) {
                /** @noinspection PhpUndefinedFieldInspection */
                $bookData['signing']['successUrl'] = str_replace('$preferredId', $this->_paymentData->preferredId, $bookData['signing']['successUrl']);
                /** @noinspection PhpUndefinedFieldInspection */
                $bookData['signing']['successUrl'] = str_replace('%24preferredId', $this->_paymentData->preferredId, $bookData['signing']['successUrl']);
                if (isset($bookData['uniqueId'])) {
                    $bookData['signing']['successUrl'] = str_replace('$uniqueId', $bookData['uniqueId'], $bookData['signing']['successUrl']);
                    $bookData['signing']['successUrl'] = str_replace('%24uniqueId', $bookData['uniqueId'], $bookData['signing']['successUrl']);
                }
            }
            if (isset($bookData['signing']['failUrl'])) {
                /** @noinspection PhpUndefinedFieldInspection */
                $bookData['signing']['failUrl'] = str_replace('$preferredId', $this->_paymentData->preferredId, $bookData['signing']['failUrl']);
                /** @noinspection PhpUndefinedFieldInspection */
                $bookData['signing']['failUrl'] = str_replace('%24preferredId', $this->_paymentData->preferredId, $bookData['signing']['failUrl']);
                if (isset($bookData['uniqueId'])) {
                    $bookData['signing']['failUrl'] = str_replace('$uniqueId', $bookData['uniqueId'], $bookData['signing']['failUrl']);
                    $bookData['signing']['failUrl'] = str_replace('%24uniqueId', $bookData['uniqueId'], $bookData['signing']['failUrl']);
                }
            }
        }

        /* If this request actually belongs to an omni flow, let's handle the incoming data differently */
        if ($this->isOmniFlow) {
            /* Prepare a frame for omni checkout */
            try {
                $preOmni = $this->prepareOmniFrame($bookData, $paymentMethodId, ResursOmniCallTypes::METHOD_PAYMENTS);
                if (isset($preOmni->html)) {
                    $this->omniFrame = $preOmni->html;
                }
            } catch (Exception $omniFrameException) {
                throw new ResursException($omniFrameException->getMessage(), $omniFrameException->getCode(), "prepareOmniFrame");
            }
            if (isset($this->omniFrame->faultCode)) {
                throw new ResursException(isset($this->omniFrame->description) ? $this->omniFrame->description : "Unknown error received from Resurs Bank OmniAPI", $this->omniFrame->faultCode, "bookPaymentOmniFrame");
            }
            return $this->omniFrame;
        }
        /* Now, if this is a request for hosted flow, handle the completed data differently */
        if ($this->isHostedFlow) {
            $bookData['orderData'] = $this->objectsIntoArray($this->_paymentOrderData);
            try {
                $hostedResult = $this->bookPaymentHosted($paymentMethodId, $bookData, $getReturnedObjectAsStd, $keepReturnObject);
            } catch (Exception $hostedException) {
                throw new ResursException($hostedException->getMessage(), $hostedException->getCode());
            }
            if (isset($hostedResult->location)) {
                return $hostedResult->location;
            } else {
                throw new ResursException("Can not find location in hosted flow", 404, "bookPaymentHosted");
            }
        }

        /* If this request was not about an omni flow, let's continue prepare the signing data */
        if (isset($bookData['signing'])) {
            $bookPaymentInit->signing = $bookData['signing'];
        }

        try {
            $bookPaymentResult = $this->simplifiedShopFlowService->bookPayment($bookPaymentInit);
        } catch (Exception $bookPaymentException) {
            if (isset($bookPaymentException->faultstring)) {
                throw new ResursException($bookPaymentException->faultstring);
            }
            throw new ResursException($bookPaymentException->getMessage(), $bookPaymentException->getCode());
        }
        if ($getReturnedObjectAsStd) {
            if (isset($bookPaymentResult->return)) {
                /* Set up a globally reachable result for the last booked payment */
                $this->lastBookPayment = $bookPaymentResult->return;
                if (!$keepReturnObject) {
                    return $this->getDataObject($bookPaymentResult->return);
                } else {
                    return $this->getDataObject($bookPaymentResult);
                }
            } else {
                throw new ResursException("bookPaymentResult does not contain a return object");
            }
        }
        return $bookPaymentResult;
    }

    /**
     * Enforce another method than the simplified flow
     * @param int $methodType
     */
    public function setPreferredPaymentService($methodType = ResursMethodTypes::METHOD_UNDEFINED)
    {
        $this->enforceService = $methodType;
        if ($methodType == ResursMethodTypes::METHOD_HOSTED) {
            $this->isHostedFlow = true;
            $this->isOmniFlow = false;
        } elseif ($methodType == ResursMethodTypes::METHOD_OMNI) {
            $this->isHostedFlow = false;
            $this->isOmniFlow = true;
        } elseif ($methodType == ResursMethodTypes::METHOD_SIMPLIFIED) {
            $this->isHostedFlow = false;
            $this->isOmniFlow = false;
        } else {
            $this->isHostedFlow = false;
            $this->isOmniFlow = false;
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
     * @return array|mixed|object
     * @throws ResursException
     */

    private function bookPaymentHosted($paymentMethodId = '', $bookData = array(), $getReturnedObjectAsStd = true, $keepReturnObject = false)
    {
        if ($this->current_environment == ResursEnvironments::ENVIRONMENT_TEST) {
            $this->env_hosted_current = $this->env_hosted_test;
        } else {
            $this->env_hosted_current = $this->env_hosted_prod;
        }
        /**
         * Missing fields may be caused by a conversion of the simplified flow, so we'll try to fill that in here
         */
        if (empty($this->preferredId)) {
            $this->preferredId = $this->generatePreferredId();
        }
        if (!isset($bookData['paymentData']['paymentMethodId'])) {
            $bookData['paymentData']['paymentMethodId'] = $paymentMethodId;
        }
        if (!isset($bookData['paymentData']['preferredId']) || (isset($bookData['paymentData']['preferredId']) && empty($bookData['paymentData']['preferredId']))) {
            $this->preferredId = $this->generatePreferredId(25, "hosted");
            $bookData['paymentData']['preferredId'] = $this->preferredId;
        }
        /**
         * Some of the paymentData are not located in the same place as simplifiedShopFlow. This part takes care of that part.
         */
        if (isset($bookData['paymentData']['waitForFraudControl'])) {
            $bookData['waitForFraudControl'] = $bookData['paymentData']['waitForFraudControl'];
        }
        if (isset($bookData['paymentData']['annulIfFrozen'])) {
            $bookData['annulIfFrozen'] = $bookData['paymentData']['annulIfFrozen'];
        }
        if (isset($bookData['paymentData']['finalizeIfBooked'])) {
            $bookData['finalizeIfBooked'] = $bookData['paymentData']['finalizeIfBooked'];
        }

        $jsonBookData = $this->toJsonByType($bookData, ResursMethodTypes::METHOD_HOSTED);
        $this->simpleWebEngine = $this->createJsonEngine($this->env_hosted_current, $jsonBookData);
        $hostedErrorResult = $this->hostedError($this->simpleWebEngine);
        // Compatibility fixed for PHP 5.3
        if (!empty($hostedErrorResult)) {
            $hostedErrNo = $this->hostedErrNo($this->simpleWebEngine);
            throw new ResursException($hostedErrorResult, $hostedErrNo);
        }
        return $this->simpleWebEngine['parsed'];
    }

    public function prepareOmniFrame($bookData = array(), $orderReference = "", $omniCallType = ResursOmniCallTypes::METHOD_PAYMENTS)
    {
        if ($this->current_environment == ResursEnvironments::ENVIRONMENT_TEST) {
            $this->env_omni_current = $this->env_omni_test;
        } else {
            $this->env_omni_current = $this->env_omni_prod;
        }
        if (empty($orderReference) && !isset($bookData['orderReference'])) {
            throw new ResursException("You must proved omnicheckout with a orderReference", 500);
        }
        if (empty($orderReference) && isset($bookData['orderReference'])) {
            $orderReference = $bookData['orderReference'];
        }
        if ($omniCallType == ResursOmniCallTypes::METHOD_PAYMENTS) {
            $omniSubPath = "/checkout/payments/" . $orderReference;
        }
        if ($omniCallType == ResursOmniCallTypes::METHOD_CALLBACK) {
            // TODO: OmniCallbacks
            $omniSubPath = "/callbacks/";
            throw new ResursException("METHOD_CALLBACK for OmniCheckout is not yet implemented");
        }
        $omniReferenceUrl = $this->env_omni_current . $omniSubPath;
        try {
            $bookDataJson = $this->toJsonByType($bookData, ResursMethodTypes::METHOD_OMNI);
            $this->simpleWebEngine = $this->createJsonEngine($omniReferenceUrl, $bookDataJson);
            $omniErrorResult = $this->omniError($this->simpleWebEngine);
            // Compatibility fixed for PHP 5.3
            if (!empty($omniErrorResult)) {
                $omniErrNo = $this->omniErrNo($this->simpleWebEngine);
                throw new ResursException($omniErrorResult, $omniErrNo);
            }
        } catch (Exception $jsonException) {
            throw new ResursException($jsonException->getMessage(), $jsonException->getCode());
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
     * @return mixed|null|string
     * @deprecated
     */
    public function getOmniFrame($omniResponse = array(), $ocShopInternalHandle = false)
    {
        /*
         * As we are using TorneLIB Curl Library, the Resurs Checkout iframe will be loaded properly without those checks.
         */
        if (is_string($omniResponse) && !empty($omniResponse)) {
            if (isset($omniResponse)) {
                /** @noinspection PhpUndefinedFieldInspection */
                return $this->clearOcShop($this->omniFrame, $ocShopInternalHandle);
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
     * @return mixed|string
     */
    private function clearOcShop($htmlString = "", $ocShopInternalHandle = false)
    {
        if ($ocShopInternalHandle) {
            preg_match_all("/\<script(.*?)\/script>/", $htmlString, $scriptStringArray);
            if (is_array($scriptStringArray) && isset($scriptStringArray[0][0]) && !empty($scriptStringArray[0][0])) {
                $scriptString = $scriptStringArray[0][0];
                preg_match_all("/src=\"(.*?)\"/", $scriptString, $getScriptSrc);
                if (is_array($getScriptSrc) && isset($getScriptSrc[1][0])) {
                    $this->ocShopScript = $getScriptSrc[1][0];
                }
            }
            $htmlString = preg_replace("/\<script(.*?)\/script>/", '', $htmlString);
        }
        return $htmlString;
    }

    /**
     * Get the iframe resizer URL if requested from a site
     *
     * @return string
     */
    public function getIframeResizerUrl()
    {
        if (!empty($this->ocShopScript)) {
            return trim($this->ocShopScript);
        }
    }

    /**
     * Retrieve the correct omnicheckout url depending chosen environment
     * @param int $EnvironmentRequest
     * @param bool $getCurrentIfSet Always return "current" if it has been set first
     * @return string
     */
    public function getOmniUrl($EnvironmentRequest = ResursEnvironments::ENVIRONMENT_TEST, $getCurrentIfSet = true)
    {
        /*
         * If current_environment is set, override incoming variable
         */
        if ($getCurrentIfSet && $this->current_environment_updated) {
            if ($this->current_environment == ResursEnvironments::ENVIRONMENT_PRODUCTION) {
                return $this->env_omni_prod;
            } else {
                return $this->env_omni_test;
            }
        }
        if ($EnvironmentRequest == ResursEnvironments::ENVIRONMENT_PRODUCTION) {
            return $this->env_omni_prod;
        } else {
            return $this->env_omni_test;
        }
    }

    /**
     * Return a string containing the last error for the current session. Returns null if no errors occured
     * @param array $omniObject
     * @return string
     */
    private function omniError($omniObject = array())
    {
        if (isset($omniObject) && isset($omniObject->exception) && isset($omniObject->message)) {
            return $omniObject->message;
        } else if (isset($omniObject) && isset($omniObject->error) && !empty($omniObject->error)) {
            return $omniObject->error;
        }
        return "";
    }

    /**
     * @param array $omniObject
     * @return string
     */
    private function omniErrNo($omniObject = array())
    {
        if (isset($omniObject) && isset($omniObject->exception) && isset($omniObject->status)) {
            return $omniObject->status;
        } else if (isset($omniObject) && isset($omniObject->error) && !empty($omniObject->error)) {
            if (isset($omniObject->status)) {
                return $omniObject->status;
            }
        }
        return "";
    }

    public function omniUpdateOrder($jsonData, $paymentId = '')
    {
        if (empty($paymentId)) {
            throw new Exception("Payment id not set");
        }
        $omniUrl = $this->getOmniUrl();
        $omniRefUrl = $omniUrl . "/checkout/payments/" . $paymentId;
        $engineResponse = $this->createJsonEngine($omniRefUrl, $jsonData, ResursCurlMethods::METHOD_PUT);
        return $engineResponse;
    }


    /**
     * Return a string containing the last error for the current session. Returns null if no errors occured
     * @param array $hostedObject
     * @return string
     */
    private function hostedError($hostedObject = array())
    {
        if (isset($hostedObject) && isset($hostedObject->exception) && isset($hostedObject->message)) {
            return $hostedObject->message;
        }
        return "";
    }

    /**
     * @param array $hostedObject
     * @return string
     */
    private function hostedErrNo($hostedObject = array())
    {
        if (isset($hostedObject) && isset($hostedObject->exception) && isset($hostedObject->status)) {
            return $hostedObject->status;
        }
        return "";
    }


    private function getBookedParameter($parameter = '', $object = null)
    {
        if (is_null($object) && is_object($this->lastBookPayment)) {
            $object = $this->lastBookPayment;
        }
        if (isset($object->return)) {
            $object = $object->return;
        }
        if (is_object($object) || is_array($object)) {
            if (isset($object->$parameter)) {
                return $object->$parameter;
            }
        }
        return null;
    }

    /**
     * Get the booked payment status
     * @param null $lastBookPayment
     * @return null
     */
    public function getBookedStatus($lastBookPayment = null)
    {
        $bookStatus = $this->getBookedParameter('bookPaymentStatus', $lastBookPayment);
        if (!empty($bookStatus)) {
            return $bookStatus;
        }
        return null;
    }

    /**
     * Get the booked payment id out of a payment
     * @param null $lastBookPayment
     * @return null
     */
    public function getBookedPaymentId($lastBookPayment = null)
    {
        $paymentId = $this->getBookedParameter('paymentId', $lastBookPayment);
        if (!empty($paymentId)) {
            return $paymentId;
        } else {
            $id = $this->getBookedParameter('id', $lastBookPayment);
            if (!empty($id)) {
                return $id;
            }
        }
        return null;
    }

    public function getBookedSigningUrl($lastBookPayment = null)
    {
        return $this->getBookedParameter('signingUrl', $lastBookPayment);
    }



    /**
     * Simplified AfterShopFlow starts here
     */

    /**
     * Set a logged in username (will be merged with the client name at aftershopFlow-level)
     *
     * @param string $currentUsername
     */
    public function setLoggedInUser($currentUsername = "")
    {
        $this->loggedInuser = $currentUsername;
    }

    /**
     * Get next invoice number - and initialize if not set.
     * @param bool $initInvoice Initializes invoice number if not set (if not set, this is set to 1 if nothing else is set)
     * @param int $firstInvoiceNumber Initializes invoice number sequence with this value if not set and requested
     * @return int Returns
     * @throws ResursException
     */
    public function getNextInvoiceNumber($initInvoice = true, $firstInvoiceNumber = 1)
    {
        $this->InitializeWsdl();
        $invoiceNumber = null;
        $peek = null;
        try {
            $peek = $this->configurationService->peekInvoiceSequence(array('nextInvoiceNumber' => null));
            $invoiceNumber = $peek->nextInvoiceNumber;
        } catch (\Exception $e) {
        }
        if (empty($invoiceNumber) && $initInvoice) {
            $this->configurationService->setInvoiceSequence(array('nextInvoiceNumber' => $firstInvoiceNumber));
            $invoiceNumber = $firstInvoiceNumber;
        }
        return $invoiceNumber;
    }

    /**
     * Convert version number to decimals
     * @return string
     */
    private function versionToDecimals()
    {
        $splitVersion = explode(".", $this->version);
        $decVersion = "";
        foreach ($splitVersion as $ver) {
            $decVersion .= str_pad(intval($ver), 2, "0", STR_PAD_LEFT);
        }
        return $decVersion;
    }

    /**
     * Scan client specific specrows for matches
     *
     * @param $clientPaymentSpec
     * @param $artNo
     * @param $quantity
     * @param bool $quantityMatch
     * @param bool $useSpecifiedQuantity If set to true, the quantity set clientside will be used rather than the exact quantity from the spec in getPayment (This requires that $quantityMatch is set to false)
     * @return bool
     */
    private function inSpec($clientPaymentSpec, $artNo, $quantity, $quantityMatch = true, $useSpecifiedQuantity = false)
    {
        $foundArt = false;
        foreach ($clientPaymentSpec as $row) {
            if (isset($row['artNo'])) {
                if ($row['artNo'] == $artNo) {
                    // If quantity match is true, quantity must match the request to return a true value
                    if ($quantityMatch) {
                        // Consider full quantity if no quantity is set
                        if (!isset($row['quantity'])) {
                            $foundArt = true;
                            return true;
                        }
                        if (isset($row['quantity']) && (float)$row['quantity'] === (float)$quantity) {
                            return true;
                        } else {
                            // Eventually set this to false, unless the float controll is successful
                            $foundArt = false;
                            // If the float control fails, also try check against integers
                            if (isset($row['quantity']) && intval($row['quantity']) > 0 && intval($quantity) > 0 && intval($row['quantity']) === intval($quantity)) {
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

    private function handleClientPaymentSpec($clientPaymentSpec = array())
    {
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
        if (isset($clientPaymentSpec['artNo'])) {
            $newClientSpec = array();
            $newClientSpec[] = $clientPaymentSpec;
        } else {
            $newClientSpec = $clientPaymentSpec;
        }
        return $newClientSpec;
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
     * @return bool True if successful
     * @throws Exception
     * @throws ResursException
     */
    public function finalizePayment($paymentId = "", $clientPaymentSpec = array(), $finalizeParams = array(), $quantityMatch = true, $useSpecifiedQuantity = false)
    {
        try {
            if (empty($this->customerId)) {
                $this->customerId = "-";
            }
            /** @noinspection PhpParamsInspection */
            $metaSetup = new resurs_addMetaData($paymentId, "CustomerId");
            $metaSetup->value = $this->customerId;
            $this->afterShopFlowService->addMetaData($metaSetup);
        } catch (Exception $metaResponseException) {
        }

        $clientPaymentSpec = $this->handleClientPaymentSpec($clientPaymentSpec);
        $finalizeResult = false;
        if (null === $paymentId) {
            throw new \Exception("Payment ID must be ID");
        }
        $paymentArray = $this->getPayment($paymentId);
        $finalizePaymentContainer = $this->renderPaymentSpecContainer($paymentId, ResursAfterShopRenderTypes::FINALIZE, $paymentArray, $clientPaymentSpec, $finalizeParams, $quantityMatch, $useSpecifiedQuantity);
        if (isset($paymentArray->id)) {
            try {
                $this->afterShopFlowService->finalizePayment($finalizePaymentContainer);
                $finalizeResult = true;
            } catch (\Exception $e) {
                throw new ResursException($e->getMessage(), 500, __FUNCTION__);
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
     * @return bool
     * @throws Exception
     * @throws ResursException
     *
     */
    public function creditPayment($paymentId = "", $clientPaymentSpec = array(), $creditParams = array(), $quantityMatch = true, $useSpecifiedQuantity = false)
    {
        try {
            if (empty($this->customerId)) {
                $this->customerId = "-";
            }
            /** @noinspection PhpParamsInspection */
            $metaSetup = new resurs_addMetaData($paymentId, "CustomerId");
            $metaSetup->value = $this->customerId;
            $this->afterShopFlowService->addMetaData($metaSetup);
        } catch (Exception $metaResponseException) {
        }

        $clientPaymentSpec = $this->handleClientPaymentSpec($clientPaymentSpec);
        $creditResult = false;
        if (null === $paymentId) {
            throw new \Exception("Payment ID must be ID");
        }
        $paymentArray = $this->getPayment($paymentId);
        $creditPaymentContainer = $this->renderPaymentSpecContainer($paymentId, ResursAfterShopRenderTypes::CREDIT, $paymentArray, $clientPaymentSpec, $creditParams, $quantityMatch, $useSpecifiedQuantity);
        if (isset($paymentArray->id)) {
            try {
                $this->afterShopFlowService->creditPayment($creditPaymentContainer);
                $creditResult = true;
            } catch (\Exception $e) {
                throw new ResursException($e->getMessage());
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
     * @return bool
     * @throws Exception
     * @throws ResursException
     */
    public function annulPayment($paymentId = "", $clientPaymentSpec = array(), $annulParams = array(), $quantityMatch = true, $useSpecifiedQuantity = false)
    {
        try {
            if (empty($this->customerId)) {
                $this->customerId = "-";
            }
            if (class_exists('resurs_addMetaData')) {
                /** @noinspection PhpParamsInspection */
                $metaSetup = new resurs_addMetaData($paymentId, "CustomerId");
                $metaSetup->value = $this->customerId;
                $this->afterShopFlowService->addMetaData($metaSetup);
            }
        } catch (Exception $metaResponseException) {
        }

        $clientPaymentSpec = $this->handleClientPaymentSpec($clientPaymentSpec);
        $annulResult = false;
        if (null === $paymentId) {
            throw new \Exception("Payment ID must be ID");
        }
        $paymentArray = $this->getPayment($paymentId);
        $annulPaymentContainer = $this->renderPaymentSpecContainer($paymentId, ResursAfterShopRenderTypes::ANNUL, $paymentArray, $clientPaymentSpec, $annulParams, $quantityMatch, $useSpecifiedQuantity);
        if (isset($paymentArray->id)) {
            try {
                $this->afterShopFlowService->annulPayment($annulPaymentContainer);
                $annulResult = true;
            } catch (\Exception $e) {
                throw new ResursException($e->getMessage());
            }
        }
        return $annulResult;
    }

    private function stripPaymentSpec($specLines = array())
    {
        $newSpec = array();
        if (is_array($specLines) && count($specLines)) {
            foreach ($specLines as $specRow) {
                if (isset($specRow->artNo) && !empty($specRow->artNo)) {
                    $newSpec[] = array(
                        'artNo' => $specRow->artNo,
                        'quantity' => $specRow->quantity
                    );
                }
            }
        }
        return $newSpec;
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
     * @return bool
     * @throws ResursException
     */
    public function cancelPayment($paymentId = "", $clientPaymentSpec = array(), $cancelParams = array(), $quantityMatch = true, $useSpecifiedQuantity = false)
    {
        try {
            if (empty($this->customerId)) {
                $this->customerId = "-";
            }
            /** @noinspection PhpParamsInspection */
            $metaSetup = new resurs_addMetaData($paymentId, "CustomerId");
            $metaSetup->value = $this->customerId;
            $this->afterShopFlowService->addMetaData($metaSetup);
        } catch (Exception $metaResponseException) {
        }
        $clientPaymentSpec = $this->handleClientPaymentSpec($clientPaymentSpec);
        $creditStateSuccess = false;
        $annulStateSuccess = false;
        $cancelStateSuccess = false;
        $lastErrorMessage = "";

        $cancelPaymentArray = $this->getPayment($paymentId);
        $creditSpecLines = array();
        $annulSpecLines = array();

        /*
         * If no clientPaymentSpec are defined, we should consider this a full cancellation. In that case, we'll sort the full payment spec so we'll pick up rows
         * that should be credited separately and vice versa for annullments. If the clientPaymentSpec are defined, the $cancelPaymentArray will be used as usual.
         */
        if (is_array($clientPaymentSpec) && !count($clientPaymentSpec)) {
            try {
                $creditSpecLines = $this->stripPaymentSpec($this->renderSpecLine($creditSpecLines, ResursAfterShopRenderTypes::CREDIT, $cancelParams));
            } catch (Exception $ignoreCredit) {
                $creditSpecLines = array();
            }
            try {
                $annulSpecLines = $this->stripPaymentSpec($this->renderSpecLine($annulSpecLines, ResursAfterShopRenderTypes::ANNUL, $cancelParams));
            } catch (Exception $ignoreAnnul) {
                $annulSpecLines = array();
            }
        }

        /* First, try to credit the requested order, if debited rows are found */
        try {
            if (!count($creditSpecLines)) {
                $creditPaymentContainer = $this->renderPaymentSpecContainer($paymentId, ResursAfterShopRenderTypes::CREDIT, $cancelPaymentArray, $clientPaymentSpec, $cancelParams, $quantityMatch, $useSpecifiedQuantity);
            } else {
                $creditPaymentContainer = $this->renderPaymentSpecContainer($paymentId, ResursAfterShopRenderTypes::CREDIT, $creditSpecLines, $clientPaymentSpec, $cancelParams, $quantityMatch, $useSpecifiedQuantity);
            }
            $this->afterShopFlowService->creditPayment($creditPaymentContainer);
            $creditStateSuccess = true;
        } catch (Exception $e) {
            $creditStateSuccess = false;
            $lastErrorMessage = $e->getMessage();
        }

        /* Second, try to annul the rest of the order, if authorized (not debited) rows are found */
        try {
            if (!count($creditSpecLines)) {
                $annulPaymentContainer = $this->renderPaymentSpecContainer($paymentId, ResursAfterShopRenderTypes::ANNUL, $cancelPaymentArray, $clientPaymentSpec, $cancelParams, $quantityMatch, $useSpecifiedQuantity);
            } else {
                $annulPaymentContainer = $this->renderPaymentSpecContainer($paymentId, ResursAfterShopRenderTypes::ANNUL, $annulSpecLines, $clientPaymentSpec, $cancelParams, $quantityMatch, $useSpecifiedQuantity);
            }
            if (is_array($annulPaymentContainer) && count($annulPaymentContainer)) {
                $this->afterShopFlowService->annulPayment($annulPaymentContainer);
                $annulStateSuccess = true;
            }
        } catch (Exception $e) {
            $annulStateSuccess = false;
            $lastErrorMessage = $e->getMessage();
        }

        /* Check if one of the above statuses is true and set the cancel as successful. If none of them are true, the cancellation has failed completely. */
        if ($creditStateSuccess) {
            $cancelStateSuccess = true;
        }
        if ($annulStateSuccess) {
            $cancelStateSuccess = true;
        }

        /* On total fail, throw the last error */
        if (!$cancelStateSuccess) {
            throw new ResursException($lastErrorMessage);
        }

        return $cancelStateSuccess;
    }


    /**
     * Find out if a payment is creditable
     * @param array $paymentArrayOrPaymentId The current payment if already requested. If this variable is sent as a string, the function will first make a getPayment automatically.
     * @return bool
     */
    public function canCredit($paymentArrayOrPaymentId = array())
    {
        if ((!is_array($paymentArrayOrPaymentId) && !is_object($paymentArrayOrPaymentId)) && !empty($paymentArrayOrPaymentId)) {
            $paymentArrayOrPaymentId = $this->getPayment($paymentArrayOrPaymentId);
        }
        if (isset($paymentArrayOrPaymentId->status)) {
            if (is_array($paymentArrayOrPaymentId->status)) {
                if (in_array("CREDITABLE", $paymentArrayOrPaymentId->status)) {
                    return true;
                }
            } else {
                if ($paymentArrayOrPaymentId->status == "CREDITABLE") {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Find out if a payment is debitable
     * @param array $paymentArrayOrPaymentId The current payment if already requested. If this variable is sent as a string, the function will first make a getPayment automatically.
     * @return bool
     */
    public function canDebit($paymentArrayOrPaymentId = array())
    {
        if ((!is_array($paymentArrayOrPaymentId) && !is_object($paymentArrayOrPaymentId)) && !empty($paymentArrayOrPaymentId)) {
            $paymentArrayOrPaymentId = $this->getPayment($paymentArrayOrPaymentId);
        }
        if (isset($paymentArrayOrPaymentId->status)) {
            if (is_array($paymentArrayOrPaymentId->status)) {
                if (in_array("DEBITABLE", $paymentArrayOrPaymentId->status)) {
                    return true;
                }
            } else {
                if ($paymentArrayOrPaymentId->status == "DEBITABLE") {
                    return true;
                }
            }
        }
        return false;
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
     * @return array
     * @throws ResursException
     */
    public function renderPaymentSpecContainer($paymentId, $renderType, $paymentArray = array(), $clientPaymentSpec = array(), $renderParams = array(), $quantityMatch = true, $useSpecifiedQuantity = false)
    {
        $paymentSpecLine = $this->renderSpecLine($paymentArray, $renderType, $renderParams);
        $totalAmount = 0;
        $totalVatAmount = 0;
        $newSpecLine = array();
        $paymentContainerContent = array();
        $paymentContainer = array();
        $paymentMethodType = isset($paymentArray->paymentMethodType) ? $paymentArray->paymentMethodType : "";
        $isInvoice = false;
        if (strtoupper($paymentMethodType) === "INVOICE") {
            $isInvoice = true;
        }

        if (!count($paymentSpecLine)) {
            /* Should occur when you for example try to annul an order that is already debited or credited */
            throw new ResursException("No articles was added during the renderingprocess (RenderType $renderType)", ResursExceptions::PAYMENTSPEC_EMPTY, __FUNCTION__);
        }

        if (is_array($paymentSpecLine) && count($paymentSpecLine)) {
            /* Calculate totalAmount to finalize */

            foreach ($paymentSpecLine as $row) {
                if (is_array($clientPaymentSpec) && count($clientPaymentSpec)) {
                    /**
                     * Partial payments control
                     * If the current article is missing in the requested $clientPaymentSpec, it should be included into the summary and therefore not be calculated.
                     */
                    if ($this->inSpec($clientPaymentSpec, $row->artNo, $row->quantity, $quantityMatch, $useSpecifiedQuantity)) {
                        /**
                         * Partial specrow quantity modifier - Beta
                         * Activated by setting $useSpecifiedQuantity to true
                         * Warning: Do not use this special feature unless you know what you are doing
                         *
                         * Used when own quantity values are set instead of the one set in the received payment spec. This is actually being used
                         * when we are for example are trying to annul parts of a specrow instead of the full row.
                         */
                        if ($useSpecifiedQuantity) {
                            foreach ($clientPaymentSpec as $item) {
                                if (isset($item['artNo']) && !empty($item['artNo']) && $item['artNo'] == $row->artNo && isset($item['quantity']) && intval($item['quantity']) > 0) {
                                    /* Recalculate the new totalVatAmount */
                                    $newTotalVatAmount = ($row->unitAmountWithoutVat * ($row->vatPct / 100)) * $item['quantity'];
                                    /* Recalculate the new totalAmount */
                                    $newTotalAmount = ($row->unitAmountWithoutVat * $item['quantity']) + $newTotalVatAmount;
                                    /* Change the new values in the current row */
                                    $row->quantity = $item['quantity'];
                                    $row->totalVatAmount = $newTotalVatAmount;
                                    $row->totalAmount = $newTotalAmount;
                                    break;
                                }
                            }
                            /* Put the manipulated row into the specline*/
                            $newSpecLine[] = $this->objectsIntoArray($row);
                            $totalAmount += $row->totalAmount;
                            $totalVatAmount += $row->totalVatAmount;
                        } else {
                            $newSpecLine[] = $this->objectsIntoArray($row);
                            $totalAmount += $row->totalAmount;
                            $totalVatAmount += $row->totalVatAmount;
                        }
                    }
                } else {
                    $newSpecLine[] = $this->objectsIntoArray($row);
                    $totalAmount += $row->totalAmount;
                    $totalVatAmount += $row->totalVatAmount;
                }
            }
            $paymentSpec = array(
                'specLines' => $newSpecLine,
                'totalAmount' => $totalAmount,
                'totalVatAmount' => $totalVatAmount
            );
            $paymentContainerContent = array(
                'paymentId' => $paymentId,
                'partPaymentSpec' => $paymentSpec
            );

            /**
             * Note: If the paymentspec are rendered without speclines, this may be caused by for example a finalization where the speclines already are finalized.
             */
            if (!count($newSpecLine)) {
                throw new ResursException("No articles has been added to the paymentspec due to mismatching clientPaymentSpec", ResursExceptions::PAYMENTSPEC_EMPTY, __FUNCTION__);
            }

            /* If no invoice id is set, we are presuming that Resurs Bank Invoice numbering sequence is the right one - Enforcing an invoice number if not exists */
            if ($isInvoice) {
                $paymentContainerContent['orderDate'] = date('Y-m-d', time());
                $paymentContainerContent['invoiceDate'] = date('Y-m-d', time());
                if (!isset($renderParams['invoiceId'])) {
                    $renderParams['invoiceId'] = $this->getNextInvoiceNumber();
                }
            }
            $renderParams['createdBy'] = $this->getCreatedBy();
            $paymentContainer = array_merge($paymentContainerContent, $renderParams);

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

    /**
     * PaymentSpecCleaner
     * @param array $currentArray The current speclineArray
     * @param array $cleanWith The array with the speclines that should be removed from currentArray
     * @param bool $includeId Include matching against id (meaning both id and artNo is trying to match to make the search safer)
     * @return array New array
     */
    private function removeFromArray($currentArray = array(), $cleanWith = array(), $includeId = false)
    {
        $cleanedArray = array();
        foreach ($currentArray as $currentObject) {
            if (is_array($cleanWith)) {
                $foundObject = false;
                foreach ($cleanWith as $currentCleanObject) {
                    if (is_object($currentCleanObject)) {
                        if (!empty($currentObject->artNo)) {
                            /**
                             * Search with both id and artNo - This may fail so we are normally ignoring the id from a specline.
                             * If you are absolutely sure that your speclines are fully matching each other, you may enabled id-searching.
                             */
                            if (!$includeId) {
                                if ($currentObject->artNo == $currentCleanObject->artNo) {
                                    $foundObject = true;
                                    break;
                                }
                            } else {
                                if ($currentObject->id == $currentCleanObject->id && $currentObject->artNo == $currentCleanObject->artNo) {
                                    $foundObject = true;
                                    break;
                                }
                            }
                        }
                    }
                }
                if (!$foundObject) {
                    $cleanedArray[] = $currentObject;
                }
            } else {
                $cleanedArray[] = $currentObject;
            }
        }
        return $cleanedArray;
    }

    /**
     * Make sure that the amount are properly appended to an URL.
     * @param string $URL
     * @param int $Amount
     * @param string $Parameter
     * @return string
     */
    private function priceAppender($URL = '', $Amount = 0, $Parameter = 'amount')
    {
        if (isset($this->priceAppenderParameter) && !empty($this->priceAppenderParameter)) {
            $Parameter = $this->priceAppenderParameter;
        }
        if (preg_match("/=$/", $URL)) {
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
     * @return array|string Returns an array if the whole method are requested, returns a string if the URL is already prepared as last parameter in
     */
    public function getSekkiUrls($totalAmount = 0, $paymentMethodID = array(), $URL = '')
    {
        if (!empty($URL)) {
            return $this->priceAppender($URL, $totalAmount);
        }
        $currentLegalUrls = array();
        // If not an array (string) or array but empty
        if ((!is_array($paymentMethodID)) || (is_array($paymentMethodID) && !count($paymentMethodID))) {
            $methods = $this->getPaymentMethods();
            foreach ($methods as $methodArray) {
                if (isset($methodArray->id)) {
                    $methodId = $methodArray->id;
                    if (isset($methodArray->legalInfoLinks)) {
                        $linkCount = 0;
                        foreach ($methodArray->legalInfoLinks as $legalInfoLinkId => $legalInfoArray) {
                            if (isset($legalInfoArray->appendPriceLast) && ($legalInfoArray->appendPriceLast === true)) {
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
                                $currentLegalUrls[$methodId][$linkCount]->url = $this->priceAppender($currentLegalUrls[$methodId][$linkCount]->url, ($totalAmount > 0 ? $totalAmount : ""));
                            }
                            $linkCount++;
                        }
                    }
                }
            }
            if (!empty($paymentMethodID)) {
                return $currentLegalUrls[$paymentMethodID];
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
                    $currentLegalUrls[$linkCount]->url = $this->priceAppender($currentLegalUrls[$linkCount]->url, ($totalAmount > 0 ? $totalAmount : ""));
                }
                $linkCount++;
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
     * @return string
     * @throws Exception
     * @link https://test.resurs.com/docs/x/_QBV
     */
    public function getCostOfPurchase($paymentMethod = '', $amount = 0, $returnBody = false, $callCss = 'costofpurchase.css', $hrefTarget = "_blank")
    {
        $returnHtml = $this->getCostOfPurchaseHtml($paymentMethod, $amount);

        /*
         * Try to make the target open as a different target, if set.
         * This will not invoke, if not set.
         */
        if (!empty($hrefTarget)) {

            /*
             * Check if we get an embedded return and fix it.
             */
            if (isset($returnHtml->return)) {
                $returnHtml = $returnHtml->return;
            }

            /*
             * Check if there are any target set, somewhere in the returned html.
             * If true, we'll consider this already done somewhere else.
             */
            if (!preg_match("/target=/is", $returnHtml)) {
                $returnHtml = preg_replace("/href=/is", 'target="' . $hrefTarget . '" href=', $returnHtml);
            }
        }

        if ($returnBody) {
            $specific = $this->getPaymentMethodSpecific($paymentMethod);
            $methodDescription = htmlentities(isset($specific->description) && !empty($specific->description) ? $specific->description : "Payment information");
            $returnBodyHtml = '
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>' . $methodDescription . '</title>
            ';

            if (is_null($callCss)) {
                $callCss = "costofpurchase.css";
            }
            if (!empty($callCss)) {
                if (!is_array($callCss)) {
                    $returnBodyHtml .= '<link rel="stylesheet" media="all" type="text/css" href="' . $callCss . '">' . "\n";
                } else {
                    foreach ($callCss as $cssLink) {
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
            $returnHtml = $returnBodyHtml;
        }

        return $returnHtml;
    }

    /**
     * While generating a getCostOfPurchase where $returnBody is true, this function adds custom html before the returned html-code from Resurs Bank
     * @param string $htmlData
     */
    public function setCostOfPurcaseHtmlBefore($htmlData = '')
    {
        $this->getcost_html_before = $htmlData;
    }

    /**
     * While generating a getCostOfPurchase where $returnBody is true, this function adds custom html after the returned html-code from Resurs Bank
     * @param string $htmlData
     */
    public function setCostOfPurcaseHtmlAfter($htmlData = '')
    {
        $this->getcost_html_after = $htmlData;
    }

    /**
     * Render a specLine-array depending on the needs (This function is explicitly used for the AfterShopFlow together with Finalize/Annul/Credit
     *
     * @param array $paymentArray
     * @param int $renderType
     * @param array $finalizeParams
     * @return array
     * @throws Exception
     */
    private function renderSpecLine($paymentArray = array(), $renderType = ResursAfterShopRenderTypes::NONE, $finalizeParams = array())
    {
        $returnSpecObject = array();
        if ($renderType == ResursAfterShopRenderTypes::NONE) {
            throw new ResursException("Can not render specLines without RenderType");
        }
        /* Preparation of the returning array*/
        $specLines = array();

        /* Preparation */
        $currentSpecs = array(
            'AUTHORIZE' => array(),
            'DEBIT' => array(),
            'CREDIT' => array(),
            'ANNUL' => array()
        );

        /*
         * This method summarizes all specrows in a proper objectarray, depending on the paymentdiff type.
         */
        /** @noinspection PhpUndefinedFieldInspection */
        if (isset($paymentArray->paymentDiffs->paymentSpec->specLines)) {
            /** @noinspection PhpUndefinedFieldInspection */
            $specType = $paymentArray->paymentDiffs->type;
            /** @noinspection PhpUndefinedFieldInspection */
            $specLineArray = $paymentArray->paymentDiffs->paymentSpec->specLines;
            if (is_array($specLineArray)) {
                foreach ($specLineArray as $subObjects) {
                    array_push($currentSpecs[$specType], $subObjects);
                }
            } else {
                array_push($currentSpecs[$specType], $specLineArray);
            }
        } else {
            // If the paymentarray does not have speclines, something else has been done with this payment
            if (isset($paymentArray->paymentDiffs)) {
                foreach ($paymentArray->paymentDiffs as $specsObject) {
                    /* Catch up the payment and split it up */
                    $specType = $specsObject->type;
                    /* Making sure that everything is handled equally */
                    $specLineArray = $specsObject->paymentSpec->specLines;
                    if (isset($specsObject->paymentSpec->specLines)) {
                        if (is_array($specLineArray)) {
                            foreach ($specLineArray as $subObjects) {
                                array_push($currentSpecs[$specType], $subObjects);
                            }
                        } else {
                            array_push($currentSpecs[$specType], $specLineArray);
                        }
                    }
                }
            }
        }

        /* Finalization is being done on all authorized rows that is not already finalized (debit), annulled or crediter*/
        if ($renderType == ResursAfterShopRenderTypes::FINALIZE) {
            $returnSpecObject = $this->removeFromArray($currentSpecs['AUTHORIZE'], array_merge($currentSpecs['DEBIT'], $currentSpecs['ANNUL'], $currentSpecs['CREDIT']));
        }
        /* Credit is being done on all authorized rows that is not annuled or already credited */
        if ($renderType == ResursAfterShopRenderTypes::CREDIT) {
            $returnSpecObject = $this->removeFromArray($currentSpecs['DEBIT'], array_merge($currentSpecs['ANNUL'], $currentSpecs['CREDIT']));
        }
        /* Annul is being done on all authorized rows that is not already annulled, debited or credited */
        if ($renderType == ResursAfterShopRenderTypes::ANNUL) {
            $returnSpecObject = $this->removeFromArray($currentSpecs['AUTHORIZE'], array_merge($currentSpecs['DEBIT'], $currentSpecs['ANNUL'], $currentSpecs['CREDIT']));
        }
        if ($renderType == ResursAfterShopRenderTypes::UPDATE) {
            $returnSpecObject = $currentSpecs['AUTHORIZE'];
        }

        return $returnSpecObject;
    }

    /**
     * @param $name
     * @param $arguments
     */
    public static function __callStatic($name, $arguments)
    {
        // Implement __callStatic() method.
    }
}

/**
 * Class ResursCallbackTypes: Callbacks that can be registered with Resurs Bank.
 */
abstract class ResursCallbackTypes
{

    /**
     * Resurs Callback Types. Callback types available from Resurs Ecommerce.
     *
     * @subpackage ResursCallbackTypes
     */

    /**
     * Callbacktype not defined
     */
    const UNDEFINED = 0;

    /**
     * Callback UNFREEZE
     *
     * Informs when an payment is unfrozen after manual fraud screening. This means that the payment may be debited (captured) and the goods can be delivered.
     * @link https://test.resurs.com/docs/display/ecom/UNFREEZE
     */
    const UNFREEZE = 1;
    /**
     * Callback ANNULMENT
     *
     * Will be sent once a payment is fully annulled at Resurs Bank, for example when manual fraud screening implies fraudulent usage. Annulling part of the payment will not trigger this event.
     * If the representative is not listening to this callback orders might be orphaned (i e without connected payment) and products bound to these orders never released.
     * @link https://test.resurs.com/docs/display/ecom/ANNULMENT
     */
    const ANNULMENT = 2;
    /**
     * Callback AUTOMATIC_FRAUD_CONTROL
     *
     * Will be sent once a payment is fully annulled at Resurs Bank, for example when manual fraud screening implies fraudulent usage. Annulling part of the payment will not trigger this event.
     * @link https://test.resurs.com/docs/display/ecom/AUTOMATIC_FRAUD_CONTROL
     */
    const AUTOMATIC_FRAUD_CONTROL = 3;
    /**
     * Callback FINALIZATION
     *
     * Once a payment is finalized automatically at Resurs Bank, for this will trigger this event, when the parameter finalizeIfBooked parmeter is set to true in paymentData. This callback will only be called if you are implementing the paymentData method with finilizedIfBooked parameter set to true, in the Simplified Shop Flow Service.
     * @link https://test.resurs.com/docs/display/ecom/FINALIZATION
     */
    const FINALIZATION = 4;
    /**
     * Callback TEST
     *
     * To test the callback mechanism. Can be used in integration testing to assure that communication works. A call is made to DeveloperService (triggerTestEvent) and Resurs Bank immediately does a callback. Note that TEST callback must be registered in the same way as all the other callbacks before it can be used.
     * @link https://test.resurs.com/docs/display/ecom/TEST
     */
    const TEST = 5;
    /**
     * Callback UPDATE
     *
     * Will be sent when a payment is updated. Resurs Bank will do a HTTP/POST call with parameter paymentId and the xml for paymentDiff to the registered URL.
     * @link https://test.resurs.com/docs/display/ecom/UPDATE
     */
    const UPDATE = 6;

    /**
     * Callback BOOKED
     *
     * Trigger: The order is in Resurs Bank system and ready for finalization
     * @link https://test.resurs.com/docs/display/ecom/BOOKED
     */
    const BOOKED = 7;
}

/**
 * Class ResursAfterShopRenderTypes
 */
abstract class ResursAfterShopRenderTypes
{
    const NONE = 0;
    const FINALIZE = 1;
    const CREDIT = 2;
    const ANNUL = 3;
    const UPDATE = 4;
}

/**
 * Class ResursEnvironments
 */
abstract class ResursEnvironments
{
    const ENVIRONMENT_PRODUCTION = 0;
    const ENVIRONMENT_TEST = 1;
    const ENVIRONMENT_NOT_SET = 2;
}

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
    const CALLBACK_UNSUFFICIENT_DATA = 6000;
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
}

/**
 * Class ResursCurlMethods
 *
 * How CURL should handle calls
 */
abstract class ResursCurlMethods
{
    const METHOD_GET = 0;
    const METHOD_POST = 1;
    const METHOD_PUT = 2;
    const METHOD_DELETE = 3;
}

/**
 * Class ResursOmniCallTypes
 * Omnicheckout callback types
 */
abstract class ResursOmniCallTypes
{
    const METHOD_PAYMENTS = 0;
    const METHOD_CALLBACK = 1;
}

/**
 * Class ResursMethodTypes
 * Preferred payment method types if called.
 */
abstract class ResursMethodTypes
{
    /** Default method */
    const METHOD_UNDEFINED = 0;
    const METHOD_SIMPLIFIED = 1;
    const METHOD_HOSTED = 2;
    const METHOD_OMNI = 3;
}
