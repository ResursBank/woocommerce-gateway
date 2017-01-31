<?php

if (!class_exists("resurs_basicPayment", false)) 
{
class resurs_basicPayment
{

    /**
     * @var id $paymentId
     * @access public
     */
    public $paymentId = null;

    /**
     * @var id $paymentMethodId
     * @access public
     */
    public $paymentMethodId = null;

    /**
     * @var nonEmptyString $paymentMethodName
     * @access public
     */
    public $paymentMethodName = null;

    /**
     * @var nonEmptyString $governmentId
     * @access public
     */
    public $governmentId = null;

    /**
     * @var string $fullName
     * @access public
     */
    public $fullName = null;

    /**
     * @var dateTime $booked
     * @access public
     */
    public $booked = null;

    /**
     * @var dateTime $modified
     * @access public
     */
    public $modified = null;

    /**
     * @var dateTime $finalized
     * @access public
     */
    public $finalized = null;

    /**
     * @var positiveDecimal $totalAmount
     * @access public
     */
    public $totalAmount = null;

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
     * @var int $totalBonusPoints
     * @access public
     */
    public $totalBonusPoints = null;

    /**
     * @param id $paymentId
     * @param id $paymentMethodId
     * @param nonEmptyString $paymentMethodName
     * @param nonEmptyString $governmentId
     * @param string $fullName
     * @param dateTime $booked
     * @param dateTime $modified
     * @param dateTime $finalized
     * @param positiveDecimal $totalAmount
     * @param boolean $frozen
     * @param paymentStatus[] $status
     * @param int $totalBonusPoints
     * @access public
     */
    public function __construct($paymentId, $paymentMethodId, $paymentMethodName, $governmentId, $fullName, $booked, $modified, $finalized, $totalAmount, $frozen, $status, $totalBonusPoints)
    {
      $this->paymentId = $paymentId;
      $this->paymentMethodId = $paymentMethodId;
      $this->paymentMethodName = $paymentMethodName;
      $this->governmentId = $governmentId;
      $this->fullName = $fullName;
      $this->booked = $booked;
      $this->modified = $modified;
      $this->finalized = $finalized;
      $this->totalAmount = $totalAmount;
      $this->frozen = $frozen;
      $this->status = $status;
      $this->totalBonusPoints = $totalBonusPoints;
    }

}

}
