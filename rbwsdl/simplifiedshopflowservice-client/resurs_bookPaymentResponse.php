<?php

if (!class_exists("resurs_bookPaymentResponse", false)) 
{
class resurs_bookPaymentResponse
{

    /**
     * @var bookPaymentResult $return
     * @access public
     */
    public $return = null;

    /**
     * @param bookPaymentResult $return
     * @access public
     */
    public function __construct($return)
    {
      $this->return = $return;
    }

}

}
