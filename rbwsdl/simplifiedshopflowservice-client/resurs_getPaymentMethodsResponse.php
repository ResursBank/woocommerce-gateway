<?php

if (!class_exists("resurs_getPaymentMethodsResponse", false)) 
{
class resurs_getPaymentMethodsResponse
{

    /**
     * @var paymentMethod[] $return
     * @access public
     */
    public $return = null;

    /**
     * @param paymentMethod[] $return
     * @access public
     */
    public function __construct($return)
    {
      $this->return = $return;
    }

}

}
