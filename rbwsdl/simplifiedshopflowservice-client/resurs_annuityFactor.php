<?php

if (!class_exists("resurs_annuityFactor", false)) 
{
class resurs_annuityFactor
{

    /**
     * @var positiveDecimal $factor
     * @access public
     */
    public $factor = null;

    /**
     * @var int $duration
     * @access public
     */
    public $duration = null;

    /**
     * @var nonEmptyString $paymentPlanName
     * @access public
     */
    public $paymentPlanName = null;

    /**
     * @param positiveDecimal $factor
     * @param int $duration
     * @param nonEmptyString $paymentPlanName
     * @access public
     */
    public function __construct($factor, $duration, $paymentPlanName)
    {
      $this->factor = $factor;
      $this->duration = $duration;
      $this->paymentPlanName = $paymentPlanName;
    }

}

}
