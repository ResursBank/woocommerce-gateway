<?php

namespace Resursbank\RBEcomPHP;

/**
 * Class RESURS_FLOW_TYPES
 * @since 1.0.26
 * @since 1.1.26
 * @since 1.2.0
 */
abstract class RESURS_FLOW_TYPES {

	const FLOW_NOT_SET = 0;
	const FLOW_SIMPLIFIED_FLOW = 1;
	const FLOW_HOSTED_FLOW = 2;
	const FLOW_RESURS_CHECKOUT = 3;

}

/**
 * Class RESURS_COUNTRY Country selector
 * @since 1.0.26
 * @since 1.1.26
 * @since 1.2.0
 */
abstract class RESURS_COUNTRY {
	const COUNTRY_NOT_SET = 0;
	const COUNTRY_SE = 1;
	const COUNTRY_DK = 2;
	const COUNTRY_NO = 3;
	const COUNTRY_FI = 4;

}

/**
 * Class RESURS_CHECKOUT_CALL_TYPES
 * @since 1.0.26
 * @since 1.1.26
 * @since 1.2.0
 */
abstract class RESURS_CHECKOUT_CALL_TYPES {
	const METHOD_PAYMENTS = 0;
	const METHOD_CALLBACK = 1;
}

/**
 * Class RESURS_CALLBACK_TYPES
 * @since 1.0.26
 * @since 1.1.26
 * @since 1.2.0
 * @link https://test.resurs.com/docs/x/LAAF
 */
abstract class RESURS_CALLBACK_TYPES {

	const CALLBACK_TYPE_NOT_SET = 0;
	const CALLBACK_TYPE_UNFREEZE = 1;
	const CALLBACK_TYPE_ANNULMENT = 2;
	const CALLBACK_TYPE_AUTOMATIC_FRAUD_CONTROL = 3;
	const CALLBACK_TYPE_FINALIZATION = 4;
	const CALLBACK_TYPE_TEST = 5;
	const CALLBACK_TYPE_UPDATE = 6;
	const CALLBACK_TYPE_BOOKED = 7;
}

/**
 * Class RESURS_AFTERSHOP_RENDER_TYPES
 * @since 1.0.26
 * @since 1.1.26
 * @since 1.2.0
 */
abstract class RESURS_AFTERSHOP_RENDER_TYPES {
	const AFTERSHOP_NO_CHOICE = 0;
	const AFTERSHOP_FINALIZE = 1;
	const AFTERSHOP_CREDIT = 2;
	const AFTERSHOP_ANNUL = 4;
	const AFTERSHOP_AUTHORIZE = 8;
}


/**
 * Class RESURS_CURL_METHODS Curl HTTP methods
 *
 * @since 1.0.26
 * @since 1.1.26
 * @since 1.2.0
 */
abstract class RESURS_CURL_METHODS {
	const METHOD_GET = 0;
	const METHOD_POST = 1;
	const METHOD_PUT = 2;
	const METHOD_DELETE = 3;
}

/**
 * Class RESURS_CALLBACK_REACHABILITY External API callback URI control codes
 * @since 1.0.26
 * @since 1.1.26
 * @since 1.2.0
 */
abstract class RESURS_CALLBACK_REACHABILITY {
	const IS_REACHABLE_NOT_AVAILABLE = 0;
	const IS_FULLY_REACHABLE = 1;
	const IS_REACHABLE_WITH_PROBLEMS = 2;
	const IS_NOT_REACHABLE = 3;
	const IS_REACHABLE_NOT_KNOWN = 4;
}

/**
 * Class RESURS_PAYMENT_STATUS_RETURNCODES Order status return codes
 *
 * @since 1.0.26
 * @since 1.1.26
 * @since 1.2.0
 */
abstract class RESURS_PAYMENT_STATUS_RETURNCODES {
	const PAYMENT_STATUS_COULD_NOT_BE_SET = 0;     // Waiting for callback or frozen
	const PAYMENT_PENDING = 10;     // Waiting for callback or frozen
	const PAYMENT_PROCESSING = 20;  // Booked, waiting for next action
	const PAYMENT_COMPLETED = 30;   // Fully finalized (debited)
	const PAYMENT_ANNULLED = 40;    // Fully annulled
	const PAYMENT_CREDITED = 50;    // Fully credited

	// Fallback statuses
	const PAYMENT_CANCELLED = 40;   // Fully annulled (this is practically a prestashop based name)
	const PAYMENT_REFUND = 50;      // Fully credited (this is practically a prestashop based name)
}

abstract class RESURS_ENVIRONMENTS {
	const ENVIRONMENT_PRODUCTION = 0;
	const ENVIRONMENT_TEST = 1;
	const ENVIRONMENT_NOT_SET = 2;
}

///

/**
 * Class ResursEnvironments
 * @since 1.0.0
 * @deprecated Use RESURS_ENVIRONMENTS
 */
abstract class ResursEnvironments extends RESURS_ENVIRONMENTS {
}

/**
 * Class ResursCallbackReachability
 * @since 1.0.0
 * @deprecated Use RESURS_CALLBACK_REACHABILITY
 */
abstract class ResursCallbackReachability extends RESURS_CALLBACK_REACHABILITY {
}

/**
 * Class ResursCurlMethods
 * @since 1.0.0
 * @deprecated Use RESURS_CURL_METHODS
 */
abstract class ResursCurlMethods extends RESURS_CURL_METHODS {
}

/**
 * Class ResursAfterShopRenderTypes
 * @since 1.0.0
 * @deprecated Use RESURS_AFTERSHOP_RENDER_TYPES
 */
abstract class ResursAfterShopRenderTypes extends RESURS_AFTERSHOP_RENDER_TYPES {
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
abstract class ResursCheckoutCallTypes extends RESURS_CHECKOUT_CALL_TYPES {
}

/**
 * Class ResursCallbackTypes Callbacks that can be registered with Resurs Bank.
 * @since 1.0.0
 * @deprecated RESURS_CALLBACK_TYPES
 */
abstract class ResursCallbackTypes extends RESURS_CALLBACK_TYPES {
	/**
	 * Callbacktype not defined
	 * @deprecated Use CALLBACK_TYPE_NOT_SET
	 */
	const UNDEFINED = 0;

	/**
	 * Callback UNFREEZE
	 *
	 * Informs when an payment is unfrozen after manual fraud screening. This means that the payment may be debited (captured) and the goods can be delivered.
	 * @link https://test.resurs.com/docs/display/ecom/UNFREEZE
	 * @deprecated Use CALLBACK_TYPE_UNFREEZE
	 */
	const UNFREEZE = 1;

	/**
	 * Callback ANNULMENT
	 *
	 * Will be sent once a payment is fully annulled at Resurs Bank, for example when manual fraud screening implies fraudulent usage. Annulling part of the payment will not trigger this event.
	 * If the representative is not listening to this callback orders might be orphaned (i e without connected payment) and products bound to these orders never released.
	 * @link https://test.resurs.com/docs/display/ecom/ANNULMENT
	 * @deprecated Use CALLBACK_TYPE_ANNULMENT
	 */
	const ANNULMENT = 2;

	/**
	 * Callback AUTOMATIC_FRAUD_CONTROL
	 *
	 * Will be sent once a payment is fully annulled at Resurs Bank, for example when manual fraud screening implies fraudulent usage. Annulling part of the payment will not trigger this event.
	 * @link https://test.resurs.com/docs/display/ecom/AUTOMATIC_FRAUD_CONTROL
	 * @deprecated Use CALLBACK_TYPE_AUTOMATIC_FRAUD_CONTROL
	 */
	const AUTOMATIC_FRAUD_CONTROL = 3;

	/**
	 * Callback FINALIZATION
	 *
	 * Once a payment is finalized automatically at Resurs Bank, for this will trigger this event, when the parameter finalizeIfBooked parmeter is set to true in paymentData. This callback will only be called if you are implementing the paymentData method with finilizedIfBooked parameter set to true, in the Simplified Shop Flow Service.
	 * @link https://test.resurs.com/docs/display/ecom/FINALIZATION
	 * @deprecated Use CALLBACK_TYPE_FINALIZATION
	 */
	const FINALIZATION = 4;

	/**
	 * Callback TEST
	 *
	 * To test the callback mechanism. Can be used in integration testing to assure that communication works. A call is made to DeveloperService (triggerTestEvent) and Resurs Bank immediately does a callback. Note that TEST callback must be registered in the same way as all the other callbacks before it can be used.
	 * @link https://test.resurs.com/docs/display/ecom/TEST
	 * @deprecated CALLBACK_TYPE_TEST
	 */
	const TEST = 5;
	/**
	 * Callback UPDATE
	 *
	 * Will be sent when a payment is updated. Resurs Bank will do a HTTP/POST call with parameter paymentId and the xml for paymentDiff to the registered URL.
	 * @link https://test.resurs.com/docs/display/ecom/UPDATE
	 * @deprecated Use CALLBACK_TYPE_UPDATE
	 */
	const UPDATE = 6;

	/**
	 * Callback BOOKED
	 *
	 * Trigger: The order is in Resurs Bank system and ready for finalization
	 * @link https://test.resurs.com/docs/display/ecom/BOOKED
	 * @deprecated Use CALLBACK_TYPE_BOOKED
	 */
	const BOOKED = 7;
}

/**
 * Class ResursMethodTypes Preferred payment method types if called.
 * @since 1.0.0
 * @deprecated Use RESURS_FLOW_TYPES
 */
abstract class ResursMethodTypes extends RESURS_FLOW_TYPES {

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
abstract class ResursCountry extends RESURS_COUNTRY {
}
