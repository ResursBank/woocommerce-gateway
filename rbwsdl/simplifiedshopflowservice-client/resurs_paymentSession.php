<?php

if (!class_exists("resurs_paymentSession", false)) 
{
class resurs_paymentSession
{

    /**
     * @var id $id
     * @access public
     */
    public $id = null;

    /**
     * @var dateTime $expirationTime
     * @access public
     */
    public $expirationTime = null;

    /**
     * @var limitApplicationFormAsObjectGraph $limitApplicationFormAsObjectGraph
     * @access public
     */
    public $limitApplicationFormAsObjectGraph = null;

    /**
     * @var limitApplicationFormAsCompiledForm $limitApplicationFormAsCompiledForm
     * @access public
     */
    public $limitApplicationFormAsCompiledForm = null;

    /**
     * @param id $id
     * @param dateTime $expirationTime
     * @param limitApplicationFormAsObjectGraph $limitApplicationFormAsObjectGraph
     * @param limitApplicationFormAsCompiledForm $limitApplicationFormAsCompiledForm
     * @access public
     */
    public function __construct($id, $expirationTime, $limitApplicationFormAsObjectGraph, $limitApplicationFormAsCompiledForm)
    {
      $this->id = $id;
      $this->expirationTime = $expirationTime;
      $this->limitApplicationFormAsObjectGraph = $limitApplicationFormAsObjectGraph;
      $this->limitApplicationFormAsCompiledForm = $limitApplicationFormAsCompiledForm;
    }

}

}
