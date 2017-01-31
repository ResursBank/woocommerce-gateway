<?php

if (!class_exists("resurs_withdrawBonusPoints", false)) 
{
class resurs_withdrawBonusPoints
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
     * @param string $governmentId
     * @param customerType $customerType
     * @param int $bonusPoints
     * @access public
     */
    public function __construct($governmentId, $customerType, $bonusPoints)
    {
      $this->governmentId = $governmentId;
      $this->customerType = $customerType;
      $this->bonusPoints = $bonusPoints;
    }

}

}
