<?php

if (!class_exists("resurs_getAddressResponse", false)) 
{
class resurs_getAddressResponse
{

    /**
     * @var address $return
     * @access public
     */
    public $return = null;

    /**
     * @param address $return
     * @access public
     */
    public function __construct($return)
    {
      $this->return = $return;
    }

}

}
