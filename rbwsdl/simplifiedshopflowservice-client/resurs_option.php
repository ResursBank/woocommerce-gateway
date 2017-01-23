<?php

if (!class_exists("resurs_option", false)) 
{
class resurs_option
{

    /**
     * @var string $label
     * @access public
     */
    public $label = null;

    /**
     * @var string $value
     * @access public
     */
    public $value = null;

    /**
     * @var string $description
     * @access public
     */
    public $description = null;

    /**
     * @var boolean $checked
     * @access public
     */
    public $checked = null;

    /**
     * @param string $label
     * @param string $value
     * @param string $description
     * @param boolean $checked
     * @access public
     */
    public function __construct($label, $value, $description, $checked)
    {
      $this->label = $label;
      $this->value = $value;
      $this->description = $description;
      $this->checked = $checked;
    }

}

}
