<?php

if (!class_exists("resurs_issueCustomerIdentificationTokenResponse", false)) 
{
class resurs_issueCustomerIdentificationTokenResponse
{

    /**
     * @var customerIdentificationResponse $return
     * @access public
     */
    public $return = null;

    /**
     * @param customerIdentificationResponse $return
     * @access public
     */
    public function __construct($return)
    {
      $this->return = $return;
    }

}

}
