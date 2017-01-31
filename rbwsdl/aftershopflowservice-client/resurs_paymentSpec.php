<?php

if (!class_exists("resurs_paymentSpec", false)) 
{
class resurs_paymentSpec
{

    /**
     * @var specLine[] $specLines
     * @access public
     */
    public $specLines = null;

    /**
     * @var positiveDecimal $totalAmount
     * @access public
     */
    public $totalAmount = null;

    /**
     * @var float $totalVatAmount
     * @access public
     */
    public $totalVatAmount = null;

    /**
     * @var int $bonusPoints
     * @access public
     */
    public $bonusPoints = null;

    /**
     * @param specLine[] $specLines
     * @param positiveDecimal $totalAmount
     * @param int $bonusPoints
     * @access public
     */
    public function __construct($specLines, $totalAmount, $bonusPoints)
    {
      $this->specLines = $specLines;
      $this->totalAmount = $totalAmount;
      $this->bonusPoints = $bonusPoints;
    }

}

}
