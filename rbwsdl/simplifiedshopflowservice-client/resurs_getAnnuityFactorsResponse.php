<?php

if (!class_exists("resurs_getAnnuityFactorsResponse", false)) 
{
class resurs_getAnnuityFactorsResponse
{

    /**
     * @var annuityFactor[] $return
     * @access public
     */
    public $return = null;

    /**
     * @param annuityFactor[] $return
     * @access public
     */
    public function __construct($return)
    {
      $this->return = $return;
    }

}

}
