<?php

if (!class_exists("resurs_limitApplicationFormAsCompiledForm", false)) 
{
class resurs_limitApplicationFormAsCompiledForm
{

    /**
     * @var nonEmptyString $form
     * @access public
     */
    public $form = null;

    /**
     * @var string $formHeader
     * @access public
     */
    public $formHeader = null;

    /**
     * @var string $formOnLoad
     * @access public
     */
    public $formOnLoad = null;

    /**
     * @param nonEmptyString $form
     * @param string $formHeader
     * @param string $formOnLoad
     * @access public
     */
    public function __construct($form, $formHeader, $formOnLoad)
    {
      $this->form = $form;
      $this->formHeader = $formHeader;
      $this->formOnLoad = $formOnLoad;
    }

}

}
