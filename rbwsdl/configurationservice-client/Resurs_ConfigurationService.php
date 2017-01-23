<?php

if (!class_exists("Resurs_ConfigurationService", false)) 
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
include_once('resurs_digestConfiguration.php');
include_once('resurs_digestAlgorithm.php');
include_once('resurs_invoiceData.php');
include_once('resurs_changePassword.php');
include_once('resurs_changePasswordResponse.php');
include_once('resurs_addPassword.php');
include_once('resurs_addPasswordResponse.php');
include_once('resurs_removePassword.php');
include_once('resurs_removePasswordResponse.php');
include_once('resurs_registerEventCallback.php');
include_once('resurs_registerEventCallbackResponse.php');
include_once('resurs_getRegisteredEventCallback.php');
include_once('resurs_getRegisteredEventCallbackResponse.php');
include_once('resurs_unregisterEventCallback.php');
include_once('resurs_unregisterEventCallbackResponse.php');
include_once('resurs_peekInvoiceSequence.php');
include_once('resurs_peekInvoiceSequenceResponse.php');
include_once('resurs_setInvoiceSequence.php');
include_once('resurs_setInvoiceSequenceResponse.php');
include_once('resurs_setInvoiceDataResponse.php');
include_once('resurs_setInvoiceData.php');
include_once('resurs_getInvoiceData.php');
include_once('resurs_getInvoiceDataResponse.php');
include_once('resurs_ECommerceError.php');

class Resurs_ConfigurationService extends \SoapClient
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
      'digestConfiguration' => '\resurs_digestConfiguration',
      'invoiceData' => '\resurs_invoiceData',
      'changePassword' => '\resurs_changePassword',
      'changePasswordResponse' => '\resurs_changePasswordResponse',
      'addPassword' => '\resurs_addPassword',
      'addPasswordResponse' => '\resurs_addPasswordResponse',
      'removePassword' => '\resurs_removePassword',
      'removePasswordResponse' => '\resurs_removePasswordResponse',
      'registerEventCallback' => '\resurs_registerEventCallback',
      'registerEventCallbackResponse' => '\resurs_registerEventCallbackResponse',
      'getRegisteredEventCallback' => '\resurs_getRegisteredEventCallback',
      'getRegisteredEventCallbackResponse' => '\resurs_getRegisteredEventCallbackResponse',
      'unregisterEventCallback' => '\resurs_unregisterEventCallback',
      'unregisterEventCallbackResponse' => '\resurs_unregisterEventCallbackResponse',
      'peekInvoiceSequence' => '\resurs_peekInvoiceSequence',
      'peekInvoiceSequenceResponse' => '\resurs_peekInvoiceSequenceResponse',
      'setInvoiceSequence' => '\resurs_setInvoiceSequence',
      'setInvoiceSequenceResponse' => '\resurs_setInvoiceSequenceResponse',
      'setInvoiceDataResponse' => '\resurs_setInvoiceDataResponse',
      'setInvoiceData' => '\resurs_setInvoiceData',
      'getInvoiceData' => '\resurs_getInvoiceData',
      'getInvoiceDataResponse' => '\resurs_getInvoiceDataResponse',
      'ECommerceError' => '\resurs_ECommerceError');

    /**
     * @param array $options A array of config values
     * @param string $wsdl The wsdl file to use
     * @access public
     */
    public function __construct(array $options = array(), $wsdl = 'https://test.resurs.com/ecommerce-test/ws/V4/ConfigurationService?wsdl')
    {
      foreach (self::$classmap as $key => $value) {
        if (!isset($options['classmap'][$key])) {
          $options['classmap'][$key] = $value;
        }
      }
      
      parent::__construct($wsdl, $options);
    }

    /**
     * Changes a password for the representative.
     *                 Please ensure that the password is of sufficient strength. If not, the operation will be rejected.
     *                 NB:Also see addPassword for more information on multiple passwords.
     *
     * @param resurs_changePassword $parameters
     * @access public
     * @return changePasswordResponse
     */
    public function changePassword($parameters)
    {
      return $this->__soapCall('changePassword', array($parameters));
    }

    /**
     * Creates a new additional password to the representative.
     *                 This function can be used to provide multiple, parallel logins for the same representative,
     *                 something that can be quite useful when accessing the eCommerce platform from different systems
     *                 that are not always in synch.
     *                 Please ensure that the password is of sufficient strength. If not, the operation will be rejected.
     *                 NB:Please be aware that separate expiration dates are kept for all the
     *                 passwords.
     *
     * @param resurs_addPassword $parameters
     * @access public
     * @return addPasswordResponse
     */
    public function addPassword($parameters)
    {
      return $this->__soapCall('addPassword', array($parameters));
    }

    /**
     * Removes an additional representative password.
     *                 NB:Please note that the "master" password cannot be removed, only those
     *                 added using the addPassword method.
     *
     * @param resurs_removePassword $parameters
     * @access public
     * @return removePasswordResponse
     */
    public function removePassword($parameters)
    {
      return $this->__soapCall('removePassword', array($parameters));
    }

    /**
     * Registers a new event callback.
     *                 See separate event documentation for details!
     *
     * @param resurs_registerEventCallback $parameters
     * @access public
     * @return registerEventCallbackResponse
     */
    public function registerEventCallback($parameters)
    {
      return $this->__soapCall('registerEventCallback', array($parameters));
    }

    /**
     * Returns the registered event callback URI template if it exists.
     *
     * @param resurs_getRegisteredEventCallback $parameters
     * @access public
     * @return getRegisteredEventCallbackResponse
     */
    public function getRegisteredEventCallback($parameters)
    {
      return $this->__soapCall('getRegisteredEventCallback', array($parameters));
    }

    /**
     * Unregisters an existing event callback.
     *
     * @param resurs_unregisterEventCallback $parameters
     * @access public
     * @return unregisterEventCallbackResponse
     */
    public function unregisterEventCallback($parameters)
    {
      return $this->__soapCall('unregisterEventCallback', array($parameters));
    }

    /**
     * Returns the next invoice number to be used for automatic generation of invoice numbers.
     *
     * @param resurs_peekInvoiceSequence $parameters
     * @access public
     * @return peekInvoiceSequenceResponse
     */
    public function peekInvoiceSequence($parameters)
    {
      return $this->__soapCall('peekInvoiceSequence', array($parameters));
    }

    /**
     * Sets the next invoice number to be used for automatic generation of invoice numbers.
     *
     * @param resurs_setInvoiceSequence $parameters
     * @access public
     * @return setInvoiceSequenceResponse
     */
    public function setInvoiceSequence($parameters)
    {
      return $this->__soapCall('setInvoiceSequence', array($parameters));
    }

    /**
     * Sets new data to the representative.
     *
     * @param resurs_setInvoiceData $parameters
     * @access public
     * @return setInvoiceDataResponse
     */
    public function setInvoiceData($parameters)
    {
      return $this->__soapCall('setInvoiceData', array($parameters));
    }

    /**
     * Gets the representatives data.
     *
     * @param resurs_getInvoiceData $parameters
     * @access public
     * @return getInvoiceDataResponse
     */
    public function getInvoiceData($parameters)
    {
      return $this->__soapCall('getInvoiceData', array($parameters));
    }

}

}
