<?php

use Resursbank\Ecommerce\Types\OrderStatus;
use Resursbank\RBEcomPHP\ResursBank;
use TorneLIB\Module\Network\NetWrapper;
use TorneLIB\Utils\Generic;

/**
 * Get specific options from the Resurs configuration set
 *
 * @param string $key
 * @param string $namespace
 * @return bool
 * @deprecated Use getResursOption
 */
function resursOption($key = "", $namespace = "woocommerce_resurs-bank_settings")
{
    return getResursOption($key, $namespace);
}

/**
 * Returns true or false depending if the key exists in the resursOption-array
 *
 * @param string $key
 *
 * @return bool
 */
function issetResursOption($key = "", $namespace = 'woocommerce_resurs-bank_settings')
{
    $response = get_option($namespace);
    if (isset($response[$key])) {
        return true;
    } else {
        return false;
    }
}

function getResursDemoOption($key)
{
    // Override database setting with the theme (demoshops) flowtype SESSION setting if it's set.
    if ($key == "flowtype") {
        if (!empty($_SESSION['rb_checkout_flow'])) {
            $accepted = ['simplifiedshopflow', 'resurs_bank_hosted', 'resurs_bank_omnicheckout'];
            if (in_array(strtolower($_SESSION['rb_checkout_flow']), $accepted)) {
                return $_SESSION['rb_checkout_flow'];
            }
        }
    }

    // Override database setting with the theme (demoshops) country SESSION setting if it's set.
    if ($key == "country") {
        if (!empty($_SESSION['rb_country'])) {
            $accepted = ['se', 'dk', 'no', 'fi'];
            if (in_array(strtolower($_SESSION['rb_country']), $accepted)) {
                return strtoupper($_SESSION['rb_country']);
            }
        }
    }

    if ($key == 'login') {
        if (!empty($_SESSION['rb_country_data'])) {
            return $_SESSION['rb_country_data']['account']['login'];
        }
    }

    if ($key == 'password') {
        if (!empty($_SESSION['rb_country_data'])) {
            return $_SESSION['rb_country_data']['account']['password'];
        }
    }
}

/**
 * @param string $key
 * @param string $namespace
 *
 * @return bool
 */
function getResursOption($key = "", $namespace = "woocommerce_resurs-bank_settings")
{
    $getOptionsNamespace = get_option($namespace);
    // Going back to support PHP 5.3 instead of 5.4+
    if (isset($getOptionsNamespace[$key])) {
        $response = $getOptionsNamespace[$key];
    } else {
        // No value set
        $response = null;

        $notsetGetDefaultValue = resursFormFieldArray($namespace);
        if (isset($notsetGetDefaultValue[$key]) && isset($notsetGetDefaultValue[$key]['default'])) {
            $response = $notsetGetDefaultValue[$key]['default'];
        }
    }

    if (empty($response)) {
        $response = get_option($key);
    }
    if ($response === 'true') {
        return true;
    }
    if ($response === 'false') {
        return false;
    }
    if ($response === 'yes') {
        return true;
    }
    if ($response === 'no') {
        return false;
    }

    $filteredResponse = apply_filters('resurs_option', $response, $key);
    if (!is_null($filteredResponse) && $response !== $filteredResponse) {
        $response = $filteredResponse;
    }

    return $response;
}

/**
 * Function used to figure out whether values are set or not
 *
 * @param string $key
 *
 * @return bool
 */
function hasResursOptionValue($key = "", $namespace = 'woocommerce_resurs-bank_settings')
{
    $optionValues = get_option($namespace);
    if (isset($optionValues[$key])) {
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
function setResursOption($key = "", $value = "", $configurationSpace = "woocommerce_resurs-bank_settings")
{
    $allOptions = get_option($configurationSpace);
    if (!empty($key)) {
        $allOptions[$key] = $value;
        update_option($configurationSpace, $allOptions);

        return true;
    }

    return false;
}

/**
 * Decide where and what Resurs plugin are allowed to interfere with.
 * This control is connected to the initial function of the plugin after plugins_loaded and controls where in
 * the is_admin structure the plugin should be working or prevented to be present:
 *      if (allowPluginToRun()) {
 *
 * @return bool|mixed|void
 */
function allowPluginToRun()
{
    // Always allow this plugin to be alive.
    $return = true;

    $info = [
        'action' => isset($_REQUEST['action']) ? $_REQUEST['action'] : '',
        'page' => isset($_REQUEST['page']) ? $_REQUEST['page'] : '',
        'post_type' => isset($_REQUEST['post_type']) ? $_REQUEST['post_type'] : '',
    ];

    if (is_admin() && getResursOption('preventGlobalInterference')) {
        // At this point, we know that we're in wp-admin, so from here we can decide whether the plugin should
        // be present, regardless of what WooCommerce thinks (mind the edit-theme-plugin-file parts).
        $return = apply_filters('allow_resurs_run', $return, $info);
    }

    return $return;
}

/**
 * This function is mainly used to assure that payment methods only have one customer type available and therefore
 * potentially should be visible in a checkout, when customerType is lacking the validation.
 *
 * @return bool
 * @since 2.2.91
 */
function hasDualCustomerTypes() {
    $transients = get_transient('resursTemporaryPaymentMethods');

    if (empty($transients)) {
        // No transients = no data = broken.
        return false;
    }
    $return = false;

    $methodList = unserialize($transients);
    $customerTypes = [];
    foreach ($methodList as $method) {
        if (!isset($method->customerType)) {
            continue;
        }
        $id = $method->id;
        $curEnableState = getResursOption(
            'enabled',
            sprintf(
                'woocommerce_resurs_bank_nr_%s_settings',
                $id
            )
        );
        if (!$curEnableState) {
            continue;
        }

        $customerType = (array)$method->customerType;

        foreach ($customerType as $type) {
            if (!empty($type) && !in_array($type, $customerTypes)) {
                $customerTypes[] = $type;
            }
            if (count($customerTypes) > 1) {
                $return = true;
                break;
            }
        }
    }

    return $return;
}

/**
 * @return array
 */
function getResursAllowedLocations()
{
    return [
        'wc-settings',
        'shop_order',
        'edit',
        'get_priceinfo_ajax',
        'get_address_customertype',
    ];
}

/**
 * @return array|WP_Post|null
 */
function getResursAllowedEditor()
{
    $post = null;
    if (isset($_REQUEST['post']) && is_numeric($_REQUEST['post'])) {
        $post = get_post($_REQUEST['post']);
    }
    return $post;
}

/**
 * wp-admin interference checks.
 *
 * This method tells the plugin when it can skip its own presence in wp-admin. This method is only called with
 * the setting preventGlobalInterference is active.
 *
 * @param bool $allow Current inbound allow state.
 * @param array $informationSet Very basic requests from _REQUEST and _POST parameters that could easily be analyzed.
 * @return bool If true, the plugin is allowed to proceed.
 * @noinspection ParameterDefaultValueIsNotNullInspection
 */
function allowResursRun($allow = false, $informationSet = [])
{
    if (in_array($informationSet['action'], getResursAllowedLocations(), true) ||
        in_array($informationSet['page'], getResursAllowedLocations(), true) ||
        in_array($informationSet['post_type'], getResursAllowedLocations(), true)
    ) {
        $allow = true;
    }
    // Normally accept ajax actions. Discovered that "forgotten actions" could fail during this run.
    if (!empty($informationSet['action'])) {
        $allow = true;
    }
    if (stripos($informationSet['action'], 'woocommerce') !== false) {
        $allow = true;
    }

    $post = getResursAllowedEditor();
    if (is_object($post) && method_exists($post, 'post_type')) {
        // Executes our internal filter method, setting $allow to false if in "product"-editor.
        $allow = apply_filters('prevent_resurs_run_on_post_type', $allow, $post->post_type);
    }
    // Allow everything during usage of our own backend, if they ever reach this method.
    if (isset($_REQUEST['wc-api']) && !$allow) {
        $allow = true;
    }

    return $allow;
}

/**
 * @param $allow
 * @param $postType
 * @return false|mixed
 */
function resursPreventPostType($allow, $postType)
{
    if ($postType === 'product') {
        $allow = false;
    }

    return $allow;
}

/**
 * Returns true if demoshop-mode is enabled.
 *
 * @return bool
 * @deprecated Use isResursTest instead!
 */
function isResursDemo()
{
    return isResursTest();
}

/**
 * @param string $key
 * @return bool|mixed|void
 */
function omniOption($key = '')
{
    $response = getResursOption($key, 'woocommerce_resurs_bank_omnicheckout_settings');

    return $response;
}

/**
 * Add notification on misconfigured filters.
 */
function notify_resurs_admin_parts_disabled()
{
    // Payment methods for simplified/hosted/RCO is normally true initially.
    $simplifiedEnabled = apply_filters('resurs_bank_simplified_checkout_methods', true);
    $omniEnabled = apply_filters('resurs_bank_simplified_checkout_methods', true);
    if ((!$simplifiedEnabled || !$omniEnabled)) {
        // Warn about remotely disabled methods.
        echo '<div class="error notice resursAdminPartsDisabled">
            ' .
            __(
                'An external plugin has partially disabled information about payment methods in your Resurs ' .
                'Bank admin console. If this is correct, ignore this message.',
                'resurs-bank-payment-gateway-for-woocommerce'
            ) .
            '</div>';
    }
}

/**
 * Defines what metadata in the order data section that is protected (= not visible).
 * @param $protected
 * @param $metaKey
 * @param $metaType
 * @return bool
 * @since 2.2.41
 */
function resurs_protected_meta_data($protected, $metaKey, $metaType)
{
    // You may like to fetch this data and display it elsewhere.
    $protectMeta = getResursProtectedMetaData();

    if (isset($protectMeta[$metaKey])) {
        $protected = true;
    }

    return $protected;
}

/**
 * What we hide in metadata section.
 *
 * @return array
 * @since 2.2.41
 */
function getResursProtectedMetaData()
{
    return [
        'hasCallbackAUTOMATIC_FRAUD_CONTROL' => 'Callback AUTOMATIC_FRAUD_CONTROL',
        'hasCallbackBOOKED' => 'Callback BOOKED',
        'hasCallbackUNFREEZE' => 'Callback UNFREEZE',
        'hasCallbackUPDATE' => 'Callback UPDATE',
        'hasCallbackANNULMENT' => 'Callback ANNULMENT',
        'hasCallbackFINALIZATION' => 'Callback FINALIZATION',
        'orderBookStatus' => __(
            'Statuses received from Resurs Bank API',
            'resurs-bank-payment-gateway-for-woocommerce'
        ),
        'paymentId' => 'paymentId',
        'hosted_redirect_time' => 'Hosted Flow Redirect Time',
        'resursBankMetaPaymentMethod' => 'Payment Method',
        'resursBankMetaPaymentMethodSpecificType' => 'SpecificType',
        'resursBankMetaPaymentMethodType' => 'Type',
        'resursBankMetaPaymentStoredVatData' => 'VAT Data',
        'resursBankPaymentFlow' => __(
            'Payment flow',
            'resurs-bank-payment-gateway-for-woocommerce'
        ),
        'orderDenied' => 'orderDenied',
        'omniPaymentMethod' => __(
            'Resurs Checkout Payment Method',
            'resurs-bank-payment-gateway-for-woocommerce'
        ),
        'paymentIdLast' => __(
            'Last used payment method',
            'resurs-bank-payment-gateway-for-woocommerce'
        ),
        'RcoProcessPaymentStart' => __(
            'Start time for RCO purchase',
            'resurs-bank-payment-gateway-for-woocommerce'
        ),
        'RcoProcessPaymentEnd' => __(
            'End time for RCO purchase',
            'resurs-bank-payment-gateway-for-woocommerce'
        ),
    ];
}

/**
 * VAT data storage returns information about how a recent order has been handled.
 * @param $id
 * @return array|mixed|string
 * @since 2.2.41
 */
function getResursStoredPaymentVatData($id, $key = '')
{
    $storedVatData = [
        'coupons_include_vat' => getResursOption('coupons_include_vat'),
        'meta_available' => false,
    ];

    try {
        $metaConfiguration = unserialize(getResursPaymentMethodMeta($id, 'resursBankMetaPaymentStoredVatData'));
    } catch (Exception $e) {
        // In case the passed string is not unserializeable, FALSE is returned and E_NOTICE is issued.
        // And if possible, make silence out of that.
        $metaConfiguration = null;
    }
    if (is_array($metaConfiguration)) {
        $metaConfiguration['meta_available'] = true;
        $return = $metaConfiguration;
    } else {
        $return = $storedVatData;
    }

    if (!empty($key) && isset($return[$key])) {
        return $return[$key];
    }

    return $return;
}

/**
 * Extends number of calculated decimals in a price on demand.
 * Filter is disabled for the moment, but can be activated if we need to round up differently.
 * @param $currentValue
 * @return mixed
 */
function resurs_order_price_decimals($currentValue)
{
    global $resurs_is_payment_spec;

    if ((bool)$resurs_is_payment_spec === true) {
        $currentValue = 5;
    }

    return $currentValue;
}

add_action('admin_notices', 'notify_resurs_admin_parts_disabled');

/**
 * @return bool|null
 */
function resurs20StartSession()
{
    return getResursOption('resursbank_start_session_before');
}

/**
 * @return bool|null
 */
function resurs20StartSessionAdmin()
{
    return getResursOption('resursbank_start_session_outside_admin_only');
}

/**
 * Fetch a "correct" order from Resurs Bank during cancellation checks depending on settings.
 *
 * @param ResursBank $theFlow
 * @param $order
 * @return mixed|null
 */
function getResursUnpaidCancellationOrder($theFlow, $order)
{
    $rPayment = null;
    try {
        $ref = wc_get_payment_id_by_order_id($order->get_id());
        if (!getResursOption('postidreference')) {
            $rPayment = $theFlow->getPayment($ref);
        } else {
            $rPayment = $theFlow->getPayment($order->get_id());
        }
    } catch (Exception $e) {
        // Ignore and proceed.
    }

    return $rPayment;
}

/**
 * Make the plugin NOT cancel active orders during checks for stock reservations that should be cancelled.
 *
 * @param int $checkout_order_get_created_via
 * @param WC_Order $order
 * @return mixed
 * @throws Exception
 */
function getResursUnpaidCancellationControl($checkout_order_get_created_via, $order)
{
    $canCancelMeta = $order->get_meta('ResursUnpaidCancellationControl');

    if (empty($canCancelMeta)) {
        $theFlow = initializeResursFlow();
        $rPayment = getResursUnpaidCancellationOrder($theFlow, $order);

        // Only add metadata once.
        setResursOrderMetaData(
            $order->get_id(),
            'ResursUnpaidCancellationControl',
            $checkout_order_get_created_via
        );

        // Only add notes once.
        if ($rPayment !== null && $theFlow->canDebit($rPayment)) {
            $checkout_order_get_created_via = 0;
            try {
                $order->update_status(
                    'on-hold',
                    __(
                        '[Resurs Bank] A process that handles stock reservations and automatic cancellation of ' .
                        'unhandled orders has discovered that this order is active at Resurs Bank. It should ' .
                        'probably not be cancelled even if WooCommerce suggested this, so it has been placed ' .
                        'in On-Hold for you.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    true
                );
            } catch (Exception $e) {
            }
        }
    }

    return $checkout_order_get_created_via;
}

/**
 * Generic admin notices.
 */
function getRbAdminNotices()
{
    if (!isset($_SESSION['rb2'])) {
        $_SESSION['rb2']['exception'] = [];
    } elseif (!isset($_SESSION['rb2']['exception'])) {
        $_SESSION['rb2']['exception'] = [];
    }

    if (is_admin() && !getResursOption('enabled')) {
        $_SESSION['rb2']['exception']['plugin_disabled'] = sprintf(
            __(
                'The plugin <i>"%s"</i> is currently <b>set to disabled</b> in the Resurs Bank WooCommerce' .
                'Control panel. This means that the plugin may not function properly. If you need it, make sure ' .
                'it is properly enabled before start using it.'
            ),
            'resurs-bank-payment-gateway-for-woocommerce'
        );
    }

    foreach ($_SESSION['rb2']['exception'] as $exceptionType => $exceptionString) {
        $class = 'notice notice-error is-dismissible';
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), $exceptionString);
    }
    if (isset($_SESSION['rb2']['exception']['plugin_disabled'])) {
        unset($_SESSION['rb2']['exception']['plugin_disabled']);
    }
    if (isset($_SESSION['rb2']['exception']['improper_version'])) {
        unset($_SESSION['rb2']['exception']['improper_version']);
    }
}

/**
 * @param string $dataContent
 * @return array
 * @2.2.89
 */
function rbSplitPostData($dataContent = '')
{
    $return = [];

    preg_match_all("/(.*?)\&/", $dataContent, $extraction);
    if (isset($extraction[1])) {
        foreach ($extraction[1] as $postDataVars) {
            $exVars = explode('=', $postDataVars, 2);
            $return[$exVars[0]] = $exVars[1];
        }
    }

    return $return;
}

/**
 * Check for version conflicts.
 */
function resursExpectVersions()
{
    $genericRequest = new Generic();
    $expectReleases = [
        ResursBank::class => '1.3.70',
        NetWrapper::class => '6.1.5',
    ];
    $genericRequest->setExpectedVersions(
        $expectReleases
    );

    if (is_admin() && getResursOption('enabled')) {
        $versionProblems = [];
        try {
            $genericRequest->getExpectedVersions(false);
        } catch (Exception $e) {
            $versionProblems[] = $e->getMessage();
        }
        if (is_admin() && session_status() === PHP_SESSION_NONE) {
            session_start();
            // Start session, but do not show $versionProblems if it is empty.
            if (count($versionProblems)) {
                $expectList = $genericRequest->getExpectationList();
                $whatYouHave = $genericRequest->getExpectationsReal();
                $expectationList = [];
                foreach ($expectList as $name => $version) {
                    if (isset($whatYouHave[$name])) {
                        if ($whatYouHave[$name] === $version) {
                            continue;
                        }
                        $expectationList[] = sprintf(
                            '<b>%s:</b> %s (<b>Have:</b> %s)',
                            $name,
                            $version,
                            $whatYouHave[$name]
                        );
                    } else {
                        $expectationList[] = sprintf('<b>%s:</b> %s', $name, $version);
                    }
                }
                if (count($expectationList)) {
                    $_SESSION['rb2']['exception']['improper_version'] = sprintf(
                        __(
                            'There is a problem with your platform: The libraries that this plugin is using is ' .
                            'probably colliding with an older version. This can cause problems with your platform as ' .
                            'important features could be missing.<br><br><b>Expected versions:</b><br>%s.',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        implode('<br>', $expectationList)
                    );
                }
            }
        }
    }
}

/**
 * Queued status handler. Should not be called directly as it is based on WC_Queue.
 *
 * @param int $orderId
 * @param mixed $resursId
 * @since Imported
 * @see https://github.com/woocommerce/woocommerce/wiki/WC_Queue---WooCommerce-Worker-Queue
 * @see https://github.com/Tornevall/wpwc-resurs/commit/6a7e44f5cdeb24a59c9b0e8fa3f2150b9f598e5c
 */
function updateQueuedOrderStatus(int $orderId, $resursId)
{
    if ($orderId && !empty($resursId)) {
        try {
            if ($orderId instanceof WC_Order) {
                $orderId = $orderId->get_id();
            }

            $properOrder = new WC_Order($orderId);

            $flow = initializeResursFlow();
            $paymentStatusList = rb_order_status_array();
            /** @var int $suggestedStatus */
            $suggestedStatus = $flow->getOrderStatusByPayment(
                $resursId
            );
            $suggestedString = $flow->getOrderStatusStringByReturnCode($suggestedStatus);
            $currentStatus = $properOrder->get_status();

            rbSimpleLogging(
                'Payment Status List:'
            );
            rbSimpleLogging(print_r($paymentStatusList, true));
            rbSimpleLogging(
                sprintf(
                    'updateQueuedOrderStatus for %d (%s), suggested: %s (%s), current is %s.',
                    $orderId,
                    $resursId,
                    $suggestedStatus,
                    $suggestedString,
                    $currentStatus
                )
            );

            if ($currentStatus === $suggestedStatus) {
                $alreadySetString = __(
                    '[Resurs Bank] Update order request ignored since the suggested status is already set.'
                );
                rbSimpleLogging($alreadySetString);
                $properOrder->add_order_note($alreadySetString);
                return;
            }

            switch (true) {
                case $suggestedStatus & OrderStatus::PENDING:
                    $properOrder->update_status(
                        $paymentStatusList[OrderStatus::PENDING],
                        sprintf(
                            '[Resurs Bank] Queued order status updated to %s.',
                            $suggestedString
                        )
                    );
                    break;
                case $suggestedStatus & OrderStatus::PROCESSING:
                    if (isResursFrozenNote($resursId, $properOrder, true)) {
                        break;
                    }
                    $properOrder->update_status(
                        $paymentStatusList[OrderStatus::PROCESSING],
                        sprintf(
                            '[Resurs Bank] Queued order status updated to %s.',
                            $suggestedString
                        )
                    );
                    break;
                case $suggestedStatus & OrderStatus::CREDITED:
                    $properOrder->update_status(
                        $paymentStatusList[OrderStatus::CREDITED],
                        sprintf(
                            '[Resurs Bank] Queued order status updated to %s.',
                            $suggestedString
                        )
                    );
                    break;
                case $suggestedStatus & OrderStatus::ANNULLED:
                    rbSimpleLogging(
                        sprintf(
                            'Resurs Bank annulled order %s.',
                            $properOrder->get_id()
                        )
                    );

                    $properOrder->update_status(
                        $paymentStatusList[OrderStatus::ANNULLED],
                        __(
                            'Resurs Bank annulled the order',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        )
                    );
                    break;
                case $suggestedStatus & (OrderStatus::COMPLETED | OrderStatus::AUTO_DEBITED):
                    if (isResursFrozenNote($resursId, $properOrder, true)) {
                        break;
                    }
                    if ($suggestedStatus & (OrderStatus::AUTO_DEBITED)) {
                        $autoDebitStatus = getResursOption('autoDebitStatus');
                        if ($autoDebitStatus === 'default' || empty($autoDebitStatus)) {
                            $properOrder->update_status(
                                $paymentStatusList[OrderStatus::COMPLETED],
                                sprintf(
                                    '[Resurs Bank] Queued order status updated to %s.',
                                    $suggestedString
                                )
                            );
                        } else {
                            $properOrder->update_status(
                                $autoDebitStatus,
                                sprintf(
                                    '[Resurs Bank] Queued order status updated to %s.',
                                    $suggestedString
                                )
                            );
                        }
                    } else {
                        $properOrder->update_status(
                            $paymentStatusList[OrderStatus::COMPLETED],
                            sprintf(
                                '[Resurs Bank] Queued order status updated to %s.',
                                $suggestedString
                            )
                        );
                    }

                    break;
                default:
                    break;
            }
        } catch (Exception $e) {
            rbSimpleLogging(print_r($e, true));
        }
    }
}

/**
 * The dumb way to avoid duplicate code for ordernotes when orders are frozem.
 * @param $resursId
 * @param $properOrder
 * @param $ignoreFrozen
 * @return bool
 * @throws Exception
 */
function isResursFrozenNote($resursId, $properOrder, $ignoreFrozen) {
    $return = false;
    $flow = initializeResursFlow();
    if ($ignoreFrozen && $flow->isFrozen($resursId)) {
        $properOrder->add_order_note(
            __(
                '[Resurs Bank] Update order request ignored due to frozen status.'
            )
        );
        $return = true;
    }
    return $return;
}

/**
 * @param $orderId
 * @param $status
 * @since 2.2.91
 */
function updateResursOrderStatusActions($orderId, $status)
{
    if ($orderId) {
        $currentOrder = new WC_Order($orderId);
        if (($currentOrder instanceof WC_Order) && $status === 'completed') {
            $currentOrder->payment_complete();
            rbSimpleLogging(
                sprintf('Order %d is completed: payment_complete() triggered!', $orderId)
            );
        }
    }
}

/**
 * @return array
 */
function rb_order_status_array()
{
    $autoFinalizationString = getResursOption('autoDebitStatus');
    return [
        OrderStatus::PROCESSING => 'processing',
        OrderStatus::CREDITED => 'refunded',
        OrderStatus::COMPLETED => 'completed',
        OrderStatus::AUTO_DEBITED => $autoFinalizationString !== 'default' ? $autoFinalizationString : 'completed',
        OrderStatus::PENDING => 'on-hold',
        OrderStatus::ANNULLED => 'cancelled',
        OrderStatus::ERROR => 'on-hold',
    ];
}

/**
 * Replaces the first implemented logging features with WooCommerce built-ins.
 *
 * @param string $logMessage
 * @param string $from
 * @see WOO-605
 * @noinspection ParameterDefaultValueIsNotNullInspection
 * @since 2.2.89
 */
function rbSimpleLogging($logMessage, $from = '')
{
    if (!class_exists('WC_Logger')) {
        return;
    }
    $logger = new WC_Logger();

    if (empty($from)) {
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $from = $_SERVER['REMOTE_ADDR'];
        } else {
            $from = 'CONSOLE';
        }
    }

    $formatted = sprintf('[ResursBank] (%s): %s', $from, $logMessage);

    $logger->info(
        $formatted,
        []
    );
}

/**
 * Method to rewrite classes on the fly in case they are suddenly missing.
 * Returns silent successful status boolean afterwards.
 * @return bool
 * @since 2.2.94
 */
function rewriteMethodsOnFly() {
    $return = false;

    try {
        $methodList = unserialize(get_transient('resursTemporaryPaymentMethods'));

        // Only rewrite class files on the fly if there are methods currently stored in the database.
        // By doing this, we do not risk unconfigured systems where payment methods are not yet fetched.
        if (is_array($methodList) && count($methodList)) {
            // idMerchant is here due to row 1188 (currently) in resursbank_settings.php for which the admin panel
            // is writing the data properly.
            $idMerchant = 0;
            $successFiles = 0;
            foreach ($methodList as $method) {
                if (write_resurs_class_to_file($method, $idMerchant)) {
                    $successFiles++;
                }
                $idMerchant++;
            }
            if ($successFiles) {
                $return = true;
                rbSimpleLogging(
                    'PaymentMethodList Classes was rendered on demand successfully.'
                );
            } else {
                rbSimpleLogging(
                    'Coud not write paymentmethod classes into class path.'
                );
            }
        }
    } catch (Exception $e) {
        rbSimpleLogging(
            sprintf(
                'PaymentMethodList Class Renderer Exception (%s): %s.',
                $e->getCode(),
                $e->getMessage()
            )
        );
    }

    return $return;
}

/**
 * @param $message
 * @return string
 * @noinspection PhpUnused
 * @since 2.2.94
 */
function resursHasNoMethods($message) {
    $message .= '<br>' . __(
        'The Resurs Bank module was unable to update the methods.',
        'resurs-bank-payment-gateway-for-woocommerce'
    );

    return $message;
}

add_filter('woocommerce_cancel_unpaid_order', 'getResursUnpaidCancellationControl', 10, 2);
add_filter('resursbank_start_session_before', 'resurs20StartSession');
add_filter('resursbank_start_session_outside_admin_only', 'resurs20StartSessionAdmin');
add_action('resursbank_update_queued_status', 'updateQueuedOrderStatus', 10, 2);
add_action('resurs_bank_order_status_update', 'updateResursOrderStatusActions', 10, 2);
