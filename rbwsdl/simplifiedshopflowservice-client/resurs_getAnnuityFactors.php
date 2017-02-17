<?php

if (!class_exists("resurs_getAnnuityFactors", false)) 
{
class resurs_getAnnuityFactors
{

    /**
     * @var id $paymentMethodId
     * @access public
     */
    public $paymentMethodId = null;

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
