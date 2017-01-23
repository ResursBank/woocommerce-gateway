<?php

if (!class_exists("resurs_getPaymentDocumentNamesResponse", false)) 
{
class resurs_getPaymentDocumentNamesResponse
{

    /**
     * @var string[] $return
     * @access public
     */
    public $return = null;

    /**
     * @param string[] $return
     * @access public
     */
    public function __construct($return)
    {
      $this->return = $return;
    }

}

}
