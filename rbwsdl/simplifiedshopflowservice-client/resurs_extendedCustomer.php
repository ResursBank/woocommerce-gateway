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
     * @param address $address
     * @param string $phone
     * @param nonEmptyString $email
     * @access public
     */
    public function __construct($address, $phone, $email)
    {
      parent::__construct($address, $phone, $email);
    }

}

}
