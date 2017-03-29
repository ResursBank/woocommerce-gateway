<?php

if (!class_exists("resurs_customer", false)) 
{
class resurs_customer
{

    /**
     * @var nonEmptyString $governmentId
     * @access public
     */
    public $governmentId = null;

    /**
     * @var address $address
     * @access public
     */
    public $address = null;

    /**
     * @var string $phone
     * @access public
     */
    public $phone = null;

    /**
     * @var nonEmptyString $email
     * @access public
     */
    public $email = null;

    /**
     * @var customerType $type
     * @access public
     */
    public $type = null;

    /**
     * @param address $address
     * @param string $phone
     * @param nonEmptyString $email
     * @access public
     */
    public function __construct($address, $phone, $email)
    {
      $this->address = $address;
      $this->phone = $phone;
      $this->email = $email;
    }

}

}
