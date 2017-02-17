<?php

if (!class_exists("resurs_digestConfiguration", false)) 
{
class resurs_digestConfiguration
{

    /**
     * @var digestAlgorithm $digestAlgorithm
     * @access public
     */
    public $digestAlgorithm = null;

    /**
     * @var string[] $digestParameters
     * @access public
     */
    public $digestParameters = null;

    /**
     * @var string $digestSalt
     * @access public
     */
    public $digestSalt = null;

    /**
     * @param digestAlgorithm $digestAlgorithm
     * @param string[] $digestParameters
     * @access public
     */
    public function __construct($digestAlgorithm, $digestParameters)
    {
      $this->digestAlgorithm = $digestAlgorithm;
      $this->digestParameters = $digestParameters;
    }

}

}
