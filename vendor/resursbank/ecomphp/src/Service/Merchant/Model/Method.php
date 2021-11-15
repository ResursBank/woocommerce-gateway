<?php

namespace Resursbank\Ecommerce\Service\Merchant\Model;

use stdClass;

class Method
{
    private $fullMethodClass;

    /**
     * @var string[]
     */
    private $properties = [
        'id',
        'customerType',
        'displayOrder',
        'description',
        'validFrom',
        'validTo',
        'supportedActions',
        'minPurchaseLimit',
        'maxPurchaseLimit',
        'minApplicationLimit',
        'maxApplicationLimit',
        'type',
        'status',
    ];

    /**
     * @var string
     */
    private $id;
    /**
     * @var array
     */
    private $customerType;
    /**
     * @var int
     */
    private $displayOrder;
    /**
     * @var string
     */
    private $description;
    /**
     * @var string
     */
    private $validFrom;
    /**
     * @var string
     */
    private $validTo;
    /**
     * @var array
     */
    private $supportedActions;
    /**
     * @var int
     */
    private $minPurchaseLimit;
    /**
     * @var int
     */
    private $maxPurchaseLimit;
    /**
     * @var int
     */
    private $minApplicationLimit;
    /**
     * @var int
     */
    private $maxApplicationLimit;

    /**
     * @var string
     */
    private $type;

    /**
     * Payment Method Status.
     * @var stdClass
     */
    private $status;

    /**
     * @param stdClass $methodObject
     */
    public function __construct(stdClass $methodObject)
    {
        $this->fullMethodClass = $methodObject;
        $this->setUpProperties();
    }

    /**
     * @return $this
     */
    private function setUpProperties(): self
    {
        foreach ($this->properties as $property) {
            $this->{$property} = $this->get($property);
        }

        return $this;
    }

    /**
     * Return property.
     * @param $key
     * @return mixed
     */
    private function get($key)
    {
        return property_exists($this->fullMethodClass, $key) ? $this->fullMethodClass->{$key} : '';
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return array
     */
    public function getCustomerType(): array
    {
        return empty($this->customerType) ? ['NATURAL', 'LEGAL'] : [$this->customerType];
    }

    /**
     * @return mixed
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return int
     */
    public function getDisplayOrder(): int
    {
        return $this->displayOrder;
    }

    /**
     * @return stdClass
     */
    public function getFullMethodClass(): stdClass
    {
        return $this->fullMethodClass;
    }

    /**
     * Max application limit, has variations of both float and int on return, but we are explicitly returning int.
     * @return int
     */
    public function getMaxApplicationLimit(): int
    {
        return $this->maxApplicationLimit;
    }

    /**
     * Max purchase limit, has variations of both float and int on return, but we are explicitly returning int.
     * @return int
     */
    public function getMaxPurchaseLimit(): int
    {
        return $this->maxPurchaseLimit;
    }

    /**
     * Minimum application limit, has variations of both float and int on return, but we are explicitly returning int.
     * @return int
     */
    public function getMinApplicationLimit(): int
    {
        return $this->minApplicationLimit;
    }

    /**
     * Minimum purchase limit, has variations of both float and int on return, but we are explicitly returning int.
     * @return int
     */
    public function getMinPurchaseLimit(): int
    {
        return $this->minPurchaseLimit;
    }

    /**
     * @return stdClass
     */
    public function getStatus(): stdClass
    {
        return $this->status;
    }

    /**
     * @return array
     */
    public function getSupportedActions()
    {
        return $this->supportedActions;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return mixed
     */
    public function getValidFrom()
    {
        return $this->validFrom;
    }

    /**
     * @return string
     */
    public function getValidTo(): string
    {
        return $this->validTo;
    }
}
