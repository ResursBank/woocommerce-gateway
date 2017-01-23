<?php

if (!class_exists("resurs_additionalDebitOfPayment", false)) 
{
class resurs_additionalDebitOfPayment
{

    /**
     * @var id $paymentId
     * @access public
     */
    public $paymentId = null;

    /**
     * @var paymentSpec $paymentSpec
     * @access public
     */
    public $paymentSpec = null;

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
