<?php

if (!class_exists("resurs_pdf", false)) 
{
class resurs_pdf
{

    /**
     * @var string $name
     * @access public
     */
    public $name = null;

    /**
     * @var base64Binary $pdfData
     * @access public
     */
    public $pdfData = null;

    /**
     * @param string $name
     * @param base64Binary $pdfData
     * @access public
     */
    public function __construct($name, $pdfData)
    {
      $this->name = $name;
      $this->pdfData = $pdfData;
    }

}

}
