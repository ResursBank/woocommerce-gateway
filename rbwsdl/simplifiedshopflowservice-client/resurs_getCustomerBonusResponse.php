<?php

if (!class_exists("resurs_getCustomerBonusResponse", false)) 
{
class resurs_getCustomerBonusResponse
{

    /**
     * @var bonus $return
     * @access public
     */
    public $return = null;

    /**
     * @param bonus $return
     * @access public
     */
    public function __construct($return)
    {
      $this->return = $return;
    }

}

}
