<?php

if (!class_exists("resurs_annulPayment", false)) 
{
class resurs_annulPayment
{

    /**
     * @var id $paymentId
     * @access public
     */
    public $paymentId = null;

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
     * @param id $paymentId
     * @access public
     */
    public function __construct($paymentId)
    {
      $this->paymentId = $paymentId;
    }

}

}
