<?php

if (!class_exists("resurs_getAddress", false)) 
{
class resurs_getAddress
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
     * @var string $customerIpAddress
     * @access public
     */
    public $customerIpAddress = null;

    /**
     * @param string $governmentId
     * @param customerType $customerType
     * @param string $customerIpAddress
     * @access public
     */
    public function __construct($governmentId, $customerType, $customerIpAddress)
    {
      $this->governmentId = $governmentId;
      $this->customerType = $customerType;
      $this->customerIpAddress = $customerIpAddress;
    }

}

}
