<?php

namespace Resursbank\Ecommerce\Types;

/**
 * Class Callback
 * @package Resursbank\Ecommerce\Types
 * @since 1.4.0
 */
class Callback
{
    const UNFREEZE = 1;
    const ANNULMENT = 2;
    const AUTOMATIC_FRAUD_CONTROL = 4;
    const FINALIZATION = 8;
    const TEST = 16;
    const UPDATE = 32;
    const BOOKED = 64;
}
