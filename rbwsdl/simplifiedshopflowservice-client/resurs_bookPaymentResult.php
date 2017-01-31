<?php

if (!class_exists("resurs_bookPaymentResult", false)) 
{
class resurs_bookPaymentResult
{

    /**
     * @var id $paymentId
     * @access public
     */
    public $paymentId = null;

    /**
     * @var bookPaymentStatus $bookPaymentStatus
     * @access public
     */
    public $bookPaymentStatus = null;

    /**
     * @var string $signingUrl
     * @access public
     */
    public $signingUrl = null;

    /**
     * @var positiveDecimal $approvedAmount
     * @access public
     */
    public $approvedAmount = null;

    /**
     * @var customer $customer
     * @access public
     */
    public $customer = null;

    /**
     * @param id $paymentId
     * @param bookPaymentStatus $bookPaymentStatus
     * @param positiveDecimal $approvedAmount
     * @param customer $customer
     * @access public
     */
    public function __construct($paymentId, $bookPaymentStatus, $approvedAmount, $customer)
    {
      $this->paymentId = $paymentId;
      $this->bookPaymentStatus = $bookPaymentStatus;
      $this->approvedAmount = $approvedAmount;
      $this->customer = $customer;
    }

}

}
