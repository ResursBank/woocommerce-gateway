<?php

if (!class_exists("resurs_paymentData", false)) 
{
class resurs_paymentData
{

    /**
     * @var id $preferredId
     * @access public
     */
    public $preferredId = null;

    /**
     * @var id $preferredTransactionId
     * @access public
     */
    public $preferredTransactionId = null;

    /**
     * @var id $paymentMethodId
     * @access public
     */
    public $paymentMethodId = null;

    /**
     * @var string $customerIpAddress
     * @access public
     */
    public $customerIpAddress = null;

    /**
     * @var boolean $waitForFraudControl
     * @access public
     */
    public $waitForFraudControl = null;

    /**
     * @var boolean $annulIfFrozen
     * @access public
     */
    public $annulIfFrozen = null;

    /**
     * @var boolean $finalizeIfBooked
     * @access public
     */
    public $finalizeIfBooked = null;

    /**
     * @param id $paymentMethodId
     * @access public
     */
    public function __construct($paymentMethodId)
    {
      $this->paymentMethodId = $paymentMethodId;
    }

}

}
