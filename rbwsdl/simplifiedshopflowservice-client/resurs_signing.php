<?php

if (!class_exists("resurs_signing", false)) 
{
class resurs_signing
{

    /**
     * @var string $successUrl
     * @access public
     */
    public $successUrl = null;

    /**
     * @var string $failUrl
     * @access public
     */
    public $failUrl = null;

    /**
     * @var boolean $forceSigning
     * @access public
     */
    public $forceSigning = null;

    /**
     * @param string $successUrl
     * @param string $failUrl
     * @access public
     */
    public function __construct($successUrl, $failUrl)
    {
      $this->successUrl = $successUrl;
      $this->failUrl = $failUrl;
    }

}

}
