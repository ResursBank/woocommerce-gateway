<?php

if (!class_exists("resurs_getCustomerBonus", false)) 
{
class resurs_getCustomerBonus
{

    /**
     * @var customerIdentification $customerIdentification
     * @access public
     */
    public $customerIdentification = null;

    /**
     * @param customerIdentification $customerIdentification
     * @access public
     */
    public function __construct($customerIdentification)
    {
      $this->customerIdentification = $customerIdentification;
    }

}

}
