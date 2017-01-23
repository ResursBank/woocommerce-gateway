<?php

if (!class_exists("resurs_issueCustomerIdentificationToken", false)) 
{
class resurs_issueCustomerIdentificationToken
{

    /**
     * @var customerCard $customerCard
     * @access public
     */
    public $customerCard = null;

    /**
     * @var string $customerIpAddress
     * @access public
     */
    public $customerIpAddress = null;

    /**
     * @param customerCard $customerCard
     * @param string $customerIpAddress
     * @access public
     */
    public function __construct($customerCard, $customerIpAddress)
    {
      $this->customerCard = $customerCard;
      $this->customerIpAddress = $customerIpAddress;
    }

}

}
