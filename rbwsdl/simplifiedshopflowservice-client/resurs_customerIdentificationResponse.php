<?php

if (!class_exists("resurs_customerIdentificationResponse", false)) 
{
class resurs_customerIdentificationResponse
{

    /**
     * @var identificationToken $token
     * @access public
     */
    public $token = null;

    /**
     * @var dateTime $expirationDate
     * @access public
     */
    public $expirationDate = null;

    /**
     * @param identificationToken $token
     * @param dateTime $expirationDate
     * @access public
     */
    public function __construct($token, $expirationDate)
    {
      $this->token = $token;
      $this->expirationDate = $expirationDate;
    }

}

}
