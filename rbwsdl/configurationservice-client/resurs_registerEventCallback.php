<?php

if (!class_exists("resurs_registerEventCallback", false)) 
{
class resurs_registerEventCallback
{

    /**
     * @var id $eventType
     * @access public
     */
    public $eventType = null;

    /**
     * @var nonEmptyString $uriTemplate
     * @access public
     */
    public $uriTemplate = null;

    /**
     * @var nonEmptyString $basicAuthUserName
     * @access public
     */
    public $basicAuthUserName = null;

    /**
     * @var nonEmptyString $basicAuthPassword
     * @access public
     */
    public $basicAuthPassword = null;

    /**
     * @var digestConfiguration $digestConfiguration
     * @access public
     */
    public $digestConfiguration = null;

    /**
     * @param id $eventType
     * @param nonEmptyString $uriTemplate
     * @access public
     */
    public function __construct($eventType, $uriTemplate)
    {
      $this->eventType = $eventType;
      $this->uriTemplate = $uriTemplate;
    }

}

}
