<?php

namespace Resurs\WooCommerce\Service\Queue;

use TorneLIB\IO\Data\Strings;
use WC_Order;
use WC_Queue_Interface;

/**
 * Ported queue handler for order statuses.
 *
 * @see https://github.com/Tornevall/wpwc-resurs/commit/6a7e44f5cdeb24a59c9b0e8fa3f2150b9f598e5c#diff-a76cb79f5a11e0fb03c0ca465dbb588142615bdaee9f3e953403f8d9cecb1f38
 * @since Imported.
 */
class QueueHandler
{
    /**
     * What we handle the normal way.
     * @var WC_Queue_Interface
     * @since Imported.
     */
    private $queue;

    /**
     * Initialize WC()->queue.
     * @since Imported.
     */
    public function __construct()
    {
        $this->queue = WC()->queue();
    }

    /**
     * Order status helper.
     *
     * @param mixed $orderId
     * @param string $resursId
     * @since Imported.
     */
    public static function setOrderStatusWithNotice(int $orderId, string $resursId)
    {
        if ($orderId instanceof WC_Order) {
            $orderId = $orderId->get_id();
        }

        rbSimpleLogging(
            sprintf(
                'Order status update for id %d (Resurs ID %s) queued.',
                $orderId,
                $resursId
            )
        );
        self::applyQueue(
            'resursbank_update_queued_status',
            [
                $orderId,
                $resursId
            ]
        );
    }

    /**
     * Apply actions to WooCommerce Action Queue.
     *
     * @param $queueName
     * @param $value
     * @since Imported
     * @see https://github.com/Tornevall/wpwc-resurs/commit/6a7e44f5cdeb24a59c9b0e8fa3f2150b9f598e5c
     */
    public static function applyQueue($queueName, $value): string
    {
        $applyArray = [
            sprintf(
                '%s',
                self::getFilterName($queueName)
            ),
            $value,
        ];

        return WC()->queue()->add(
            ...array_merge($applyArray, self::getFilterArgs(func_get_args()))
        );
    }

    /**
     * @param $filterName
     * @return string
     * @since Imported
     */
    public static function getFilterName($filterName): string
    {
        // Simplified port of snake_casing.
        return (new Strings())->getSnakeCase($filterName);
    }

    /**
     * Clean up arguments and return the real ones.
     *
     * @param $args
     * @return array
     * @since Imported
     */
    public static function getFilterArgs($args): array
    {
        if (is_array($args) && count($args) > 2) {
            array_shift($args);
            array_shift($args);
        }

        return $args;
    }

    /**
     * Prepare for WC_Queue.
     * @return WC_Queue_Interface
     * @since 0.0.1.0
     */
    public function getQueue(): WC_Queue_Interface
    {
        return $this->queue;
    }
}
