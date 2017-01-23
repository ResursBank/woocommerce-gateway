<?php

if (!class_exists("resurs_ECommerceError", false)) 
{
class resurs_ECommerceError
{

    /**
     * @var nonEmptyString $errorTypeDescription
     * @access public
     */
    public $errorTypeDescription = null;

    /**
     * @var int $errorTypeId
     * @access public
     */
    public $errorTypeId = null;

    /**
     * @var boolean $fixableByYou
     * @access public
     */
    public $fixableByYou = null;

    /**
     * @var nonEmptyString $userErrorMessage
     * @access public
     */
    public $userErrorMessage = null;

    /**
     * @param nonEmptyString $errorTypeDescription
     * @param int $errorTypeId
     * @param boolean $fixableByYou
     * @param nonEmptyString $userErrorMessage
     * @access public
     */
    public function __construct($errorTypeDescription, $errorTypeId, $fixableByYou, $userErrorMessage)
    {
      $this->errorTypeDescription = $errorTypeDescription;
      $this->errorTypeId = $errorTypeId;
      $this->fixableByYou = $fixableByYou;
      $this->userErrorMessage = $userErrorMessage;
    }

}

}
