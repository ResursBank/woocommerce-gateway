<?php

namespace Resursbank\Ecommerce\Types;

/**
 * Class CheckoutType
 * @package Resursbank\Ecommerce
 * @since 1.4.0
 */
class CheckoutType
{
    const SIMPLIFIED_FLOW = 1;
    const HOSTED_FLOW = 2;
    const RESURS_CHECKOUT = 3;

    /**
     * The "minimalistic flow" has been used in validations with payments where only the necessary fields are required.
     * This content matching should probably another solution.
     * @var int
     * @deprecated Still used in ECom 1.3 but should be avoided.
     */
    const MINIMALISTIC = 98;
}
