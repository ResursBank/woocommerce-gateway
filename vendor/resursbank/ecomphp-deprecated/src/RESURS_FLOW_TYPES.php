<?php

namespace Resursbank\RBEcomPHP;

/**
 * Class RESURS_FLOW_TYPES
 * @package Resursbank\RBEcomPHP
 * @deprecated Do not query this. Use Resursbank\Ecommerce\CheckoutType.
 */
abstract class RESURS_FLOW_TYPES
{
    const NOT_SET = 0;
    const SIMPLIFIED_FLOW = 1;
    const HOSTED_FLOW = 2;
    const RESURS_CHECKOUT = 3;

    /** @var int You lazy? */
    const RCO = 3;

    /** @var int Absolutely minimalistic flow with data necessary to render anything at all (and matching data) */
    const MINIMALISTIC = 98;

    /** @deprecated Redundant name */
    const FLOW_NOT_SET = 0;
    /** @deprecated Redundant name */
    const FLOW_SIMPLIFIED_FLOW = 1;
    /** @deprecated Redundant name */
    const FLOW_HOSTED_FLOW = 2;
    /** @deprecated Redundant name */
    const FLOW_RESURS_CHECKOUT = 3;

    /** @deprecated Redundant name */
    const FLOW_MINIMALISTIC = 98;

    /** @var bool Indicator that you are using obsolete resources. */
    const MALFUNCTION = true;
}
