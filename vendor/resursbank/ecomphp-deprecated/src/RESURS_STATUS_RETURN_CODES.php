<?php

namespace Resursbank\RBEcomPHP;

/**
 * Class RESURS_STATUS_RETURN_CODES
 * @package Resursbank\RBEcomPHP
 * @deprecated Do not query this. Use Resursbank\Ecommerce\Types\OrderStatus.
 */
class RESURS_STATUS_RETURN_CODES extends RESURS_PAYMENT_STATUS_RETURNCODES
{
    private static $returnStrings = [
        RESURS_PAYMENT_STATUS_RETURNCODES::PENDING => 'pending',
        RESURS_PAYMENT_STATUS_RETURNCODES::PROCESSING => 'processing',
        RESURS_PAYMENT_STATUS_RETURNCODES::COMPLETED => 'completed',
        RESURS_PAYMENT_STATUS_RETURNCODES::ANNULLED => 'annul',
        RESURS_PAYMENT_STATUS_RETURNCODES::CREDITED => 'credit',
    ];

    /**
     * Set up return strings for statuses.
     *
     * @param $codeOrArray mixed RESURS_PAYMENT_STATUS_RETURNCODES or array with the return codes.
     * @param $codeString string If $codeOrArray = RESURS_PAYMENT_STATUS_RETURNCODES, then set the return string here.
     * @throws Exception
     */
    public static function setReturnString($codeOrArray, $codeString = null)
    {
        if (is_numeric($codeOrArray) && isset(self::$returnStrings[$codeOrArray])) {
            if ($codeString !== null) {
                self::$returnStrings[$codeOrArray] = $codeString;
            } else {
                throw new Exception('Status string must not be empty.', 400);
            }
        } elseif (is_array($codeOrArray)) {
            // Do not replace the entire  array.
            foreach ($codeOrArray as $key => $value) {
                self::$returnStrings[$key] = $value;
            }
        } else {
            throw new Exception('Must set returncodes as array or RESURS_PAYMENT_STATUS_RETURNCODES.', 400);
        }
    }

    /**
     * @param int $returnCode
     * @return string|array
     */
    public static function getReturnString($returnCode = 0)
    {
        return isset(self::$returnStrings[$returnCode]) ? self::$returnStrings[$returnCode] : self::$returnStrings;
    }
}
