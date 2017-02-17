<?php

if (!class_exists("resurs_removePassword", false)) 
{
class resurs_removePassword
{

    /**
     * @var id $identifier
     * @access public
     */
    public $identifier = null;

    /**
     * @param id $identifier
     * @access public
     */
    public function __construct($identifier)
    {
      $this->identifier = $identifier;
    }

}

}
