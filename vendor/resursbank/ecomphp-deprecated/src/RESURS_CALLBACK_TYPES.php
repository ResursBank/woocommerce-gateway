<?php

namespace Resursbank\RBEcomPHP;

/**
 * Class RESURS_CALLBACK_TYPES
 * @package Resursbank\RBEcomPHP
 * @deprecated Do not query this. Use Resursbank\Ecommerce\Callback.
 */
class RESURS_CALLBACK_TYPES
{
    const NOT_SET = 0;
    const UNFREEZE = 1;
    const ANNULMENT = 2;
    const AUTOMATIC_FRAUD_CONTROL = 4;
    const FINALIZATION = 8;
    const TEST = 16;
    const UPDATE = 32;
    const BOOKED = 64;

    /**
     * @deprecated Do not use this!
     */
    const CALLBACK_TYPE_NOT_SET = 0;
    /**
     * @deprecated Do not use this!
     */
    const CALLBACK_TYPE_UNFREEZE = 1;
    /**
     * @deprecated Do not use this!
     */
    const CALLBACK_TYPE_ANNULMENT = 2;
    /**
     * @deprecated Do not use this!
     */
    const CALLBACK_TYPE_AUTOMATIC_FRAUD_CONTROL = 4;
    /**
     * @deprecated Do not use this!
     */
    const CALLBACK_TYPE_FINALIZATION = 8;
    /**
     * @deprecated Do not use this!
     */
    const CALLBACK_TYPE_TEST = 16;
    /**
     * @deprecated Do not use this!
     */
    const CALLBACK_TYPE_UPDATE = 32;
    /**
     * @deprecated Do not use this!
     */
    const CALLBACK_TYPE_BOOKED = 64;
}
