<?php

if (!class_exists("resurs_customerCard", false)) 
{
class resurs_customerCard
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
     * @var string $cardNumber
     * @access public
     */
    public $cardNumber = null;

    /**
     * @param string $governmentId
     * @param customerType $customerType
     * @param string $cardNumber
     * @access public
     */
    public function __construct($governmentId, $customerType, $cardNumber)
    {
      $this->governmentId = $governmentId;
      $this->customerType = $customerType;
      $this->cardNumber = $cardNumber;
    }

}

}
