<?php

if (!class_exists("resurs_getPaymentMethods", false)) 
{
class resurs_getPaymentMethods
{

    /**
     * @var language $language
     * @access public
     */
    public $language = null;

    /**
     * @var customerType $customerType
     * @access public
     */
    public $customerType = null;

    /**
     * @var positiveDecimal $purchaseAmount
     * @access public
     */
    public $purchaseAmount = null;

    /**
     * @access public
     */
    public function __construct()
    {
    
    }

}

}
