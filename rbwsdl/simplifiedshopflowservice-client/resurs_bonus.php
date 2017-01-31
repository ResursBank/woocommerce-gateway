<?php

if (!class_exists("resurs_bonus", false)) 
{
class resurs_bonus
{

    /**
     * @var int $points
     * @access public
     */
    public $points = null;

    /**
     * @param int $points
     * @access public
     */
    public function __construct($points)
    {
      $this->points = $points;
    }

}

}
