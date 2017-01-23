<?php

if (!class_exists("resurs_getCostOfPurchaseHtmlResponse", false)) 
{
class resurs_getCostOfPurchaseHtmlResponse
{

    /**
     * @var string $return
     * @access public
     */
    public $return = null;

    /**
     * @param string $return
     * @access public
     */
    public function __construct($return)
    {
      $this->return = $return;
    }

}

}
