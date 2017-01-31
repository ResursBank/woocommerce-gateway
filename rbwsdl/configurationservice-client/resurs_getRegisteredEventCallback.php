<?php

if (!class_exists("resurs_getRegisteredEventCallback", false)) 
{
class resurs_getRegisteredEventCallback
{

    /**
     * @var id $eventType
     * @access public
     */
    public $eventType = null;

    /**
     * @param id $eventType
     * @access public
     */
    public function __construct($eventType)
    {
      $this->eventType = $eventType;
    }

}

}
