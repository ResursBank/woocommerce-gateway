<?php

namespace Resursbank\Ecommerce\Service\Merchant\Model;

use stdClass;
use function is_array;

class PaymentMethods
{
    private $fullResponsePayload;
    private $paymentMethodList;

    /**
     * This constructor needs to have the original stdClass included, as returned from the API.
     *
     * @param stdClass $apiResponse
     */
    public function __construct($apiResponse)
    {
        $this->fullResponsePayload = $apiResponse;
        $this->getPreparedPaymentMethods();
    }

    /**
     * @return $this
     */
    private function getPreparedPaymentMethods(): self
    {
        if (is_array($this->fullResponsePayload)) {
            foreach ($this->fullResponsePayload as $methodObject) {
                $this->paymentMethodList[] = new Method($methodObject);
            }
        }

        return $this;
    }

    /**
     * @return mixed
     */
    public function getList()
    {
        return $this->paymentMethodList;
    }
}
