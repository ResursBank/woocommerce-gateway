<?php

if (!class_exists("resurs_address", false)) 
{
class resurs_address
{

    /**
     * @var nonEmptyString $fullName
     * @access public
     */
    public $fullName = null;

    /**
     * @var nonEmptyString $firstName
     * @access public
     */
    public $firstName = null;

    /**
     * @var nonEmptyString $lastName
     * @access public
     */
    public $lastName = null;

    /**
     * @var string $addressRow1
     * @access public
     */
    public $addressRow1 = null;

    /**
     * @var nonEmptyString $addressRow2
     * @access public
     */
    public $addressRow2 = null;

    /**
     * @var nonEmptyString $postalArea
     * @access public
     */
    public $postalArea = null;

    /**
     * @var nonEmptyString $postalCode
     * @access public
     */
    public $postalCode = null;

    /**
     * @var countryCode $country
     * @access public
     */
    public $country = null;

    /**
     * @param nonEmptyString $fullName
     * @param nonEmptyString $firstName
     * @param nonEmptyString $lastName
     * @param string $addressRow1
     * @param nonEmptyString $addressRow2
     * @param nonEmptyString $postalArea
     * @param nonEmptyString $postalCode
     * @param countryCode $country
     * @access public
     */
    public function __construct($fullName, $firstName, $lastName, $addressRow1, $addressRow2, $postalArea, $postalCode, $country)
    {
      $this->fullName = $fullName;
      $this->firstName = $firstName;
      $this->lastName = $lastName;
      $this->addressRow1 = $addressRow1;
      $this->addressRow2 = $addressRow2;
      $this->postalArea = $postalArea;
      $this->postalCode = $postalCode;
      $this->country = $country;
    }

}

}
