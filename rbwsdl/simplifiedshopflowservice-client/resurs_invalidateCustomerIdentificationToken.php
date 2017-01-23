<?php

if (!class_exists("resurs_invalidateCustomerIdentificationToken", false)) 
{
class resurs_invalidateCustomerIdentificationToken
{

    /**
     * @var identificationToken[] $token
     * @access public
     */
    public $token = null;

    /**
     * @var nonEmptyString $governmentId
     * @access public
     */
    public $governmentId = null;

    /**
     * @var customerType $customerType
     * @access public
     */
    public $customerType = null;

    /**
     * @param identificationToken[] $token
     * @param nonEmptyString $governmentId
     * @param customerType $customerType
     * @access public
     */
    public function __construct($token, $governmentId, $customerType)
    {
      $this->token = $token;
      $this->governmentId = $governmentId;
      $this->customerType = $customerType;
    }

}

}
