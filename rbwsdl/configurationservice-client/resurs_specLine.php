<?php

if (!class_exists("resurs_specLine", false)) 
{
class resurs_specLine
{

    /**
     * @var id $id
     * @access public
     */
    public $id = null;

    /**
     * @var string $artNo
     * @access public
     */
    public $artNo = null;

    /**
     * @var string $description
     * @access public
     */
    public $description = null;

    /**
     * @var float $quantity
     * @access public
     */
    public $quantity = null;

    /**
     * @var string $unitMeasure
     * @access public
     */
    public $unitMeasure = null;

    /**
     * @var float $unitAmountWithoutVat
     * @access public
     */
    public $unitAmountWithoutVat = null;

    /**
     * @var percent $vatPct
     * @access public
     */
    public $vatPct = null;

    /**
     * @var float $totalVatAmount
     * @access public
     */
    public $totalVatAmount = null;

    /**
     * @var float $totalAmount
     * @access public
     */
    public $totalAmount = null;

    /**
     * @param id $id
     * @param string $artNo
     * @param string $description
     * @param float $quantity
     * @param string $unitMeasure
     * @param float $unitAmountWithoutVat
     * @param percent $vatPct
     * @param float $totalVatAmount
     * @param float $totalAmount
     * @access public
     */
    public function __construct($id, $artNo, $description, $quantity, $unitMeasure, $unitAmountWithoutVat, $vatPct, $totalVatAmount, $totalAmount)
    {
      $this->id = $id;
      $this->artNo = $artNo;
      $this->description = $description;
      $this->quantity = $quantity;
      $this->unitMeasure = $unitMeasure;
      $this->unitAmountWithoutVat = $unitAmountWithoutVat;
      $this->vatPct = $vatPct;
      $this->totalVatAmount = $totalVatAmount;
      $this->totalAmount = $totalAmount;
    }

}

}
