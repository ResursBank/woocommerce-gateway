<?php

namespace Resursbank\Ecommerce\Service\Merchant;

use Exception;
use Resursbank\Ecommerce\Service\Merchant\Api\getPaymentMethodsResponse;
use Resursbank\Ecommerce\Service\Merchant\Model\PaymentMethods;
use TorneLIB\Exception\ExceptionHandler;

class MerchantApi extends MerchantApiConnector
{
    /**
     * Store id to use during full session.
     *
     * @var string
     */
    private $storeId = '';

    /**
     * @return array
     * @throws ExceptionHandler
     */
    public function getStores()
    {
        return $this->getMerchantRequest('stores')->content;
    }

    /**
     * @param $storeId
     * @throws ExceptionHandler
     */
    public function setStoreId($storeId)
    {
        if (!empty($storeId)) {
            $this->storeId = $this->getStoreByIdNum($storeId);
        }

        return $this;
    }

    /**
     * StoreID Automation. Making sure that we are always using a proper store id, regardless of how it is pushed
     * into the API.
     *
     * @param string $storeId
     * @return string
     * @throws ExceptionHandler
     */
    public function getStoreId($storeId = null)
    {
        if (!empty($storeId)) {
            $return = is_numeric($storeId) ? $this->getStoreByIdNum($storeId) : $storeId;
        } elseif (!empty($this->storeId)) {
            $return = is_numeric($this->storeId) ? $this->getStoreByIdNum($this->storeId) : $this->storeId;
        } else {
            $return = '';
        }

        return $return;
    }

    /**
     * Transform a numeric store id to Resurs internal.
     *
     * @param $idNum
     * @throws ExceptionHandler
     */
    public function getStoreByIdNum($idNum)
    {
        $return = '';
        $storeList = $this->getStores();

        foreach ($storeList as $store) {
            if ((int)$store->nationalStoreId === (int)$idNum || $store->id === $idNum) {
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
    public function getPaymentMethods($storeId = null)
    {
        return new PaymentMethods(
            $this->getMerchantRequest(
                sprintf(
                    'stores/%s/payment_methods',
                    $this->getStoreId($storeId)
                )
            )->paymentMethods
        );
    }
}
