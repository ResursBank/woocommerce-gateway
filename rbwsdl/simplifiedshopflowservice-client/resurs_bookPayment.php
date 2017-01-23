<?php

if (!class_exists("resurs_bookPayment", false)) 
{
class resurs_bookPayment
{

    /**
     * @var paymentData $paymentData
     * @access public
     */
    public $paymentData = null;

    /**
     * @var paymentSpec $orderData
     * @access public
     */
    public $orderData = null;

    /**
     * @var mapEntry[] $metaData
     * @access public
     */
    public $metaData = null;

    /**
     * @var extendedCustomer $customer
     * @access public
     */
    public $customer = null;

    /**
     * @var cardData $card
     * @access public
     */
    public $card = null;

    /**
     * @var signing $signing
     * @access public
     */
    public $signing = null;

    /**
     * @var invoiceData $invoiceData
     * @access public
     */
    public $invoiceData = null;

    /**
     * @var nonEmptyString $bookedCallbackUrl
     * @access public
     */
    public $bookedCallbackUrl = null;

    /**
     * @param paymentData $paymentData
     * @param paymentSpec $orderData
     * @param extendedCustomer $customer
     * @param nonEmptyString $bookedCallbackUrl
     * @access public
     */
    public function __construct($paymentData, $orderData, $customer, $bookedCallbackUrl)
    {
      $this->paymentData = $paymentData;
      $this->orderData = $orderData;
      $this->customer = $customer;
      $this->bookedCallbackUrl = $bookedCallbackUrl;
    }

}

}
