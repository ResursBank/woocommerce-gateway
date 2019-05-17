<?php

/**
 * Class ResursBank_PreCore Class that will be fully implemented in v3, built for special usages in v2.2
 */
class ResursBank3_PreCore
{
    /**
     * @param $isCheckout
     * @param bool $storePrior
     * @return bool
     * @throws Exception
     */
    private static function setCustomerPageTrack($isCheckout)
    {
        $CORE = self::getResursCore();

        $omniRef = $CORE->getSession('omniRef');
        $currentWcOrderId = wc_get_order_id_by_payment_id($omniRef);
        //if (!$isCheckout && !empty($currentWcOrderId)) {

        // We'll land here if the page are reloaded. Use above if-condition
        // if you don't want to se this on reloads.
        if (!empty($currentWcOrderId)) {
            // If there is a recent order but the customer is about to leave the checkout,
            // make sure the updatePaymentReference can rerun.
            delete_post_meta($currentWcOrderId, 'updateResursReferenceSuccess');
        }

        $isCheckout = apply_filters('resursbank_location_last_checkout', $isCheckout);
        $CORE->setSession('resursbank_location_last_checkout', $isCheckout);

        return true;
    }

    /**
     * @return bool
     */
    private function isSession()
    {
        global $woocommerce;

        $return = false;
        if (isset($woocommerce->session)) {
            $return = true;
        }

        return $return;
    }

    /**
     * @param $key
     * @param $value
     */
    public function setSession($key, $value)
    {
        if ($this->isSession()) {
            WC()->session->set($key, $value);
        } else {
            $_SESSION[$key] = $value;
        }
    }

    /**
     * @param $key
     * @return array|mixed|string
     */
    public function getSession($key)
    {
        $return = null;

        if ($this->isSession()) {
            $return = WC()->session->get($key);
        } else {
            if (isset($_SESSION[$key])) {
                $return = $_SESSION[$key];
            }
        }

        return $return;
    }


    //// STATICS

    /**
     * Tell session when customer is outside checkout and store the value
     *
     * @return bool
     * @throws Exception
     */
    public static function setCustomerIsOutsideCheckout()
    {
        return self::setCustomerPageTrack(false);
    }

    /**
     * Tell session when customer is really in the checkout.
     *
     * @return bool
     * @throws Exception
     */
    public static function setCustomerIsInCheckout()
    {
        return self::setCustomerPageTrack(true);
    }

    /**
     * @return ResursBank3_PreCore
     */
    public static function getResursCore()
    {
        return new ResursBank3_PreCore();
    }
}


// Plugin-v3 triggers
// Trigger precense from checkout and store historically in a session.
add_action('woocommerce_before_checkout_form', 'ResursBank3_PreCore::setCustomerIsInCheckout');

// Trigger absence from checkout and store historically in a session.
add_action('woocommerce_add_to_cart', 'ResursBank3_PreCore::setCustomerIsOutsideCheckout');
