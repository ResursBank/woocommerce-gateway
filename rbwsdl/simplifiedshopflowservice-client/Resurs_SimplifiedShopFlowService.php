<?php

if (!class_exists("Resurs_SimplifiedShopFlowService", false)) 
{
include_once('resurs_customer.php');
include_once('resurs_address.php');
include_once('resurs_mapEntry.php');
include_once('resurs_countryCode.php');
include_once('resurs_language.php');
include_once('resurs_paymentSpec.php');
include_once('resurs_specLine.php');
include_once('resurs_paymentStatus.php');
include_once('resurs_limit.php');
include_once('resurs_limitDecision.php');
include_once('resurs_customerType.php');
include_once('resurs_paymentMethodType.php');
include_once('resurs_invoiceDeliveryTypeEnum.php');
include_once('resurs_bookPaymentStatus.php');
include_once('resurs_bookPaymentResult.php');
include_once('resurs_extendedCustomer.php');
include_once('resurs_cardData.php');
include_once('resurs_paymentData.php');
include_once('resurs_signing.php');
include_once('resurs_invoiceData.php');
include_once('resurs_paymentMethod.php');
include_once('resurs_webLink.php');
include_once('resurs_annuityFactor.php');
include_once('resurs_paymentSession.php');
include_once('resurs_bookingResult.php');
include_once('resurs_limitApplicationFormAsCompiledForm.php');
include_once('resurs_limitApplicationFormAsObjectGraph.php');
include_once('resurs_formElement.php');
include_once('resurs_option.php');
include_once('resurs_fraudControlStatus.php');
include_once('resurs_customerIdentification.php');
include_once('resurs_bonus.php');
include_once('resurs_customerIdentificationResponse.php');
include_once('resurs_customerCard.php');
include_once('resurs_getCostOfPurchaseHtml.php');
include_once('resurs_getCostOfPurchaseHtmlResponse.php');
include_once('resurs_getPaymentMethods.php');
include_once('resurs_getPaymentMethodsResponse.php');
include_once('resurs_getAnnuityFactors.php');
include_once('resurs_getAnnuityFactorsResponse.php');
include_once('resurs_getAddress.php');
include_once('resurs_getAddressResponse.php');
include_once('resurs_getCustomerBonus.php');
include_once('resurs_getCustomerBonusResponse.php');
include_once('resurs_issueCustomerIdentificationToken.php');
include_once('resurs_issueCustomerIdentificationTokenResponse.php');
include_once('resurs_invalidateCustomerIdentificationToken.php');
include_once('resurs_invalidateCustomerIdentificationTokenResponse.php');
include_once('resurs_bookSignedPayment.php');
include_once('resurs_bookPayment.php');
include_once('resurs_bookPaymentResponse.php');
include_once('resurs_ECommerceError.php');

class Resurs_SimplifiedShopFlowService extends \SoapClient
{

    /**
     * @var array $classmap The defined classes
     * @access private
     */
    private static $classmap = array(
      'customer' => '\resurs_customer',
      'address' => '\resurs_address',
      'mapEntry' => '\resurs_mapEntry',
      'paymentSpec' => '\resurs_paymentSpec',
      'specLine' => '\resurs_specLine',
      'limit' => '\resurs_limit',
      'bookPaymentResult' => '\resurs_bookPaymentResult',
      'extendedCustomer' => '\resurs_extendedCustomer',
      'cardData' => '\resurs_cardData',
      'paymentData' => '\resurs_paymentData',
      'signing' => '\resurs_signing',
      'invoiceData' => '\resurs_invoiceData',
      'paymentMethod' => '\resurs_paymentMethod',
      'webLink' => '\resurs_webLink',
      'annuityFactor' => '\resurs_annuityFactor',
      'paymentSession' => '\resurs_paymentSession',
      'bookingResult' => '\resurs_bookingResult',
      'limitApplicationFormAsCompiledForm' => '\resurs_limitApplicationFormAsCompiledForm',
      'limitApplicationFormAsObjectGraph' => '\resurs_limitApplicationFormAsObjectGraph',
      'formElement' => '\resurs_formElement',
      'option' => '\resurs_option',
      'customerIdentification' => '\resurs_customerIdentification',
      'bonus' => '\resurs_bonus',
      'customerIdentificationResponse' => '\resurs_customerIdentificationResponse',
      'customerCard' => '\resurs_customerCard',
      'getCostOfPurchaseHtml' => '\resurs_getCostOfPurchaseHtml',
      'getCostOfPurchaseHtmlResponse' => '\resurs_getCostOfPurchaseHtmlResponse',
      'getPaymentMethods' => '\resurs_getPaymentMethods',
      'getPaymentMethodsResponse' => '\resurs_getPaymentMethodsResponse',
      'getAnnuityFactors' => '\resurs_getAnnuityFactors',
      'getAnnuityFactorsResponse' => '\resurs_getAnnuityFactorsResponse',
      'getAddress' => '\resurs_getAddress',
      'getAddressResponse' => '\resurs_getAddressResponse',
      'getCustomerBonus' => '\resurs_getCustomerBonus',
      'getCustomerBonusResponse' => '\resurs_getCustomerBonusResponse',
      'issueCustomerIdentificationToken' => '\resurs_issueCustomerIdentificationToken',
      'issueCustomerIdentificationTokenResponse' => '\resurs_issueCustomerIdentificationTokenResponse',
      'invalidateCustomerIdentificationToken' => '\resurs_invalidateCustomerIdentificationToken',
      'invalidateCustomerIdentificationTokenResponse' => '\resurs_invalidateCustomerIdentificationTokenResponse',
      'bookSignedPayment' => '\resurs_bookSignedPayment',
      'bookPayment' => '\resurs_bookPayment',
      'bookPaymentResponse' => '\resurs_bookPaymentResponse',
      'ECommerceError' => '\resurs_ECommerceError');

    /**
     * @param array $options A array of config values
     * @param string $wsdl The wsdl file to use
     * @access public
     */
    public function __construct(array $options = array(), $wsdl = 'https://test.resurs.com/ecommerce-test/ws/V4/SimplifiedShopFlowService?wsdl')
    {
      foreach (self::$classmap as $key => $value) {
        if (!isset($options['classmap'][$key])) {
          $options['classmap'][$key] = $value;
        }
      }
      
      parent::__construct($wsdl, $options);
    }

    /**
     * Retrieves detailed cost of purchase information in HTML format.
     *
     *                 Resurs Bank is legaly obliged to show this information everywhere it's payment methods are marketed.
     *                 This information either be fetched with this method or linked. If linking is preferred, the links returned
     *                 with the payment method (getPaymentMethods) is to be used.
     *
     * @param resurs_getCostOfPurchaseHtml $parameters
     * @access public
     * @return getCostOfPurchaseHtmlResponse
     */
    public function getCostOfPurchaseHtml($parameters)
    {
      return $this->__soapCall('getCostOfPurchaseHtml', array($parameters));
    }

    /**
     * Retrieves detailed information on the payment methods available to the representative.
     *
     * @param resurs_getPaymentMethods $parameters
     * @access public
     * @return getPaymentMethodsResponse
     */
    public function getPaymentMethods($parameters)
    {
      return $this->__soapCall('getPaymentMethods', array($parameters));
    }

    /**
     * Retrieves the annuity factors for a given payment method.
     *
     * @param resurs_getAnnuityFactors $parameters
     * @access public
     * @return getAnnuityFactorsResponse
     */
    public function getAnnuityFactors($parameters)
    {
      return $this->__soapCall('getAnnuityFactors', array($parameters));
    }

    /**
     * Retrieves address information. Currently only works in sweden!
     *                 Note that the customerType parameter is optional right now, but in short
     *                 notice this will be required (minOccurs=1)
     *
     * @param resurs_getAddress $parameters
     * @access public
     * @return getAddressResponse
     */
    public function getAddress($parameters)
    {
      return $this->__soapCall('getAddress', array($parameters));
    }

    /**
     * Fetches the bonus the customer have, if any.
     *                 Read more about bonus
     *
     * @param resurs_getCustomerBonus $parameters
     * @access public
     * @return getCustomerBonusResponse
     */
    public function getCustomerBonus($parameters)
    {
      return $this->__soapCall('getCustomerBonus', array($parameters));
    }

    /**
     * Invalidates customer identification token(s).
     *
     * @param resurs_invalidateCustomerIdentificationToken $parameters
     * @access public
     * @return invalidateCustomerIdentificationTokenResponse
     */
    public function invalidateCustomerIdentificationToken($parameters)
    {
      return $this->__soapCall('invalidateCustomerIdentificationToken', array($parameters));
    }

    /**
     * Issues a customer identification token that can identify this customer in further operations. These
     *                 functions do require the customer to be identified, and they require either a token, or information
     *                 to identify the customer.
     *                 Tokens are intended to be saved with the user profile in the web shop. In this way we delegate
     *                 identification of the customer to the web shop after the initial identification is done.
     *
     * @param resurs_issueCustomerIdentificationToken $parameters
     * @access public
     * @return issueCustomerIdentificationTokenResponse
     */
    public function issueCustomerIdentificationToken($parameters)
    {
      return $this->__soapCall('issueCustomerIdentificationToken', array($parameters));
    }

    /**
     * Initializes a signing session.This is only necessary if there is to
     *                 be a signing. However, calling the method just in case may be a good idea.
     *
     * @param resurs_bookSignedPayment $parameters
     * @access public
     * @return bookPaymentResponse
     */
    public function bookSignedPayment($parameters)
    {
      return $this->__soapCall('bookSignedPayment', array($parameters));
    }

    /**
     * Books the payment. This reserves the purchase amount on the customer's account.
     *                 Effectively, it also ends the shop flow.
     *
     * @param resurs_bookPayment $parameters
     * @access public
     * @return bookPaymentResponse
     */
    public function bookPayment($parameters)
    {
      return $this->__soapCall('bookPayment', array($parameters));
    }

}

}
