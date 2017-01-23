<?php

if (!class_exists("resurs_sortOrder", false)) 
{
class resurs_sortOrder
{

    /**
     * @var boolean $ascending
     * @access public
     */
    public $ascending = null;

    /**
     * @var sortAlternative[] $sortColumns
     * @access public
     */
    public $sortColumns = null;

    /**
     * @param boolean $ascending
     * @param sortAlternative[] $sortColumns
     * @access public
     */
    public function __construct($ascending, $sortColumns)
    {
      $this->ascending = $ascending;
      $this->sortColumns = $sortColumns;
    }

}

}
