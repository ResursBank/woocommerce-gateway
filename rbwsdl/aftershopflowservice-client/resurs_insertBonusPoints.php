<?php

if (!class_exists("resurs_insertBonusPoints", false)) 
{
class resurs_insertBonusPoints
{

    /**
     * @var string $governmentId
     * @access public
     */
    public $governmentId = null;

    /**
     * @var customerType $customerType
     * @access public
     */
    public $customerType = null;

    /**
     * @var int $bonusPoints
     * @access public
     */
    public $bonusPoints = null;

    /**
     * @var date $expirationDate
     * @access public
     */
    public $expirationDate = null;

    /**
     * @param string $governmentId
     * @param customerType $customerType
     * @param int $bonusPoints
     * @param date $expirationDate
     * @access public
     */
    public function __construct($governmentId, $customerType, $bonusPoints, $expirationDate)
    {
      $this->governmentId = $governmentId;
      $this->customerType = $customerType;
      $this->bonusPoints = $bonusPoints;
      $this->expirationDate = $expirationDate;
    }

}

}
