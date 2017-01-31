<?php

if (!class_exists("resurs_getInvoiceDataResponse", false)) 
{
class resurs_getInvoiceDataResponse
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
