<?php

if (!class_exists("resurs_findPaymentsResponse", false)) 
{
class resurs_findPaymentsResponse
{

    /**
     * @var basicPayment[] $return
     * @access public
     */
    public $return = null;

    /**
     * @param basicPayment[] $return
     * @access public
     */
    public function __construct($return)
    {
      $this->return = $return;
    }

}

}
