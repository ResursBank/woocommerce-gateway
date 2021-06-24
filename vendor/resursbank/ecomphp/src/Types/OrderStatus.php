<?php

namespace Resursbank\Ecommerce\Types;

/**
 * Class OrderStatus
 * Extended order status, which includes statuses others than the defaults (1-64) and which could be applied on
 * the full order (like frozen, etc).
 * @package Resursbank\Ecommerce\Types
 */
class OrderStatus
{
    /** @var int Payment initialized, not yet handled. */
    const PENDING = 1;

    /** @var int Payment booked and ready to process. */
    const PROCESSING = 2;

    /** @var int Order is fully paid and completed. */
    const COMPLETED = 4;

    /** @var int Order is annulled. */
    const ANNULLED = 8;

    /** @var int Order is credited. */
    const CREDITED = 16;

    /** @var int Order was finalized instantly after the booking (SWISH, INTERNET, etc). */
    const AUTO_DEBITED = 32;

    /**
     * Orders stuck in frozen mode.
     * @var int
     */
    const MANUAL_INSPECTION = 64;

    /**
     * @var int
     * @deprecated If this doesn't work, we have worse problems.
     */
    const ERROR = 128;
}
