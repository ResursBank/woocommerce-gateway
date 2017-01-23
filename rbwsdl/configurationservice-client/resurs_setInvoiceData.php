<?php

if (!class_exists("resurs_setInvoiceData", false)) 
{
class resurs_setInvoiceData
{

    /**
     * @var invoiceData $invoiceData
     * @access public
     */
    public $invoiceData = null;

    /**
     * @param invoiceData $invoiceData
     * @access public
     */
    public function __construct($invoiceData)
    {
      $this->invoiceData = $invoiceData;
    }

}

}
