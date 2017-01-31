<?php

if (!class_exists("resurs_getPaymentResponse", false)) 
{
class resurs_getPaymentResponse
{

    /**
     * @var payment $return
     * @access public
     */
    public $return = null;

    /**
     * @param payment $return
     * @access public
     */
    public function __construct($return)
    {
      $this->return = $return;
    }

}

}
