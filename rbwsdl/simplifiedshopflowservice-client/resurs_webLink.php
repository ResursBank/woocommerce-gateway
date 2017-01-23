<?php

if (!class_exists("resurs_webLink", false)) 
{
class resurs_webLink
{

    /**
     * @var boolean $appendPriceLast
     * @access public
     */
    public $appendPriceLast = null;

    /**
     * @var nonEmptyString $endUserDescription
     * @access public
     */
    public $endUserDescription = null;

    /**
     * @var nonEmptyString $url
     * @access public
     */
    public $url = null;

    /**
     * @param boolean $appendPriceLast
     * @param nonEmptyString $endUserDescription
     * @param nonEmptyString $url
     * @access public
     */
    public function __construct($appendPriceLast, $endUserDescription, $url)
    {
      $this->appendPriceLast = $appendPriceLast;
      $this->endUserDescription = $endUserDescription;
      $this->url = $url;
    }

}

}
