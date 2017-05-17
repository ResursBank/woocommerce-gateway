<?php

if (!class_exists("resurs_extendedCustomer", false)) 
{
include_once('resurs_customer.php');

class resurs_extendedCustomer extends resurs_customer
{

    /**
     * @var string $cellPhone
     * @access public
     */
    public $cellPhone = null;

    /**
     * @var string $yourCustomerId
     * @access public
     */
    public $yourCustomerId = null;

    /**
     * @var address $deliveryAddress
     * @access public
     */
    public $deliveryAddress = null;

    /**
     * @var string $contactGovernmentId
     * @access public
     */
    public $contactGovernmentId = null;

    /**
     * @var mapEntry[] $additionalData
     * @access public
     */
    public $additionalData = null;

    /**
     * @param nonEmptyString $governmentId
     * @param address $address
     * @param string $phone
     * @param nonEmptyString $email
     * @param customerType $type
     * @access public
     */
    public function __construct($governmentId, $address, $phone, $email, $type)
    {
      parent::__construct($governmentId, $address, $phone, $email, $type);
    }

}

}
