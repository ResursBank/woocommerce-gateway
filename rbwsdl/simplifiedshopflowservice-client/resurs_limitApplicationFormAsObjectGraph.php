<?php

if (!class_exists("resurs_limitApplicationFormAsObjectGraph", false)) 
{
class resurs_limitApplicationFormAsObjectGraph
{

    /**
     * @var string $formId
     * @access public
     */
    public $formId = null;

    /**
     * @var formElement[] $formElement
     * @access public
     */
    public $formElement = null;

    /**
     * @param string $formId
     * @param formElement[] $formElement
     * @access public
     */
    public function __construct($formId, $formElement)
    {
      $this->formId = $formId;
      $this->formElement = $formElement;
    }

}

}
