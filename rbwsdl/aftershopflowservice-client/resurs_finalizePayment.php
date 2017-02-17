<?php

if (!class_exists("resurs_finalizePayment", false)) 
{
class resurs_finalizePayment
{

    /**
     * @var id $paymentId
     * @access public
     */
    public $paymentId = null;

    /**
     * @var id $preferredTransactionId
     * @access public
     */
    public $preferredTransactionId = null;

    /**
     * @var paymentSpec $partPaymentSpec
     * @access public
     */
    public $partPaymentSpec = null;

    /**
     * @var nonEmptyString $createdBy
     * @access public
     */
    public $createdBy = null;

    /**
     * @var id $orderId
     * @access public
     */
    public $orderId = null;

    /**
     * @var date $orderDate
     * @access public
     */
    public $orderDate = null;

    /**
     * @var id $invoiceId
     * @access public
     */
    public $invoiceId = null;

    /**
     * @var date $invoiceDate
     * @access public
     */
    public $invoiceDate = null;

    /**
     * @var invoiceDeliveryTypeEnum $invoiceDeliveryType
     * @access public
     */
    public $invoiceDeliveryType = null;

    /**
     * @param id $paymentId
     * @access public
     */
    public function __construct($paymentId)
    {
      $this->paymentId = $paymentId;
    }

}

}
