<?php

if (!class_exists("resurs_mapEntry", false)) 
{
class resurs_mapEntry
{

    /**
     * @var nonEmptyString $key
     * @access public
     */
    public $key = null;

    /**
     * @var string $value
     * @access public
     */
    public $value = null;

    /**
     * @param nonEmptyString $key
     * @param string $value
     * @access public
     */
    public function __construct($key, $value)
    {
      $this->key = $key;
      $this->value = $value;
    }

}

}
