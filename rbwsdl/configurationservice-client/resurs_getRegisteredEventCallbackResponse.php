<?php

if (!class_exists("resurs_getRegisteredEventCallbackResponse", false)) 
{
class resurs_getRegisteredEventCallbackResponse
{

    /**
     * @var string $uriTemplate
     * @access public
     */
    public $uriTemplate = null;

    /**
     * @param string $uriTemplate
     * @access public
     */
    public function __construct($uriTemplate)
    {
      $this->uriTemplate = $uriTemplate;
    }

}

}
