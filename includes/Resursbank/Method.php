<?php

if (!class_exists('WC_Resursbank_Method') && class_exists('WC_Gateway_ResursBank')) {

    /**
     * Generic Method Class for Resurs Bank
     * Class WC_Resursbank_Method
     */
    class WC_Resursbank_Method extends WC_Gateway_ResursBank
    {
        protected $METHOD_TYPE;

        function __construct($id = '')
        {
            // id, description, title


        }
    }

}
