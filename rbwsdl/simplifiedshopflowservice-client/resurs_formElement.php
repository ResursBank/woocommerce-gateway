<?php

if (!class_exists("resurs_formElement", false)) 
{
class resurs_formElement
{

    /**
     * @var string $label
     * @access public
     */
    public $label = null;

    /**
     * @var string $description
     * @access public
     */
    public $description = null;

    /**
     * @var string $format
     * @access public
     */
    public $format = null;

    /**
     * @var string $formatmessage
     * @access public
     */
    public $formatmessage = null;

    /**
     * @var string $defaultvalue
     * @access public
     */
    public $defaultvalue = null;

    /**
     * @var option[] $option
     * @access public
     */
    public $option = null;

    /**
     * @var string $type
     * @access public
     */
    public $type = null;

    /**
     * @var string $name
     * @access public
     */
    public $name = null;

    /**
     * @var boolean $mandatory
     * @access public
     */
    public $mandatory = null;

    /**
     * @var int $length
     * @access public
     */
    public $length = null;

    /**
     * @var int $min
     * @access public
     */
    public $min = null;

    /**
     * @var int $max
     * @access public
     */
    public $max = null;

    /**
     * @var string $unit
     * @access public
     */
    public $unit = null;

    /**
     * @var int $level
     * @access public
     */
    public $level = null;

    /**
     * @param string $label
     * @param string $description
     * @param string $format
     * @param string $formatmessage
     * @param string $defaultvalue
     * @param option[] $option
     * @param string $type
     * @param string $name
     * @param boolean $mandatory
     * @param int $length
     * @param int $min
     * @param int $max
     * @param string $unit
     * @param int $level
     * @access public
     */
    public function __construct($label, $description, $format, $formatmessage, $defaultvalue, $option, $type, $name, $mandatory, $length, $min, $max, $unit, $level)
    {
      $this->label = $label;
      $this->description = $description;
      $this->format = $format;
      $this->formatmessage = $formatmessage;
      $this->defaultvalue = $defaultvalue;
      $this->option = $option;
      $this->type = $type;
      $this->name = $name;
      $this->mandatory = $mandatory;
      $this->length = $length;
      $this->min = $min;
      $this->max = $max;
      $this->unit = $unit;
      $this->level = $level;
    }

}

}
