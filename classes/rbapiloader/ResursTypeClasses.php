<?php

namespace Resursbank\RBEcomPHP;

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
    const METHOD_CHECKOUT = 3;

    /**
     * @deprecated 1.0.0 Use METHOD_CHECKOUT instead
     */
    const METHOD_OMNI = 3;
    /**
     * @deprecated 1.0.0 Use METHOD_CHECKOUT instead
     */
    const METHOD_RESURSCHECKOUT = 3;
}

/**
 * Class ResursCountry
 * @since 1.0.2
 */
abstract class ResursCountry {
    const COUNTRY_UNSET = 0;
    const COUNTRY_SE = 1;
    const COUNTRY_DK = 2;
    const COUNTRY_NO = 3;
    const COUNTRY_FI = 4;
}

/**
 * Class ResursOmniCallTypes
 * Omnicheckout callback types
 * @since 1.0.2
 */
abstract class ResursCheckoutCallTypes
{
    const METHOD_PAYMENTS = 0;
    const METHOD_CALLBACK = 1;
}

/**
 * Class ResursCallbackTypes Callbacks that can be registered with Resurs Bank.
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
    const ANNUL = 4;
    const AUTHORIZE = 8;

    /** @deprecated */
    const UPDATE = 16;
}

/**
 * Class ResursCurlMethods (Those are types, but class is not named as this)
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
 * Class ResursCallbackReachability While using external controls on url reachability, this is required (also types)
 */
abstract class ResursCallbackReachability
{
	const IS_REACHABLE_NOT_AVAILABLE = 0;
	const IS_FULLY_REACHABLE = 1;
	const IS_REACHABLE_WITH_PROBLEMS = 2;
	const IS_NOT_REACHABLE = 3;
	const IS_REACHABLE_NOT_KNOWN = 4;
}