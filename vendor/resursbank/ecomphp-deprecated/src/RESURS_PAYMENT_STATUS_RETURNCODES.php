<?php

namespace Resursbank\RBEcomPHP;

/**
 * Class RESURS_PAYMENT_STATUS_RETURNCODES
 * @package Resursbank\RBEcomPHP
 * @deprecated Do not query this. Use Resursbank\Ecommerce\PaymentStatus or Resursbank\Ecommerce\OrderStatus.
 */
class RESURS_PAYMENT_STATUS_RETURNCODES
{
    const PENDING = 1;
    const PROCESSING = 2;
    const COMPLETED = 4;
    const ANNULLED = 8;
    const CREDITED = 16;
    const AUTO_DEBITED = 32;
    const MANUAL_INSPECTION = 64;
    const ERROR = 128;

    /**
     * @var int
     * @deprecated
     */
    const PAYMENT_PENDING = 1;
    /**
     * @var int
     * @deprecated
     */
    const PAYMENT_PROCESSING = 2;
    /**
     * @var int
     * @deprecated
     */
    const PAYMENT_COMPLETED = 4;
    /**
     * @var int
     * @deprecated
     */
    const PAYMENT_ANNULLED = 8;
    /**
     * @var int
     * @deprecated
     */
    const PAYMENT_CREDITED = 16;
    /**
     * @var int
     * @deprecated
     */
    const PAYMENT_AUTOMATICALLY_DEBITED = 32;

    /**
     * @var int
     * @deprecated Use ERROR instead. Or do not use it at all!
     */
    const PAYMENT_STATUS_COULD_NOT_BE_SET = 128;
}
