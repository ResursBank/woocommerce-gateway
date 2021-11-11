<?php

namespace Resursbank\Ecommerce\Service\Merchant;

use Exception;
use TorneLIB\Exception\ExceptionHandler;

class MerchantApi extends MerchantApiConnector
{
    /**
     * @return array
     * @throws ExceptionHandler
     */
    public function getStores()
    {
        return $this->getMerchantRequest('stores')->content;
    }

    /**
     * @param $idNum
     * @throws ExceptionHandler
     */
    public function getStoreByIdNum($idNum)
    {
        $return = '';
        $storeList = $this->getStores();

        foreach ($storeList as $store) {
            if ((int)$store->nationalStoreId === (int)$idNum) {
                $return = $store->id;
                break;
            }
        }

        if (empty($return)) {
            throw new Exception(sprintf('There is no store with id %s.', $idNum), 1900);
        }

        return $return;
    }

    /**
     * @param $storeId
     * @return mixed|string
     * @throws ExceptionHandler
     */
    public function getPaymentMethods($storeId)
    {
        return $this->getMerchantRequest(
            sprintf(
                'stores/%s/payment_methods',
                is_numeric($storeId) ? $this->getStoreByIdNum($storeId) : $storeId
            )
        )->paymentMethods;
    }
}
