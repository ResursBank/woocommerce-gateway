<?php

if (!class_exists("resurs_changePassword", false)) 
{
class resurs_changePassword
{

    /**
     * @var id $identifier
     * @access public
     */
    public $identifier = null;

    /**
     * @var nonEmptyString $newPassword
     * @access public
     */
    public $newPassword = null;

    /**
     * @param nonEmptyString $newPassword
     * @access public
     */
    public function __construct($newPassword)
    {
      $this->newPassword = $newPassword;
    }

}

}
