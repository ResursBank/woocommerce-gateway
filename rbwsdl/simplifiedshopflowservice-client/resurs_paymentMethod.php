<?php

if (!class_exists("resurs_paymentMethod", false)) 
{
class resurs_paymentMethod
{

    /**
     * @var id $id
     * @access public
     */
    public $id = null;

    /**
     * @var string $description
     * @access public
     */
    public $description = null;

    /**
     * @var webLink[] $legalInfoLinks
     * @access public
     */
    public $legalInfoLinks = null;

    /**
     * @var positiveDecimal $minLimit
     * @access public
     */
    public $minLimit = null;

    /**
     * @var positiveDecimal $maxLimit
     * @access public
     */
    public $maxLimit = null;

    /**
     * @var paymentMethodType $type
     * @access public
     */
    public $type = null;

    /**
     * @var customerType[] $customerType
     * @access public
     */
    public $customerType = null;

    /**
     * @var string $specificType
     * @access public
     */
    public $specificType = null;

    /**
     * @param id $id
     * @param string $description
     * @param webLink[] $legalInfoLinks
     * @param positiveDecimal $minLimit
     * @param positiveDecimal $maxLimit
     * @param paymentMethodType $type
     * @param string $specificType
     * @access public
     */
    public function __construct($id, $description, $legalInfoLinks, $minLimit, $maxLimit, $type, $specificType)
    {
      $this->id = $id;
      $this->description = $description;
      $this->legalInfoLinks = $legalInfoLinks;
      $this->minLimit = $minLimit;
      $this->maxLimit = $maxLimit;
      $this->type = $type;
      $this->specificType = $specificType;
    }

}

}
