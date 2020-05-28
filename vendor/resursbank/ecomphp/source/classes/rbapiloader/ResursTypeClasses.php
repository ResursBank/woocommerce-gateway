<?php

namespace Resursbank\RBEcomPHP;

/**
 * Class RESURS_FLOW_TYPES
 *
 * @since 1.0.26
 * @since 1.1.26
 * @since 1.2.0
 */
abstract class RESURS_FLOW_TYPES
{
    const NOT_SET = 0;
    const SIMPLIFIED_FLOW = 1;
    const HOSTED_FLOW = 2;
    const RESURS_CHECKOUT = 3;

    /** @var int You lazy? */
    const RCO = 3;

    /** @var int Absolutely minimalistic flow with data necessary to render anything at all (and matching data) */
    const MINIMALISTIC = 98;

    /** @deprecated Redundant name */
    const FLOW_NOT_SET = 0;
    /** @deprecated Redundant name */
    const FLOW_SIMPLIFIED_FLOW = 1;
    /** @deprecated Redundant name */
    const FLOW_HOSTED_FLOW = 2;
    /** @deprecated Redundant name */
    const FLOW_RESURS_CHECKOUT = 3;

    /** @deprecated Redundant name */
    const FLOW_MINIMALISTIC = 98;
}

/**
 * Class RESURS_COUNTRY Country selector
 * @since 1.0.26
 * @since 1.1.26
 * @since 1.2.0
 */
abstract class RESURS_COUNTRY
{
    const SE = 1;
    const DK = 2;
    const NO = 3;
    const FI = 4;

    /** @deprecated Redundant name */
    const COUNTRY_NOT_SET = 0;
    /** @deprecated Redundant name */
    const COUNTRY_SE = 1;
    /** @deprecated Redundant name */
    const COUNTRY_DK = 2;
    /** @deprecated Redundant name */
    const COUNTRY_NO = 3;
    /** @deprecated Redundant name */
    const COUNTRY_FI = 4;
}

/**
 * Class RESURS_CHECKOUT_CALL_TYPES
 * @since 1.0.26
 * @since 1.1.26
 * @since 1.2.0
 * @deprecated Never in use
 */
abstract class RESURS_CHECKOUT_CALL_TYPES
{
    const METHOD_PAYMENTS = 0;
    const METHOD_CALLBACK = 1;
}

/**
 * Class RESURS_CALLBACK_TYPES Bitmask based types so that more than one type can be chosen in one call.
 * Callback type "not set" is not compatible in bitmask mode.
 *
 * @since 1.0.26
 * @since 1.1.26
 * @since 1.2.0
 * @link https://test.resurs.com/docs/x/LAAF
 */
abstract class RESURS_CALLBACK_TYPES
{
    const NOT_SET = 0;
    const UNFREEZE = 1;
    const ANNULMENT = 2;
    const AUTOMATIC_FRAUD_CONTROL = 4;
    const FINALIZATION = 8;
    const TEST = 16;
    const UPDATE = 32;
    const BOOKED = 64;

    /** @deprecated Use NOT_SET */
    const CALLBACK_TYPE_NOT_SET = 0;
    /** @deprecated Use UNFREEZE */
    const CALLBACK_TYPE_UNFREEZE = 1;
    /** @deprecated Use ANNULMENT */
    const CALLBACK_TYPE_ANNULMENT = 2;
    /** @deprecated Use AUTOMATIC_FRAUD_CONTROL */
    const CALLBACK_TYPE_AUTOMATIC_FRAUD_CONTROL = 4;
    /** @deprecated Use FINALIZATION */
    const CALLBACK_TYPE_FINALIZATION = 8;
    /** @deprecated Use TEST */
    const CALLBACK_TYPE_TEST = 16;
    /** @deprecated Use UPDATE */
    const CALLBACK_TYPE_UPDATE = 32;
    /** @deprecated Use BOOKED */
    const CALLBACK_TYPE_BOOKED = 64;
}

/**
 * Class RESURS_AFTERSHOP_RENDER_TYPES Bitmask based types so that more than one type can be chosen in one call
 * @since 1.0.26
 * @since 1.1.26
 * @since 1.2.0
 */
abstract class RESURS_AFTERSHOP_RENDER_TYPES
{
    const NONE = 0;
    const FINALIZE = 1;
    const CREDIT = 2;
    const ANNUL = 4;
    const AUTHORIZE = 8;

    /** @deprecated Redundant name */
    const AFTERSHOP_NO_CHOICE = 0;
    /** @deprecated Redundant name */
    const AFTERSHOP_FINALIZE = 1;
    /** @deprecated Redundant name */
    const AFTERSHOP_CREDIT = 2;
    /** @deprecated Redundant name */
    const AFTERSHOP_ANNUL = 4;
    /** @deprecated Redundant name */
    const AFTERSHOP_AUTHORIZE = 8;
}

/**
 * Class RESURS_METAHASH_TYPES
 */
abstract class RESURS_METAHASH_TYPES
{
    const HASH_DISABLED = 0;
    const HASH_ORDERLINES = 1;
    const HASH_CUSTOMER = 2;
}


/**
 * Class RESURS_CURL_METHODS Curl HTTP methods
 *
 * @since 1.0.26
 * @since 1.1.26
 * @since 1.2.0
 */
abstract class RESURS_CURL_METHODS
{
    const GET = 0;
    const POST = 1;
    const PUT = 2;
    const DELETE = 3;

    /** @deprecated Redundant name */
    const METHOD_GET = 0;
    /** @deprecated Redundant name */
    const METHOD_POST = 1;
    /** @deprecated Redundant name */
    const METHOD_PUT = 2;
    /** @deprecated Redundant name */
    const METHOD_DELETE = 3;
}

/**
 * Class RESURS_CALLBACK_REACHABILITY External API callback URI control codes
 * @since 1.0.26
 * @since 1.1.26
 * @since 1.2.0
 */
abstract class RESURS_CALLBACK_REACHABILITY
{
    const IS_REACHABLE_NOT_AVAILABLE = 0;
    const IS_FULLY_REACHABLE = 1;
    const IS_REACHABLE_WITH_PROBLEMS = 2;
    const IS_NOT_REACHABLE = 3;
    const IS_REACHABLE_NOT_KNOWN = 4;
}

/**
 * Class RESURS_PAYMENT_STATUS_RETURNCODES Order status return codes
 *
 * Changed values to bitmasked data as of 1.3.14 (1.1.41 + 1.0.41), so we can fetch
 * multiple values in one request.
 *
 * @since 1.0.26
 * @since 1.1.26
 * @since 1.2.0
 * @link https://test.resurs.com/docs/x/QwH1 EComPHP: Instant FINALIZATION / Bitmasking constants
 * @link https://test.resurs.com/docs/x/KAH1 EComPHP: Bitmasking features
 */
abstract class RESURS_PAYMENT_STATUS_RETURNCODES
{
    /**
     * Skip "not in use" since this off-value may cause flaws in status updates (true when matching false flags).
     */
    const NOT_IN_USE = 0;
    const PAYMENT_PENDING = 1;
    const PAYMENT_PROCESSING = 2;
    const PAYMENT_COMPLETED = 4;
    const PAYMENT_ANNULLED = 8;
    const PAYMENT_CREDITED = 16;
    const PAYMENT_AUTOMATICALLY_DEBITED = 32;
    const PAYMENT_MANUAL_INSPECTION = 64;   // When an order by some reason gets stuck in manual inspections
    const PAYMENT_STATUS_COULD_NOT_BE_SET = 128;  // No flags are set

    /** @deprecated Fallback status only, use PAYMENT_ANNULLED */
    const PAYMENT_CANCELLED = 8;
    /** @deprecated Fallback status only, use PAYMENT_CREDITED */
    const PAYMENT_REFUND = 16;
}

/**
 * Class RESURS_ENVIRONMENTS
 * @package Resursbank\RBEcomPHP
 */
abstract class RESURS_ENVIRONMENTS
{
    /**
     * @var int
     */
    const PRODUCTION = 0;

    /**
     * Test (default).
     * @var int
     */
    const TEST = 1;

    /**
     * Not set by anyone.
     * @var int
     * @deprecated Not in use
     */
    const NOT_SET = 2;

    /**
     * @var int
     * @deprecated Redundant name.
     */
    const ENVIRONMENT_PRODUCTION = 0;

    /**
     * @var int
     * @deprecated Redundant name.
     */
    const ENVIRONMENT_TEST = 1;

    /**
     * @var int
     * @deprecated Redundant name.
     */
    const ENVIRONMENT_NOT_SET = 2;
}

/**
 * Class RESURS_URL_ENCODE_TYPES How to encode urls.
 *
 * This class of encoding rules are based on emergency solutions if something went wrong with
 * the standard [unencoded] urls.
 *
 * @package Resursbank\RBEcomPHP
 * @since 1.3.16
 * @since 1.1.44
 * @since 1.0.44
 * @deprecated Do not use this unless you see urlencoding problems in your environment.
 */
abstract class RESURS_URL_ENCODE_TYPES
{
    const NONE = 0;
    const PATH_ONLY = 1;
    const FULL = 2;
    const SUCCESSURL = 4;
    const BACKURL = 8;
    const FAILURL = 16;
    const LEAVE_FIRST_PART = 32;
}

///

/**
 * Class ResursEnvironments
 * @since 1.0.0
 * @deprecated Use RESURS_ENVIRONMENTS
 */
abstract class ResursEnvironments extends RESURS_ENVIRONMENTS
{
}

/**
 * Class ResursCallbackReachability
 * @since 1.0.0
 * @deprecated Use RESURS_CALLBACK_REACHABILITY
 */
abstract class ResursCallbackReachability extends RESURS_CALLBACK_REACHABILITY
{
}

/**
 * Class ResursCurlMethods
 * @since 1.0.0
 * @deprecated Use RESURS_CURL_METHODS
 */
abstract class ResursCurlMethods extends RESURS_CURL_METHODS
{
}

/**
 * Class ResursAfterShopRenderTypes
 * @since 1.0.0
 * @deprecated Use RESURS_AFTERSHOP_RENDER_TYPES
 */
abstract class ResursAfterShopRenderTypes extends RESURS_AFTERSHOP_RENDER_TYPES
{
    /** @deprecated */
    const NONE = 0;
    /** @deprecated */
    const FINALIZE = 1;
    /** @deprecated */
    const CREDIT = 2;
    /** @deprecated */
    const ANNUL = 4;
    /** @deprecated */
    const AUTHORIZE = 8;

    /** @deprecated */
    const UPDATE = 16;
}

/**
 * Class ResursOmniCallTypes
 * Omnicheckout callback types
 * @since 1.0.2
 * @deprecated Use RESURS_CHECKOUT_CALL_TYPES
 */
abstract class ResursCheckoutCallTypes extends RESURS_CHECKOUT_CALL_TYPES
{
}

/**
 * Class ResursCallbackTypes Callbacks that can be registered with Resurs Bank. Do not use this.
 *
 * @since 1.0.0
 * @deprecated RESURS_CALLBACK_TYPES
 */
abstract class ResursCallbackTypes extends RESURS_CALLBACK_TYPES
{
}

/**
 * Class ResursMethodTypes Preferred payment method types if called.
 * @since 1.0.0
 * @deprecated Use RESURS_FLOW_TYPES
 */
abstract class ResursMethodTypes extends RESURS_FLOW_TYPES
{
    /** @deprecated Use FLOW_NOT_SET */
    const METHOD_UNDEFINED = 0;
    /** @deprecated Use FLOW_SIMPLIFIED_FLOW */
    const METHOD_SIMPLIFIED = 1;
    /** @deprecated Use FLOW_HOSTED_FLOW */
    const METHOD_HOSTED = 2;

    /** @deprecated Use FLOW_RESURS_CHECKOUT */
    const METHOD_CHECKOUT = 3;
    /** @deprecated 1.0.0 Use METHOD_CHECKOUT */
    const METHOD_OMNI = 3;
    /** @deprecated 1.0.0 Use METHOD_CHECKOUT */
    const METHOD_RESURSCHECKOUT = 3;

    /** @deprecated Legacy shopflow (removed) */
    const FLOW_LEGACY = 99;
}

/**
 * Class ResursCountry
 * @since 1.0.2
 * @deprecated Use RESURS_COUNTRY
 */
abstract class ResursCountry extends RESURS_COUNTRY
{
}

if (!@class_exists('Bit') && @class_exists('MODULE_NETBITS')) {
    class Bit extends MODULE_NETBITS {

    }
}
