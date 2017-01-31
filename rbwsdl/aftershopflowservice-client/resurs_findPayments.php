<?php

if (!class_exists("resurs_findPayments", false)) 
{
class resurs_findPayments
{

    /**
     * @var searchCriteria $searchCriteria
     * @access public
     */
    public $searchCriteria = null;

    /**
     * @var int $pageNumber
     * @access public
     */
    public $pageNumber = null;

    /**
     * @var int $itemsPerPage
     * @access public
     */
    public $itemsPerPage = null;

    /**
     * @var sortOrder $sortBy
     * @access public
     */
    public $sortBy = null;

    /**
     * @param searchCriteria $searchCriteria
     * @access public
     */
    public function __construct($searchCriteria)
    {
      $this->searchCriteria = $searchCriteria;
    }

}

}
