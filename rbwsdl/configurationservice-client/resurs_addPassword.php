<?php

if (!class_exists("resurs_addPassword", false)) 
{
class resurs_addPassword
{

    /**
     * @var id $identifier
     * @access public
     */
    public $identifier = null;

    /**
     * @var string $description
     * @access public
     */
    public $description = null;

    /**
     * @var nonEmptyString $newPassword
     * @access public
     */
    public $newPassword = null;

    /**
     * @param id $identifier
     * @param nonEmptyString $newPassword
     * @access public
     */
    public function __construct($identifier, $newPassword)
    {
      $this->identifier = $identifier;
      $this->newPassword = $newPassword;
    }

}

}
