<?php

if (!class_exists("resurs_getCostOfPurchaseHtml", false)) 
{
class resurs_getCostOfPurchaseHtml
{

    /**
     * @var id $paymentMethodId
     * @access public
     */
    public $paymentMethodId = null;

    /**
     * @var positiveDecimal $amount
     * @access public
     */
    public $amount = null;

    /**
     * @param id $paymentMethodId
     * @param positiveDecimal $amount
     * @access public
     */
    public function __construct($paymentMethodId, $amount)
    {
      $this->paymentMethodId = $paymentMethodId;
      $this->amount = $amount;
    }

}

}
