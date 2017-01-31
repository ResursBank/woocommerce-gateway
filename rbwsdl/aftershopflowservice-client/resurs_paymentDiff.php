<?php

if (!class_exists("resurs_paymentDiff", false)) 
{
class resurs_paymentDiff
{

    /**
     * @var paymentDiffType $type
     * @access public
     */
    public $type = null;

    /**
     * @var id $transactionId
     * @access public
     */
    public $transactionId = null;

    /**
     * @var dateTime $created
     * @access public
     */
    public $created = null;

    /**
     * @var string $createdBy
     * @access public
     */
    public $createdBy = null;

    /**
     * @var paymentSpec $paymentSpec
     * @access public
     */
    public $paymentSpec = null;

    /**
     * @var id $orderId
     * @access public
     */
    public $orderId = null;

    /**
     * @var id $invoiceId
     * @access public
     */
    public $invoiceId = null;

    /**
     * @var nonEmptyString[] $documentNames
     * @access public
     */
    public $documentNames = null;

    /**
     * @param paymentDiffType $type
     * @param id $transactionId
     * @param dateTime $created
     * @param string $createdBy
     * @param paymentSpec $paymentSpec
     * @param id $orderId
     * @param id $invoiceId
     * @param nonEmptyString[] $documentNames
     * @access public
     */
    public function __construct($type, $transactionId, $created, $createdBy, $paymentSpec, $orderId, $invoiceId, $documentNames)
    {
      $this->type = $type;
      $this->transactionId = $transactionId;
      $this->created = $created;
      $this->createdBy = $createdBy;
      $this->paymentSpec = $paymentSpec;
      $this->orderId = $orderId;
      $this->invoiceId = $invoiceId;
      $this->documentNames = $documentNames;
    }

}

}
