<?php

if (!class_exists("resurs_creditPayment", false)) 
{
class resurs_creditPayment
{

    /**
     * @var id $paymentId
     * @access public
     */
    public $paymentId = null;

    /**
     * @var id $preferredTransactionId
     * @access public
     */
    public $preferredTransactionId = null;

    /**
     * @var paymentSpec $partPaymentSpec
     * @access public
     */
    public $partPaymentSpec = null;

    /**
     * @var nonEmptyString $createdBy
     * @access public
     */
    public $createdBy = null;

    /**
     * @var id $creditNoteId
     * @access public
     */
    public $creditNoteId = null;

    /**
     * @var date $creditNoteDate
     * @access public
     */
    public $creditNoteDate = null;

    /**
     * @var invoiceDeliveryTypeEnum $invoiceDeliveryType
     * @access public
     */
    public $invoiceDeliveryType = null;

    /**
     * @param id $paymentId
     * @access public
     */
    public function __construct($paymentId)
    {
      $this->paymentId = $paymentId;
    }

}

}
