<?php

if (!class_exists("resurs_payment", false)) 
{
class resurs_payment
{

    /**
     * @var id $id
     * @access public
     */
    public $id = null;

    /**
     * @var positiveDecimal $totalAmount
     * @access public
     */
    public $totalAmount = null;

    /**
     * @var mapEntry[] $metaData
     * @access public
     */
    public $metaData = null;

    /**
     * @var positiveDecimal $limit
     * @access public
     */
    public $limit = null;

    /**
     * @var paymentDiff[] $paymentDiffs
     * @access public
     */
    public $paymentDiffs = null;

    /**
     * @var customer $customer
     * @access public
     */
    public $customer = null;

    /**
     * @var address $deliveryAddress
     * @access public
     */
    public $deliveryAddress = null;

    /**
     * @var dateTime $booked
     * @access public
     */
    public $booked = null;

    /**
     * @var dateTime $finalized
     * @access public
     */
    public $finalized = null;

    /**
     * @var id $paymentMethodId
     * @access public
     */
    public $paymentMethodId = null;

    /**
     * @var string $paymentMethodName
     * @access public
     */
    public $paymentMethodName = null;

    /**
     * @var boolean $fraud
     * @access public
     */
    public $fraud = null;

    /**
     * @var boolean $frozen
     * @access public
     */
    public $frozen = null;

    /**
     * @var paymentStatus[] $status
     * @access public
     */
    public $status = null;

    /**
     * @var id $storeId
     * @access public
     */
    public $storeId = null;

    /**
     * @var paymentMethodType $paymentMethodType
     * @access public
     */
    public $paymentMethodType = null;

    /**
     * @var int $totalBonusPoints
     * @access public
     */
    public $totalBonusPoints = null;

    /**
     * @param id $id
     * @param positiveDecimal $totalAmount
     * @param mapEntry[] $metaData
     * @param positiveDecimal $limit
     * @param paymentDiff[] $paymentDiffs
     * @param customer $customer
     * @param address $deliveryAddress
     * @param dateTime $booked
     * @param dateTime $finalized
     * @param id $paymentMethodId
     * @param string $paymentMethodName
     * @param boolean $fraud
     * @param boolean $frozen
     * @param paymentStatus[] $status
     * @param id $storeId
     * @param paymentMethodType $paymentMethodType
     * @param int $totalBonusPoints
     * @access public
     */
    public function __construct($id, $totalAmount, $metaData, $limit, $paymentDiffs, $customer, $deliveryAddress, $booked, $finalized, $paymentMethodId, $paymentMethodName, $fraud, $frozen, $status, $storeId, $paymentMethodType, $totalBonusPoints)
    {
      $this->id = $id;
      $this->totalAmount = $totalAmount;
      $this->metaData = $metaData;
      $this->limit = $limit;
      $this->paymentDiffs = $paymentDiffs;
      $this->customer = $customer;
      $this->deliveryAddress = $deliveryAddress;
      $this->booked = $booked;
      $this->finalized = $finalized;
      $this->paymentMethodId = $paymentMethodId;
      $this->paymentMethodName = $paymentMethodName;
      $this->fraud = $fraud;
      $this->frozen = $frozen;
      $this->status = $status;
      $this->storeId = $storeId;
      $this->paymentMethodType = $paymentMethodType;
      $this->totalBonusPoints = $totalBonusPoints;
    }

}

}
