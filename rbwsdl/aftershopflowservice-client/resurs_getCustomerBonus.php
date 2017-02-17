<?php

if (!class_exists("resurs_getCustomerBonus", false)) 
{
class resurs_getCustomerBonus
{

    /**
     * @var string $governmentId
     * @access public
     */
    public $governmentId = null;

    /**
     * @var customerType $customerType
     * @access public
     */
    public $customerType = null;

    /**
     * @param string $governmentId
     * @param customerType $customerType
     * @access public
     */
    public function __construct($governmentId, $customerType)
    {
      $this->governmentId = $governmentId;
      $this->customerType = $customerType;
    }

}

}
