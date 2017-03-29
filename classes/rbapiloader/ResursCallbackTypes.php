<?php
/**
 * Resurs Bank Passthrough API - A pretty silent ShopFlowSimplifier for Resurs Bank.
 * Compatible with simplifiedFlow, hostedFlow and Resurs Checkout.
 * Requirements: WSDL stubs from WSDL2PHPGenerator (deprecated edition)
 * Important notes: As the WSDL files are generated, it is highly important to run tests before release.
 *
 * Last update: See the lastUpdate variable
 * @package RBEcomPHP
 * @author Resurs Bank Ecommerce <ecommerce.support@resurs.se>
 * @version 1.0-beta
 * @branch 1.0
 * @link https://test.resurs.com/docs/x/KYM0 Get started - PHP Section
 * @link https://test.resurs.com/docs/x/TYNM EComPHP Usage
 * @license Apache License
 */

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