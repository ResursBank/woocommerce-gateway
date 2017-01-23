<?php

if (!class_exists("resurs_peekInvoiceSequenceResponse", false)) 
{
class resurs_peekInvoiceSequenceResponse
{

    /**
     * @var int $nextInvoiceNumber
     * @access public
     */
    public $nextInvoiceNumber = null;

    /**
     * @param int $nextInvoiceNumber
     * @access public
     */
    public function __construct($nextInvoiceNumber)
    {
      $this->nextInvoiceNumber = $nextInvoiceNumber;
    }

}

}
