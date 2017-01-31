<?php

if (!class_exists("resurs_getPaymentDocumentNames", false)) 
{
class resurs_getPaymentDocumentNames
{

    /**
     * @var id $paymentId
     * @access public
     */
    public $paymentId = null;

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
