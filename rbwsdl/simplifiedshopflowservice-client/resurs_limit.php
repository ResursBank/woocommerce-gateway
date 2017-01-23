<?php

if (!class_exists("resurs_limit", false)) 
{
class resurs_limit
{

    /**
     * @var positiveDecimal $approvedAmount
     * @access public
     */
    public $approvedAmount = null;

    /**
     * @var limitDecision $decision
     * @access public
     */
    public $decision = null;

    /**
     * @var customer $customer
     * @access public
     */
    public $customer = null;

    /**
     * @var id $limitRequestId
     * @access public
     */
    public $limitRequestId = null;

    /**
     * @param positiveDecimal $approvedAmount
     * @param limitDecision $decision
     * @param customer $customer
     * @param id $limitRequestId
     * @access public
     */
    public function __construct($approvedAmount, $decision, $customer, $limitRequestId)
    {
      $this->approvedAmount = $approvedAmount;
      $this->decision = $decision;
      $this->customer = $customer;
      $this->limitRequestId = $limitRequestId;
    }

}

}
