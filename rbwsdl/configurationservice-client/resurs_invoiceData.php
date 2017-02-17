<?php

if (!class_exists("resurs_invoiceData", false)) 
{
class resurs_invoiceData
{

    /**
     * @var nonEmptyString $name
     * @access public
     */
    public $name = null;

    /**
     * @var nonEmptyString $street
     * @access public
     */
    public $street = null;

    /**
     * @var nonEmptyString $zipcode
     * @access public
     */
    public $zipcode = null;

    /**
     * @var nonEmptyString $city
     * @access public
     */
    public $city = null;

    /**
     * @var nonEmptyString $country
     * @access public
     */
    public $country = null;

    /**
     * @var nonEmptyString $phone
     * @access public
     */
    public $phone = null;

    /**
     * @var string $fax
     * @access public
     */
    public $fax = null;

    /**
     * @var nonEmptyString $email
     * @access public
     */
    public $email = null;

    /**
     * @var nonEmptyString $homepage
     * @access public
     */
    public $homepage = null;

    /**
     * @var nonEmptyString $vatreg
     * @access public
     */
    public $vatreg = null;

    /**
     * @var nonEmptyString $orgnr
     * @access public
     */
    public $orgnr = null;

    /**
     * @var boolean $companytaxnote
     * @access public
     */
    public $companytaxnote = null;

    /**
     * @var base64Binary $logotype
     * @access public
     */
    public $logotype = null;

    /**
     * @var string $modifiedby
     * @access public
     */
    public $modifiedby = null;

    /**
     * @param nonEmptyString $name
     * @param nonEmptyString $street
     * @param nonEmptyString $zipcode
     * @param nonEmptyString $city
     * @param nonEmptyString $country
     * @param nonEmptyString $phone
     * @param string $fax
     * @param nonEmptyString $email
     * @param nonEmptyString $homepage
     * @param nonEmptyString $vatreg
     * @param nonEmptyString $orgnr
     * @param boolean $companytaxnote
     * @param base64Binary $logotype
     * @param string $modifiedby
     * @access public
     */
    public function __construct($name, $street, $zipcode, $city, $country, $phone, $fax, $email, $homepage, $vatreg, $orgnr, $companytaxnote, $logotype, $modifiedby)
    {
      $this->name = $name;
      $this->street = $street;
      $this->zipcode = $zipcode;
      $this->city = $city;
      $this->country = $country;
      $this->phone = $phone;
      $this->fax = $fax;
      $this->email = $email;
      $this->homepage = $homepage;
      $this->vatreg = $vatreg;
      $this->orgnr = $orgnr;
      $this->companytaxnote = $companytaxnote;
      $this->logotype = $logotype;
      $this->modifiedby = $modifiedby;
    }

}

}
