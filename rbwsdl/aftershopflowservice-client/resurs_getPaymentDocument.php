<?php

if (!class_exists("resurs_getPaymentDocument", false)) 
{
class resurs_getPaymentDocument
{

    /**
     * @var id $paymentId
     * @access public
     */
    public $paymentId = null;

    /**
     * @var nonEmptyString $documentName
     * @access public
     */
    public $documentName = null;

    /**
     * @param id $paymentId
     * @param nonEmptyString $documentName
     * @access public
     */
    public function __construct($paymentId, $documentName)
    {
      $this->paymentId = $paymentId;
      $this->documentName = $documentName;
    }

}

}
