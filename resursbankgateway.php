<?php

/**
 * Plugin Name: Resurs Bank Payment Gateway for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/resurs-bank-payment-gateway-for-woocommerce/
 * Description: Extends WooCommerce with a Resurs Bank gateway
 * WC Tested up to: 3.3.5
 * Version: 2.2.6
 * Author: Resurs Bank AB
 * Author URI: https://test.resurs.com/docs/display/ecom/WooCommerce
 * Text Domain: WC_Payment_Gateway
 * Domain Path: /languages
 */

define( 'RB_WOO_VERSION', '2.2.6' );
define( 'RB_ALWAYS_RELOAD_JS', true );
define( 'RB_WOO_CLIENTNAME', 'resus-bank-payment-gateway-for-woocommerce' );

require_once(__DIR__ . '/vendor/autoload.php');

include( 'functions.php' );
use \Resursbank\RBEcomPHP\RESURS_CALLBACK_TYPES;
use \Resursbank\RBEcomPHP\RESURS_PAYMENT_STATUS_RETURNCODES;
use \Resursbank\RBEcomPHP\RESURS_ENVIRONMENTS;
use \Resursbank\RBEcomPHP\RESURS_FLOW_TYPES;

if ( function_exists( 'add_action' ) ) {
	add_action( 'plugins_loaded', 'woocommerce_gateway_resurs_bank_init' );
	add_action( 'admin_notices', 'resurs_bank_admin_notice' );
}

$resursGlobalNotice = false;

/**
 * Initialize Resurs Bank Plugin
 */
function woocommerce_gateway_resurs_bank_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}
	if ( class_exists( 'WC_Resurs_Bank' ) ) {
		return;
	}

	/*
     * (Very) Simplified locale and country enforcer. Do not use unless necessary, since it may break something.
     */
	if ( isset( $_GET['forcelanguage'] ) && isset( $_SERVER['HTTP_REFERER'] ) ) {
		$languages   = array(
			'sv_SE' => 'SE',
			'nb_NO' => 'NO',
			'da_DK' => 'DK',
			'fi'    => 'FI'
		);
		$setLanguage = $_GET['forcelanguage'];
		if ( isset( $languages[ $setLanguage ] ) ) {
			$sellTo      = array( $languages[ $setLanguage ] );
			$wooSpecific = get_option( 'woocommerce_specific_allowed_countries' );
			/*
             * Follow woocommerce options. A little.
             */
			if ( is_array($wooSpecific) && count( $wooSpecific ) ) {
				update_option( 'woocommerce_specific_allowed_countries', $sellTo );
			} else {
				update_option( 'woocommerce_specific_allowed_countries', array() );
			}
			setResursOption( 'country', $languages[ $setLanguage ] );
			update_option( 'WPLANG', $setLanguage );
			update_option( 'woocommerce_default_country', $languages[ $setLanguage ] );
		}
		wp_safe_redirect( $_SERVER['HTTP_REFERER'] );
		exit;
	}

	/**
	 * Localization
	 */
	load_plugin_textdomain( 'WC_Payment_Gateway', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	/**
	 * Resurs Bank Gateway class
	 */
	class WC_Resurs_Bank extends WC_Payment_Gateway {

		/** @var \Resursbank\RBEcomPHP\ResursBank */
		protected $flow;
		protected $rates;
		private $callback_types;
		private $baseLiveURL;
		private $baseTestURL;
		private $serverEnv;

		/**
		 * Constructor method for Resurs Bank plugin
		 *
		 * This method initializes various properties and fetches payment methods, either from the tranient API or from Resurs Bank API.
		 * It is also responsible for calling generate_payment_gateways, if these need to be refreshed.
		 */
		public function __construct() {
			global $current_user, $wpdb, $woocommerce;
			add_action( 'woocommerce_api_wc_resurs_bank', array( $this, 'check_callback_response' ) );
			if ( function_exists( 'wp_get_current_user' ) ) {
				wp_get_current_user();
			} else {
				get_currentuserinfo();
			}

			hasResursOmni();
			isResursSimulation(); // Make sure settings are properly set each round

			$this->id = "resurs-bank";
			//$this->title = "Resurs Bank";
			$this->method_title   = "Resurs Bank Administration";
			$this->has_fields     = false;
			$this->callback_types = array(
				'UNFREEZE'                => array(
					'uri_components'    => array(
						'paymentId' => 'paymentId',
					),
					'digest_parameters' => array(
						'paymentId' => 'paymentId',
					),
				),
				'AUTOMATIC_FRAUD_CONTROL' => array(
					'uri_components'    => array(
						'paymentId' => 'paymentId',
						'result'    => 'result',
					),
					'digest_parameters' => array(
						'paymentId' => 'paymentId',
					),
				),
				'ANNULMENT'               => array(
					'uri_components'    => array(
						'paymentId' => 'paymentId',
					),
					'digest_parameters' => array(
						'paymentId' => 'paymentId',
					),
				),
				'FINALIZATION'            => array(
					'uri_components'    => array(
						'paymentId' => 'paymentId',
					),
					'digest_parameters' => array(
						'paymentId' => 'paymentId',
					),
				),
				'BOOKED'                  => array(
					'uri_components'    => array(
						'paymentId' => 'paymentId',
					),
					'digest_parameters' => array(
						'paymentId' => 'paymentId',
					),
				),
                'UPDATE'                  => array(
					'uri_components'    => array(
						'paymentId' => 'paymentId',
					),
					'digest_parameters' => array(
						'paymentId' => 'paymentId',
					),
				),
				'TEST'                    => array(
					'uri_components'    => array(
						'prm1' => 'param1',
						'prm2' => 'param2',
						'prm3' => 'param3',
						'prm4' => 'param4',
						'prm5' => 'param5'
					),
					'digest_parameters' => array(
						'parameter1' => 'param1',
						'parameter2' => 'param2',
						'parameter3' => 'param3',
						'parameter4' => 'param4',
						'parameter5' => 'param5'
					)
				)
			);
			$this->init_form_fields();
			$this->init_settings();
			$this->title       = $this->get_option( 'title' );
			$this->description = $this->get_option( 'description' );
			$this->login       = $this->get_option( 'login' );
			$this->password    = $this->get_option( 'password' );
			$this->baseLiveURL = $this->get_option( 'baseLiveURL' );
			$this->baseTestURL = $this->get_option( 'baseTestURL' );
			$this->serverEnv   = $this->get_option( 'serverEnv' );

			/*
             * The flow configurator is only available in demo mode.
             * 170203: Do not remove this since it is internally used (not only i demoshop).
             */
			if ( isset( $_REQUEST['flowconfig'] ) ) {
				if ( isResursDemo() ) {
					$updatedFlow        = false;
					$currentFlowType    = getResursOption('flowtype');
					$availableFlowTypes = array(
						'simplifiedshopflow'       => 'Simplified Flow',
						'resurs_bank_hosted'       => 'Resurs Bank Hosted Flow',
						'resurs_bank_omnicheckout' => 'Resurs Bank Omni Checkout'
					);
					if ( isset( $_REQUEST['setflow'] ) && $availableFlowTypes[ $_REQUEST['setflow'] ] ) {
						$updatedFlow     = true;
						$currentFlowType = $_REQUEST['setflow'];
						setResursOption( "flowtype", $currentFlowType );
						$omniOption = get_option( 'woocommerce_resurs_bank_omnicheckout_settings' );
						if ( $currentFlowType == "resurs_bank_omnicheckout" ) {
							$omniOption['enabled'] = 'yes';
						} else {
							$omniOption['enabled'] = 'no';
						}
						update_option( 'woocommerce_resurs_bank_omnicheckout_settings', $omniOption );
						if ( isset( $_REQUEST['liveflow'] ) ) {
							wp_safe_redirect( wc_get_checkout_url() );
							die();
						}
					}

					$methodUpdateMessage = "";
					if ( isset( $this->login ) && ! empty( $this->login ) && $updatedFlow ) {
						try {
							$this->paymentMethods = $this->get_payment_methods();
							$methodUpdateMessage = __( 'Payment method gateways are updated', 'WC_Payment_Gateway' ) . "...\n";
						} catch ( Exception $e ) {
							$methodUpdateMessage = $e->getMessage();
						}
					}

					echo '
                <form method="post" action="?flowconfig">
                <select name="setflow">
                ';
					$selectedFlowType = "";
					foreach ( $availableFlowTypes as $selectFlowType => $flowTypeDescription ) {
						if ( $selectFlowType == $currentFlowType ) {
							$selectedFlowType = "selected";
						} else {
							$selectedFlowType = "";
						}
						echo '<option value="' . $selectFlowType . '" ' . $selectedFlowType . '>' . $flowTypeDescription . '</option>' . "\n";
					};
					echo '
                <input type="submit" value="' . __( 'Change the flow type', 'WC_Payment_Gateway' ) . '"><br>
                </select>
                </form>
                <a href="' . get_home_url() . '">' . __( 'Back to shop', 'WC_Payment_Gateway' ) . '</a><br>
                <a href="' . wc_get_checkout_url() . '">' . __( 'Back to checkout', 'WC_Payment_Gateway' ) . '</a><br>
                <br>
                ' . $methodUpdateMessage;
				} else {
					echo __( 'Changing flows when the plugin is not in demo mode is not possible', 'WC_Payment_Gateway' );
				}
				exit;
			}

			$this->flowOptions = null;

			if ( hasEcomPHP() ) {
				if ( ! empty( $this->login ) && ! empty( $this->password ) ) {
					/** @var \Resursbank\RBEcomPHP\ResursBank */
					$this->flow = initializeResursFlow();
					$setSessionEnable = true;
					$setSession       = isset( $_REQUEST['set-no-session'] ) ? $_REQUEST['set-no-session'] : null;
					if ( $setSession == 1 ) {
						$setSessionEnable = false;
					} else {
						$setSessionEnable = true;
					}

					/*
                     * Not using is_checkout() since themes may not work the same work.
                     *
                     * In some cases, there won't be any session set if this is done. So we'll look for
                     * the session instead.
                     */
					if ( isset( WC()->session ) && $setSessionEnable ) {
						$omniRef        = $this->flow->getPreferredPaymentId( 25, "RC" );
						$newOmniRef     = $omniRef;
						$currentOmniRef = WC()->session->get( 'omniRef' );
						$omniId         = WC()->session->get( "omniid" );
						if ( isset( $_REQUEST['event-type'] ) && $_REQUEST['event-type'] == "prepare-omni-order" && isset( $_REQUEST['orderRef'] ) && ! empty( $_REQUEST['orderRef'] ) ) {
							$omniRef           = $_REQUEST['orderRef'];
							$currentOmniRefAge = 0;
							$omniRefCreated    = time();
						}

						$omniRefCreated    = WC()->session->get( 'omniRefCreated' );
						$currentOmniRefAge = time() - $omniRefCreated;
						if ( empty( $currentOmniRef ) ) {
							/*
                             * Empty references, create
                             */
							WC()->session->set( 'omniRef', $omniRef );
							WC()->session->set( 'omniRefCreated', time() );
							WC()->session->set( 'omniRefAge', $currentOmniRefAge );
						}
					} else {
						if ( isset( $_REQUEST['omnicheckout_nonce'] ) && wp_verify_nonce( $_REQUEST['omnicheckout_nonce'], "omnicheckout" ) ) {
							if ( isset( $_REQUEST['purchaseFail'] ) && $_REQUEST['purchaseFail'] == 1 ) {
								$returnResult = array(
									'success'     => false,
									'errorString' => "",
									'errorCode'   => "",
									'verified'    => false,
									'hasOrder'    => false,
									'resursData'  => array()
								);
								if ( isset( $_GET['pRef'] ) ) {
									$purchaseFailOrderId = wc_get_order_id_by_payment_id( $_GET['pRef'] );
									$purchareFailOrder   = new WC_Order( $purchaseFailOrderId );
									$purchareFailOrder->update_status( 'failed', __( 'Resurs Bank denied purchase', 'WC_Payment_Gateway' ) );
									WC()->session->set( "resursCreatePass", 0 );
									$returnResult['success']     = true;
									$returnResult['errorString'] = "Denied by Resurs";
									$returnResult['errorCode']   = "200";
									$this->returnJsonResponse( $returnResult, $returnResult['errorCode'] );
									die();
								}
								$this->returnJsonResponse( $returnResult, $returnResult['errorCode'] );
								die();
							}
						}
					}
				}
			}

			if ( hasWooCommerce( "2.0.0", ">=" ) ) {
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
					$this,
					'process_admin_options'
				) );
			} else {
				add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
			}

			if ( ! $this->is_valid_for_use() ) {
				$this->enabled = false;
			}
		}

		/**
         * Are we in omni mode?
		 * @return bool
		 */
		function isResursOmni() {
		    // Returned from somewhere else
			return isResursOmni();
		}

		/**
		 * Initialize the form fields for the plugin
		 */
		function init_form_fields() {
			$this->form_fields = getResursWooFormFields();

			/*
             * In case of upgrades where defaults are not yet set, automatically set them up.
             */
			if ( ! hasResursOptionValue( "getAddress" ) ) {
				setResursOption( "getAddress", "true" );
			}
			if ( ! hasResursOptionValue( "getAddressUseProduction" ) ) {
				setResursOption( "getAddressUseProduction", "false" );
			}
			if ( ! hasResursOptionValue( "streamlineBehaviour" ) ) {
				setResursOption( "streamlineBehaviour", "true" );
			}

			if ( ! isResursDemo() ) {
				unset( $this->form_fields['getAddressUseProduction'], $this->form_fields['ga_login'], $this->form_fields['ga_password'] );
			}

			if ( isset( $this->form_fields['flowtype'] ) && isset( $this->form_fields['flowtype']['options'] ) && is_array( $this->form_fields['flowtype']['options'] ) && isset( $this->form_fields['flowtype']['options']['resurs_bank_omnicheckout'] ) ) {
				unset( $this->form_fields['flowtype']['options']['resurs_bank_omnicheckout'] );
			}
		}

		/**
		 * Check the callback event received and perform the appropriate action
		 */
		public function check_callback_response() {
			global $wpdb;

			$mySession        = false;
			$url_arr          = parse_url( $_SERVER["REQUEST_URI"] );
			$url_arr['query'] = str_replace( 'amp;', '', $url_arr['query'] );
			parse_str( $url_arr['query'], $request );
			if ( ! is_array( $request ) ) {
				$request = array();
			}
			if ( ! count( $request ) && isset( $_GET['event-type'] ) ) {
				$request = $_GET;
			}
			$event_type = $request['event-type'];

			if ( $event_type == "TEST" ) {
				set_transient( 'resurs_callbacks_received', time() );
				set_transient( 'resurs_callbacks_content', $_REQUEST );
				header( 'HTTP/1.0 204 CallbackWithoutDigestTriggerOK' );
				die();
			}
			if ( $event_type == "noevent" ) {
				$myResponse   = null;
				$myBool       = false;
				$errorMessage = "";

				$setType      = isset( $_REQUEST['puts'] ) ? $_REQUEST['puts'] : "";
				$setValue     = isset( $_REQUEST['value'] ) ? $_REQUEST['value'] : "";
				$reqNamespace = isset( $_REQUEST['ns'] ) ? $_REQUEST['ns'] : "";
				$reqType      = isset( $_REQUEST['wants'] ) ? $_REQUEST['wants'] : "";
				$reqNonce     = isset( $_REQUEST['ran'] ) ? $_REQUEST['ran'] : "";

				$newPaymentMethodsList = null;
				$envVal                = null;
				if ( ! empty( $reqType ) || ! empty( $setType ) ) {
					if ( wp_verify_nonce( $reqNonce, "requestResursAdmin" ) && $reqType ) {
						$mySession  = true;
						$reqType    = str_replace( $reqNamespace . "_", '', $reqType );
						$myBool     = true;
						$myResponse = getResursOption( $reqType );
						if ( empty( $myResponse ) ) {
							// Make sure this returns a string and not a bool.
							$myResponse = '';
						}
					} else if ( wp_verify_nonce( $reqNonce, "requestResursAdmin" ) && $setType ) {
						$mySession = true;
						$failSetup = false;
						$subVal    = isset( $_REQUEST['s'] ) ? $_REQUEST['s'] : "";
						$envVal    = isset( $_REQUEST['e'] ) ? $_REQUEST['e'] : "";
						if ( $setType == "woocommerce_resurs-bank_password" ) {
							$testUser = $subVal;
							$testPass = $setValue;
							$flowEnv  = getServerEnv();
							if ( ! empty( $envVal ) ) {
								if ( $envVal == "test" ) {
									$flowEnv = RESURS_ENVIRONMENTS::ENVIRONMENT_TEST;
								} else if ( $envVal == "live" ) {
									$flowEnv = RESURS_ENVIRONMENTS::ENVIRONMENT_PRODUCTION;
								} else if ( $envVal == "production" ) {
									$flowEnv = RESURS_ENVIRONMENTS::ENVIRONMENT_PRODUCTION;
								}
								$newFlow = initializeResursFlow( $testUser, $testPass, $flowEnv );
							} else {
								$newFlow = initializeResursFlow( $testUser, $testPass );
							}
							try {
								$newPaymentMethodsList = $newFlow->getPaymentMethods();
								$myBool                = true;
							} catch ( Exception $e ) {
								$myBool       = false;
								$failSetup    = true;
                                /** @var $errorMessage */
								$errorMessage = $e->getMessage();
								/** @var $prevError \Exception */
								$prevError    = $e->getPrevious();
								if (!empty($prevError)) {
								    $errorMessage = $prevError->getMessage();
                                }
                                if (preg_match("/simplifiedshopflowservice/i", $errorMessage)) {
								    //$errorMessage = __('Could not update settings from service. Are you sure that your credentials are correct?', 'WC_Payment_Gateway');
                                }
							}
						}
						if ( isset( $newPaymentMethodsList['error'] ) && ! empty( $newPaymentMethodsList['error'] ) ) {
							$failSetup    = true;
							$errorMessage = $newPaymentMethodsList['error'];
							$myBool       = false;
						}
						$setType = str_replace( $reqNamespace . "_", '', $setType );
						if ( ! $failSetup ) {
							$myBool = true;
							setResursOption( $setType, $setValue );
							setResursOption( "login", $subVal );
							if ( ! empty( $envVal ) ) {
								setResursOption( "serverEnv", $envVal );
							}
							$myResponse['element'] = array( "currentResursPaymentMethods", "callbackContent" );
							set_transient( 'resurs_bank_last_callback_setup', 0 );
							$myResponse['html'] = '<br><div class="labelBoot labelBoot-success labelBoot-big labelBoot-nofat labelBoot-center">' . __( 'Please reload or save this page to have this list updated', 'WC_Payment_Gateway' ) . '</div><br><br>';
						}
					}
				} else {
					if ( isset( $_REQUEST['run'] ) ) {
						// Since our tests with WP 4.7.5, the nonce control seems to not work properly even if the nonce is actually
						// are calculated correctly. This is a very temporary fix for that problem.
						$nonceIsFailing = true;
						if ( wp_verify_nonce( $reqNonce, "requestResursAdmin" ) || $nonceIsFailing ) {
							$mySession = true;
							$arg       = null;
							if ( isset( $_REQUEST['arg'] ) ) {
								$arg = $_REQUEST['arg'];
							}
							$responseArray = array();
							if ( $_REQUEST['run'] == "updateResursPaymentMethods" ) {
								try {
									//$responseArray = $this->flow->getPaymentMethods();
									// Do not reveal stuff at this level.
									$responseArray = true;
								} catch ( Exception $e ) {
									$errorMessage = $e->getMessage();
								}
							} else if ($_REQUEST['run'] == 'annuityDuration') {
								$data = isset($_REQUEST['data']) ? $_REQUEST['data'] : null;
								if (!empty($data)) {
									setResursOption("resursAnnuityDuration", $data);
								}
							} else if ($_REQUEST['run'] == 'annuityToggle') {
								$priorAnnuity = getResursOption("resursAnnuityMethod");
								$annuityFactors = $this->flow->getAnnuityFactors($arg);
								setResursOption("resursCurrentAnnuityFactors", $annuityFactors);
								$selectorOptions = "";
								// Also kill self
								$scriptit = 'resursRemoveAnnuityElements(\''.$arg.'\')';
								if ($priorAnnuity == $arg) {
								    $selector = "";
									$responseHtml = '<span id="annuityClick_'.$arg.'" class="status-disabled tips" data-tip="' . __( 'Disabled', 'woocommerce' ) . '" onclick="runResursAdminCallback(\'annuityToggle\', \''.$arg.'\');'.$scriptit.';">-</span>' . "\n" . $selector;
									setResursOption("resursAnnuityMethod", "");
									setResursOption("resursAnnuityDuration", "");
									$isEnabled = "no";
								} else {
									if (is_array($annuityFactors) && count($annuityFactors)) {
										$firstDuration = "";
										foreach ($annuityFactors as $factor) {
											if (!$firstDuration) {
												$firstDuration = $factor->duration;
											}
											$selectorOptions .= '<option value="'.$factor->duration.'">'.$factor->paymentPlanName.'</option>';
										}
										setResursOption("resursAnnuityMethod", $arg);
										setResursOption("resursAnnuityDuration", $firstDuration);
									}
									$isEnabled = "yes";
									$selector = '<select class="resursConfigSelectShort" id="annuitySelector_'.$arg.'" onchange="runResursAdminCallback(\'annuityDuration\', \''.$arg.'\', this.value)">'.$selectorOptions.'</select>';
									$responseHtml = '<span id="annuityClick_'.$arg.'" class="status-enabled tips" data-tip="' . __( 'Enabled', 'woocommerce' ) . '" onclick="runResursAdminCallback(\'annuityToggle\', \''.$arg.'\');'.$scriptit.';">-</span>' . "\n" . $selector;
								}
								$responseArray['valueSet'] = $isEnabled;
								$responseArray['element']  = "annuity_" . $arg;
								$responseArray['html']     = $responseHtml;
							} else if ( $_REQUEST['run'] == "methodToggle" ) {
								$dbMethodName   = "woocommerce_resurs_bank_nr_" . $arg . "_settings";
								$responseMethod = get_option( $dbMethodName );
								if ( is_array( $responseMethod ) && count( $responseMethod ) ) {
									$myBool    = true;
									$isEnabled = $responseMethod['enabled'];
									if ( $isEnabled == "yes" || $isEnabled == "true" || $isEnabled == "1" ) {
										$isEnabled    = "no";
										$responseHtml = '<span class="status-disabled tips" data-tip="' . __( 'Disabled', 'woocommerce' ) . '">-</span>';
									} else {
										$isEnabled    = "yes";
										$responseHtml = '<span class="status-enabled tips" data-tip="' . __( 'Enabled', 'woocommerce' ) . '">-</span>';
									}
									setResursOption( "enabled", $isEnabled, $dbMethodName );
									$responseArray['valueSet'] = $isEnabled;
									$responseArray['element']  = "status_" . $arg;
									$responseArray['html']     = $responseHtml;
								} else {
									$errorMessage = __( "Configuration has not yet been initiated.", "WC_Payment_Gateway" );
								}
							} else if ( $_REQUEST['run'] == "getMyCallbacks" ) {
								$responseArray = array(
									'callbacks' => array()
								);
								$login         = getResursOption( "login" );
								$password      = getResursOption( "password" );
								if ( ! empty( $login ) && ! empty( $password ) ) {
									$lastFetchedCacheTime = time() - get_transient( "resurs_callback_templates_cache_last" );
									$lastFetchedCache     = get_transient( "resurs_callback_templates_cache" );
									if ( $lastFetchedCacheTime >= 86400 || empty( $lastFetchedCache ) || isset( $_REQUEST['force'] ) ) {
										try {
											$responseArray['callbacks'] = $this->flow->getCallBacksByRest( true );
											set_transient( "resurs_callback_templates_cache_last", time() );
											$myBool = true;
										} catch ( Exception $e ) {
											$errorMessage = $e->getMessage();
										}
										set_transient( "resurs_callback_templates_cache", $responseArray['callbacks'] );
										$responseArray['cached'] = false;
									} else {
										$myBool                     = true;
										$responseArray['callbacks'] = $lastFetchedCache;
										$responseArray['cached']    = true;
									}
								}
							} else if ( $_REQUEST['run'] == "setMyCallbacks" ) {
								$responseArray = array();
								$login         = getResursOption( "login" );
								$password      = getResursOption( "password" );
								if ( ! empty( $login ) && ! empty( $password ) ) {
									set_transient( 'resurs_bank_last_callback_setup', time() );
									try {
										$salt = uniqid( mt_rand(), true );
										set_transient( 'resurs_bank_digest_salt', $salt );
										$regCount                             = 0;
										$responseArray['registeredCallbacks'] = 0;
										$rList                                = array();
										set_transient( "resurs_callback_templates_cache_last", 0 );
										foreach ( $this->callback_types as $callback => $options ) {
											$setUriTemplate     = $this->register_callback( $callback, $options );
											$rList[ $callback ] = $setUriTemplate;
											$regCount ++;
										}
										if ( $regCount > 0 ) {
											$myBool = true;
										}
										set_transient( 'resurs_callbacks_sent', time() );
										$triggeredTest                         = $this->flow->triggerCallback();
										$responseArray['registeredCallbacks']  = $regCount;
										$responseArray['registeredTemplates']  = $rList;
										$responseArray['testTriggerActive']    = $triggeredTest;
										$responseArray['testTriggerTimestamp'] = strftime( '%Y-%m-%d (%H:%M:%S)', time() );
									} catch ( Exception $e ) {
										$responseArray['errorstring'] = $e->getMessage();
									}
								}
							} else if ( $_REQUEST['run'] == 'getLastCallbackTimestamp' ) {
								$lastRecv                 = get_transient( 'resurs_callbacks_received' );
								$myBool                   = true;
								$responseArray['element'] = "lastCbRec";
								if ( $lastRecv > 0 ) {
									$responseArray['html'] = '<div style="margin-bottom:5px; margin-top: 5px;"><span id="receivedCallbackConfirm" class="labelBoot labelBoot-success">' . __( 'Test callback received', 'WC_Payment_Gateway' ) . '</span></div>';
									// strftime('%Y-%m-%d (%H:%M:%S)', $lastRecv)
								} else {
									$responseArray['html'] = __( 'Never', 'WC_Payment_Gateway' );
								}
							} else if ( $_REQUEST['run'] == 'cleanRbSettings' ) {
								$numDel                         = $wpdb->query( "DELETE FROM " . $wpdb->options . " WHERE option_name LIKE '%resurs%bank%'" );
								$responseArray['deleteOptions'] = $numDel;
								$responseArray['element']       = "process_cleanResursSettings";
								if ( $numDel > 0 ) {
									$myBool                = true;
									$responseArray['html'] = "OK";
								} else {
									$responseArray['html'] = "";
								}
							} else if ( $_REQUEST['run'] == 'cleanRbMethods' ) {
								$numDel     = 0;
								$numConfirm = 0;
								try {
									$wpdb->query( "DELETE FROM " . $wpdb->options . " WHERE option_name LIKE '%resursTemporaryPaymentMethods%'" );
								} catch ( \Exception $dbException ) {

								}
								// Make sure that the globs does not return anything else than an array.
								$globIncludes = glob( plugin_dir_path( __FILE__ ) . 'includes/*.php' );
								if (is_array($globIncludes)) {
									foreach ( $globIncludes as $filename ) {
										@unlink( $filename );
										$numDel ++;
									}
									$globIncludes = glob( plugin_dir_path( __FILE__ ) . 'includes/*.php' );
									if (is_array($globIncludes)) {
										foreach ( $globIncludes as $filename ) {
											$numConfirm ++;
										}
									}
								}
								$responseArray['deleteFiles'] = 0;
								$responseArray['element']     = "process_cleanResursMethods";
								if ( $numConfirm != $numDel ) {
									$responseArray['deleteFiles'] = $numDel;
									$responseArray['html']        = "OK";
									$myBool                       = true;
								} else {
									$responseArray['html'] = "";
								}
							} else if ( $_REQUEST['run'] == 'setNewPaymentFee' ) {
								$responseArray['update'] = 0;
								if ( isset( $_REQUEST['data'] ) && count( $_REQUEST['data'] ) ) {
									$paymentFeeData = $_REQUEST['data'];
									if ( isset( $paymentFeeData['feeId'] ) && isset( $paymentFeeData['feeValue'] ) ) {
										$feeId                     = preg_replace( '/^[a-z0-9]$/i', '', $paymentFeeData['feeId'] );
										$feeValue                  = intval( $paymentFeeData['feeValue'] );
										$methodNameSpace           = "woocommerce_resurs_bank_nr_" . $feeId . "_settings";
										$responseArray['feeId']    = $feeId;
										$responseArray['oldValue'] = getResursOption( "price", $methodNameSpace );
										$responseArray['update']   = setResursOption( "price", $feeValue, $methodNameSpace ) === true ? 1 : 0;
									}
								}
							}
							$myResponse = array(
								$_REQUEST['run'] . "Response" => $responseArray
							);
						}
					}
				}
				$response = array(
					'response'     => $myResponse,
					'success'      => $myBool,
					'session'      => $mySession === true ? 1 : 0,
					'errorMessage' => nl2br($errorMessage)
				);
				$this->returnJsonResponse( $response );
				exit;
			}
			if ( $event_type === 'check_signing_response' ) {
				$this->check_signing_response();

				return;
			}
			if ( $event_type === "prepare-omni-order" ) {
				$this->prepare_omni_order();

				return;
			}
			$currentSalt = get_transient( 'resurs_bank_digest_salt' );

			$orderId = wc_get_order_id_by_payment_id( $request['paymentId'] );
			$order   = new WC_Order( $orderId );
			if ( ! $this->flow->getValidatedCallbackDigest( isset( $request['paymentId'] ) ? $request['paymentId'] : null, $currentSalt, isset( $request['digest'] ) ? $request['digest'] : null, isset( $request['result'] ) ? $request['result'] : null ) ) {
				$order->add_order_note( __( 'The Resurs Bank event ' . $event_type . ' was received but not accepted (digest fault)', 'WC_Payment_Gateway' ) );
				header( 'HTTP/1.1 406 Digest not accepted', true, 406 );
				echo "406: Digest not accepted";
				exit;
			}

			$currentStatus = $order->get_status();
			switch ( $event_type ) {
				case 'UNFREEZE':
					update_post_meta( $orderId, 'hasCallback' . $event_type, time() );
                    $this->updateOrderByResursPaymentStatus($order, $currentStatus, $request['paymentId'], RESURS_CALLBACK_TYPES::CALLBACK_TYPE_UNFREEZE);
					$order->add_order_note( __( 'The Resurs Bank event UNFREEZE received', 'WC_Payment_Gateway' ) );
					ThirdPartyHooksSetPaymentTrigger( "callback", $request['paymentId'], $orderId, $event_type );
					break;
				case 'AUTOMATIC_FRAUD_CONTROL':
					update_post_meta( $orderId, 'hasCallback' . $event_type, time() );
					$this->updateOrderByResursPaymentStatus($order, $currentStatus, $request['paymentId'], RESURS_CALLBACK_TYPES::CALLBACK_TYPE_AUTOMATIC_FRAUD_CONTROL, $request['result']);
					switch ( $request['result'] ) {
						case 'THAWED':
							$order->add_order_note( __( 'The Resurs Bank event AUTOMATIC_FRAUD_CONTROL returned THAWED', 'WC_Payment_Gateway' ) );
							ThirdPartyHooksSetPaymentTrigger( "callback", $request['paymentId'], $orderId, $event_type );
							break;
						case 'FROZEN':
							$order->add_order_note( __( 'The Resurs Bank event AUTOMATIC_FRAUD_CONTROL returned FROZEN', 'WC_Payment_Gateway' ) );
							ThirdPartyHooksSetPaymentTrigger( "callback", $request['paymentId'], $orderId, $event_type );
							break;
						default:
							break;
					}
					break;
				case 'TEST':
					break;
				case 'ANNULMENT':
					update_post_meta( $orderId, 'hasCallback' . $event_type, time() );
					update_post_meta( ! isWooCommerce3() ? $order->id : $order->get_id(), 'hasAnnulment', 1 );
					$order->update_status( 'cancelled' );
					if ( ! isWooCommerce3() ) {
						$order->cancel_order( __( 'ANNULMENT event received from Resurs Bank', 'WC_Payment_Gateway' ) );
					}
					// Not running suggestedMethod here as we have anoter procedure to cancel orders
					ThirdPartyHooksSetPaymentTrigger( "callback", $request['paymentId'], $orderId, $event_type );
					break;
				case 'FINALIZATION':
					update_post_meta( $orderId, 'hasCallback' . $event_type, time() );
					$this->updateOrderByResursPaymentStatus($order, $currentStatus, $request['paymentId'], RESURS_CALLBACK_TYPES::CALLBACK_TYPE_FINALIZATION);
					$order->add_order_note( __( 'FINALIZATION event received from Resurs Bank', 'WC_Payment_Gateway' ) );
					$order->payment_complete();
					ThirdPartyHooksSetPaymentTrigger( "callback", $request['paymentId'], $orderId, $event_type );
					break;
				case 'BOOKED':
					update_post_meta( $orderId, 'hasCallback' . $event_type, time() );
					if ( $currentStatus != "cancelled" ) {
						$optionReduceOrderStock = getResursOption( 'reduceOrderStock' );
						$hasReduceStock = get_post_meta($orderId, 'hasReduceStock');
						if ( $optionReduceOrderStock && empty( $hasReduceStock ) ) {
							update_post_meta( $orderId, 'hasReduceStock', time() );
							if (isWooCommerce3()) {
								wc_reduce_stock_levels($order->get_id());
							} else {
								$order->reduce_order_stock();
							}
						}
						$this->updateOrderByResursPaymentStatus($order, $currentStatus, $request['paymentId'], RESURS_CALLBACK_TYPES::CALLBACK_TYPE_BOOKED);
						$order->add_order_note( __( 'BOOKED event received from Resurs Bank', 'WC_Payment_Gateway' ) );
						ThirdPartyHooksSetPaymentTrigger( "callback", $request['paymentId'], $orderId, $event_type );
					}
					break;
				/*
				 * The below code belongs to the BOOKED event.
				 * In the future, injecting order lines as the BOOKED callback is running may be supported, but since
				 * WooCommerce itself offers a bunch of extra fees, we are currently excluding this, since we missing too much
				 * important values to inject a proper payment spec into woocommerce orderadmin. Besides, by only injecting data
				 * like this, may prevent other systems to catch summaries of a correct order.
				 */
				/*
				$dataPOST = null;
				if ($_SERVER['CONTENT_LENGTH'] > 0) {
					$dataPOST = @json_decode(trim(file_get_contents('php://input')));
				}
				if (isset($dataPOST->addedPaymentSpecificationLines)) {
					foreach ($dataPOST->addedPaymentSpecificationLines as $addedArticle) {
						// artNo, description, quantity, unitAmountWithoutVat, vatPct, totalVatAmount
						$item = array(
							'order_item_name' => $addedArticle->artNo,
							'order_item_type' => 'line_item'
						);
						$item_id = wc_add_order_item($orderId, $item);
						wc_add_order_item_meta( $item_id, '_qty', $addedArticle->quantity);
						wc_add_order_item_meta( $item_id, '_line_subtotal', $addedArticle->unitAmountWithoutVat*$addedArticle->quantity);
						wc_add_order_item_meta( $item_id, '_line_total', $addedArticle->unitAmountWithoutVat);
						wc_add_order_item_meta( $item_id, '_line_subtotal_tax', $addedArticle->totalVatAmount);
						wc_add_order_item_meta( $item_id, '_line_tax', $addedArticle->totalVatAmount);
						$tax_data             = array();
						$tax_data['total']    = wc_format_decimal($addedArticle->totalVatAmount);
						$tax_data['subtotal'] = wc_format_decimal($addedArticle->totalVatAmount);
						$postMeta = get_post_meta($orderId);
						$orderTotal = $postMeta['_order_total'][0] + $addedArticle->unitAmountWithoutVat + $addedArticle->totalVatAmount;
						wc_add_order_item_meta( $item_id, '_line_tax_data', $tax_data );
						update_post_meta( $orderId, '_order_total', $orderTotal);
					}
				}*/
				case 'UPDATE':
					$this->updateOrderByResursPaymentStatus($order, $currentStatus, $request['paymentId'], RESURS_CALLBACK_TYPES::CALLBACK_TYPE_UPDATE);
					$order->add_order_note( __( 'UPDATE event received from Resurs Bank', 'WC_Payment_Gateway' ) );
					break;
				default:
					break;
			}
			header( 'HTTP/1.1 204 Accepted' );
			die();
		}

		/**
		 * @param string $currentStatus
		 * @param string $newStatus
		 * @param WC_Order $woocommerceOrder
		 * @param RESURS_PAYMENT_STATUS_RETURNCODES $suggestedStatusCode
		 *
		 * @return bool
		 */
		private function synchronizeResursOrderStatus($currentStatus, $newStatus, $woocommerceOrder, $suggestedStatusCode) {
		    if ($currentStatus != $newStatus) {
			    $woocommerceOrder->update_status( $newStatus );
			    $woocommerceOrder->add_order_note( __( 'Updated order based on Resurs Bank current order status', 'WC_Payment_Gateway' ) . " (".$this->flow->getOrderStatusStringByReturnCode($suggestedStatusCode) . ")");
			    return true;
		    }
			$woocommerceOrder->add_order_note( __( 'Request order status update upon Resurs Bank current payment order status, left unchanged since the order is already updated', 'WC_Payment_Gateway' ) . " (".$this->flow->getOrderStatusStringByReturnCode($suggestedStatusCode) . ")");
		    return false;
        }

		/**
		 * @param $woocommerceOrder
		 * @param string $currentWcStatus
		 * @param string $paymentIdOrPaymentObject
		 * @param int $byCallbackEvent
		 * @param array $callbackEventDataArrayOrString
		 *
		 * @return int|RESURS_PAYMENT_STATUS_RETURNCODES
		 * @throws Exception
		 */
		private function updateOrderByResursPaymentStatus($woocommerceOrder, $currentWcStatus = '', $paymentIdOrPaymentObject = '', $byCallbackEvent = RESURS_CALLBACK_TYPES::CALLBACK_TYPE_NOT_SET, $callbackEventDataArrayOrString = array()) {
			try {
				/** @var $suggestedStatus RESURS_PAYMENT_STATUS_RETURNCODES */
				$suggestedStatus = $this->flow->getOrderStatusByPayment( $paymentIdOrPaymentObject, $byCallbackEvent, $callbackEventDataArrayOrString );

				switch ( $suggestedStatus ) {
					case RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_PROCESSING:
						$this->synchronizeResursOrderStatus( $currentWcStatus, 'processing', $woocommerceOrder, $suggestedStatus );

						//$woocommerceOrder->add_order_note( __( 'Updated order based on Resurs Bank Payment Status', 'WC_Payment_Gateway' ) . " (Payment_Processing)");
						return $suggestedStatus;
					case RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_REFUND:
						$this->synchronizeResursOrderStatus( $currentWcStatus, 'refunded', $woocommerceOrder, $suggestedStatus );

						//$woocommerceOrder->add_order_note( __( 'Updated order based on Resurs Bank Payment Status', 'WC_Payment_Gateway' ) . " (Payment_Refund)");
						return $suggestedStatus;
					case RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_COMPLETED:
						$this->synchronizeResursOrderStatus( $currentWcStatus, 'completed', $woocommerceOrder, $suggestedStatus );

						//$woocommerceOrder->add_order_note( __( 'Updated order based on Resurs Bank Payment Status', 'WC_Payment_Gateway' ) . " (Payment_Completed)");
						return $suggestedStatus;
					case RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_PENDING:
						$this->synchronizeResursOrderStatus( $currentWcStatus, 'on-hold', $woocommerceOrder, $suggestedStatus );

						//$woocommerceOrder->add_order_note( __( 'Updated order based on Resurs Bank Payment Status', 'WC_Payment_Gateway' ) . " (Payment_Pending)");
						return $suggestedStatus;
					case RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_CANCELLED:
						$woocommerceOrder->update_status( 'cancelled' );
						if ( ! isWooCommerce3() ) {
							$woocommerceOrder->cancel_order( __( 'Resurs Bank annulled the order', 'WC_Payment_Gateway' ) );
						}

						return $suggestedStatus;
					default:
						$this->synchronizeResursOrderStatus( $currentWcStatus, 'on-hold', $woocommerceOrder, $suggestedStatus );
						//$woocommerceOrder->add_order_note( __( 'Updated order based on Resurs Bank Payment Status', 'WC_Payment_Gateway' ) . " (Generic_Payment_Status - on-hold)");
						break;
				}
			} catch (\Exception $e) {
			    // Ignore errors
            }
			return RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_STATUS_COULD_NOT_BE_SET;
        }

		/**
		 * Register a callback event (EComPHP)
		 *
		 * @param  string $type The callback type to be registered
		 * @param  array $options The parameters for the SOAP request
		 *
		 * @return bool|mixed|string
		 * @throws Exception
		 */
		public function register_callback( $type, $options ) {
			$uriTemplate = null;
			if ( false === is_object( $this->flow ) ) {
				/** @var \Resursbank\RBEcomPHP\ResursBank */
				$this->flow = initializeResursFlow();
			}
			try {
				$testTemplate      = home_url( '/' );
				$useTemplate       = $testTemplate;
				$customCallbackUri = resursOption( "customCallbackUri" );
				if ( ! empty( $customCallbackUri ) && $testTemplate != $customCallbackUri ) {
					$useTemplate = $customCallbackUri;
				}
				$uriTemplate = $useTemplate;
				$uriTemplate = add_query_arg( 'wc-api', 'WC_Resurs_Bank', $uriTemplate );
				$uriTemplate .= '&event-type=' . $type;
				foreach ( $options['uri_components'] as $key => $value ) {
					$uriTemplate .= '&' . $key . '=' . '{' . $value . '}';
				}
				if ( $type == "TEST" ) {
					$uriTemplate .= '&thisRandomValue=' . rand( 10000, 32000 );
				} else {
					$uriTemplate .= '&digest={digest}';
				}
				$uriTemplate  .= '&ts=' . strftime( "%y%m%d%H%M", time() );
				$callbackType = $this->flow->getCallbackTypeByString( $type );
				$this->flow->setCallbackDigestSalt( get_transient( 'resurs_bank_digest_salt' ) );
				$this->flow->setRegisterCallback( $callbackType, $uriTemplate );
			} catch ( Exception $e ) {
				throw new Exception( $e );
			}

			return $uriTemplate;
		}

		/**
		 * Get digest parameters for register callback
		 *
		 * @param  array $params The parameters
		 *
		 * @return array         The parameters reordered
		 */
		public function get_digest_parameters( $params ) {
			$arr = array();
			foreach ( $params as $key => $value ) {
				$arr[] = $value;
			}

			return $arr;
		}


		/**
		 * Initialize the web services through EComPHP-Simplified
		 *
		 * @param  string $username The username for the API, is fetched from options if not specified
		 * @param  string $password The password for the API, is fetched from options if not specified
		 *
		 * @return boolean          Return whether or not the action succeeded
		 */
		public function init_webservice( $username = '', $password = '' ) {
			try {
				/** @var \Resursbank\RBEcomPHP\ResursBank */
				$this->flow = initializeResursFlow();
			} catch ( Exception $initFlowException ) {
				return false;
			}

			return true;
		}

		// Payment spec functions is a part of the bookPayment functions
		/**
         * Get specLines for initiated payment session
         *
		 * @param WC_Cart $cart WooCommerce cart containing order items
		 *
		 * @return array       The specLines for startPaymentSession
		 */
		protected static function get_spec_lines( $cart ) {
			$spec_lines = array();
			foreach ( $cart as $item ) {
			    /** @var WC_Product $data */
				$data     = $item['data'];
				/** @var WC_Tax $_tax */
				$_tax     = new WC_Tax();  //looking for appropriate vat for specific product
				$rates    = array();
				$taxClass = $data->get_tax_class();
				$rates    = @array_shift( $_tax->get_rates( $taxClass ) );
				if ( isset( $rates['rate'] ) ) {
					$vatPct = (double) $rates['rate'];
				} else {
					$vatPct = 0;
				}
				$priceExTax     = ( ! isWooCommerce3() ? $data->get_price_excluding_tax() : wc_get_price_excluding_tax( $data ) );
				$totalVatAmount = ( $priceExTax * ( $vatPct / 100 ) );
				$setSku         = $data->get_sku();
				$bookArtId      = ( ! isWooCommerce3() ? $data->id : $data->get_id() );
				$postTitle      = ( ! isWooCommerce3() ? $data->post->post_title : $data->get_title() );
				$optionUseSku   = getResursOption( "useSku" );
				if ( $optionUseSku && ! empty( $setSku ) ) {
					$bookArtId = $setSku;
				}
				$artDescription = ( empty( $postTitle ) ? __( 'Article description missing', 'WC_Payment_Gateway' ) : $postTitle );
				$spec_lines[]   = array(
					'id'                   => $bookArtId,
					'artNo'                => $bookArtId,
					'description'          => $artDescription,
					'quantity'             => $item['quantity'],
					'unitMeasure'          => '',
					'unitAmountWithoutVat' => $priceExTax,
					'vatPct'               => $vatPct,
					'totalVatAmount'       => ( $priceExTax * ( $vatPct / 100 ) ),
					'totalAmount'          => ( ( $priceExTax * $item['quantity'] ) + ( $totalVatAmount * $item['quantity'] ) ),
					'type'                 => 'ORDER_LINE'
				);
			}

			return $spec_lines;
		}

		/**
		 * Get and convert payment spec from cart, convert it to Resurs Specrows
		 *
		 * @param WC_Cart $cart Order items
		 * @param bool $specLinesOnly Return only the array of speclines
		 *
		 * @return array The paymentSpec for startPaymentSession
		 * @throws Exception
		 */
		protected static function get_payment_spec( $cart, $specLinesOnly = false ) {
			global $woocommerce;

			//$payment_fee_tax_pct = (float) getResursOption( 'pricePct' );
            /** @var WC_Cart $currentCart */
			$currentCart         = $cart->get_cart();
			$spec_lines          = self::get_spec_lines( $currentCart );
			$shipping            = (float) $cart->shipping_total;
			$shipping_tax        = (float) $cart->shipping_tax_total;
			$shipping_total      = (float) ( $shipping + $shipping_tax );
			/*
             * Compatibility (Discovered in PHP7)
			 */
			$shipping_tax_pct = ( ! is_nan( @round( $shipping_tax / $shipping, 2 ) * 100 ) ? @round( $shipping_tax / $shipping, 2 ) * 100 : 0 );

			$spec_lines[]          = array(
				'id'                   => 'frakt',
				'artNo'                => '00_frakt',
				'description'          => __( 'Shipping', 'WC_Payment_Gateway' ),
				'quantity'             => '1',
				'unitMeasure'          => '',
				'unitAmountWithoutVat' => $shipping,
				'vatPct'               => $shipping_tax_pct,
				'totalVatAmount'       => $shipping_tax,
				'totalAmount'          => $shipping_total,
				'type'                 => 'SHIPPING_FEE',
			);
			$payment_method        = $woocommerce->session->chosen_payment_method;
			$payment_fee           = getResursOption( 'price', 'woocommerce_' . $payment_method . '_settings' );
			$payment_fee           = (float) ( isset( $payment_fee ) ? $payment_fee : '0' );
			$payment_fee_tax_class = getResursOption('priceTaxClass');
			if ( ! hasWooCommerce( "2.3", ">=" ) ) {
				$payment_fee_tax_class_rates = $cart->tax->get_rates( $payment_fee_tax_class );
				$payment_fee_tax             = $cart->tax->calc_tax( $payment_fee, $payment_fee_tax_class_rates, false, true );
			} else {
				// ->tax has been deprecated since WC 2.3
				$payment_fee_tax_class_rates = WC_Tax::get_rates( $payment_fee_tax_class );
				$payment_fee_tax             = WC_Tax::calc_tax( $payment_fee, $payment_fee_tax_class_rates, false, true );
			}

			$payment_fee_total_tax = 0;
			foreach ( $payment_fee_tax as $value ) {
				$payment_fee_total_tax = $payment_fee_total_tax + $value;
			}
			$tax_rates_pct_total = 0;
			foreach ( $payment_fee_tax_class_rates as $key => $rate ) {
				$tax_rates_pct_total = $tax_rates_pct_total + (float) $rate['rate'];
			}

			$ResursFeeName = "";
			$fees          = $cart->get_fees();

			if ( is_array( $fees ) ) {
				foreach ( $fees as $fee ) {
					/*
					 * Ignore this fee if it matches the Resurs description.
					 */
					if ( $fee->tax > 0 ) {
						$rate = ( $fee->tax / $fee->amount ) * 100;
					} else {
						$rate = 0;
					}
					if ( ! empty( $fee->id ) ) {
						$spec_lines[] = array(
							'id'                   => $fee->id,
							'artNo'                => $fee->id,
							'description'          => $fee->name,
							'quantity'             => 1,
							'unitMeasure'          => '',
							'unitAmountWithoutVat' => $fee->amount,
							'vatPct'               => ! is_nan( $rate ) ? $rate : 0,
							'totalVatAmount'       => $fee->tax,
							'totalAmount'          => $fee->amount + $fee->tax,
						);
					}
				}
			}
			if ( $cart->coupons_enabled() ) {
				$coupons = $cart->get_coupons();
				if ( is_array($coupons) && count( $coupons ) > 0 ) {
					$coupon_values     = $cart->coupon_discount_amounts;
					$coupon_tax_values = $cart->coupon_discount_tax_amounts;
					/**
					 * @var  $code
					 * @var  $coupon WC_Coupon
					 */
					foreach ( $coupons as $code => $coupon ) {
						$post         = get_post( ( ! isWooCommerce3() ? $coupon->id : $coupon->get_id() ) );
						$couponId     = ( ! isWooCommerce3() ? $coupon->id : $coupon->get_id() );
						$couponCode   = ( ! isWooCommerce3() ? $coupon->id : $coupon->get_code() );
						$spec_lines[] = array(
							'id'                   => $couponId,
							'artNo'                => $couponCode . '_' . 'kupong',
							'description'          => $post->post_excerpt,
							'quantity'             => 1,
							'unitMeasure'          => '',
							'unitAmountWithoutVat' => ( 0 - (float) $coupon_values[ $code ] ) + ( 0 - (float) $coupon_tax_values[ $code ] ),
							'vatPct'               => 0,
							'totalVatAmount'       => 0,
							'totalAmount'          => ( 0 - (float) $coupon_values[ $code ] ) + ( 0 - (float) $coupon_tax_values[ $code ] ),
							'type'                 => 'DISCOUNT',
						);
					}
				}
			}
			$ourPaymentSpecCalc = self::calculateSpecLineAmount( $spec_lines );
			if ( ! $specLinesOnly ) {
				$payment_spec = array(
					'specLines'      => $spec_lines,
					'totalAmount'    => $ourPaymentSpecCalc['totalAmount'],
					'totalVatAmount' => $ourPaymentSpecCalc['totalVatAmount'],
				);
			} else {
				return $spec_lines;
			}

			return $payment_spec;
		}

		/**
		 * @param array $specLine
		 *
		 * @return array
		 */
		protected static function calculateSpecLineAmount( $specLine = array() ) {
			$setPaymentSpec = array( 'totalAmount' => 0, 'totalVatAmount' => 0 ); // defaults
			if ( is_array( $specLine ) && count( $specLine ) ) {
				foreach ( $specLine as $row ) {
					$setPaymentSpec['totalAmount']    += $row['totalAmount'];
					$setPaymentSpec['totalVatAmount'] += $row['totalVatAmount'];
				}
			}

			return $setPaymentSpec;
		}

		protected static function resurs_hostedflow_create_payment() {
			global $woocommerce;
			/** @var $flow \Resursbank\RBEcomPHP\ResursBank */
			$flow = initializeResursFlow();
			$flow->setPreferredPaymentService( RESURS_FLOW_TYPES::FLOW_HOSTED_FLOW );
			$flow->Include = array();
		}

		/**
		 * Function formerly known as the forms session, where forms was created from a response from Resurs.
		 * From now on, we won't get any returned values from this function. Instead, we'll create the form at this
		 * level.
		 *
		 * @param  int $payment_id The chosen payment method
		 * @param null $method_class
		 *
		 * @throws Exception
		 */
		public function start_payment_session( $payment_id, $method_class = null ) {
			global $woocommerce;
			$this->flow     = initializeResursFlow();
			$currentCountry = getResursOption( 'country' );
			$regExRules     = array();
			$minMaxError = null;
			$methodList = null;

			$cart             = $woocommerce->cart;
			$paymentSpec      = $this->get_payment_spec( $cart );
			$totalAmount      = $paymentSpec['totalAmount'];
			$fieldGenHtml     = "";
			$sessionHasErrors = false;

			$resursTemporaryPaymentMethodsTime = get_transient("resursTemporaryPaymentMethodsTime");
			$timeDiff = time() - $resursTemporaryPaymentMethodsTime;

			$countryCredentialArray = array();
			$hasCountry = false;
			if ( isResursDemo() && isset($_SESSION['rb_country']) && class_exists( "CountryHandler" ) ) {
				if (isset($_SESSION['rb_country'])) {
					$methodList = get_transient( 'resursMethods' . $_SESSION['rb_country'] );
					$hasCountry = true;
				}
			}
			if (!$hasCountry) {
				try {
					if ( $timeDiff >= 3600 ) {
						$methodList = $this->flow->getPaymentMethods();
						set_transient( "resursTemporaryPaymentMethodsTime", time(), 3600 );
						set_transient( "resursTemporaryPaymentMethods", serialize( $methodList ), 3600 );
					} else {
						$methodList = unserialize( get_transient( "resursTemporaryPaymentMethods" ) );
					}
				} catch ( Exception $e ) {
					$sessionHasErrors    = true;
					$sessionErrorMessage = $e->getMessage();
				}
			}

			// Get the read more from internal translation if not set
			$read_more = ( ! empty( $translation ) && isset( $translation['read_more'] ) && ! empty( $translation['read_more'] ) ) ? $translation['read_more'] : __( 'Read more', 'WC_Payment_Gateway' );

			if ( ! $sessionHasErrors ) {
			    if (is_array($methodList)) {
				    foreach ( $methodList as $methodIndex => $method ) {
					    $id           = $method->id;
					    $min          = $method->minLimit;
					    $max          = $method->maxLimit;
					    $customerType = $method->customerType;
					    $specificType = $method->specificType;

					    $inheritFields = array(
						    'applicant-email-address'    => 'billing_email',
						    'applicant-mobile-number'    => 'billing_phone_field',
						    'applicant-telephone-number' => 'billing_phone_field'
					    );
					    $labels        = array(
						    'contact-government-id'      => __( 'Contact government id', 'WC_Payment_Gateway' ),
						    'applicant-government-id'    => __( 'Applicant government ID', 'WC_Payment_Gateway' ),
						    'applicant-full-name'        => __( 'Applicant full name', 'WC_Payment_Gateway' ),
						    'applicant-email-address'    => __( 'Applicant email address', 'WC_Payment_Gateway' ),
						    'applicant-telephone-number' => __( 'Applicant telephone number', 'WC_Payment_Gateway' ),
						    'applicant-mobile-number'    => __( 'Applicant mobile number', 'WC_Payment_Gateway' ),
						    'card-number'                => __( 'Card number', 'WC_Payment_Gateway' ),
					    );
					    // Appears to happen when LEGAL are chosen
					    $labelsLegal = array(
						    'applicant-government-id' => __( 'Company government ID', 'WC_Payment_Gateway' ),
					    );
					    $minMaxError = false;
					    if ( $totalAmount >= $min && $totalAmount <= $max ) {
						    try {
							    $regExRules = $this->flow->getRegEx( '', $currentCountry, $customerType );
						    } catch ( Exception $e ) {
							    echo $e->getMessage();
						    }
						    if ( strtolower( $id ) == strtolower( $payment_id ) ) {
							    // When boths customer types are allowed, this is going arrayified.
							    // In that case, select the one that the customer has chosen. Default is NATURAL
							    if ( ! isset( $_REQUEST['ssnCustomerType'] ) ) {
								    $_REQUEST['ssnCustomerType'] = "NATURAL";
							    }
							    $customerTypeTest = $_REQUEST['ssnCustomerType'];
							    if ( is_array( $customerType ) && in_array( $customerTypeTest, $customerType ) ) {
								    $customerType = $customerTypeTest;
							    }
							    $requiredFormFields = $this->flow->getTemplateFieldsByMethodType( $method, $customerType, $specificType );
							    $buttonCssClasses   = "btn btn-info active";
							    $ajaxUrl            = admin_url( 'admin-ajax.php' );
							    if ( ! isResursHosted() ) {
								    $fieldGenHtml .= '<div>' . $method_class->description . '</div>';
								    foreach ( $requiredFormFields['fields'] as $fieldName ) {
									    $doDisplay           = "block";
									    $streamLineBehaviour = getResursOption( "streamlineBehaviour" );
									    if ( $streamLineBehaviour ) {
										    if ( $this->flow->canHideFormField( $fieldName ) ) {
											    $doDisplay = "none";
										    }
										    /*
											 * As we do get the applicant government id from the getaddress field, we don't have to show that here.
											 */
										    if ( $fieldName == "applicant-government-id" ) {
											    /*
												 * But only if it is enabled
												 */
											    $optionGetAddress = getResursOption( "getAddress" );
											    if ( $optionGetAddress ) {
												    $doDisplay = "none";
											    }
										    }
									    }
									    $setLabel = $labels[ $fieldName ];
									    if ( isset( $labelsLegal[ $fieldName ] ) && ! empty( $labelsLegal[ $fieldName ] ) && $customerType != "NATURAL" ) {
										    $setLabel = $labelsLegal[ $fieldName ];
									    }
									    $fieldGenHtml .= '<div style="display:' . $doDisplay . ';width:100%;" class="resurs_bank_payment_field_container">';
									    $fieldGenHtml .= '<label for="' . $fieldName . '" style="width:100%;display:block;">' . $setLabel . '</label>';
									    $fieldGenHtml .= '<input onkeyup="rbFormChange(\'' . $fieldName . '\', this)" id="' . $fieldName . '" type="text" name="' . $fieldName . '">';
									    $fieldGenHtml .= '</div>';
								    }

								    /*
									 * MarGul Change
									 * Use translations for the Read More Button. Also added a fixed width and height on the onClick button.
									 */
								    if ( class_exists( "CountryHandler" ) ) {
									    $translation = CountryHandler::getDictionary();
								    } else {
									    $translation = array();
								    }
								    $costOfPurchase = $ajaxUrl . "?action=get_cost_ajax";
								    if ( $specificType != "CARD" ) {
									    $fieldGenHtml .= '<button type="button" class="' . $buttonCssClasses . '" onClick="window.open(\'' . $costOfPurchase . '&method=' . $method->id . '&amount=' . $cart->total . '\', \'costOfPurchasePopup\',\'toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,copyhistory=no,resizable=yes,width=650px,height=740px\')">' . __( $read_more, 'WC_Payment_Gateway' ) . '</button>';
								    }
								    // Fix: There has been an echo here, instead of a fieldGenHtml
								    $fieldGenHtml .= '<input type="hidden" value="' . $id . '" class="resurs-bank-payment-method">';
							    } else {
								    $costOfPurchase = $ajaxUrl . "?action=get_cost_ajax";
								    $fieldGenHtml   = $this->description . "<br><br>";
								    if ( $specificType != "CARD" ) {
									    $fieldGenHtml .= '<button type="button" class="' . $buttonCssClasses . '" onClick="window.open(\'' . $costOfPurchase . '&method=' . $method->id . '&amount=' . $cart->total . '\', \'costOfPurchasePopup\',\'toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,copyhistory=no,resizable=yes,width=650px,height=740px\')">' . __( $read_more, 'WC_Payment_Gateway' ) . '</button>';
								    }
							    }
						    }
					    } else {
						    $minMaxError = true;
					    }
				    }
			    } else {
			        $fieldGenHtml = __('Something went wrong while trying to get the required form fields for the payment methods', 'WC_Payment_Gateway');
                }
			} else {
				$fieldGenHtml = __( 'Something went wrong during communication with Resurs Bank', 'WC_Payment_Gateway' ) . "<br><br>\n<i>" . $sessionErrorMessage . "</i>";
			}
			if ( ! empty( $fieldGenHtml ) ) {
				echo $fieldGenHtml;
			}
			if ( isResursTest() ) {
				/*if ( isset($minMaxError) ) {
					echo '<div style="font-style:italic">' . __( 'Your environment is currently in test mode and the payment amount is lower or higher that the payment method allows.<br>In production mode, this payment method will be hidden.', 'WC_Payment_Gateway' ) . "</div>";
				}*/
			}
		}

		/**
		 * Proccess the payment
         *
		 * @param  int $order_id WooCommerce order ID
		 *
		 * @return array|void Null on failure, array on success
		 * @throws Exception
		 */
		public function process_payment( $order_id ) {
			global $woocommerce;
			// Skip procedure of process_payment if the session is based on a finalizing omnicheckout.
			if ( defined( 'OMNICHECKOUT_PROCESSPAYMENT' ) ) {
				return;
			}
			$order       = new WC_Order( $order_id );

			if (getResursOption( "postidreference" )) {
			    $preferredId = $order_id;
            } else {
				$preferredId = $this->flow->getPreferredPaymentId( 25 );
            }

			update_post_meta( $order_id, 'paymentId', $preferredId );
			$customer  = $woocommerce->customer;
			$className = isset( $_REQUEST['payment_method'] ) ? $_REQUEST['payment_method'] : null;

			$payment_settings = get_option( 'woocommerce_' . $className . '_settings' );
			/** @var \Resursbank\RBEcomPHP\ResursBank */
			$this->flow       = initializeResursFlow();
			$bookDataArray    = array();

			if ( isset( $_REQUEST['applicant-government-id'] ) && ! empty( $_REQUEST['applicant-government-id'] ) ) {
				$_REQUEST['applicant-government-id'] = trim( $_REQUEST['applicant-government-id'] );
			}

			$bookDataArray['address'] = array(
				'fullName'    => $_REQUEST['billing_last_name'] . ' ' . $_REQUEST['billing_first_name'],
				'firstName'   => $_REQUEST['billing_first_name'],
				'lastName'    => $_REQUEST['billing_last_name'],
				'addressRow1' => $_REQUEST['billing_address_1'],
				'addressRow2' => ( empty( $_REQUEST['billing_address_2'] ) ? '' : $_REQUEST['billing_address_2'] ),
				'postalArea'  => $_REQUEST['billing_city'],
				'postalCode'  => $_REQUEST['billing_postcode'],
				'country'     => $_REQUEST['billing_country'],
			);
			if ( isset( $_REQUEST['ship_to_different_address'] ) ) {
				$bookDataArray['deliveryAddress'] = array(
					'fullName'    => $_REQUEST['shipping_last_name'] . ' ' . $_REQUEST['shipping_first_name'],
					'firstName'   => $_REQUEST['shipping_first_name'],
					'lastName'    => $_REQUEST['shipping_last_name'],
					'addressRow1' => $_REQUEST['shipping_address_1'],
					'addressRow2' => ( empty( $_REQUEST['shipping_address_2'] ) ? '' : $_REQUEST['shipping_address_2'] ),
					'postalArea'  => $_REQUEST['shipping_city'],
					'postalCode'  => $_REQUEST['shipping_postcode'],
					'country'     => $_REQUEST['shipping_country'],
				);
			}
			if ( empty( $_REQUEST['shipping_address_2'] ) ) {
				unset( $bookDataArray['deliveryAddress']['addressRow2'] );
			};
			if ( empty( $_REQUEST['billing_address_2'] ) ) {
				unset( $bookDataArray['address']['addressRow2'] );
			};

			/* Generate successUrl for the signing (Legacy) */
			$success_url = home_url( '/' );
			$success_url = add_query_arg( 'wc-api', 'WC_Resurs_Bank', $success_url );
			$success_url = add_query_arg( 'order_id', $order_id, $success_url );
			$success_url = add_query_arg( 'utm_nooverride', '1', $success_url );
			$success_url = add_query_arg( 'event-type', 'check_signing_response', $success_url );
			$success_url = add_query_arg( 'set-no-session', '1', $success_url );
			$success_url = add_query_arg( 'payment_id', $preferredId, $success_url );
			if ( isResursHosted() ) {
				$success_url = add_query_arg( 'flow-type', 'check_hosted_response', $success_url );
				$bookDataArray['backUrl'] = html_entity_decode( $order->get_cancel_order_url() ) . "&isBack=1";
			}
			//$success_url = add_query_arg( 'uniq', '$uniqueId', $success_url );

			$urlFail = html_entity_decode( $order->get_cancel_order_url() );
			$urlSuccess = $success_url;

			$bookDataArray['uniqueId'] = sha1( uniqid( microtime( true ), true ) );
			$bookDataArray['signing']  = array(
				'successUrl'   => $success_url,
				'failUrl'      => $urlFail,
				'forceSigning' => false
			);

			$bookDataArray['paymentData'] = array(
				'waitForFraudControl' => resursOption( 'waitForFraudControl' ),
				'annulIfFrozen'       => resursOption( 'annulIfFrozen' ),
				'finalizeIfBooked'    => resursOption( 'finalizeIfBooked' ),
				'preferredId'         => $preferredId
			);
			$shortMethodName              = str_replace( 'resurs_bank_nr_', '', $className );
			$cart                         = $woocommerce->cart;
			$paymentSpec                  = $this->get_payment_spec( $cart, true );

			$methodSpecification = $this->getTransientMethod( $shortMethodName );
            $useCustomerType = "";

			if (!is_array($methodSpecification->customerType)) {
				if ( $methodSpecification->customerType == "NATURAL" ) {
                    $useCustomerType = "NATURAL";
				} else if ( $methodSpecification->customerType == "LEGAL" ) {
                    $useCustomerType = "LEGAL";
				}
			} else {
			    $useCustomerType = "NATURAL";
            }

			$bookDataArray['specLine'] = $paymentSpec;
			$bookDataArray['customer'] = array(
				'governmentId' => ( isset( $_REQUEST['applicant-government-id'] ) ? $_REQUEST['applicant-government-id'] : "" ),
				'phone'        => ( isset( $_REQUEST['applicant-telephone-number'] ) ? $_REQUEST['applicant-telephone-number'] : "" ),
				'email'        => ( isset( $_REQUEST['applicant-email-address'] ) ? $_REQUEST['applicant-email-address'] : "" ),
				'type'         => ( isset( $_REQUEST['ssnCustomerType'] ) ? $_REQUEST['ssnCustomerType'] : $useCustomerType )
			);
			if ( isset( $methodSpecification->specificType ) && ( $methodSpecification->specificType == "REVOLVING_CREDIT" || $methodSpecification->specificType == "CARD" ) ) {
				$bookDataArray['customer']['governmentId'] = isset( $_REQUEST['applicant-government-id'] ) ? $_REQUEST['applicant-government-id'] : "";
				$bookDataArray['customer']['type']         = isset( $_REQUEST['ssnCustomerType'] ) ? $_REQUEST['ssnCustomerType'] : $useCustomerType;
				if ( $methodSpecification->specificType == "REVOLVING_CREDIT" ) {
					$this->flow->setCardData();
				} else {
					if ( isset( $_REQUEST['card-number'] ) ) {
						$cardNumber = $_REQUEST['card-number'];
						$this->flow->setCardData( $cardNumber );
					}
				}
			}
			if ( $methodSpecification->customerType == "LEGAL" ) {
				$bookDataArray['customer']['contactGovernmentId'] = ( isset( $_REQUEST['contact-government-id'] ) ? $_REQUEST['contact-government-id'] : null );
			}
			if ( isset( $_REQUEST['applicant-mobile-number'] ) && ! empty( $_REQUEST['applicant-mobile-number'] ) ) {
				$bookDataArray['customer']['cellPhone'] = $_REQUEST['applicant-mobile-number'];
			}
			$supportProviderMethods = true;
			try {
				if ( isResursHosted() ) {
					if ( isset( $_REQUEST['ssn_field'] ) && ! empty( $_REQUEST['ssn_field'] ) ) {
						$bookDataArray['customer']['governmentId'] = $_REQUEST['ssn_field'];
					}
					if ( isset( $_REQUEST['billing_phone'] ) ) {
						$bookDataArray['customer']['phone'] = $_REQUEST['billing_phone'];
					}
					if ( isset( $_REQUEST['billing_email'] ) ) {
						$bookDataArray['customer']['email'] = $_REQUEST['billing_email'];
					}
					if ( isset( $_REQUEST['ssnCustomerType'] ) ) {
						$bookDataArray['customer']['type'] = $_REQUEST['ssnCustomerType'];
					}
					if (empty($bookDataArray['customer']['type'])) {
						$bookDataArray['customer']['type'] = $useCustomerType;
					}
					$bookDataArray['paymentData']['preferredId'] = $preferredId;
					$hostedFlowBookingFailure   = false;
					$hostedFlowUrl = null;
					$hostedBookPayment = null;

					if ( $methodSpecification->type == "PAYMENT_PROVIDER" && ! $supportProviderMethods ) {
						wc_add_notice( __( 'The payment method is not available for the selected payment flow', 'WC_Payment_Gateway' ), 'error' );

						return;
					} else {
						try {
							$this->flow->setPreferredPaymentService( RESURS_FLOW_TYPES::FLOW_HOSTED_FLOW );
							$hostedFlowUrl = $this->flow->createPayment( $shortMethodName, $bookDataArray );
						} catch ( \Exception $hostedException ) {
							$hostedFlowBookingFailure = true;
							wc_add_notice( $hostedException->getMessage(), 'error' );
						}
					}

					//$successUrl = isset($hostedFlowPayload['successUrl']) ? $hostedFlowPayload['successUrl'] : null;
					//$backUrl = isset($hostedFlowPayload['backUrl']) ? $hostedFlowPayload['backUrl'] : null;
                    // Failurl is currently the only needed variable from the payload
					//$failUrl = isset($hostedFlowPayload['failUrl']) ? $hostedFlowPayload['failUrl'] : null;

					if ( ! $hostedFlowBookingFailure && ! empty( $hostedFlowUrl ) ) {
						$order->update_status( 'pending' );
						update_post_meta( $order_id, 'paymentId', $preferredId );
						return array(
							'result'   => 'success',
							'redirect' => $hostedFlowUrl
						);
					} else {
						$order->update_status( 'failed', __( 'An error occured during the update of the booked payment (hostedFlow) - the payment id which was never received properly', 'WC_Payment_Gateway' ) );
						return array(
							'result'   => 'failure',
							'redirect' => $urlFail
						);
					}
				} else {
					if ( $methodSpecification->type == "PAYMENT_PROVIDER" && ! $supportProviderMethods ) {
						wc_add_notice( __( 'The payment method is not available for the selected payment flow', 'WC_Payment_Gateway' ), 'error' );

						return;
					} else {
						$storeId = apply_filters( "resursbank_set_storeid", null );
						if ( ! empty( $storeId ) ) {
							$bookDataArray['storeId'] = $storeId;
							update_post_meta( $order_id, 'resursStoreId', $storeId );
						}
						// If woocommerce forms do offer phone and email, while our own don't, use them.
						if ( empty( $bookDataArray['customer']['phone'] ) && isset( $_REQUEST['billing_phone'] ) && ! empty( $_REQUEST['billing_phone'] ) ) {
							$bookDataArray['customer']['phone'] = $_REQUEST['billing_phone'];
						}
						if ( empty( $bookDataArray['customer']['email'] ) && isset( $_REQUEST['billing_phone'] ) && ! empty( $_REQUEST['billing_email'] ) ) {
							$bookDataArray['customer']['email'] = $_REQUEST['billing_email'];
						}
						$bookPaymentResult = $this->flow->createPayment( $shortMethodName, $bookDataArray );
					}
				}
			} catch ( Exception $bookPaymentException ) {
				wc_add_notice( __( $bookPaymentException->getMessage(), 'WC_Payment_Gateway' ), 'error' );
				return;
			}

			$bookedStatus = trim(isset($bookPaymentResult->bookPaymentStatus) ? $bookPaymentResult->bookPaymentStatus : null);
			$bookedPaymentId = isset($bookPaymentResult->paymentId) ? $bookPaymentResult->paymentId : null;
			if ( empty( $bookedPaymentId ) ) {
				$bookedStatus = "FAILED";
			} else {
				update_post_meta( $order_id, 'paymentId', $bookedPaymentId );
			}
			switch ( $bookedStatus ) {
				case 'FINALIZED':
					define('RB_SYNCHRONOUS_MODE', true);
					WC()->session->set( "order_awaiting_payment", true );
					//$order->update_status( 'completed' );
					try {
						$order->set_status( 'completed', __( 'Order is debited and completed', 'WC_Payment_Gateway' ), true );
						$order->save();
					} catch ( \Exception $e ) {
						wc_add_notice( $e->getMessage(), 'error' );
						return;
					}
    				WC()->cart->empty_cart();
					return array( 'result' => 'success', 'redirect' => $this->get_return_url( $order ) );
					break;
				case 'BOOKED':
					$order->update_status( 'processing' );
					$optionReduceOrderStock = getResursOption( 'reduceOrderStock' );
					$hasReduceStock = get_post_meta($order_id, 'hasReduceStock');
					if ( $optionReduceOrderStock && empty( $hasReduceStock ) ) {
						update_post_meta( $order_id, 'hasReduceStock', time() );
					    if (isWooCommerce3()) {
						    wc_reduce_stock_levels($order_id);
					    } else {
						    $order->reduce_order_stock();
					    }
					}
					WC()->cart->empty_cart();

					return array( 'result' => 'success', 'redirect' => $this->get_return_url( $order ) );
					break;
				case 'FROZEN':
					$order->update_status( 'on-hold' );
					WC()->cart->empty_cart();
					return array( 'result' => 'success', 'redirect' => $this->get_return_url( $order ) );
					break;
				case 'SIGNING':
				    $signingUrl = isset($bookPaymentResult->signingUrl) ? $bookPaymentResult->signingUrl : null;
				    if (!is_null($signingUrl)) {
					    return array(
						    'result'   => 'success',
						    'redirect' => $signingUrl
					    );
				    }
					$order->update_status( 'failed' );
					wc_add_notice( __( 'Payment can not complete. A problem with the signing url occurred. Contact customer services for more information.', 'WC_Payment_Gateway' ), 'error' );
					break;
				case 'DENIED':
					$order->update_status( 'failed' );
					wc_add_notice( __( 'The payment can not complete. Contact customer services for more information.', 'WC_Payment_Gateway' ), 'error' );

					return;
					break;
				case 'FAILED':
					$order->update_status( 'failed', __( 'An error occured during the update of the booked payment. The payment ID was never received properly in the payment process', 'WC_Payment_Gateway' ) );
					wc_add_notice( __( 'An unknown error occured. Please, try again later', 'WC_Payment_Gateway' ), 'error' );

					return;
					break;
				default:
					wc_add_notice( __( 'An unknown error occured. Please, try again later', 'WC_Payment_Gateway' ), 'error' );

					return;
					break;
			}
		}

		/**
		 * Get specific payment method object, from transient
		 *
		 * @param string $methodId
		 *
		 * @return array
         * @throws \Exception
		 */
		public function getTransientMethod( $methodId = '' ) {
			//$methodList = get_transient('resurs_bank_payment_methods');
			if ( empty( $this->flow ) ) {
				/** @var \Resursbank\RBEcomPHP\ResursBank */
				$this->flow = initializeResursFlow();
			}
			$methodList = $this->flow->getPaymentMethods();
			if ( is_array( $methodList ) ) {
				foreach ( $methodList as $methodArray ) {
					if ( strtolower( $methodArray->id ) == strtolower( $methodId ) ) {
						return $methodArray;
					}
				}
			}

			return array();
		}

		/**
		 * @param $error
		 *
		 * @return mixed
		 */
		public function error_prepare_omni_order( $error ) {
			return $error;
		}

		/**
		 * Prepare the order for the checkout
		 */
		public function prepare_omni_order() {
			/** @var WC_Checkout $resursOrder What will be created if successful, and what will report undefined variable if unsuccessful */
			$resursOrder = null;
			$updatePaymentReference = false;

			// Get incoming request
			$url_arr          = parse_url( $_SERVER["REQUEST_URI"] );
			$url_arr['query'] = str_replace( 'amp;', '', $url_arr['query'] );
			parse_str( $url_arr['query'], $request );

			/*
             * Get requested order reference
             */
			//$requestedPaymentId = isset($request['orderRef']) && !empty($request['orderRef']) ? $request['orderRef'] : null;

			/*
             * Check the order reference against the session
             */
			$requestedPaymentId   = WC()->session->get( 'omniRef' );
			$requestedUpdateOrder = WC()->session->get( 'omniId' );

			/*
             * Get the customer data that should be created with the order
             */
			$customerData = isset( $_POST['customerData'] ) && is_array( $_POST['customerData'] ) ? $_POST['customerData'] : array();

			/*
             * Get, if exists, the payment method and use it
             */
			$omniPaymentMethod = isset( $_REQUEST['paymentMethod'] ) && ! empty( $_REQUEST['paymentMethod'] ) ? $_REQUEST['paymentMethod'] : "resurs_bank_omnicheckout";


			$errorString = "";
			$errorCode   = "";
			// Default json data response
			$returnResult = array(
				'success'     => false,
				'errorString' => "",
				'errorCode'   => "",
				'verified'    => false,
				'hasOrder'    => false,
				'resursData'  => array()
			);

			$returnResult['resursData']['reqId']    = $requestedPaymentId;
			$returnResult['resursData']['reqLocId'] = $requestedUpdateOrder;

			$returnResult['success'] = false;

			if (isset($_REQUEST['updateReference'])) {
				if ( isset( $_REQUEST['omnicheckout_nonce'] ) ) {
					if ( wp_verify_nonce( $_REQUEST['omnicheckout_nonce'], "omnicheckout" ) ) {
						if ( isset( $_REQUEST['orderRef'] ) && isset( $_REQUEST['orderId'] ) ) {
							$flow = initializeResursFlow();
							try {
								$flow->updatePaymentReference( $_REQUEST['orderRef'], $_REQUEST['orderId'] );
								update_post_meta( $_REQUEST['orderId'], 'paymentId', $_REQUEST['orderId'] );
								update_post_meta( $_REQUEST['orderId'], 'paymentIdLast', $_REQUEST['orderRef'] );
								$returnResult['success'] = true;
								$this->returnJsonResponse( $returnResult, 200 );
							} catch ( \Exception $e ) {
								$returnResult['success']     = false;
								$returnResult['errorString'] = $e->getMessage();
								$returnResult['errorCode']   = 500;
								$this->returnJsonResponse( $returnResult, $returnResult['errorCode'] );

							}
						} else {
							$returnResult['success']     = false;
							$returnResult['errorString'] = "Order reference or orderId not set";
							$returnResult['errorCode']   = 404;
							$this->returnJsonResponse( $returnResult, $returnResult['errorCode'] );
						}
						die;
					}
				}
			}

			if ( ! is_array( $customerData ) ) {
				$customerData = array();
			}
			if ( ! count( $customerData ) ) {
				$returnResult['errorString'] = "No customer data set";
				$returnResult['errorCode']   = "404";
				$this->returnJsonResponse( $returnResult );
			}

			$responseCode       = 0;
			$allowOrderCreation = false;

			// Without the nonce, no background order can prepare
			if ( isset( $_REQUEST['omnicheckout_nonce'] ) ) {
				if ( wp_verify_nonce( $_REQUEST['omnicheckout_nonce'], "omnicheckout" ) ) {

					$hasInternalErrors        = false;
					$returnResult['verified'] = true;

                    // This procedure normally works.
					$testLocalOrder = wc_get_order_id_by_payment_id( $requestedPaymentId );
					if ( ( empty( $testLocalOrder ) && $requestedUpdateOrder ) || ( ! is_numeric( $testLocalOrder ) && is_numeric( $testLocalOrder ) && $testLocalOrder != $requestedUpdateOrder ) ) {
						$testLocalOrder = $requestedUpdateOrder;
					}

					$returnResult['resursData']['locId'] = $requestedPaymentId;

					// If the order has already been created, the user may have been clicking more than one time in the frame, eventually due to payment method changes.
					$wooBillingAddress     = array();
					$wooDeliveryAddress    = array();
					$resursBillingAddress  = isset( $customerData['address'] ) && is_array( $customerData['address'] ) ? $customerData['address'] : array();
					$resursDeliveryAddress = isset( $customerData['delivery'] ) && is_array( $customerData['delivery'] ) ? $customerData['delivery'] : array();
					$failBilling           = true;
					$customerEmail         = ! empty( $resursBillingAddress['email'] ) ? $resursBillingAddress['email'] : "";
					if ( count( $resursBillingAddress ) ) {
						$wooBillingAddress = array(
							'first_name' => ! empty( $resursBillingAddress['firstname'] ) ? $resursBillingAddress['firstname'] : "",
							'last_name'  => ! empty( $resursBillingAddress['surname'] ) ? $resursBillingAddress['surname'] : "",
							'address_1'  => ! empty( $resursBillingAddress['address'] ) ? $resursBillingAddress['address'] : "",
							'address_2'  => ! empty( $resursBillingAddress['addressExtra'] ) ? $resursBillingAddress['addressExtra'] : "",
							'city'       => ! empty( $resursBillingAddress['city'] ) ? $resursBillingAddress['city'] : "",
							'postcode'   => ! empty( $resursBillingAddress['postal'] ) ? $resursBillingAddress['postal'] : "",
							'country'    => ! empty( $resursBillingAddress['countryCode'] ) ? $resursBillingAddress['countryCode'] : "",
							'email'      => ! empty( $resursBillingAddress['email'] ) ? $resursBillingAddress['email'] : "",
							'phone'      => ! empty( $resursBillingAddress['telephone'] ) ? $resursBillingAddress['telephone'] : "",
						);
						$failBilling       = false;
					}
					if ( $failBilling ) {
						$returnResult['errorString'] = "Billing address update failed";
						$returnResult['errorCode']   = "404";
						$this->returnJsonResponse( $returnResult, $returnResult['errorCode'] );
					}
					if ( count( $resursDeliveryAddress ) ) {
						$_POST['ship_to_different_address'] = true;
						$wooDeliveryAddress                 = array(
							'first_name' => ! empty( $resursDeliveryAddress['firstname'] ) ? $resursDeliveryAddress['firstname'] : "",
							'last_name'  => ! empty( $resursDeliveryAddress['surname'] ) ? $resursDeliveryAddress['surname'] : "",
							'address_1'  => ! empty( $resursDeliveryAddress['address'] ) ? $resursDeliveryAddress['address'] : "",
							'address_2'  => ! empty( $resursDeliveryAddress['addressExtra'] ) ? $resursDeliveryAddress['addressExtra'] : "",
							'city'       => ! empty( $resursDeliveryAddress['city'] ) ? $resursDeliveryAddress['city'] : "",
							'postcode'   => ! empty( $resursDeliveryAddress['postal'] ) ? $resursDeliveryAddress['postal'] : "",
							'country'    => ! empty( $resursDeliveryAddress['countryCode'] ) ? $resursDeliveryAddress['countryCode'] : "",
							'email'      => ! empty( $resursDeliveryAddress['email'] ) ? $resursDeliveryAddress['email'] : "",
							'phone'      => ! empty( $resursDeliveryAddress['telephone'] ) ? $resursDeliveryAddress['telephone'] : "",
						);
					} else {
					    // Helper for "sameAddress"-cases.
						$_POST['ship_to_different_address'] = false;
						$wooDeliveryAddress                 = $wooBillingAddress;
					}

					define( 'OMNICHECKOUT_PROCESSPAYMENT', true );
					if ( ! $testLocalOrder ) {
						/*
                         * WooCommerce POST-helper. Since we force removal of required fields in woocommerce, we need to help wooCommerce
                         * adding the correct fields at this level to possibly pass through the internal field validation.
                         */
						foreach ( $wooBillingAddress as $billingKey => $billingValue ) {
							if ( ! isset( $_POST[ $billingKey ] ) ) {
								$_POST[ "billing_" . $billingKey ]    = $billingValue;
								$_REQUEST[ "billing_" . $billingKey ] = $billingValue;
							}
						}
						foreach ( $wooDeliveryAddress as $deliveryKey => $deliveryValue ) {
							if ( ! isset( $_POST[ $deliveryKey ] ) ) {
								$_POST[ "shipping_" . $deliveryKey ]    = $deliveryValue;
								$_REQUEST[ "shipping_" . $deliveryKey ] = $deliveryValue;
							}
						}
						$resursOrder = new WC_Checkout();
						try {
							/*
                             * As we work with the session, we'd try to get the current order that way.
                             * process_checkout() does a lot of background work for this.
                             */
							$internalErrorMessage = "";
							$internalErrorCode    = 0;
							try {
								$resursOrder->process_checkout();
								$wcNotices       = wc_get_notices();
								if ( isset( $wcNotices['error'] ) ) {
									$hasInternalErrors           = true;
									$internalErrorMessage        = implode( "<br>\n", $wcNotices['error'] );
									$internalErrorCode           = 200;
									$returnResult['success']     = false;
									$returnResult['errorString'] = ! empty( $internalErrorMessage ) ? $internalErrorMessage : "OrderId missing";
									$returnResult['errorCode']   = $internalErrorCode;
									wc_clear_notices();
									$this->returnJsonResponse( $returnResult, $returnResult['errorCode'] );
								}
							} catch ( Exception $e ) {
								$hasInternalErrors    = true;
								$internalErrorMessage = $e->getMessage();
								$internalErrorCode    = $e->getCode();
							}
							$order = null;
							$orderId = null;
							try {
								$orderId = WC()->session->get( "order_awaiting_payment" );
								$order   = new WC_Order( $orderId );
							} catch ( Exception $e ) {
								$hasInternalErrors    = true;
								$internalErrorMessage = $e->getMessage();
								$internalErrorCode    = $e->getCode();
							}
							WC()->session->set( 'omniId', $orderId );
							$returnResult['orderId']           = $orderId;
							$returnResult['session']           = WC()->session;
							$returnResult['hasInternalErrors'] = $hasInternalErrors;
							if ( $orderId > 0 && ! $hasInternalErrors ) {
                                /** @var WC_Gateway_ResursBank_Omni $omniClass */
								$omniClass = new WC_Gateway_ResursBank_Omni();
								$order->set_payment_method( $omniClass );
								$order->set_address( $wooBillingAddress, 'billing' );
								$order->set_address( $wooDeliveryAddress, 'shipping' );
								update_post_meta( $orderId, 'paymentId', $requestedPaymentId );
								update_post_meta( $orderId, 'omniPaymentMethod', $omniPaymentMethod );
								$hasInternalErrors    = false;
								$internalErrorMessage = null;
							} else {
								$returnResult['success']     = false;
								$returnResult['errorString'] = ! empty( $internalErrorMessage ) ? $internalErrorMessage : "OrderId missing";
								$returnResult['errorCode']   = $internalErrorCode;
								$this->returnJsonResponse( $returnResult, $returnResult['errorCode'] );
								die();
							}
						} catch ( Exception $createOrderException ) {
							$returnResult['success']     = false;
							$returnResult['errorString'] = $createOrderException->getMessage();
							$returnResult['errorCode']   = $createOrderException->getCode();
							$this->returnJsonResponse( $returnResult, $returnResult['errorCode'] );
							die();
						}
						$returnResult['success'] = true;
						$responseCode            = 200;
						WC()->session->set( "resursCreatePass", "1" );
					} else {
					    // If the order already exists, continue without errors (if we reached this code, it has been because of the nonce which should be considered safe enough)
						$order = new WC_Order( $testLocalOrder );
						$order->set_address( $wooBillingAddress, 'billing' );
						$order->set_address( $wooDeliveryAddress, 'shipping' );
						$returnResult['success']     = true;
						$returnResult['hasOrder']    = true;
						$returnResult['usingOrder']  = $testLocalOrder;
						$returnResult['errorString'] = "Order already exists";
						$returnResult['errorCode']   = 200;
						$responseCode                = 200;
					}
				} else {
					$returnResult['errorString'] = "nonce mismatch";
					$returnResult['errorCode']   = 403;
					$responseCode                = 403;
				}
			} else {
				$returnResult['errorString'] = "nonce missing";
				$returnResult['errorCode']   = 403;
				$responseCode                = 403;
			}
			$this->returnJsonResponse( $returnResult, $responseCode, $resursOrder );
		}

		/**
		 * @param array $jsonArray
		 * @param int $responseCode
		 * @param null $resursOrder
		 */
		private function returnJsonResponse( $jsonArray = array(), $responseCode = 200, $resursOrder = null ) {
			header( "Content-Type: application/json", true, $responseCode );
			echo json_encode( $jsonArray );
			die();
		}

		/**
		 * Check result of signing, book the payment and complete the order
		 */
		public function check_signing_response() {
			global $woocommerce;

			$url_arr          = parse_url( $_SERVER["REQUEST_URI"] );
			$url_arr['query'] = str_replace( 'amp;', '', $url_arr['query'] );
			parse_str( $url_arr['query'], $request );
			$order_id       = isset( $request['order_id'] ) && ! empty( $request['order_id'] ) ? $request['order_id'] : null;
			/** @var $order WC_Order */
			$order          = new WC_Order( $order_id );
			$getRedirectUrl = $this->get_return_url( $order );
			$currentStatus  = $order->get_status();

			$paymentId              = wc_get_payment_id_by_order_id( $order_id );
			$isHostedFlow           = false;
			$requestedPaymentId     = $request['payment_id'];
			$hasBookedHostedPayment = false;
			$bookedPaymentId        = 0;
			$bookedStatus           = null;
			$paymentInfo            = null;

			$flowType = isset( $request['flow-type'] ) ? $request['flow-type'] : "";
			if ( isset( $_REQUEST['flow-type'] ) && empty( $flowType ) ) {
				$flowType = $_REQUEST['flow-type'];
			}
			$eventType = isset( $request['event-type'] ) ? $request['event-type'] : "";
			if ( isset( $_REQUEST['event-type'] ) && empty( $eventType ) ) {
				$eventType = $_REQUEST['event-type'];
			}
			if ( isset( $request['flow-type'] ) ) {
				if ( $request['flow-type'] == "check_hosted_response" ) {
					if ( isResursHosted() ) {
						$isHostedFlow    = true;
						$bookedPaymentId = $requestedPaymentId;
						try {
							$paymentInfo = $this->flow->getPayment( $requestedPaymentId );
						} catch ( Exception $e ) {
						}
						$bookedStatus = "BOOKED";
						// If unable to credit/debit, it may have been annulled
						if ( ! $this->flow->canCredit( $paymentInfo ) && ! $this->flow->canDebit( $paymentInfo ) ) {
							$bookedStatus = "FAILED";
						}
						// Able to credit the order by not debit, it may be finalized.
						if ( $this->flow->canCredit( $paymentInfo ) && ! $this->flow->canDebit( $paymentInfo ) ) {
							$bookedStatus = "FINALIZED";
						}
						if ( isset( $paymentInfo->frozen ) ) {
							$bookedStatus = 'FROZEN';
						}
					}
				} else if ( $flowType == "check_omni_response" ) {
					/*
                     * This part will from now take care of successful orders - the stuff that has been left below is however needed to "finalize"
                     * the payment when the customer is redirected back to the landing page.
                     *
                     * (Finalize in this case is not just Resurs finalization, it's also about completing the order at the WooCom-side)
                     */
					WC()->session->set( 'omniRef', null );
					WC()->session->set( 'omniRefCreated', null );
					WC()->session->set( 'omniRefAge', null );
					WC()->session->set( 'omniId', null );

					$paymentId = isset( $request['payment_id'] ) && ! empty( $request['payment_id'] ) ? $request['payment_id'] : null;
					$order_id  = wc_get_order_id_by_payment_id( $paymentId );
					$order     = new WC_Order( $order_id );

					$storeId = apply_filters( "resursbank_set_storeid", null );
					if ( ! empty( $storeId ) ) {
						update_post_meta( $order_id, 'resursStoreId', $storeId );
					}

					if ( $request['failInProgress'] == "1" || isset( $_REQUEST['failInProgress'] ) && $_REQUEST['failInProgress'] == "1" ) {
						$order->update_status( 'cancelled', __( 'The payment failed during purchase', 'WC_Payment_Gateway' ) );
						wc_add_notice( __( "The purchase from Resurs Bank was by some reason not accepted. Please contact customer services, or try again with another payment method.", 'WC_Payment_Gateway' ), 'error' );
						WC()->session->set( "order_awaiting_payment", true );
						$getRedirectUrl = $woocommerce->cart->get_cart_url();
					} else {
						$optionReduceOrderStock = getResursOption( 'reduceOrderStock' );
						$hasReduceStock         = get_post_meta( $order_id, 'hasReduceStock' );
						// While waiting for the order confirmation from Resurs Bank, reducing stock may be necessary, anyway.
						if ( $optionReduceOrderStock && empty( $hasReduceStock ) ) {
							update_post_meta( $order_id, 'hasReduceStock', time() );
							if ( isWooCommerce3() ) {
								wc_reduce_stock_levels( $order_id );
							} else {
								$order->reduce_order_stock();
							}
						}
						$getRedirectUrl = $this->get_return_url( $order );
						$order->update_status( 'processing', __( 'The payment are signed and booked', 'WC_Payment_Gateway' ) );
						WC()->cart->empty_cart();
					}
					wp_safe_redirect( $getRedirectUrl );

					return;
				}
			}

			if ( $paymentId != $requestedPaymentId && ! $isHostedFlow ) {
				$order->update_status( 'failed' );
				wc_add_notice( __( 'The payment can not complete. Contact customer services for more information.', 'WC_Payment_Gateway' ), 'error' );
			}

			$signedResult = null;
			$bookSigned = false;

			if ( ! $isHostedFlow ) {
				try {
					$signedResult = $this->flow->bookSignedPayment( $paymentId );
					$bookSigned   = true;
				} catch ( Exception $bookSignedException ) {
				}
				if ( $bookSigned ) {
					$bookedStatus = isset($signedResult->bookPaymentStatus) ? $signedResult->bookPaymentStatus : null;
					$bookedPaymentId = isset($signedResult->paymentId) ? $signedResult->paymentId : null;
				}
			}

			if ( ( empty( $bookedPaymentId ) && ! $bookSigned ) && ! $isHostedFlow ) {
				// This is where we land where $bookSigned gets false, normally when there is an exception at the bookSignedPayment level
                // Before leaving this process, we'll check if something went wrong and the booking is already there
				$hasGetPaymentErrors        = false;
				$exceptionMessage           = null;
				$getPaymentExceptionMessage = null;
				$paymentCheck               = null;
				try {
					$paymentCheck = $this->flow->getPayment( $paymentId );
				} catch ( Exception $getPaymentException ) {
					$hasGetPaymentErrors        = true;
					$getPaymentExceptionMessage = $getPaymentException->getMessage();
					die("WTF");
				}
				$paymentIdCheck = isset($paymentCheck->paymentId) ? $paymentCheck->paymentId : null;
				/* If there is a payment, this order has been already got booked */
				if ( ! empty( $paymentIdCheck ) ) {
					wc_add_notice( __( 'The payment already exists', 'WC_Payment_Gateway' ), 'error' );
				} else {
					/* If not, something went wrong further into the processing */
					if ( $hasGetPaymentErrors ) {
						if ( isset( $getPaymentException ) && ! empty( $getPaymentException ) ) {
							//$exceptionMessage = $getPaymentException->getMessage();
							wc_add_notice( __( 'We could not finish your order. Please, contact support for more information.', 'WC_Payment_Gateway' ), 'error' );
						}
						wc_add_notice( $exceptionMessage, 'error' );
					} else {
						wc_add_notice( __( 'An unknown error occured in signing method. Please, try again later', 'WC_Payment_Gateway' ), 'error' );
					}
				}
				/* We should however not return with a success */
				//wp_safe_redirect($this->get_return_url($order));
				wp_safe_redirect( $woocommerce->cart->get_cart_url() );
			}

			try {
				/* So, if we passed through the above control, it's time to check out the status */
				if ( $bookedPaymentId ) {
					update_post_meta( $order_id, 'paymentId', $bookedPaymentId );
				} else {
					/* When things fail, and there is no id available (we should hopefully never get here, since we're making other controls above) */
					$bookedStatus = "DENIED";
				}
				/* Continue. */
				if ( $bookedStatus == 'FROZEN' ) {
					$order->update_status( 'on-hold', __( 'The payment are frozen, while waiting for manual control', 'WC_Payment_Gateway' ) );
				} elseif ( $bookedStatus == 'BOOKED' ) {
					$order->update_status( 'processing', __( 'The payment are signed and booked', 'WC_Payment_Gateway' ) );
				} elseif ( $bookedStatus == 'FINALIZED' ) {
					//define('RB_SYNCHRONOUS_MODE', true);
					WC()->session->set( "order_awaiting_payment", true );
					try {
						$order->set_status( 'completed', __( 'Order is debited and completed', 'WC_Payment_Gateway' ), true );
						$order->save();
					} catch ( \Exception $e ) {
						wc_add_notice( $e->getMessage(), 'error' );
						return;
					}

					$order->update_status( 'completed', __( 'The payment are signed and debited', 'WC_Payment_Gateway' ) );
				} elseif ( $bookedStatus == 'DENIED' ) {
					$order->update_status( 'failed' );
					wc_add_notice( __( 'The payment can not complete. Contact customer services for more information.', 'WC_Payment_Gateway' ), 'error' );
					$getRedirectUrl = $woocommerce->cart->get_cart_url();
				} elseif ( $bookedStatus == 'FAILED' ) {
					$order->update_status( 'failed', __( 'An error occured during the update of the booked payment. The payment id was never received properly in signing response', 'WC_Payment_Gateway' ) );
					wc_add_notice( __( 'An unknown error occured. Please, try again later', 'WC_Payment_Gateway' ), 'error' );
					$getRedirectUrl = $woocommerce->cart->get_cart_url();
				}
			} catch ( Exception $e ) {
				wc_add_notice( __( 'Something went wrong during the signing process.', 'WC_Payment_Gateway' ), 'error' );
				$getRedirectUrl = $woocommerce->cart->get_cart_url();
			}

			$hasAnnulment = get_post_meta( ! isWooCommerce3() ? $order->id : $order->get_id(), "hasAnnulment", true );
			if ( ! $getRedirectUrl || $hasAnnulment == "1" ) {
				$getRedirectUrl = $woocommerce->cart->get_cart_url();
			}

			wp_safe_redirect( $getRedirectUrl );

			return;
		}

		/**
		 * Generate the payment methods that were returned from Resurs Bank API
		 *
		 * @param  array $payment_methods The payment methods
		 */
		public function generate_payment_gateways( $payment_methods ) {
			$methods     = array();
			$class_files = array();
			foreach ( $payment_methods as $payment_method ) {
				$methods[]     = 'resurs-bank-id-' . $payment_method->id;
				$class_files[] = 'resurs_bank_nr_' . $payment_method->id . '.php';
				$this->write_class_to_file( $payment_method );
			}
			$this->UnusedPaymentClassesCleanup( $class_files );
			set_transient( 'resurs_bank_class_files', $class_files );
		}

		/**
		 * Generates and writes a class for a specified payment methods to file
		 *
		 * @param  stdClass $payment_method A payment method return from Resurs Bank API
		 */
		public function write_class_to_file( $payment_method ) {
			write_resurs_class_to_file( $payment_method );
		}

		/**
		 * Validate the payment fields
		 *
		 * Never called from within this class, only by those that extends from this class and that are created in write_class_to_file
         *
		 * @return bool Whether or not the validation passed
		 * @throws Exception
		 */
		public function validate_fields() {
			global $woocommerce;
			$className = $_REQUEST['payment_method'];

			$methodName      = str_replace( 'resurs_bank_nr_', '', $className );
			$transientMethod = $this->getTransientMethod( $methodName );
			$countryCode     = isset( $_REQUEST['billing_country'] ) ? $_REQUEST['billing_country'] : "";
			$customerType    = isset( $_REQUEST['ssnCustomerType'] ) ? $_REQUEST['ssnCustomerType'] : "NATURAL";

			/** @var $flow \Resursbank\RBEcomPHP\ResursBank */
			$flow                = initializeResursFlow();
			$regEx               = $flow->getRegEx( null, $countryCode, $customerType );
			$methodFieldsRequest = $flow->getTemplateFieldsByMethodType( $transientMethod, $customerType );
			$methodFields        = $methodFieldsRequest['fields'];

			$validationFail = false;
			foreach ( $methodFields as $fieldName ) {
				if ( isset( $_REQUEST[ $fieldName ] ) && isset( $regEx[ $fieldName ] ) ) {
					$regExString       = $regEx[ $fieldName ];
					$regExString       = str_replace( '\\\\', '\\', $regExString );
					$fieldData         = isset($_REQUEST[ $fieldName ]) ? trim($_REQUEST[ $fieldName ]) : "";
					$invalidFieldError = __( 'The field', 'WC_Payment_Gateway' ) . " " . $fieldName . " " . __( 'has invalid information', 'WC_Payment_Gateway' ) . " (" . ( ! empty( $fieldData ) ? $fieldData : __( "It can't be empty", 'WC_Payment_Gateway' ) ) . ")";
					if ($fieldName == "card-number" && empty($fieldData)) {
					    continue;
                    }
					if ( preg_match( "/email/", $fieldName ) ) {
						if ( ! filter_var( $_REQUEST[ $fieldName ], FILTER_VALIDATE_EMAIL ) ) {
							wc_add_notice( $invalidFieldError, 'error' );
						}
					} else {
						if ( ! preg_match( '/' . $regExString . '/', $_REQUEST[ $fieldName ] ) ) {
							wc_add_notice( $invalidFieldError, 'error' );
							$validationFail = true;
						}
					}
				}
			}
			if ( $validationFail ) {
				return false;
			}

			return true;
		}

		/**
		 * @return bool
		 */
		function is_valid_for_use() {
			return true;
		}

		/**
		 * Retrieves the best guess of the client's actual IP address.
		 * Takes into account numerous HTTP proxy headers due to variations
		 * in how different ISPs handle IP addresses in headers between hops.
		 *
		 * Developer note 2016-03-02
		 *
		 * Since proxy based headers, that is normally sent by web-browsers or proxy engines, can be manipulated/spoofed,
		 * we are not accepting manipulated headers of this type, by default.
		 *
		 * Therefore, we're not accepting headers that could be manipulated by default.
		 */
		public static function get_ip_address() {
			$handleNatConnections = getResursOption( 'handleNatConnections' );
			if ( $handleNatConnections ) {
				// check for shared internet/ISP IP
				if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) && self::validate_ip( $_SERVER['HTTP_CLIENT_IP'] ) ) {
					return $_SERVER['HTTP_CLIENT_IP'];
				}
				// check for IPs passing through proxies
				if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
					// check if multiple ips exist in var
					$iplist = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
					foreach ( $iplist as $ip ) {
						if ( self::validate_ip( $ip ) ) {
							return $ip;
						}
					}
				}
				if ( ! empty( $_SERVER['HTTP_X_FORWARDED'] ) && self::validate_ip( $_SERVER['HTTP_X_FORWARDED'] ) ) {
					return $_SERVER['HTTP_X_FORWARDED'];
				}
				if ( ! empty( $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'] ) && self::validate_ip( $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'] ) ) {
					return $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
				}
				if ( ! empty( $_SERVER['HTTP_FORWARDED_FOR'] ) && self::validate_ip( $_SERVER['HTTP_FORWARDED_FOR'] ) ) {
					return $_SERVER['HTTP_FORWARDED_FOR'];
				}
				if ( ! empty( $_SERVER['HTTP_FORWARDED'] ) && self::validate_ip( $_SERVER['HTTP_FORWARDED'] ) ) {
					return $_SERVER['HTTP_FORWARDED'];
				}
			}

			// return unreliable ip since all else failed
			return $_SERVER['REMOTE_ADDR'];
		}

		/** @var string $ip Access to undeclared static property fix */
		private static $ip;

		/**
		 * Ensures an ip address is both a valid IP and does not fall within
		 * a private network range.
		 * @access public
		 *
		 * @param string $ip
		 *
		 * @return bool
		 */
		public static function validate_ip( $ip ) {
			if ( filter_var( $ip, FILTER_VALIDATE_IP,
					FILTER_FLAG_IPV4 |
					FILTER_FLAG_IPV6 |
					FILTER_FLAG_NO_PRIV_RANGE |
					FILTER_FLAG_NO_RES_RANGE ) === false
			) {
				return false;
			}

			self::$ip = $ip;

			return true;
		}

		/**
		 * Output the admin options for the plugin. Also used for checking for various buttonclicks, for example registering callbacks
		 */
		public function admin_options() {
			$_REQUEST['tab']     = "tab_resursbank";
			$_REQUEST['section'] = "";
			$url                 = admin_url( 'admin.php' );
			$url                 = add_query_arg( 'page', $_REQUEST['page'], $url );
			$url                 = add_query_arg( 'tab', $_REQUEST['tab'], $url );
			$url                 = add_query_arg( 'section', $_REQUEST['section'], $url );
			wp_safe_redirect( $url );
			die( "Deprecated space" );
		}

		/**
		 * @param $temp_class_files
		 */
		private function UnusedPaymentClassesCleanup( $temp_class_files ) {
			$allIncludes = array();
			$path        = plugin_dir_path( __FILE__ ) . 'includes/';
			$globIncludes = glob( plugin_dir_path( __FILE__ ) . 'includes/*.php' );
			if (is_array($globIncludes)) {
				foreach ( $globIncludes as $filename ) {
					$allIncludes[] = str_replace( $path, '', $filename );
				}
			}
			if ( is_array( $temp_class_files ) ) {
				foreach ( $allIncludes as $exclude ) {
					if ( ! in_array( $exclude, $temp_class_files ) ) {
						@unlink( $path . $exclude );
					}
				}
			}
		}

		/**
		 * Get available payment methods. Either from Resurs Bank API or transient cache
		 *
		 * @param bool $force_file_refresh If new files should be forced or not
		 * @param bool $skipGateway Set to true if you want to skip the gateway generator (normally, you want this while listing methods in a checkout, not else)
		 *
		 * @return array Array containing an error message, if any errors occurred, and the payment methods, if any available and no errors occurred.
		 * @throws Exception
		 */
		public function get_payment_methods( $force_file_refresh = false, $skipGateway = false ) {
			$returnArr = array();
			try {
				$paymentMethods = $this->flow->getPaymentMethods();
				if ( ! $skipGateway ) {
					$this->generate_payment_gateways( $paymentMethods );
				}
				/*
                 *  This is normally wanted by some parts of the system
                 */
				set_transient( 'resurs_bank_payment_methods', $paymentMethods );
				$returnArr['error']              = '';
				$returnArr['methods']            = $paymentMethods;
				$returnArr['generate_new_files'] = true;
			} catch ( Exception $e ) {
				$returnArr['error']              = $e->getMessage();
				$returnArr['methods']            = '';
				$returnArr['generate_new_files'] = false;
			}

			return $returnArr;
		}

		/**
		 * Get address for a specific government ID
		 *
		 * @return void Prints the address data as JSON
		 * @throws Exception
		 */
		public static function get_address_ajax() {
			$results = array();
			if ( isset( $_REQUEST ) && 'SE' == getResursOption('country') ) {
                $customerType = isset( $_REQUEST['customerType'] ) ? ( $_REQUEST['customerType'] != 'LEGAL' ? 'NATURAL' : 'LEGAL' ) : 'NATURAL';

				$serverEnv = getResursOption( "serverEnv" );
				/*
                 * Overriding settings here, if we want getAddress picked from production instead of test.
                 * The only requirement for this to work is that we are running in test and credentials for production is set.
                 */
				$userProd                = getResursOption( "ga_login" );
				$passProd                = getResursOption( "ga_password" );
				$getAddressUseProduction = getResursOption( "getAddressUseProduction" );
				$disabledProdTests       = true;      // TODO: Set this to false in future, when we're ready again (https://resursbankplugins.atlassian.net/browse/WOO-44)
				if ( $getAddressUseProduction && isResursDemo() && $serverEnv == "test" && ! empty( $userProd ) && ! empty( $passProd ) && ! $disabledProdTests ) {
					$results = getAddressProd( $_REQUEST['ssn'], $customerType, self::get_ip_address() );
				} else {
					/** @var \Resursbank\RBEcomPHP\ResursBank */
					$flow = initializeResursFlow();
					try {
						$results = $flow->getAddress( $_REQUEST['ssn'], $customerType, self::get_ip_address() );
					} catch ( Exception $e ) {
						$results = array( "error" => __( 'Can not get the address from current government ID', 'WC_Payment_Gateway' ) );
					}
				}
			}
			header( "Content-type: application/json; charset=utf-8" );
			echo json_encode( $results );
			die();
		}

		public static function get_cost_ajax() {
			global $styles;
			require_once( 'resursbankgateway.php' );
			$costOfPurchaseHtml = "";
			/** @var $flow \Resursbank\RBEcomPHP\ResursBank */
			$flow               = initializeResursFlow();
			$method             = $_REQUEST['method'];
			$amount             = floatval( $_REQUEST['amount'] );

			$wooCommerceStyle = realpath( get_stylesheet_directory() ) . "/css/woocommerce.css";
			$styles           = array();

			$costOfPurchaseCss = getResursOption( 'costOfPurchaseCss' );
			if ( empty( $costOfPurchaseCss ) ) {
				if ( file_exists( $wooCommerceStyle ) ) {
					$styles[] = get_stylesheet_directory_uri() . "/css/woocommerce.css";
				}
				/**
				 * Try to find out if there is a costofpurchase-file defaulting to our plugin
				 */
				$cssPathFile              = dirname( __FILE__ ) . '/css/costofpurchase.css';
				$costOfPurchaseCssDefault = plugin_dir_url( __FILE__ ) . 'css/costofpurchase.css';
				/**
				 * Make sure it exists and if so, add it to the styles and the viewport.
				 */
				if ( file_exists( $cssPathFile ) ) {
					$styles[]          = plugin_dir_url( __FILE__ ) . 'css/costofpurchase.css';
					$costOfPurchaseCss = $costOfPurchaseCssDefault;
				}
			}

			try {
				$htmlBefore = '<div class="cost-of-purchase-box"><a class="woocommerce button" onclick="window.close()" href="javascript:void(0);">' . __( 'Close', 'WC_Payment_Gateway' ) . '</a>';
				$htmlAfter  = '</div>';

				$flow->setCostOfPurcaseHtmlBefore( $htmlBefore );
				$flow->setCostOfPurcaseHtmlAfter( $htmlAfter );

				/**
				 * Fix for issue #66520, where the CSS pointer has not been added properly to our default location.
				 */
				$costOfPurchaseHtml = $flow->getCostOfPurchase( $method, $amount, true, $costOfPurchaseCss, "_blank" );
			} catch ( Exception $e ) {
			}
			echo $costOfPurchaseHtml;
			die();
		}

		/**
		 * Get information about selected payment method in checkout, to control the method listing
		 */
		public static function get_address_customertype() {
			/** @var $flow \Resursbank\RBEcomPHP\ResursBank */
			$flow                = initializeResursFlow();
			$methodsHasErrors    = false;
			$methodsErrorMessage = null;
			$paymentMethods = null;

			$resursTemporaryPaymentMethodsTime = get_transient("resursTemporaryPaymentMethodsTime");
			$timeDiff = time() - $resursTemporaryPaymentMethodsTime;
			try {
				if ($timeDiff >= 3600) {
					$paymentMethods = $flow->getPaymentMethods();
					set_transient("resursTemporaryPaymentMethodsTime", time(), 3600);
					set_transient("resursTemporaryPaymentMethods", serialize($paymentMethods), 3600);
				} else {
					$paymentMethods = unserialize(get_transient("resursTemporaryPaymentMethods"));
				}
			} catch ( Exception $e ) {
				$methodsHasErrors    = true;
				$methodsErrorMessage = $e->getMessage();
			}
			$requestedCustomerType = isset( $_REQUEST['customerType'] ) ? $_REQUEST['customerType'] : "NATURAL";
			$responseArray         = array(
				'natural' => array(),
				'legal'   => array()
			);

			if ( is_array( $paymentMethods ) ) {
				foreach ( $paymentMethods as $objId ) {
					if ( isset( $objId->id ) && isset( $objId->customerType ) ) {
						$nr = "resurs_bank_nr_" . $objId->id;
						if ( ! is_array( $objId->customerType ) ) {
							$responseArray[ strtolower( $objId->customerType ) ][] = $nr;
						} else {
							foreach ( $objId->customerType as $customerType ) {
								$responseArray[ strtolower( $customerType ) ][] = $nr;
							}
						}
					}
				}
			}

			if ( $methodsHasErrors ) {
				$responseArray = array(
					'errorstring' => $methodsErrorMessage
				);
			}

			header( 'Content-Type: application/json' );
			print( json_encode( $responseArray ) );
			die();
		}

		/**
		 * Get the plugin url
		 *
		 * @return string
		 */
		public function plugin_url() {
			return untrailingslashit( plugins_url( '/', __FILE__ ) );
		}

		/**
		 * Get the plugin url
		 *
		 * @return string
		 */
		public static function plugin_url_static() {
			return untrailingslashit( plugins_url( '/', __FILE__ ) );
		}

		/**
		 * Called when the status of an order is changed
		 *
		 * @param  int $order_id The order id
		 * @param  string $old_status_slug The old status
		 * @param  string $new_status_slug The new stauts
		 */
		public static function order_status_changed( $order_id, $old_status_slug, $new_status_slug ) {
			global $woocommerce, $current_user;

			if (defined('RB_SYNCHRONOUS_MODE')) {
				return;
			}

			$order = new WC_Order( $order_id );
			if ( ! isWooCommerce3() ) {
				$payment_method = $order->payment_method;
			} else {
				$payment_method = $order->get_payment_method();
			}

			$payment_id = get_post_meta( ! isWooCommerce3() ? $order->id : $order->get_id(), 'paymentId', true );
			if ( false === (boolean) preg_match( '/resurs_bank/', $payment_method ) ) {
				return;
			}

			if ( isset( $_REQUEST['wc-api'] ) || isset( $_REQUEST['cancel_order'] ) ) {
				return;
			}

			$url              = admin_url( 'post.php' );
			$url              = add_query_arg( 'post', $order_id, $url );
			$url              = add_query_arg( 'action', 'edit', $url );
			$old_status       = get_term_by( 'slug', sanitize_title( $old_status_slug ), 'shop_order_status' );
			$new_status       = get_term_by( 'slug', sanitize_title( $new_status_slug ), 'shop_order_status' );
			$order_total      = $order->get_total();
			$order_fees       = $order->get_fees();

			/** @var $resursFlow \Resursbank\RBEcomPHP\ResursBank */
			$resursFlow       = initializeResursFlow();
			$flowErrorMessage = null;

			if ( $payment_id ) {
				try {
					$payment = $resursFlow->getPayment( $payment_id );
				} catch ( \Exception $getPaymentException ) {
					return;
				}
				if ( isset( $payment ) ) {
					if ( false === is_array( $payment->status ) ) {
						$status = array( $payment->status );
					} else {
						$status = $payment->status;
					}
				} else {
					return;
				}
			} else {
				// No payment id, no Resurs handling
				return;
			}

			switch ( $old_status_slug ) {
				case 'pending':
					break;
				case 'failed':
					break;
				case 'processing':
					break;
				case 'completed':
					break;
				case 'on-hold':
					break;
				case 'cancelled':
					if ( in_array( 'IS_ANNULLED', $status ) ) {
						$_SESSION['resurs_bank_admin_notice'] = array(
							'type'    => 'error',
							'message' => 'Denna order är annulerad och går därmed ej att ändra status på',
						);

						wp_set_object_terms( $order_id, array( $old_status->slug ), 'shop_order_status', false );
						wp_safe_redirect( $url );
						exit;
					}
					break;
				case 'refunded':
					if ( in_array( 'IS_CREDITED', $status ) ) {
						$_SESSION['resurs_bank_admin_notice'] = array(
							'type'    => 'error',
							'message' => 'Denna order är krediterad och går därmed ej att ändra status på',
						);

						wp_set_object_terms( $order_id, array( $old_status->slug ), 'shop_order_status', false );
						wp_safe_redirect( $url );
						exit;
					}
					break;
				default:
					break;
			}

			switch ( $new_status_slug ) {
				case 'pending':
					break;
				case 'failed':
					break;
				case 'processing':
					break;
				case 'completed':
					/*$optionDisableAftershop = getResursOption( "disableAftershopFunctions" );
					if ( $optionDisableAftershop ) {
						break;
					}*/
					$flowCode         = 0;
					$flowErrorMessage = "";
					if ( $resursFlow->canDebit( $payment ) ) {
						try {
							$resursFlow->paymentFinalize( $payment_id );
							wp_set_object_terms( $order_id, array( $old_status_slug ), 'shop_order_status', false );
						} catch ( Exception $e ) {
							$flowCode         = $e->getCode();
							if ($flowCode == 29) {
							    // If the internal error code is 29 (ALREADY_EXISTS_INVOICE_ID, ref: https://test.resurs.com/docs/x/jgEF), try to repair the problem
                                // that cuases this.
							    $resursFlow->getNextInvoiceNumberByDebits();
                            }
							$flowErrorMessage = "[".__('Error', 'WC_Payment_Gateway') . " " . $flowCode . "] " . $e->getMessage();
							$order->update_status( $old_status_slug );
							$order->add_order_note( __( 'Finalization failed', 'WC_Payment_Gateway' ) . ": " . $flowErrorMessage );
						}
					} else {
						// Generate a notice if the order has been debited from for example payment admin.
						// This notice requires that an order is not debitable (if it is, there's more to debit anyway, so in that case the above finalization event will occur)
						if ( $resursFlow->getIsDebited() ) {
							$order->add_order_note( __( 'This order has already been finalized externally', 'WC_Payment_Gateway' ) );
						} else {
							// Generate error message if the order is something else than debited and debitable
							$orderNote = __( 'This order is in a state at Resurs Bank where it can not be finalized', 'WC_Payment_Gateway' );
							$order->add_order_note( $orderNote );
							$flowErrorMessage = $orderNote;
						}
					}
					if ( ! empty( $flowErrorMessage ) ) {
						$_SESSION['resurs_bank_admin_notice'] = array(
							'type'    => 'error',
							'message' => $flowErrorMessage
						);
					}
					wp_safe_redirect( $url );
					break;
				case 'on-hold':
					break;
				case 'cancelled':
					try {
						$resursFlow->paymentCancel( $payment_id );
						$order->add_order_note( __( 'Cancelled status set: Resurs Bank API was called for cancellation', 'WC_Payment_Gateway' ) );
					} catch ( Exception $e ) {
						$flowErrorMessage = $e->getMessage();
					}
					if ( null !== $flowErrorMessage ) {
						$_SESSION['resurs_bank_admin_notice'] = array(
							'type'    => 'error',
							'message' => $flowErrorMessage
						);
						wp_set_object_terms( $order_id, array( $old_status_slug ), 'shop_order_status', false );
						wp_safe_redirect( $url );
					}
					break;
				case 'refunded':
					try {
						$resursFlow->paymentCancel( $payment_id );
						$order->add_order_note( __( 'Refunded status set: Resurs Bank API was called for cancellation', 'WC_Payment_Gateway' ) );
					} catch ( Exception $e ) {
						$flowErrorMessage = $e->getMessage();
					}
					if ( null !== $flowErrorMessage ) {
						$_SESSION['resurs_bank_admin_notice'] = array(
							'type'    => 'error',
							'message' => $flowErrorMessage
						);
						wp_set_object_terms( $order_id, array( $old_status_slug ), 'shop_order_status', false );
						wp_safe_redirect( $url );
					}
					break;
				default:
					break;
			}

			return;
		}
		// Class ends here
	}

	/**
	 * Adds the SSN field to the checkout form for fetching a address
	 *
	 * @param  WC_Checkout $checkout The WooCommerce checkout object
	 *
	 * @return WC_Checkout           The WooCommerce checkout object
	 */
	function add_ssn_checkout_field( $checkout ) {
		if ( ! getResursOption('enabled') ) {
			return $checkout;
		}

		$selectedCountry  = getResursOption( "country" );
		$optionGetAddress = getResursOption( "getAddress" );
		$private          = __( 'Private', 'WC_Payment_Gateway' );
		$company          = __( 'Company', 'WC_Payment_Gateway' );
		if ( $optionGetAddress && ! isResursOmni() ) {
			/*
             * MarGul change
             * If it's demoshop get the translation.
             */
			if ( isResursDemo() && class_exists( 'CountryHandler' ) ) {
				$translation = CountryHandler::getDictionary();
				$private     = $translation['private'];
				$company     = $translation['company'];
			}
			// Here we use the translated or not translated values for Private and Company radiobuttons
			$resursTemporaryPaymentMethodsTime = get_transient("resursTemporaryPaymentMethodsTime");
			$timeDiff = time() - $resursTemporaryPaymentMethodsTime;
			if ( $timeDiff >= 3600 ) {
                /** @var $theFlow \Resursbank\RBEcomPHP\ResursBank */
                $theFlow = initializeResursFlow();
                $methodList = $theFlow->getPaymentMethods();
				set_transient( "resursTemporaryPaymentMethodsTime", time(), 3600 );
				set_transient( "resursTemporaryPaymentMethods", serialize( $methodList ), 3600 );
			} else {
				$methodList = unserialize( get_transient( "resursTemporaryPaymentMethods" ) );
			}
			$naturalCount = 0;
			$legalCount = 0;
			if (is_array($methodList)) {
			    foreach ($methodList as $method) {
                    $customerType = $method->customerType;
                    if (is_array($customerType)) {
                        if (in_array("NATURAL",$customerType)) {
                            $naturalCount++;
                        }
                        if (in_array("LEGAL",$customerType)) {
                            $legalCount++;
                        }
                    } else {
                        if ($customerType == "NATURAL") {
                            $naturalCount++;
                        }
                        if ($customerType == "LEGAL") {
                            $legalCount++;
                        }
                    }
                }
            }

            $viewNatural = "display:;";
            $viewLegal = "display:;";
            if ($naturalCount > 0 && !$legalCount) {
                $viewNatural = "display: none;";
            }
            if (!$naturalCount && $legalCount) {
                $viewLegal = "display: none;";
            }

            if ($naturalCount) {echo '<span id="ssnCustomerRadioNATURAL" style="'.$viewNatural.'"><input type="radio" id="ssnCustomerType" onclick="getMethodType(\'natural\')" checked="checked" name="ssnCustomerType" value="NATURAL"> ' . $private . "</span> ";}
			if ($legalCount) {echo '<span id="ssnCustomerRadioLEGAL" style="'.$viewLegal.'"><input type="radio" id="ssnCustomerType" onclick="getMethodType(\'legal\')" name="ssnCustomerType" value="LEGAL"> ' . $company . "</span>";}
			echo '<input type="hidden" id="resursSelectedCountry" value="' . $selectedCountry . '">';
			woocommerce_form_field( 'ssn_field', array(
				'type'        => 'text',
				'class'       => array( 'ssn form-row-wide resurs_ssn_field' ),
				'label'       => __( 'Government ID', 'WC_Payment_Gateway' ),
				'placeholder' => __( 'Enter your government id (social security number)', 'WC_Payment_Gateway' ),
			), $checkout->get_value( 'ssn_field' ) );
			if ( 'SE' == $selectedCountry ) {
				/*
                 * MarGul change
                 * Take the translation for Get Address.
                 */
				if ( class_exists( 'CountryHandler' ) ) {
					$translation = CountryHandler::getDictionary();
				} else {
					$translation = array();
				}
				$get_address = ( ! empty( $translation ) ) ? $translation['get_address'] : __( 'Get address', 'WC_Payment_Gateway' );
				echo '<a href="#" class="button" id="fetch_address">' . $get_address . '</a> <span id="fetch_address_status" style="display: none;"><img src="'.plugin_dir_url( __FILE__ ) . "loader.gif".'" border="0"></span>
                <br>';
			}
		}

		return $checkout;
	}

	/**
	 * Adds Resurs Bank javascript file
	 *
	 * @return null Returns null if Resurs Bank plugin is not enabled
	 */
	function enqueue_script() {
		if ( ! getResursOption( 'enabled' ) ) {
			return;
		}
		$OmniVars = array();
		if ( isResursOmni() ) {
			$omniRefAge = null;
			wp_enqueue_script( 'resursomni', plugin_dir_url( __FILE__ ) . 'js/omnicheckout.js', array(), RB_WOO_VERSION . ( defined( 'RB_ALWAYS_RELOAD_JS' ) && RB_ALWAYS_RELOAD_JS === true ? "-" . time() : "" ) );
			$omniBookUrl   = home_url( '/' );
			$omniBookUrl   = add_query_arg( 'wc-api', 'WC_Resurs_Bank', $omniBookUrl );
			$omniBookUrl   = add_query_arg( 'event-type', 'prepare-omni-order', $omniBookUrl );
			$omniBookUrl   = add_query_arg( 'set-no-session', '1', $omniBookUrl );
			$omniBookNonce = wp_nonce_url( $omniBookUrl, "omnicheckout", "omnicheckout_nonce" );

			/** @var $flow Resursbank\RBEcomPHP\ResursBank */
			$flow    = initializeResursFlow();
			$sEnv    = getServerEnv();
			$OmniUrl = $flow->getCheckoutUrl( $sEnv );

			$isWooSession = false;
			if ( isset( WC()->session ) ) {
				$isWooSession = true;
			}
			if ( $isWooSession ) {
				$omniRef        = WC()->session->get( 'omniRef' );
				$omniRefCreated = WC()->session->get( 'omniRefCreated' );
				$omniRefAge     = intval( WC()->session->get( 'omniRefAge' ) );
			}

			$gateways = WC()->payment_gateways()->get_available_payment_gateways();

			$OmniVars = array(
				'RESURSCHECKOUT_IFRAME_URL'            => $OmniUrl,
				'RESURSCHECKOUT'                       => home_url(),
				'OmniPreBookUrl'                       => $omniBookNonce,
				'OmniRef'                              => isset( $omniRef ) && ! empty( $omniRef ) ? $omniRef : null,
				'OmniRefCreated'                       => isset( $omniRefCreated ) && ! empty( $omniRefCreated ) ? $omniRefCreated : null,
				'OmniRefAge'                           => $omniRefAge,
				'isResursTest'                         => isResursTest(),
				'iframeShape'                          => getResursOption( "iframeShape", "woocommerce_resurs_bank_omnicheckout_settings" ),
				'useStandardFieldsForShipping'         => getResursOption( "useStandardFieldsForShipping", "woocommerce_resurs_bank_omnicheckout_settings" ),
				'showResursCheckoutStandardFieldsTest' => getResursOption( "showResursCheckoutStandardFieldsTest" ),
				'gatewayCount'                         => (is_array($gateways) ? count( $gateways ) : 0),
				'postidreference'                      => getResursOption( "postidreference" )
			);
			$setSessionEnable = true;
			$setSession       = isset( $_REQUEST['set-no-session'] ) ? $_REQUEST['set-no-session'] : null;
			if ( $setSession == 1 ) {
				$setSessionEnable = false;
			} else {
				$setSessionEnable = true;
			}

			// During the creation of new omnivars, make sure they are not duplicates from older orders.
			if ( $setSessionEnable && function_exists( 'WC' ) && $isWooSession ) {
				$currentOmniRef = WC()->session->get( 'omniRef' );
				// The resursCreatePass variable is only set when everything was successful.
				$resursCreatePass = WC()->session->get( 'resursCreatePass' );
				$orderControl     = wc_get_order_id_by_payment_id( $currentOmniRef );
				if ( ! empty( $orderControl ) && ! empty( $currentOmniRef ) ) {
					$checkOrder = new WC_Order( $orderControl );
					// currentOrderStatus checks what status the order had when created
					$currentOrderStatus  = $checkOrder->get_status();
					$preventCleanup      = array(
						'pending',
						'failed'
					);
					$allowCleanupSession = false;
					if ( ! in_array( $currentOrderStatus, $preventCleanup ) ) {
						$allowCleanupSession = true;
					}
					if ( ( $resursCreatePass && $currentOmniRef ) || ( $allowCleanupSession ) ) {
						$refreshUrl  = wc_get_cart_url();
						$thisSession = new WC_Session_Handler();
						$thisSession->destroy_session();
						$thisSession->cleanup_sessions();
						wp_destroy_all_sessions();
						wp_safe_redirect( $refreshUrl );
					}
				}
			}
		}

		$resursLanguageLocalization = array(
			'getAddressEnterGovernmentId' => __( 'Enter social security number', 'WC_Payment_Gateway' ),
			'getAddressEnterCompany'      => __( 'Enter corporate government identity', 'WC_Payment_Gateway' ),
			'labelGovernmentId'           => __( 'Government id', 'WC_Payment_Gateway' ),
			'labelCompanyId'              => __( 'Corporate government id', 'WC_Payment_Gateway' ),
		);

		// Country language overrider - MarGul
		if ( isResursDemo() && class_exists( 'CountryHandler' ) ) {
			$translation                = CountryHandler::getDictionary();
			$resursLanguageLocalization = [
				'getAddressEnterGovernmentId' => __( $translation['enter_ssn_num'], 'WC_Payment_Gateway' ),
				'getAddressEnterCompany'      => __( $translation['enter_gov_id'], 'WC_Payment_Gateway' ),
				'labelGovernmentId'           => __( $translation['gov_id'], 'WC_Payment_Gateway' ),
				'labelCompanyId'              => __( $translation['corp_gov_id'], 'WC_Payment_Gateway' ),
			];
		}

		$generalJsTranslations = array(
			'deliveryRequiresSigning'         => __( "Changing delivery address requires signing", 'WC_Payment_Gateway' ),
			'ssnElementMissing'               => __( "I can not show errors since the element is missing", 'WC_Payment_Gateway' ),
			'purchaseAjaxInternalFailure'     => __( "The purchase has failed, due to an internal server error: The shop could not properly update the order.", 'WC_Payment_Gateway' ),
			'updatePaymentReferenceFailure'   => __( "The purchase was processed, but the payment reference failed to update", 'WC_Payment_Gateway' ),
			'resursPurchaseNotAccepted'       => __( "The purchase was rejected by Resurs Bank. Please contact customer services, or try again with another payment method.", 'WC_Payment_Gateway' ),
			'theAjaxWasNotAccepted'           => __( "Something went wrong when we tried to book your order. Please contact customer support for more information.", 'WC_Payment_Gateway' ),
			'theAjaxWentWrong'                => __( "An internal error occured while trying to book the order. Please contact customer support for more information.", 'WC_Payment_Gateway' ),
			'theAjaxWentWrongWithThisMessage' => __( "An internal error occured while trying to book the order:", 'WC_Payment_Gateway' ) . " ",
			'contactSupport'                  => __( "Please contact customer support for more information.", 'WC_Payment_Gateway' )
		);

		$oneRandomValue     = null;
		$randomizeJsLoaders = getResursOption( "randomizeJsLoaders" );
		if ( $randomizeJsLoaders ) {
			$oneRandomValue = "?randomizeMe=" . rand( 1024, 65535 );
		}
		$ajaxObject = array( 'ajax_url' => admin_url( 'admin-ajax.php' ) );
		wp_enqueue_style( 'resursInternal', plugin_dir_url( __FILE__ ) . 'css/resursinternal.css', array(), RB_WOO_VERSION . ( defined( 'RB_ALWAYS_RELOAD_JS' ) && RB_ALWAYS_RELOAD_JS === true ? "-" . time() : "" ) );
		wp_enqueue_script( 'resursbankmain', plugin_dir_url( __FILE__ ) . 'js/resursbank.js' . $oneRandomValue, array( 'jquery' ), RB_WOO_VERSION . ( defined( 'RB_ALWAYS_RELOAD_JS' ) && RB_ALWAYS_RELOAD_JS === true ? "-" . time() : "" ) );
		wp_localize_script( 'resursbankmain', 'rb_getaddress_fields', $resursLanguageLocalization );
		wp_localize_script( 'resursbankmain', 'rb_general_translations', $generalJsTranslations );
		wp_localize_script( 'resursbankmain', 'ajax_object', $ajaxObject );
		wp_localize_script( 'resursbankmain', 'omnivars', $OmniVars );
	}

	/**
	 * Adds Javascript to the Resurs Bank Payment Gateway settings panel
	 *
	 * @param string $hook The current page
	 *
	 * @return null        Returns null current page is not correct
	 */
	function admin_enqueue_script( $hook ) {
		$images                     = plugin_dir_url( __FILE__ ) . "img/";
        $resursLogo = $images . "resurs-standard.png";

		wp_enqueue_style( 'resursInternal', plugin_dir_url( __FILE__ ) . 'css/resursinternal.css', array(), RB_WOO_VERSION . ( defined( 'RB_ALWAYS_RELOAD_JS' ) && RB_ALWAYS_RELOAD_JS === true ? "-" . time() : "" ) );
		wp_enqueue_script( 'resursBankAdminScript', plugin_dir_url( __FILE__ ) . 'js/resursbankadmin.js', array(), RB_WOO_VERSION . ( defined( 'RB_ALWAYS_RELOAD_JS' ) && RB_ALWAYS_RELOAD_JS === true ? "-" . time() : "" ) );

		$requestForCallbacks = callbackUpdateRequest();

		$callbackUriCacheTime = get_transient( "resurs_callback_templates_cache_last" );
		$lastFetchedCacheTime = $callbackUriCacheTime > 0 ? strftime( "%Y-%m-%d, %H:%M", $callbackUriCacheTime ) : "";

		$adminJs = array(
			'resursSpinner'          => plugin_dir_url( __FILE__ ) . "loader.gif",
			'resursSpinnerLocal'     => plugin_dir_url( __FILE__ ) . "loaderLocal.gif",
			'callbackUrisCache'      => __( 'The list of urls below is cached from an earlier response from Resurs Bank', 'WC_Payment_Gateway' ),
			'callbackUrisCacheTime'  => $lastFetchedCacheTime,
			'callbacks_registered'   => __( 'callbacks has been registered', 'WC_Payment_Gateway' ),
			'update_callbacks'       => __( 'Update callbacks again', 'WC_Payment_Gateway' ),
			'requestForCallbacks'    => $requestForCallbacks,
			'noCallbacksSet'         => __( 'No registered callbacks could be found', 'WC_Payment_Gateway' ),
			'annulCantBeAlone'       => __( 'This setting requires waitForFraudControl to be active', 'WC_Payment_Gateway' ),
			'couldNotSetNewFee'      => __( 'Unable to set new fee', 'WC_Payment_Gateway' ),
			'newFeeHasBeenSet'       => __( 'Fee has been saved', 'WC_Payment_Gateway' ),
			'callbacks_pending'      => __( 'Waiting for callback', 'WC_Payment_Gateway' ),
			'callbacks_not_received' => __( 'Callback not yet received', 'WC_Payment_Gateway' ),
			'callbacks_slow'         => nl2br( __( 'It seems that your site has not received any callbacks yet.\nEither your site are unreachable, or the callback tester is for the moment slow.', 'WC_Payment_Gateway' ) ),
			'resursBankTabLogo'      => $resursLogo
		);
		wp_localize_script( 'resursBankAdminScript', 'adminJs', $adminJs );
		$configUrl = home_url( "/" );
		$configUrl = add_query_arg( 'event-type', 'noevent', $configUrl );
		$configUrl = add_query_arg( 'wc-api', 'WC_Resurs_Bank', $configUrl );
		$adminAjax = array(
			'ran' => wp_nonce_url( $configUrl, "requestResursAdmin", 'ran' )
		);
		wp_localize_script( 'resursBankAdminScript', 'rbAjaxSetup', $adminAjax );

		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}
		if ( version_compare( PHP_VERSION, '5.4.0', '<' ) ) {
			$_SESSION['resurs_bank_admin_notice']['message'] = __( 'The Resurs Bank Addon for WooCommerce may not work properly in PHP 5.3 or older. You should consider upgrading to 5.4 or higher.', 'WC_Payment_Gateway' );
			$_SESSION['resurs_bank_admin_notice']['type']    = 'resurswoo_phpversion_deprecated';
		}

		if ( ! isset( $_REQUEST['section'] ) ) {
			return;
		}
		if ( 'wc_resurs_bank' !== $_REQUEST['section'] ) {
			return;
		}
	}

	/**
	 * Start session on Wordpress init
	 */
	function start_session() {
		if ( ! session_id() ) {
			session_start();
		}
	}

	/**
	 * End session on Wordpress login and logout
	 */
	function end_session() {
		session_destroy();
	}

	/**
	 * Used to enable wp_safe_redirect in ceratin situations
	 */
	function app_output_buffer() {
		if ( isset( $_REQUEST['woocommerce_resurs-bank_refreshPaymentMethods'] ) || isset( $_REQUEST['second_update_status'] ) || isset( $_REQUEST['save'] ) || isset( $_SESSION ) ) {
			ob_start();
		}
	}

	/**
	 * Used to output a notice to the admin interface
	 */
	function resurs_bank_admin_notice() {
		global $resursGlobalNotice, $resursSelfSession;
		if ( isset( $_SESSION['resurs_bank_admin_notice'] ) || $resursGlobalNotice === true ) {
			if ( is_array( $_SESSION ) ) {
				if ( ! count( $_SESSION ) && count( $resursSelfSession ) ) {
					$_SESSION = $resursSelfSession;
				}
				$notice = '<div class=' . $_SESSION['resurs_bank_admin_notice']['type'] . '>';
				$notice .= '<p>' . $_SESSION['resurs_bank_admin_notice']['message'] . '</p>';
				$notice .= '</div>';
				echo $notice;
				unset( $_SESSION['resurs_bank_admin_notice'] );
			}
		}
	}

	function test_before_shipping() {
	}

	// If glob returns null (error) nothing should run
	$incGlob = glob( plugin_dir_path( __FILE__ ) . '/includes/*.php' );
	if (is_array($incGlob)) {
		foreach ( $incGlob as $filename ) {
			if ( ! in_array( $filename, get_included_files() ) ) {
				include $filename;
			}
		}
	}
	$staticGlob = glob( plugin_dir_path( __FILE__ ) . '/staticflows/*.php' );
	if (is_array($staticGlob)) {
		foreach ( $staticGlob as $filename ) {
			if ( ! in_array( $filename, get_included_files() ) ) {
				include $filename;
			}
		}
	}

	function rb_settings_pages( $settings ) {
		$settings[] = include( plugin_dir_path( __FILE__ ) . "/resursbank_settings.php" );
		return $settings;
	}


	/* Payment gateway stuff */

	/**
	 * Add the Gateway to WooCommerce
	 *
	 * @param  array $methods The available payment methods
	 *
	 * @return array          The available payment methods
	 */
	function woocommerce_add_resurs_bank_gateway( $methods ) {
		$methods[] = 'WC_Resurs_Bank';
		if ( is_admin() && is_array( $methods ) ) {
			foreach ( $methods as $id => $m ) {
				//if (preg_match("/^resurs_bank_nr_/i", $m) || $m == "WC_Resurs_Bank") {
				if ( preg_match( "/^resurs_bank_/i", $m ) ) {
					unset( $methods[ $id ] );
				}
			}
			$methods = array_values( $methods );
		}

		return $methods;
	}

	/**
	 * Remove the gateway from the available payment options at checkout
	 *
	 * @param  array $gateways The array of payment gateways
	 *
	 * @return array           The array of payment gateways
	 */
	function woocommerce_resurs_bank_available_payment_gateways( $gateways ) {
		unset( $gateways['resurs-bank'] );

		return $gateways;
	}

	/**
	 * @param $columns
	 *
	 * @return array
	 */
	function resurs_order_column_header( $columns ) {
		$new_columns = array();
		foreach ( $columns as $column_name => $column_info ) {
			$new_columns[ $column_name ] = $column_info;
			if ( $column_name == "order_title" ) {
				$new_columns['resurs_order_id'] = __( 'Resurs Reference', 'WC_Payment_Gateway' );
			}
		}

		return $new_columns;
	}

	/**
	 * @param $column
	 */
	function resurs_order_column_info( $column ) {
		global $post;
		if ( $column == "resurs_order_id" ) {
			$resursId = wc_get_payment_id_by_order_id( $post->ID );
			echo $resursId;
		}
	}

	function resurs_annuity_factors() {
		/** @var $product WC_Product_Simple */
		global $product;
		$displayAnnuity = "";
		if ( is_object( $product ) ) {

			/** @var $flow \Resursbank\RBEcomPHP\ResursBank */
			$flow           = initializeResursFlow();
			$annuityMethod = trim(getResursOption( "resursAnnuityMethod" ));
			$annuityFactorsOverride = null;
			$annuityDurationOverride = null;

			if ( isResursDemo() && isset($_SESSION['rb_country']) && class_exists( "CountryHandler" ) ) {
				$countryHandler = new \CountryHandler();
				$annuityFactorsOverride = $countryHandler->getAnnuityFactors();
				$annuityDurationOverride = $countryHandler->getAnnuityFactorsDuration();
			}

			if ( ! empty( $annuityMethod ) ) {
				$annuityFactorPrice = $product->get_price();

				try {
					$methodList = null;
					if (empty($annuityFactorsOverride)) {
						$methodList = $flow->getPaymentMethodSpecific( $annuityMethod );
					}

					if ( ! is_array( $methodList ) && !is_object($methodList) ) {
						$methodList = array();
					}
					$allowAnnuity = false;
					if ((is_array($methodList) && count($methodList)) || is_object($methodList)) {
					    $allowAnnuity = true;
                    }
					// Make sure the payment method exists. If there is overriders from the demoshop here, we'd know exists on the hard coded values.
					if ($allowAnnuity || !empty($annuityFactorsOverride)) {
						if (!empty($annuityFactorsOverride)) {
							$annuityFactors  = $annuityFactorsOverride;
						} else {
							$annuityFactors = getResursOption("resursCurrentAnnuityFactors");
						}
						if (!empty($annuityFactorsOverride)) {
							$annuityDuration  = $annuityDurationOverride;
						} else {
							$annuityDuration = getResursOption("resursAnnuityDuration");
						}
						$payFrom      = $flow->getAnnuityPriceByDuration( $annuityFactorPrice, $annuityFactors, $annuityDuration );
						$currentCountry = getResursOption('country');
						if ($currentCountry != "FI") {
						    $paymentLimit = 150;
                        } else {
						    $paymentLimit = 15;
                        }
                        $realPaymentLimit = $paymentLimit;
						if ( isResursTest()	) {
							$paymentLimit = 1;
						}
						if ($payFrom >= $paymentLimit) {
							$payFromAnnuity = wc_price( $payFrom );
							$costOfPurchase = admin_url( 'admin-ajax.php' ) . "?action=get_cost_ajax&method=$annuityMethod&amount=" . $annuityFactorPrice;
							$onclick        = 'window.open(\'' . $costOfPurchase . '\')';
							$displayAnnuity .= '<div class="resursPartPaymentInfo">';
							if (isResursTest()) {
								$displayAnnuity .= '<div style="font-size: 11px !important; font-color:#990000 !important; font-style: italic; padding:0px !important; margin: 0px !important;">' . __( 'Test enabled: In production, this information is shown when the minimum amount is above', 'WC_Payment_Gateway') . " <b>" . $realPaymentLimit . "</b></div>";
							}
							$displayAnnuity .= '<span>' . __( 'Part pay from', 'WC_Payment_Gateway' ) . ' ' . $payFromAnnuity . ' ' . __( 'per month', 'WC_Payment_Gateway' ) . '</span> | ';
							$displayAnnuity .= '<span class="resursPartPayInfoLink" onclick="' . $onclick . '">' . __( 'Info', 'WC_Payment_Gateway' ) . '</span>';
							$displayAnnuity .= '</div>';
						}
						//$fieldGenHtml .= '<button type="button" class="' . $buttonCssClasses . '" onClick="window.open(\'' . $costOfPurchase . '&method=' . $method->id . '&amount=' . $cart->total . '\', \'costOfPurchasePopup\',\'toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,copyhistory=no,resizable=yes,width=650px,height=740px\')">' . __( $read_more, 'WC_Payment_Gateway' ) . '</button>';

					} else {
						//$displayAnnuity = __('Annuity factors can not be displayed: Payment method is missing in merchant configuration.', 'WC_Payment_Gateway');
					}
				} catch (\Exception $annuityException) {
					// In the multilingual demoshop there might be exceptions when the session is lost.
					// Exceptions may also occur there, when the wrong payment method is checked and wrong language is chosen.
					$displayAnnuity .= __('Annuity factors can not be displayed for the moment', 'WC_Payment_Gateway') . ": " . $annuityException->getMessage();
				}
			}
		}
		echo $displayAnnuity;
	}

	/**
     * This function allows partial refunding based on amount rather than article numbers.
     *
     * Written experimental for the future - eventually - since the logcis allows a lot more than we have time to fix right now.
     * For exampel, in this function we also need to figure out how much that is actually left to annul or credit before sending the actions.
     * If we try to credit more than is authorized or credit a part of the payment that is already annulled, the credit will fail.
     *
	 * @param string $refundId
	 * @param array $refundArgs
	 *
	 * @return bool
	 */
	function resurs_order_refund($refundId = '', $refundArgs = array()) {
	    $refundStatus = false;
	    $refundObject = new WC_Order_Refund($refundId);
	    $amount = $refundObject->get_amount();
	    $reason = $refundObject->get_reason();
	    $itemCount = $refundObject->get_item_count();
	    $parent = $refundObject->get_parent_id();;
		$resursId = wc_get_payment_id_by_order_id($parent);

		/** @var $refundFlow Resursbank\RBEcomPHP\ResursBank */
		$refundFlow = initializeResursFlow();

	    if (!$itemCount && $amount >= 0 && $parent > 0 && !empty($resursId)) {
	        try {
		        $refundFlow->addOrderLine( $reason, __( 'Refund', 'WC_Payment_Gateway' ), $amount);
		        // totalAmount / limit
		        if ($refundFlow->getIsDebited($resursId)) {
			        $refundStatus = $refundFlow->paymentCredit( $resursId );
		        } else {
			        $refundStatus = $refundFlow->paymentAnnul( $resursId );
                }
	        } catch (\Exception $refundException) {
            }
        }
        return $refundStatus;
    }
	//add_action( 'woocommerce_refund_created', 'resurs_order_refund' );

	add_filter( 'woocommerce_get_settings_pages', 'rb_settings_pages' );
	add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_resurs_bank_gateway' );
	add_filter( 'woocommerce_available_payment_gateways', 'woocommerce_resurs_bank_available_payment_gateways' ); // Had prio 1
	add_filter( 'woocommerce_before_checkout_billing_form', 'add_ssn_checkout_field' );
	add_action( 'woocommerce_order_status_changed', 'WC_Resurs_Bank::order_status_changed', 10, 3 );
	add_action( 'wp_enqueue_scripts', 'enqueue_script', 0 );
	add_action( 'admin_enqueue_scripts', 'admin_enqueue_script' );
	add_action( 'wp_ajax_get_address_ajax', 'WC_Resurs_Bank::get_address_ajax' );
	add_action( 'wp_ajax_nopriv_get_address_ajax', 'WC_Resurs_Bank::get_address_ajax' );
	add_action( 'wp_ajax_get_cost_ajax', 'WC_Resurs_Bank::get_cost_ajax' );
	add_action( 'wp_ajax_nopriv_get_cost_ajax', 'WC_Resurs_Bank::get_cost_ajax' );
	add_action( 'wp_ajax_get_address_customertype', 'WC_Resurs_Bank::get_address_customertype' );
	add_action( 'wp_ajax_nopriv_get_address_customertype', 'WC_Resurs_Bank::get_address_customertype' );
	add_action( 'init', 'start_session', 1 );
	add_action( 'wp_logout', 'end_session' );
	add_action( 'wp_login', 'end_session' );
	add_action( 'init', 'app_output_buffer', 2 );
	add_action( 'admin_notices', 'resurs_bank_admin_notice' );
	add_action( 'woocommerce_before_checkout_shipping_form', 'test_before_shipping' );
	add_action( 'woocommerce_before_delete_order_item', 'resurs_remove_order_item' );
	add_action( 'woocommerce_admin_order_data_after_order_details', 'resurs_order_data_info_after_order' );
	add_action( 'woocommerce_admin_order_data_after_billing_address', 'resurs_order_data_info_after_billing' );
	add_action( 'woocommerce_admin_order_data_after_shipping_address', 'resurs_order_data_info_after_shipping' );
	add_filter( 'woocommerce_order_button_html', 'resurs_omnicheckout_order_button_html' ); // Omni
	add_filter( 'woocommerce_no_available_payment_methods_message', 'resurs_omnicheckout_payment_gateways_check' );
	add_action( 'woocommerce_single_product_summary', 'resurs_annuity_factors' );
	if ( getResursOption( "showPaymentIdInOrderList" ) ) {
		add_filter( 'manage_edit-shop_order_columns', 'resurs_order_column_header' );
		add_action( 'manage_shop_order_posts_custom_column', 'resurs_order_column_info' );
	}
}

/**
 * @param null $order
 */
function resurs_order_data_info_after_order( $order = null ) {
	resurs_order_data_info( $order, 'AO' );
}

/**
 * @param null $order
 */
function resurs_order_data_info_after_billing( $order = null ) {
	resurs_order_data_info( $order, 'AB' );
}

/**
 * @param null $order
 */
function resurs_order_data_info_after_shipping( $order = null ) {
	resurs_order_data_info( $order, 'AS' );
}

function resurs_no_debit_debited() {
	?>
    <div class="notice notice-error">
        <p><?php _e( 'It seems this order has already been finalized from an external system - if your order is finished you may update it here aswell', 'WC_Payment_Gateway' ); ?></p>
    </div>
	<?php
}

/**
 * Hook into WooCommerce OrderAdmin fetch payment data from Resurs Bank.
 * This hook are tested from WooCommerce 2.1.5 up to WooCommcer 2.5.2
 *
 * @param WC_Order $order
 * @param null $orderDataInfoAfter
 *
 * @throws Exception
 */
function resurs_order_data_info( $order = null, $orderDataInfoAfter = null ) {
	global $orderInfoShown;
	$resursPaymentInfo = null;
	$showOrderInfoAfterOption = getResursOption( "showOrderInfoAfter", "woocommerce_resurs-bank_settings" );
	$showOrderInfoAfter       = ! empty( $showOrderInfoAfterOption ) ? $showOrderInfoAfterOption : "AO";
	if ( $showOrderInfoAfter != $orderDataInfoAfter ) {
		return;
	}
	if ( $orderInfoShown ) {
		return;
	}

	$orderInfoShown     = true;
	$renderedResursData = '';
	if ( ! isWooCommerce3() ) {
		$resursPaymentId = get_post_meta( $order->id, 'paymentId', true );
	} else {
		$resursPaymentId = get_post_meta( $order->get_id(), 'paymentId', true );
	}
	if ( ! empty( $resursPaymentId ) ) {
		$hasError = "";
		try {
			/** @var $rb \Resursbank\RBEcomPHP\ResursBank */
			$rb                = initializeResursFlow();
			try {
				$resursPaymentInfo = $rb->getPayment( $resursPaymentId );
			} catch (\Exception $e) {
			    $errorMessage = $e->getMessage();
			    if ($e->getCode() == 8) {
			        // REFERENCED_DATA_DONT_EXISTS
                    $errorMessage = __("Referenced data don't exist", 'WC_Payment_Gateway') . "<br>\n<br>\n";
                    $errorMessage .= __("This error might occur when for example a payment doesn't exist at Resurs Bank. Normally this happens when payments have failed or aborted before it can be completed", 'WC_Payment_Gateway');
                }
			    echo '
                <div class="clear">&nbsp;</div>
                <div class="order_data_column_container resurs_orderinfo_container resurs_orderinfo_text">
                    <div style="padding: 30px;border:none;" id="resursInfo">
                        <span class="paymentInfoWrapLogo"><img src="' . plugin_dir_url( __FILE__ ) . '/img/rb_logo.png' . '"></span>
                        <fieldset>
                        <b>'.__('Following error ocurred when we tried to fetch information about the payment', 'WC_Payment_Gateway').'</b><br>
                        <br>
                        '.$errorMessage.'<br>
                    </fieldset>
                    </div>
                </div>
			    ';
			    return;
			}
			$currentWcStatus   = $order->get_status();
			$notIn             = array( "completed", "cancelled", "refunded" );
			if ( ! $rb->canDebit( $resursPaymentInfo ) && $rb->getIsDebited( $resursPaymentInfo ) && ! in_array( $currentWcStatus, $notIn ) ) {
				resurs_no_debit_debited();
			}
		} catch ( Exception $e ) {
			$hasError         = $e->getMessage();
		}
		$renderedResursData .= '
                <div class="clear">&nbsp;</div>
                <div class="order_data_column_container resurs_orderinfo_container resurs_orderinfo_text">
                <div class="resurs-read-more-box">
                <div style="padding: 30px;border:none;" id="resursInfo">
                ';

		$invoices = array();
		if ( empty( $hasError ) ) {
			$status = "AUTHORIZE";
			if ( is_array( $resursPaymentInfo->paymentDiffs ) ) {
				$invoices = $rb->getPaymentInvoices($resursPaymentInfo);
				foreach ( $resursPaymentInfo->paymentDiffs as $paymentDiff ) {
					if ( $paymentDiff->type === "DEBIT" ) {
						$status = "DEBIT";
					}
					if ( $paymentDiff->type === "ANNUL" ) {
						$status = "ANNUL";
					}
					if ( $paymentDiff->type === "CREDIT" ) {
						$status = "CREDIT";
					}
				}
			} else {
				if ( $resursPaymentInfo->paymentDiffs->type === "DEBIT" ) {
					$status = "DEBIT";
				}
				if ( $resursPaymentInfo->paymentDiffs->type === "ANNUL" ) {
					$status = "ANNUL";
				}
				if ( $resursPaymentInfo->paymentDiffs->type === "CREDIT" ) {
					$status = "CREDIT";
				}
			}
			$renderedResursData .= '<div class="resurs_orderinfo_text paymentInfoWrapStatus paymentInfoHead">';
			if ( $status === "AUTHORIZE" ) {
				$renderedResursData .= __( 'The order is booked', 'WC_Payment_Gateway' );
			} elseif ( $status === "DEBIT" ) {
				if ( ! $rb->canDebit( $resursPaymentInfo ) ) {
					$renderedResursData .= __( 'The order is debited', 'WC_Payment_Gateway' );
				} else {
					$renderedResursData .= __( 'The order is partially debited', 'WC_Payment_Gateway' );
				}
			} elseif ( $status === "CREDIT" ) {
				$renderedResursData .= __( 'The order is credited', 'WC_Payment_Gateway' );
			} elseif ( $status === "ANNUL" ) {
				$renderedResursData .= __( 'The order is annulled', 'WC_Payment_Gateway' );
			} else {
				//$renderedResursData .= '<p>' . __('Confirm the invoice to be sent before changes can be made to order. <br> Changes of the invoice must be made in resurs bank management.') . '</p>';
			}
			$renderedResursData .= '</div>
                     <span class="paymentInfoWrapLogo"><img src="' . plugin_dir_url( __FILE__ ) . '/img/rb_logo.png' . '"></span>
                ';

			$addressInfo = "";
			if ( is_object( $resursPaymentInfo->customer->address ) ) {
				$addressInfo .= isset( $resursPaymentInfo->customer->address->addressRow1 ) && ! empty( $resursPaymentInfo->customer->address->addressRow1 ) ? $resursPaymentInfo->customer->address->addressRow1 . "\n" : "";
				$addressInfo .= isset( $resursPaymentInfo->customer->address->addressRow2 ) && ! empty( $resursPaymentInfo->customer->address->addressRow2 ) ? $resursPaymentInfo->customer->address->addressRow2 . "\n" : "";
				$addressInfo .= isset( $resursPaymentInfo->customer->address->postalArea ) && ! empty( $resursPaymentInfo->customer->address->postalArea ) ? $resursPaymentInfo->customer->address->postalArea . "\n" : "";
				$addressInfo .= ( isset( $resursPaymentInfo->customer->address->country ) && ! empty( $resursPaymentInfo->customer->address->country ) ? $resursPaymentInfo->customer->address->country : "" ) . " " . ( isset( $resursPaymentInfo->customer->address->postalCode ) && ! empty( $resursPaymentInfo->customer->address->postalCode ) ? $resursPaymentInfo->customer->address->postalCode : "" ) . "\n";
			}
			ThirdPartyHooksSetPaymentTrigger( 'orderinfo', $resursPaymentId, ! isWooCommerce3() ? $order->id : $order->get_id() );

			$unsetKeys = array(
				'id',
				'paymentMethodId',
				'storeId',
				'paymentMethodName',
				'paymentMethodType',
				'totalAmount',
				'limit',
				'fraud',
				'frozen',
				'customer',
				'paymentDiffs'
			);

			$renderedResursData .= '
                <br>
                <fieldset>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . __( 'Payment ID', 'WC_Payment_Gateway' ) . ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . ( isset( $resursPaymentInfo->id ) && ! empty( $resursPaymentInfo->id ) ? $resursPaymentInfo->id : "" ) . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . __( 'Payment method ID', 'WC_Payment_Gateway' ) . ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . ( isset( $resursPaymentInfo->paymentMethodId ) && ! empty( $resursPaymentInfo->paymentMethodId ) ? $resursPaymentInfo->paymentMethodId : "" ) . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . __( 'Store ID', 'WC_Payment_Gateway' ) . ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . ( isset( $resursPaymentInfo->storeId ) && ! empty( $resursPaymentInfo->storeId ) ? $resursPaymentInfo->storeId : "" ) . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . __( 'Payment method name', 'WC_Payment_Gateway' ) . ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . ( isset( $resursPaymentInfo->paymentMethodName ) && ! empty( $resursPaymentInfo->paymentMethodName ) ? $resursPaymentInfo->paymentMethodName : "" ) . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . __( 'Payment method type', 'WC_Payment_Gateway' ) . ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . ( isset( $resursPaymentInfo->paymentMethodType ) && ! empty( $resursPaymentInfo->paymentMethodName ) ? $resursPaymentInfo->paymentMethodType : "" ) . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . __( 'Payment amount', 'WC_Payment_Gateway' ) . ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . ( isset( $resursPaymentInfo->totalAmount ) && ! empty( $resursPaymentInfo->totalAmount ) ? round( $resursPaymentInfo->totalAmount, 2 ) : "" ) . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . __( 'Payment limit', 'WC_Payment_Gateway' ) . ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . ( isset( $resursPaymentInfo->limit ) && ! empty( $resursPaymentInfo->limit ) ? round( $resursPaymentInfo->limit, 2 ) : "" ) . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . __( 'Fraud', 'WC_Payment_Gateway' ) . ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . ( isset( $resursPaymentInfo->fraud ) && ! empty( $resursPaymentInfo->fraud ) ? $resursPaymentInfo->fraud ? __( 'Yes' ) : __( 'No' ) : __( 'No' ) ) . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . __( 'Frozen', 'WC_Payment_Gateway' ) . ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . ( isset( $resursPaymentInfo->frozen ) && ! empty( $resursPaymentInfo->frozen ) ? $resursPaymentInfo->frozen ? __( 'Yes' ) : __( 'No' ) : __( 'No' ) ) . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . __( 'Customer name', 'WC_Payment_Gateway' ) . ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . ( is_object( $resursPaymentInfo->customer->address ) && ! empty( $resursPaymentInfo->customer->address->fullName ) ? $resursPaymentInfo->customer->address->fullName : "" ) . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . __( 'Delivery address', 'WC_Payment_Gateway' ) . ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . ( ! empty( $addressInfo ) ? nl2br( $addressInfo ) : "" ) . '</span>
            ';

			if ( is_array( $invoices ) && count( $invoices ) ) {
				$renderedResursData .= '
                            <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">Invoices:</span>
                            <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . implode(", ", $invoices) . '</span>
                        ';
			}

			$continueView = $resursPaymentInfo;
			foreach ( $continueView as $key => $value ) {
				if ( in_array( $key, $unsetKeys ) ) {
					unset( $continueView->$key );
				}
			}
			if ( is_object( $continueView ) ) {
				foreach ( $continueView as $key => $value ) {
					if ( ! is_array( $value ) && ! is_object( $value ) ) {
						$renderedResursData .= '
                            <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . ucfirst( $key ) . ':</span>
                            <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . ( ! empty( $value ) ? nl2br( $value ) : "" ) . '</span>
                        ';
					} else {
						if ($key == "metaData") {
							if (is_array($value)) {
								foreach ($value as $metaArray) {
									$renderedResursData .= '
                                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . ucfirst( $metaArray->key ) . ':</span>
                                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . $metaArray->value . '</span>
                                    ';
								}
							} else {
								$renderedResursData .= '
                                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . ucfirst( $value->key ) . ':</span>
                                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . $value->value . '</span>
                                ';
							}
						} else {
							foreach ( $value as $subKey => $subValue ) {
								$renderedResursData .= '
                                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . ucfirst($key) . " (" . ucfirst( $subKey ) . '):</span>
                                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . ( ! empty( $subValue ) ? nl2br( $subValue ) : "" ) . '</span>
                                ';
							}
						}
					}
				}
			}
		}
		$renderedResursData .= '</fieldset>
                <p class="resurs-read-more" id="resursInfoButton"><a href="#" class="button">' . __( 'Read more', 'WC_Payment_Gateway' ) . '</a></p>
                </div>
                </div>
                </div>
            ';
	}
	//}
	echo $renderedResursData;
}

/**
 * Convert version number to decimals
 * @return string
 */
function rbWcGwVersionToDecimals() {
	$splitVersion = explode( ".", RB_WOO_VERSION );
	$decVersion   = "";
	foreach ( $splitVersion as $ver ) {
		$decVersion .= str_pad( intval( $ver ), 2, "0", STR_PAD_LEFT );
	}

	return $decVersion;
}

/**
 * @return string
 */
function rbWcGwVersion() {
	return RB_WOO_VERSION;
}

/**
 * Allows partial hooks from this plugin
 *
 * @param string $type
 * @param string $content
 */
function ThirdPartyHooks( $type = '', $content = '', $addonData = array() ) {
	$type             = strtolower( $type );
	$allowedHooks     = array( 'orderinfo', 'callback' );
	$paymentInfoHooks = array( 'orderinfo', 'callback' );
	// Start with an empty content array
	$sendHookContent = array();

	// Put on any extra that the hook wishes to add
	if ( is_array( $addonData ) && count( $addonData ) ) {
		foreach ( $addonData as $addonKey => $addonValue ) {
			$sendHookContent[ $addonKey ] = $addonValue;
		}
	}

	// If the hook is basedon sending payment data info ...
	if ( in_array( strtolower( $type ), $paymentInfoHooks ) ) {
        // ... then prepare the necessary data without revealing the full getPayment()-object.
        // This is for making data available for any payment bridging needed for external systems to synchronize payment statuses if needed.
		$sendHookContent['id']         = isset( $content->id ) ? $content->id : '';
		$sendHookContent['fraud']      = isset( $content->fraud ) ? $content->fraud : '';
		$sendHookContent['frozen']     = isset( $content->frozen ) ? $content->frozen : '';
		$sendHookContent['status']     = isset( $content->status ) ? $content->status : '';
		$sendHookContent['booked']     = isset( $content->booked ) ? strtotime( $content->booked ) : '';
		$sendHookContent['finalized']  = isset( $content->finalized ) ? strtotime( $content->finalized ) : '';
		$sendHookContent['iscallback'] = isset( $content->iscallback ) ? $content->iscallback : '';
	}
	if ( in_array( strtolower( $type ), $allowedHooks ) ) {
		do_action( "resurs_hook_" . $type, $sendHookContent );
	}
}

/**
 * Hooks that should initiate payment controlling, may be runned through the same function - making sure that we only call for that hook if everything went nicely.
 *
 * @param string $type
 * @param string $paymentId
 * @param null $internalOrderId
 * @param null $callbackType
 *
 * @throws Exception
 */
function ThirdPartyHooksSetPaymentTrigger( $type = '', $paymentId = '', $internalOrderId = null, $callbackType = null ) {
    /** @var $flow \Resursbank\RBEcomPHP\ResursBank */
	$flow          = initializeResursFlow();
	$paymentDataIn = array();
	try {
		$paymentDataIn = $flow->getPayment( $paymentId );
		if ( $type == "callback" && ! is_null( $callbackType ) ) {
			$paymentDataIn->iscallback = $callbackType;
		} else {
			$paymentDataIn->iscallback = null;
		}
		if ( ! is_null( $internalOrderId ) ) {
			$paymentDataIn->internalOrderId = $internalOrderId;
		}
		if ( is_object( $paymentDataIn ) ) {
			return ThirdPartyHooks( $type, $paymentDataIn );
		}
	} catch ( Exception $e ) {
	}
}


/**
 * Unconditional OrderRowRemover for Resurs Bank. This function will run before the primary remove_order_item() in the WooCommerce-plugin.
 * This function won't remove any product on the woocommerce-side, it will however update the payment at Resurs Bank.
 * If removal at Resurs fails by any reason, this method will stop the removal from WooAdmin, so we won't destroy any synch.
 *
 * @param $item_id
 *
 * @return bool
 */
function resurs_remove_order_item( $item_id ) {
	if ( ! $item_id ) {
		return false;
	}
	// Make sure we still keep the former security
	if ( ! current_user_can( 'edit_shop_orders' ) ) {
		die( - 1 );
	}

	/** @var $resursFlow \Resursbank\RBEcomPHP\ResursBank */
	$resursFlow = null;
	if ( hasEcomPHP() ) {
		$resursFlow = initializeResursFlow();
	}
	$clientPaymentSpec = array();
	if ( null !== $resursFlow ) {
		$productId  = wc_get_order_item_meta( $item_id, '_product_id' );
		$productQty = wc_get_order_item_meta( $item_id, '_qty' );
		$orderId    = r_wc_get_order_id_by_order_item_id( $item_id );

		$resursPaymentId = get_post_meta( $orderId, 'paymentId', true );

		if ( empty( $productId ) ) {
			$testItemType = r_wc_get_order_item_type_by_item_id( $item_id );
			$testItemName = r_wc_get_order_item_type_by_item_id( $item_id );
			if ( $testItemType === "shipping" ) {
				$clientPaymentSpec[] = array(
					'artNo'    => "00_frakt",
					'quantity' => 1
				);
			} elseif ( $testItemType === "coupon" ) {
				$clientPaymentSpec[] = array(
					'artNo'    => $testItemName . "_kupong",
					'quantity' => 1
				);
			} elseif ( $testItemType === "fee" ) {
				if ( function_exists( 'wc_get_order' ) ) {
					$current_order       = wc_get_order( $orderId );
					$feeName             = '00_' . str_replace( ' ', '_', $current_order->payment_method_title ) . "_fee";
					$clientPaymentSpec[] = array(
						'artNo'    => $feeName,
						'quantity' => 1
					);
				} else {
					$order_failover_test = new WC_Order( $orderId );
					///$payment_fee = array_values($order->get_items('fee'))[0];
					$feeName             = '00_' . str_replace( ' ', '_', $order_failover_test->payment_method_title ) . "_fee";
					$clientPaymentSpec[] = array(
						'artNo'    => $feeName,
						'quantity' => 1
					);
					//die("Can not fetch order information from WooCommerce (Function wc_get_order() not found)");
				}
			}
		} else {
			$clientPaymentSpec[] = array(
				'artNo'    => $productId,
				'quantity' => $productQty
			);
		}

		try {
			$order = new WC_Order($orderId);
			$removeResursRow = $resursFlow->paymentCancel( $resursPaymentId, $clientPaymentSpec );
			$order->add_order_note( __( 'Orderline Removal: Resurs Bank API was called to remove orderlines', 'WC_Payment_Gateway' ) );
		} catch ( Exception $e ) {
			$resultArray = array(
				'success' => false,
				'fail'    => utf8_encode( $e->getMessage() )
			);
			echo $e->getMessage();
			die();
		}
		if ( ! $removeResursRow ) {
			echo "Cancelling payment failed without a proper reason";
			die();
		}
	}
}

/**
 * Get order by current payment id
 *
 * @param string $paymentId
 *
 * @return null|string
 */
function wc_get_order_id_by_payment_id( $paymentId = '' ) {
	global $wpdb;
	$order_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = 'paymentId' and meta_value = '%s'", $paymentId ) );
	$order_id_last = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = 'paymentIdLast' and meta_value = '%s'", $paymentId ) );

	// If updateOrderReference-setting is enabled, also look for a prior variable, to track down the correct order based on the metadata tag paymentIdLast
	if (getResursOption('postidreference') && !empty($order_id_last) && empty($order_id)) {
	    return $order_id_last;
    }

	return $order_id;
}

/**
 * Get payment id by order id
 *
 * @param string $orderId
 *
 * @return null|string
 */
function wc_get_payment_id_by_order_id( $orderId = '' ) {
	global $wpdb;
	$order_id = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}postmeta WHERE meta_key = 'paymentId' and post_id = '%s'", $orderId ) );

	return $order_id;
}

/**
 * @param string $flagKey
 *
 * @return bool|string
 */
function getResursFlag($flagKey = null) {
	$allFlags = array();
	$flagRow = getResursOption("devFlags");
	$flagsArray = explode(",", $flagRow);
	if (is_array($flagsArray)) {
		foreach ($flagsArray as $flagIndex => $flagParameter) {
			$flagEx = explode("=", $flagParameter,2);
			if (is_array($flagEx) && isset($flagEx[1])) {
				// Handle as parameter key with values
                if (!is_null($flagKey)) {
	                if ( strtolower( $flagEx[0] ) == strtolower( $flagKey ) ) {
		                return $flagEx[1];
	                }
                } else {
                    $allFlags[$flagEx[0]] = $flagEx[1];
                }
			} else {
			    if (!is_null($flagKey)) {
				    // Handle as defined true
				    if ( strtolower( $flagParameter ) == strtolower( $flagKey ) ) {
					    return true;
				    }
			    } else {
				    $allFlags[$flagParameter] = true;
			    }
			}
		}
	}
	if (is_null($flagKey)) {
	    return $allFlags;
    }
	return false;
}

/**
 * Get specific options from the Resurs configuration set
 *
 * @param string $key
 * @param string $namespace
 *
 * @return bool
 */
function resursOption( $key = "", $namespace = "woocommerce_resurs-bank_settings" ) {
	/*
     * MarGul change
     * If it's demoshop it will take the config from sessions instead of db
     */
	if ( isResursDemo() ) {
		// Override database setting with the theme (demoshops) flowtype SESSION setting if it's set.
		if ( $key == "flowtype" ) {
			if ( ! empty( $_SESSION['rb_checkout_flow'] ) ) {
				$accepted = [ 'simplifiedshopflow', 'resurs_bank_hosted', 'resurs_bank_omnicheckout' ];
				if ( in_array( strtolower( $_SESSION['rb_checkout_flow'] ), $accepted ) ) {
					return $_SESSION['rb_checkout_flow'];
				}
			}
		}

		// Override database setting with the theme (demoshops) country SESSION setting if it's set.
		if ( $key == "country" ) {
			if ( ! empty( $_SESSION['rb_country'] ) ) {
				$accepted = [ 'se', 'dk', 'no', 'fi' ];
				if ( in_array( strtolower( $_SESSION['rb_country'] ), $accepted ) ) {
					return strtoupper( $_SESSION['rb_country'] );
				}
			}
		}

		if ( $key == 'login' ) {
			if ( ! empty( $_SESSION['rb_country_data'] ) ) {
				return $_SESSION['rb_country_data']['account']['login'];
			}
		}

		if ( $key == 'password' ) {
			if ( ! empty( $_SESSION['rb_country_data'] ) ) {
				return $_SESSION['rb_country_data']['account']['password'];
			}
		}
	}

	$getOptionsNamespace = get_option( $namespace );
	// Going back to support PHP 5.3 instead of 5.4+
	if ( isset( $getOptionsNamespace[ $key ] ) ) {
		$response = $getOptionsNamespace[ $key ];
	} else {
		// No value set
		$response = null;
	}

	if ( empty( $response ) ) {
		$response = get_option( $key );
	}
	if ( $response === "true" ) {
		return true;
	}
	if ( $response === "false" ) {
		return false;
	}
	if ( $response === "yes" ) {
		return true;
	}
	if ( $response === "no" ) {
		return false;
	}

	return $response;
}

/**
 * Returns true or false depending if the key exists in the resursOption-array
 *
 * @param string $key
 *
 * @return bool
 */
function issetResursOption( $key = "", $namespace = 'woocommerce_resurs-bank_settings' ) {
	$response = get_option( $namespace );
	if ( isset( $response[ $key ] ) ) {
		return true;
	} else {
		return false;
	}
}

/**
 * @param string $key
 * @param string $namespace
 *
 * @return bool
 */
function getResursOption( $key = "", $namespace = "woocommerce_resurs-bank_settings" ) {
	return resursOption( $key, $namespace );
}

/**
 * Function used to figure out whether values are set or not
 *
 * @param string $key
 *
 * @return bool
 */
function hasResursOptionValue( $key = "", $namespace = 'woocommerce_resurs-bank_settings' ) {
	$optionValues = get_option( $namespace );
	if ( isset( $optionValues[ $key ] ) ) {
		return true;
	}

	return false;
}

/**
 * Set a new value in resursoptions
 *
 * @param string $key
 * @param string $value
 * @param string $configurationSpace
 *
 * @return bool
 */
function setResursOption( $key = "", $value = "", $configurationSpace = "woocommerce_resurs-bank_settings" ) {
	$allOptions = get_option( $configurationSpace );
	if ( ! empty( $key ) ) {
		$allOptions[ $key ] = $value;
		update_option( $configurationSpace, $allOptions );

		return true;
	}

	return false;
}

if ( ! function_exists( 'r_wc_get_order_id_by_order_item_id' ) ) {
	/**
	 * Get the order id from where a specific item resides
	 *
	 * @param $item_id
	 *
	 * @return null|string
	 * @since 2.0.2
	 */
	function r_wc_get_order_id_by_order_item_id( $item_id ) {
		global $wpdb;
		$item_id  = absint( $item_id );
		$order_id = $wpdb->get_var( $wpdb->prepare( "SELECT order_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = '%d'", $item_id ) );

		return $order_id;
	}
}
if ( ! function_exists( 'r_wc_get_order_item_type_by_item_id' ) ) {
	/**
	 * Get the order item type (or name) by item id
	 *
	 * @param $item_id
	 *
	 * @return null|string
	 * @since 2.0.2
	 */
	function r_wc_get_order_item_type_by_item_id( $item_id, $getItemName = false ) {
		global $wpdb;
		$item_id = absint( $item_id );
		if ( ! $getItemName ) {
			$order_item_type = $wpdb->get_var( $wpdb->prepare( "SELECT order_item_type FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = '%d'", $item_id ) );

			return $order_item_type;
		} else {
			$order_item_name = $wpdb->get_var( $wpdb->prepare( "SELECT order_item_name FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = '%d'", $item_id ) );

			return $order_item_name;
		}
	}
}

/**
 * Initialize EComPHP, the key of almost everything in this plugin
 *
 * @param string $overrideUser
 * @param string $overridePassword
 * @param int $setEnvironment
 *
 * @return \Resursbank\RBEcomPHP\ResursBank
 * @throws Exception
 */
function initializeResursFlow( $overrideUser = "", $overridePassword = "", $setEnvironment = RESURS_ENVIRONMENTS::ENVIRONMENT_NOT_SET ) {
	global $current_user;
	$username       = resursOption( "login" );
	$password       = resursOption( "password" );
	$useEnvironment = getServerEnv();
	if ( $setEnvironment !== RESURS_ENVIRONMENTS::ENVIRONMENT_NOT_SET ) {
		$useEnvironment = $setEnvironment;
	}
	if ( ! empty( $overrideUser ) ) {
		$username = $overrideUser;
	}
	if ( ! empty( $overridePassword ) ) {
		$password = $overridePassword;
	}

	/** @var $initFlow \Resursbank\RBEcomPHP\ResursBank */
	$initFlow                      = new \Resursbank\RBEcomPHP\ResursBank( $username, $password );
	$sslHandler = getResursFlag("DISABLE_SSL_VALIDATION");
	if (isResursTest() && $sslHandler) {
		$initFlow->setDebug(true);
		$initFlow->setSslValidation(false);
	}
	$allFlags = getResursFlag(null);
	foreach ($allFlags as $flagKey => $flagValue) {
	    if (!empty($flagKey)) {
		    $initFlow->setFlag( $flagKey, $flagValue );
	    }
	}

	$initFlow->setUserAgent( RB_WOO_CLIENTNAME . "-" . RB_WOO_VERSION);
	$initFlow->setEnvironment( $useEnvironment );
	$initFlow->setDefaultUnitMeasure();
	if ( isset( $_REQUEST['testurl'] ) ) {
		$baseUrlTest = $_REQUEST['testurl'];
		// Set this up once
		if ( $baseUrlTest == "unset" || empty( $baseUrlTest ) ) {
			unset( $_SESSION['customTestUrl'], $baseUrlTest );
		} else {
			$_SESSION['customTestUrl'] = $baseUrlTest;
		}
	}
	if ( isset( $_SESSION['customTestUrl'] ) ) {
		$_SESSION['customTestUrl'] = $initFlow->setTestUrl( $_SESSION['customTestUrl'] );
	}
	try {
		if ( function_exists( 'wp_get_current_user' ) ) {
			wp_get_current_user();
		} else {
			get_currentuserinfo();
		}
		if ( isset( $current_user->user_login ) ) {
			$initFlow->setLoggedInUser( $current_user->user_login );
		}
	} catch ( Exception $e ) {
	}
	$country = getResursOption( "country" );
	$initFlow->setCountryByCountryCode( $country );
	if ($initFlow->getCountry() == "FI") {
	    $initFlow->setDefaultUnitMeasure("kpl");
    }
	return $initFlow;
}

/**
 * @param string $ssn
 * @param string $customerType
 * @param string $ip
 *
 * @return array|mixed|null
 */
function getAddressProd( $ssn = '', $customerType = '', $ip = '' ) {
	global $current_user;
	$username = resursOption( "ga_login" );
	$password = resursOption( "ga_password" );
	if ( ! empty( $username ) && ! empty( $password ) ) {
	    /** @var \Resursbank\RBEcomPHP\ResursBank $initFlow */
		$initFlow                      = new ResursBank( $username, $password );
		$initFlow->setUserAgent( RB_WOO_CLIENTNAME . "-" . RB_WOO_VERSION);
		//$initFlow->setUserAgent( "ResursBankPaymentGatewayForWoocommerce" . RB_WOO_VERSION );
		//$initFlow->setUserAgent( "WooCommerce ResursBank Payment Gateway " . ( defined( 'RB_WOO_VERSION' ) ? RB_WOO_VERSION : "Unknown version" ) );
		$initFlow->setEnvironment( RESURS_ENVIRONMENTS::ENVIRONMENT_PRODUCTION );
		try {
			$getResponse = $initFlow->getAddress( $ssn, $customerType, $ip );

			return $getResponse;
		} catch ( Exception $e ) {
			echo json_encode( array( "Unavailable credentials - " . $e->getMessage() ) );
		}
	} else {
		echo json_encode( array( "Unavailable credentials" ) );
	}
	die();
}

/**
 * Get current Resurs Environment setup (demo/test or production)
 *
 * @return int
 */
function getServerEnv() {
	$useEnvironment = RESURS_ENVIRONMENTS::ENVIRONMENT_TEST;

	$serverEnv    = getResursOption('serverEnv');
	$demoshopMode = getResursOption('demoshopMode');

	if ( $serverEnv == 'live' ) {
		$useEnvironment = RESURS_ENVIRONMENTS::ENVIRONMENT_PRODUCTION;
	}
	/*
     * Prohibit production mode if this is a demoshop
     */
	if ( $serverEnv == 'test' || $demoshopMode == "true" ) {
		$useEnvironment = RESURS_ENVIRONMENTS::ENVIRONMENT_TEST;
	}

	return $useEnvironment;
}

/**
 * Returns true if this is a test environment
 * @return bool
 */
function isResursTest() {
	$currentEnv = getServerEnv();
	if ( $currentEnv === RESURS_ENVIRONMENTS::ENVIRONMENT_TEST ) {
		return true;
	}

	return false;
}

/**
 * Payment gateway destroyer.
 *
 * Only enabled in very specific environments.
 *
 * @return bool
 */
function isResursSimulation() {
	if ( ! isResursTest() ) {
		return repairResursSimulation();
	}
	$devResursSimulation = getResursOption( "devResursSimulation" );
	if ( $devResursSimulation ) {
		if ( isset( $_SERVER['HTTP_HOST'] ) ) {
			$mustContain            = array( '.loc$', '.local$', '^localhost$', '.localhost$' );
			$hasRequiredEnvironment = false;
			foreach ( $mustContain as $hostContainer ) {
				if ( preg_match( "/$hostContainer/", $_SERVER['HTTP_HOST'] ) ) {
					return true;
				}
			}
			/*
             * If you really want to force this, use one of the following variables from a define or, if in .htaccess:
             * SetEnv FORCE_RESURS_SIMULATION "true"
             * As this is invoked, only if really set to test mode, this should not be able to destroy anything in production.
             */
			if ( ( defined( 'FORCE_RESURS_SIMULATION' ) && FORCE_RESURS_SIMULATION === true ) || ( isset( $_SERVER['FORCE_RESURS_SIMULATION'] ) && $_SERVER['FORCE_RESURS_SIMULATION'] == "true" ) ) {
				return true;
			}
		}
	}

	return repairResursSimulation();
}

/**
 * @param bool $returnRepairState
 *
 * @return bool
 */
function repairResursSimulation( $returnRepairState = false ) {
	setResursOption( "devSimulateErrors", $returnRepairState );

	return $returnRepairState;
}

/********************** OMNICHECKOUT RELATED STARTS HERE ******************/

/**
 * Check if the current payment method is currently enabled and selected
 *
 * @param bool $ignoreActiveFlag
 *
 * @return bool
 */
function isResursOmni( $ignoreActiveFlag = false ) {
	global $woocommerce;
	$returnValue       = false;
	$externalOmniValue = null;
	$currentMethod     = "";
	if ( isset( $woocommerce->session ) ) {
		$currentMethod = $woocommerce->session->get( 'chosen_payment_method' );
	}
	$flowType = resursOption( "flowtype" );
	$hasOmni  = hasResursOmni( $ignoreActiveFlag );
	if ( ( $hasOmni == 1 || $hasOmni === true ) && ( ! empty( $currentMethod ) && $flowType === $currentMethod ) ) {
		$returnValue = true;
	}
	/*
	 * If Omni is enabled and the current chosen method is empty, pre-select omni
	 */
	if ( ( $hasOmni == 1 || $hasOmni === true ) && $flowType === "resurs_bank_omnicheckout" && empty( $currentMethod ) ) {
		$returnValue = true;
	}
	if ( $returnValue ) {
		// If the checkout is normally set to be enabled, this gives external plugins a chance to have it disabled
		$externalOmniValue = apply_filters( "resursbank_temporary_disable_checkout", null );
		if ( ! is_null( $externalOmniValue ) ) {
			$returnValue = ( $externalOmniValue ? false : true );
		}
	}

	return $returnValue;
}

/**
 * Check if the hosted flow is enabled and chosen
 *
 * @return bool
 */
function isResursHosted() {
	$hasHosted = hasResursHosted();
	if ( $hasHosted == 1 || $hasHosted === true ) {
		return true;
	}

	return false;
}

/**
 * @return bool
 */
function hasEcomPHP() {
	if ( class_exists( 'ResursBank' ) || class_exists( 'Resursbank\RBEcomPHP\ResursBank' ) ) {
		return true;
	}

	return false;
}

/**
 * Check if the omniFlow is enabled at all (through flowType)
 *
 * @param bool $ignoreActiveFlag Check this setting even though the plugin is not active
 *
 * @return bool
 */
function hasResursOmni( $ignoreActiveFlag = false ) {
	$resursEnabled = resursOption( "enabled" );
	$flowType      = resursOption( "flowtype" );
	if ( is_admin() ) {
		$omniOption = get_option( 'woocommerce_resurs_bank_omnicheckout_settings' );
		if ( $flowType == "resurs_bank_omnicheckout" ) {
			$omniOption['enabled'] = 'yes';
		} else {
			$omniOption['enabled'] = 'no';
		}
		update_option( 'woocommerce_resurs_bank_omnicheckout_settings', $omniOption );
	}
	if ( $resursEnabled != "yes" && ! $ignoreActiveFlag ) {
		return false;
	}
	if ( $flowType == "resurs_bank_omnicheckout" ) {
		return true;
	}

	return false;
}

/**
 * @return bool
 */
function hasResursHosted() {
	$resursEnabled = resursOption( "enabled" );
	$flowType      = resursOption( "flowtype" );
	if ( $resursEnabled != "yes" ) {
		return false;
	}
	if ( $flowType == "resurs_bank_hosted" ) {
		return true;
	}

	return false;
}

/**
 * @param $classButtonHtml
 */
function resurs_omnicheckout_order_button_html( $classButtonHtml ) {
	global $woocommerce;
	if ( ! isResursOmni() ) {
		echo $classButtonHtml;
	}
}

/**
 * Payment methods validator for OmniCheckout
 *
 * @param $paymentGatewaysCheck
 *
 * @return null
 */
function resurs_omnicheckout_payment_gateways_check( $paymentGatewaysCheck ) {
	global $woocommerce;
	$paymentGatewaysCheck = $woocommerce->payment_gateways->get_available_payment_gateways();
	if ( is_array( $paymentGatewaysCheck ) ) {
		$paymentGatewaysCheck = array();
	}
	if ( ! count( $paymentGatewaysCheck ) ) {
		// If there is no active payment gateways except for omniCheckout, the warning of no available payment gateways has to be suppressed
		if ( isResursOmni() ) {
			return null;
		}

		return __( 'There are currently no payment methods available', 'WC_Payment_Gateway' );
	}

	return $paymentGatewaysCheck;
}

/**
 * Check if there are gateways active (Omni related)
 * @return bool
 */
function hasPaymentGateways() {
	global $woocommerce;
	$paymentGatewaysCheck = $woocommerce->payment_gateways->get_available_payment_gateways();
	if ( is_array( $paymentGatewaysCheck ) ) {
		$paymentGatewaysCheck = array();
	}
	if ( count( $paymentGatewaysCheck ) > 1 ) {
		return true;
	}

	return false;
}

/********************** OMNICHECKOUT RELATED ENDS HERE ******************/

function resurs_gateway_activation() {
	set_transient( 'ResursWooGatewayVersion', rbWcGwVersionToDecimals() );
}

if ( is_admin() ) {
	register_activation_hook( __FILE__, 'resurs_gateway_activation' );
}

/**
 * Returns true if demoshop-mode is enabled.
 * @return bool
 */
function isResursDemo() {
	$resursSettings = get_option( 'woocommerce_resurs-bank_settings' );
	$demoshopMode = isset($resursSettings['demoshopMode']) ? $resursSettings['demoshopMode'] : false;
	if ( $demoshopMode === "true" ) {
		return true;
	}
	if ( $demoshopMode === "yes" ) {
		return true;
	}
	if ( $demoshopMode === "false" ) {
		return false;
	}
	if ( $demoshopMode === "no" ) {
		return false;
	}
	return false;
}

/**
 * @param string $versionRequest
 * @param string $operator
 *
 * @return bool
 * @throws \Exception
 */
function hasWooCommerce( $versionRequest = "2.0.0", $operator = ">=" ) {
	if ( version_compare( WOOCOMMERCE_VERSION, $versionRequest, $operator ) ) {
		return true;
	}
}

/**
 * @param string $checkVersion
 *
 * @return bool
 */
function isWooCommerce3($checkVersion = '3.0.0') {
	return hasWooCommerce( $checkVersion );
}

isResursSimulation();
