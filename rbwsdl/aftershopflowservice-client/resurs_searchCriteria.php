<?php

if (!class_exists("resurs_searchCriteria", false)) 
{
class resurs_searchCriteria
{

    /**
     * @var id $anyId
     * @access public
     */
    public $anyId = null;

    /**
     * @var id $paymentMethodId
     * @access public
     */
    public $paymentMethodId = null;

    /**
     * @var nonEmptyString $governmentId
     * @access public
     */
    public $governmentId = null;

    /**
     * @var string $customerName
     * @access public
     */
    public $customerName = null;

    /**
     * @var dateTime $bookedFrom
     * @access public
     */
    public $bookedFrom = null;

    /**
     * @var dateTime $bookedTo
     * @access public
     */
    public $bookedTo = null;

    /**
     * @var dateTime $modifiedFrom
     * @access public
     */
    public $modifiedFrom = null;

    /**
     * @var dateTime $modifiedTo
     * @access public
     */
    public $modifiedTo = null;

    /**
     * @var dateTime $finalizedFrom
     * @access public
     */
    public $finalizedFrom = null;

    /**
     * @var dateTime $finalizedTo
     * @access public
     */
    public $finalizedTo = null;

    /**
     * @var positiveDecimal $amountFrom
     * @access public
     */
    public $amountFrom = null;

    /**
     * @var positiveDecimal $amountTo
     * @access public
     */
    public $amountTo = null;

    /**
     * @var int $bonusFrom
     * @access public
     */
    public $bonusFrom = null;

    /**
     * @var int $bonusTo
     * @access public
     */
    public $bonusTo = null;

    /**
     * @var boolean $frozen
     * @access public
     */
    public $frozen = null;

    /**
     * @var withMetaData $withMetaData
     * @access public
     */
    public $withMetaData = null;

    /**
     * @var paymentStatus[] $statusSet
     * @access public
     */
    public $statusSet = null;

    /**
     * @var paymentStatus[] $statusNotSet
     * @access public
     */
    public $statusNotSet = null;

    /**
     * @var boolean $bonusIsUsed
     * @access public
     */
    public $bonusIsUsed = null;

    /**
     * @param paymentStatus[] $statusSet
     * @param paymentStatus[] $statusNotSet
     * @param boolean $bonusIsUsed
     * @access public
     */
    public function __construct($statusSet, $statusNotSet, $bonusIsUsed)
    {
      $this->statusSet = $statusSet;
      $this->statusNotSet = $statusNotSet;
      $this->bonusIsUsed = $bonusIsUsed;
    }

}

}
