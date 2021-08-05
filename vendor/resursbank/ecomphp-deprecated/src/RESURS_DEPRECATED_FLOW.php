<?php

namespace Resursbank\RBEcomPHP;

use RESURS_EXCEPTIONS;

/**
 * Class RESURS_DEPRECATED_FLOW Thing with relation to Resurs Bank deprecated flow
 * WARNING: Use this class at your own risk as it may contain glitches. Maintenance is only done
 * when really necessary.
 *
 * @package Resursbank\RBEcomPHP
 * @deprecated Avoid using this template holder as data tend to get outdated.
 */
class RESURS_DEPRECATED_FLOW
{
    private $formTemplateRuleArray;
    private $templateFieldsByMethodResponse;

    /**
     * Defines if we are allowed to skip government id validation. Payment provider methods
     * normally does this when running in simplified mode. In other cases, validation will be
     * handled by Resurs Bank and this setting shoudl not be affected by this
     *
     * @var bool $canSkipGovernmentIdValidation
     */
    private $canSkipGovernmentIdValidation = false;

    /**
     * Override formTemplateFieldsetRules in case of important needs or unexpected changes
     *
     * @param $customerType
     * @param $methodType
     * @param $fieldArray
     *
     * @return array
     * @deprecated Build your own integration
     */
    public function setFormTemplateRules($customerType, $methodType, $fieldArray)
    {
        $this->formTemplateRuleArray = [
            $customerType => [
                'fields' => [
                    $methodType => $fieldArray,
                ],
            ],
        ];

        return $this->formTemplateRuleArray;
    }

    /**
     * Get regular expression ruleset for a specific payment formfield
     *
     * If no form field name are given, all the fields are returned for a specific payment method.
     * Parameters are case insensitive.
     *
     * @param string $formFieldName
     * @param $countryCode
     * @param $customerType
     * @return array
     * @throws \Exception
     * @deprecated Build your own integration
     */
    public function getRegEx($formFieldName = '', $countryCode = '', $customerType = '')
    {
        //$returnRegEx = array();

        /** @noinspection PhpDeprecationInspection */
        $templateRule = $this->getFormTemplateRules();
        $returnRegEx = $templateRule['regexp'];

        if (empty($countryCode)) {
            throw new \Exception(
                __FUNCTION__ . ": Country code is missing in getRegEx-request for form fields",
                RESURS_EXCEPTIONS::REGEX_COUNTRYCODE_MISSING
            );
        }
        if (empty($customerType)) {
            throw new \Exception(
                __FUNCTION__ . ": Customer type is missing in getRegEx-request for form fields",
                RESURS_EXCEPTIONS::REGEX_CUSTOMERTYPE_MISSING
            );
        }

        if (!empty($countryCode) && isset($returnRegEx[strtoupper($countryCode)])) {
            $returnRegEx = $returnRegEx[strtoupper($countryCode)];
            if (!empty($customerType)) {
                if (!is_array($customerType)) {
                    if (isset($returnRegEx[strtoupper($customerType)])) {
                        $returnRegEx = $returnRegEx[strtoupper($customerType)];
                        if (isset($returnRegEx[strtolower($formFieldName)])) {
                            $returnRegEx = $returnRegEx[strtolower($formFieldName)];
                        }
                    }
                } else {
                    foreach ($customerType as $cType) {
                        if (isset($returnRegEx[strtoupper($cType)])) {
                            $returnRegEx = $returnRegEx[strtoupper($cType)];
                            if (isset($returnRegEx[strtolower($formFieldName)])) {
                                $returnRegEx = $returnRegEx[strtolower($formFieldName)];
                            }
                        }
                    }
                }
            }
        }

        return $returnRegEx;
    }

    /**
     * Retrieve html-form rules for each payment method type, including regular expressions for the form fields, to validate against.
     *
     * @return array
     * @deprecated Build your own integration
     */
    public function getFormTemplateRules()
    {
        $formTemplateRules = [
            'NATURAL' => [
                'fields' => [
                    'INVOICE' => [
                        'applicant-government-id',
                        'applicant-telephone-number',
                        'applicant-mobile-number',
                        'applicant-email-address',
                    ],
                    'CARD' => [
                        'applicant-government-id',
                    ],
                    'PAYMENT_PROVIDER' => [
                        'applicant-government-id',
                        'applicant-telephone-number',
                        'applicant-mobile-number',
                        'applicant-email-address',
                    ],
                    'REVOLVING_CREDIT' => [
                        'applicant-government-id',
                        'applicant-telephone-number',
                        'applicant-mobile-number',
                        'applicant-email-address',
                    ],
                    'PART_PAYMENT' => [
                        'applicant-government-id',
                        'applicant-telephone-number',
                        'applicant-mobile-number',
                        'applicant-email-address',
                    ],
                ],
            ],
            'LEGAL' => [
                'fields' => [
                    'INVOICE' => [
                        'applicant-government-id',
                        'applicant-telephone-number',
                        'applicant-mobile-number',
                        'applicant-email-address',
                        'applicant-full-name',
                        'contact-government-id',
                    ],
                    'PAYMENT_PROVIDER' => [
                        'applicant-government-id',
                        'applicant-telephone-number',
                        'applicant-mobile-number',
                        'applicant-email-address',
                        'applicant-full-name',
                        'contact-government-id',
                    ],
                ],
            ],
            'display' => [
                'applicant-government-id',
                'card-number',
                'applicant-full-name',
                'contact-government-id',
            ],
            'regexp' => [
                'SE' => [
                    'NATURAL' => [
                        'applicant-government-id' => '^(18\d{2}|19\d{2}|20\d{2}|\d{2})(0[1-9]|1[0-2])([0][1-9]|[1-2][0-9]|3[0-1])(\-|\+)?([\d]{4})$',
                        'applicant-telephone-number' => '^(0|\+46|0046)[ |-]?(200|20|70|73|76|74|[1-9][0-9]{0,2})([ |-]?[0-9]){5,8}$',
                        'applicant-mobile-number' => '^(0|\+46|0046)[ |-]?(200|20|70|73|76|74|[1-9][0-9]{0,2})([ |-]?[0-9]){5,8}$',
                        'applicant-email-address' => '^[A-Za-z0-9!#%&\'*+/=?^_`~-]+(\.[A-Za-z0-9!#%&\'*+/=?^_`~-]+)*@([A-Za-z0-9]+)(([\.\-]?[a-zA-Z0-9]+)*)\.([A-Za-z]{2,})$',
                        'card-number' => '^([1-9][0-9]{3}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4})$',
                    ],
                    'LEGAL' => [
                        'applicant-government-id' => '^(16\d{2}|18\d{2}|19\d{2}|20\d{2}|\d{2})(\d{2})(\d{2})(\-|\+)?([\d]{4})$',
                        'applicant-telephone-number' => '^(0|\+46|0046)[ |-]?(200|20|70|73|76|74|[1-9][0-9]{0,2})([ |-]?[0-9]){5,8}$',
                        'applicant-mobile-number' => '^(0|\+46|0046)[ |-]?(200|20|70|73|76|74|[1-9][0-9]{0,2})([ |-]?[0-9]){5,8}$',
                        'applicant-email-address' => '^[A-Za-z0-9!#%&\'*+/=?^_`~-]+(\.[A-Za-z0-9!#%&\'*+/=?^_`~-]+)*@([A-Za-z0-9]+)(([\.\-]?[a-zA-Z0-9]+)*)\.([A-Za-z]{2,})$',
                        'card-number' => '^([1-9][0-9]{3}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4})$',
                    ],
                ],
                'DK' => [
                    'NATURAL' => [
                        'applicant-government-id' => '^((3[0-1])|([1-2][0-9])|(0[1-9]))((1[0-2])|(0[1-9]))(\d{2})(\-)?([\d]{4})$',
                        'applicant-telephone-number' => '^(\+45|0045|)?[ |-]?[2-9]([ |-]?[0-9]){7,7}$',
                        'applicant-mobile-number' => '^(\+45|0045|)?[ |-]?[2-9]([ |-]?[0-9]){7,7}$',
                        'applicant-email-address' => '^[A-Za-z0-9!#%&\'*+/=?^_`~-]+(\.[A-Za-z0-9!#%&\'*+/=?^_`~-]+)*@([A-Za-z0-9]+)(([\.\-]?[a-zA-Z0-9]+)*)\.([A-Za-z]{2,})$',
                        'card-number' => '^([1-9][0-9]{3}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4})$',
                    ],
                    'LEGAL' => [
                        'applicant-government-id' => null,
                        'applicant-telephone-number' => '^(\+45|0045|)?[ |-]?[2-9]([ |-]?[0-9]){7,7}$',
                        'applicant-mobile-number' => '^(\+45|0045|)?[ |-]?[2-9]([ |-]?[0-9]){7,7}$',
                        'applicant-email-address' => '^[A-Za-z0-9!#%&\'*+/=?^_`~-]+(\.[A-Za-z0-9!#%&\'*+/=?^_`~-]+)*@([A-Za-z0-9]+)(([\.\-]?[a-zA-Z0-9]+)*)\.([A-Za-z]{2,})$',
                        'card-number' => '^([1-9][0-9]{3}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4})$',
                    ],
                ],
                'NO' => [
                    'NATURAL' => [
                        'applicant-government-id' => '^([0][1-9]|[1-2][0-9]|3[0-1])(0[1-9]|1[0-2])(\d{2})(\-)?([\d]{5})$',
                        'applicant-telephone-number' => '^(\+47|0047|)?[ |-]?[2-9]([ |-]?[0-9]){7,7}$',
                        'applicant-mobile-number' => '^(\+47|0047|)?[ |-]?[2-9]([ |-]?[0-9]){7,7}$',
                        'applicant-email-address' => '^[A-Za-z0-9!#%&\'*+/=?^_`~-]+(\.[A-Za-z0-9!#%&\'*+/=?^_`~-]+)*@([A-Za-z0-9]+)(([\.\-]?[a-zA-Z0-9]+)*)\.([A-Za-z]{2,})$',
                        'card-number' => '^([1-9][0-9]{3}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4})$',
                    ],
                    'LEGAL' => [
                        'applicant-government-id' => '^([89]([ |-]?[0-9]){8})$',
                        'applicant-telephone-number' => '^(\+47|0047|)?[ |-]?[2-9]([ |-]?[0-9]){7,7}$',
                        'applicant-mobile-number' => '^(\+47|0047|)?[ |-]?[2-9]([ |-]?[0-9]){7,7}$',
                        'applicant-email-address' => '^[A-Za-z0-9!#%&\'*+/=?^_`~-]+(\.[A-Za-z0-9!#%&\'*+/=?^_`~-]+)*@([A-Za-z0-9]+)(([\.\-]?[a-zA-Z0-9]+)*)\.([A-Za-z]{2,})$',
                        'card-number' => '^([1-9][0-9]{3}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4})$',
                    ],
                ],
                'FI' => [
                    'NATURAL' => [
                        'applicant-government-id' => '^([\d]{6})[\+\-A]([\d]{3})([0123456789ABCDEFHJKLMNPRSTUVWXY])$',
                        'applicant-telephone-number' => '^((\+358|00358|0)[-| ]?(1[1-9]|[2-9]|[1][0][1-9]|201|2021|[2][0][2][4-9]|[2][0][3-8]|29|[3][0][1-9]|71|73|[7][5][0][0][3-9]|[7][5][3][0][3-9]|[7][5][3][2][3-9]|[7][5][7][5][3-9]|[7][5][9][8][3-9]|[5][0][0-9]{0,2}|[4][0-9]{1,3})([-| ]?[0-9]){3,10})?$',
                        'applicant-mobile-number' => '^((\+358|00358|0)[-| ]?(1[1-9]|[2-9]|[1][0][1-9]|201|2021|[2][0][2][4-9]|[2][0][3-8]|29|[3][0][1-9]|71|73|[7][5][0][0][3-9]|[7][5][3][0][3-9]|[7][5][3][2][3-9]|[7][5][7][5][3-9]|[7][5][9][8][3-9]|[5][0][0-9]{0,2}|[4][0-9]{1,3})([-| ]?[0-9]){3,10})?$',
                        'applicant-email-address' => '^[A-Za-z0-9!#%&\'*+/=?^_`~-]+(\.[A-Za-z0-9!#%&\'*+/=?^_`~-]+)*@([A-Za-z0-9]+)(([\.\-]?[a-zA-Z0-9]+)*)\.([A-Za-z]{2,})$',
                        'card-number' => '^([1-9][0-9]{3}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4})$',
                    ],
                    'LEGAL' => [
                        'applicant-government-id' => '^((\d{7})(\-)?\d)$',
                        'applicant-telephone-number' => '^((\+358|00358|0)[-| ]?(1[1-9]|[2-9]|[1][0][1-9]|201|2021|[2][0][2][4-9]|[2][0][3-8]|29|[3][0][1-9]|71|73|[7][5][0][0][3-9]|[7][5][3][0][3-9]|[7][5][3][2][3-9]|[7][5][7][5][3-9]|[7][5][9][8][3-9]|[5][0][0-9]{0,2}|[4][0-9]{1,3})([-| ]?[0-9]){3,10})?$',
                        'applicant-mobile-number' => '^((\+358|00358|0)[-| ]?(1[1-9]|[2-9]|[1][0][1-9]|201|2021|[2][0][2][4-9]|[2][0][3-8]|29|[3][0][1-9]|71|73|[7][5][0][0][3-9]|[7][5][3][0][3-9]|[7][5][3][2][3-9]|[7][5][7][5][3-9]|[7][5][9][8][3-9]|[5][0][0-9]{0,2}|[4][0-9]{1,3})([-| ]?[0-9]){3,10})?$',
                        'applicant-email-address' => '^[A-Za-z0-9!#%&\'*+/=?^_`~-]+(\.[A-Za-z0-9!#%&\'*+/=?^_`~-]+)*@([A-Za-z0-9]+)(([\.\-]?[a-zA-Z0-9]+)*)\.([A-Za-z]{2,})$',
                        'card-number' => '^([1-9][0-9]{3}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4}[ ]{0,1}[0-9]{4})$',
                    ],
                ],
            ],
        ];
        if (isset($this->formTemplateRuleArray) && is_array($this->formTemplateRuleArray) && count($this->formTemplateRuleArray)) {
            foreach ($this->formTemplateRuleArray as $cType => $cArray) {
                $formTemplateRules[$cType] = $cArray;
            }
        }

        return $formTemplateRules;
    }

    /**
     * Returns a true/false for a specific form field value depending on the response created by getTemplateFieldsByMethodType.
     *
     * This function is a part of Resurs Bank streamline support and actually defines the recommended value whether the field should try propagate it's data from the current store values or not.
     * Doing this, you may be able to hide form fields that already exists in the store, so the customer does not need to enter the values twice.
     *
     * @param string $formField The field you want to test
     * @param bool $canThrow Make the function throw an exception instead of silently return false if getTemplateFieldsByMethodType has not been run yet
     *
     * @return bool Returns false if you should NOT hide the field
     * @throws \Exception
     * @deprecated Build your own integration
     */
    public function canHideFormField($formField = "", $canThrow = false)
    {
        if (is_array($this->templateFieldsByMethodResponse) && count($this->templateFieldsByMethodResponse) && isset($this->templateFieldsByMethodResponse['fields']) && isset($this->templateFieldsByMethodResponse['display'])) {
            $currentDisplay = $this->templateFieldsByMethodResponse['display'];
            if (in_array($formField, $currentDisplay)) {
                $canHideSet = false;
            } else {
                $canHideSet = true;
            }
        } else {
            /* Make sure that we don't hide things that does not exists in our configuration */
            $canHideSet = false;
        }

        if ($canThrow && !$canHideSet) {
            throw new \Exception(
                __FUNCTION__ . ": templateFieldsByMethodResponse is empty. You have to run getTemplateFieldsByMethodType first",
                RESURS_EXCEPTIONS::FORMFIELD_CANHIDE_EXCEPTION
            );
        }

        return $canHideSet;
    }

    /**
     * Defines if we are allowed to skip government id validation. Payment provider methods
     * normally does this when running in simplified mode. In other cases, validation will be
     * handled by Resurs Bank and this setting shoudl not be affected by this
     *
     * @return bool
     */
    public function getCanSkipGovernmentIdValidation()
    {
        return $this->canSkipGovernmentIdValidation;
    }

    /**
     * Get form fields by a specific payment method. This function retrieves the payment method in real time.
     *
     * @param string $paymentMethodName
     *
     * @return array
     * @throws \Exception
     * @deprecated Build your own integration
     */
    public function getFormFieldsByMethod(
        $paymentMethodName = ""
    ) {
        /** @noinspection PhpDeprecationInspection */
        return $this->getTemplateFieldsByMethod($paymentMethodName);
    }

    /**
     * Get template fields by a specific payment method. This function retrieves the payment method in real time.
     *
     * @param string $paymentMethodName
     *
     * @return array
     * @throws \Exception
     * @deprecated Build your own integration
     */
    public function getTemplateFieldsByMethod(
        $paymentMethodName = ""
    ) {
        /** @noinspection PhpDeprecationInspection */
        return $this->getTemplateFieldsByMethodType($this->getPaymentMethodSpecific($paymentMethodName));
    }

    /**
     * Get field set rules for web-forms
     *
     * $paymentMethodType can be both a string or a object. If it is a object, the function will handle the incoming data as it is the complete payment method
     * configuration (meaning, data may be cached). In this case, it will take care of the types in the method itself. If it is a string, it will handle the data
     * as the configuration has already been solved out.
     *
     * When building forms for a webshop, a specific number of fields are required to show on screen. This function brings the right fields automatically.
     * The deprecated flow generates form fields and returns them to the shop owner platform, with the form fields that is required for the placing an order.
     * It also returns a bunch of regular expressions that is used to validate that the fields is correctly filled in. This function partially emulates that flow,
     * so the only thing a integrating developer needs to take care of is the html code itself.
     * @link https://test.resurs.com/docs/x/s4A0 Regular expressions
     *
     * @param string|array $paymentMethodName
     * @param string $customerType
     * @param string $specificType
     *
     * @return array
     * @deprecated Use this if you don't want to think by yourself but on your own risk
     */
    public function getTemplateFieldsByMethodType(
        $paymentMethodName = "",
        $customerType = "",
        $specificType = ""
    ) {
        /** @noinspection PhpDeprecationInspection */
        $templateRules = $this->getFormTemplateRules();
        //$returnedRules     = array();
        $returnedRuleArray = [];
        /* If the client is requesting a getPaymentMethod-object we'll try to handle that information instead (but not if it is empty) */
        if (is_object($paymentMethodName) || is_array($paymentMethodName)) {
            if (is_object($paymentMethodName)) {
                // Prevent arrays to go through here and crash something
                if (!is_array($customerType)) {
                    /** @noinspection PhpUndefinedFieldInspection */
                    if (isset($templateRules[strtoupper($customerType)]) && (isset($templateRules[strtoupper($customerType)]['fields'][strtoupper($paymentMethodName->specificType)]) || isset($templateRules[strtoupper($customerType)]['fields'][strtoupper($paymentMethodName->type)]))) {
                        if (isset($templateRules[strtoupper($customerType)]['fields'][strtoupper($paymentMethodName->specificType)])) {
                            /** @noinspection PhpUndefinedFieldInspection */
                            $returnedRuleArray = $templateRules[strtoupper($customerType)]['fields'][strtoupper($paymentMethodName->specificType)];
                        } else {
                            $returnedRuleArray = $templateRules[strtoupper($customerType)]['fields'][strtoupper($paymentMethodName->type)];
                        }
                        if ($paymentMethodName->type === 'PAYMENT_PROVIDER') {
                            $this->canSkipGovernmentIdValidation = true;
                            $returnedRuleArray = $templateRules[strtoupper($customerType)]['fields'][strtoupper($paymentMethodName->type)];
                        }
                    }
                }
            } else {
                if (is_array($paymentMethodName)) {
                    /*
                     * This should probably not happen and the developers should probably also stick to objects as above.
                     */
                    if (is_array($paymentMethodName) && count($paymentMethodName)) {
                        if (isset($templateRules[strtoupper($customerType)]) && (isset($templateRules[strtoupper($customerType)]['fields'][strtoupper($paymentMethodName['specificType'])]) || isset($templateRules[strtoupper($customerType)]['fields'][strtoupper($paymentMethodName['type'])]))) {
                            if (isset($templateRules[strtoupper($customerType)]['fields'][strtoupper($paymentMethodName['specificType'])])) {
                                /** @noinspection PhpUndefinedFieldInspection */
                                $returnedRuleArray = $templateRules[strtoupper($customerType)]['fields'][strtoupper($paymentMethodName['specificType'])];
                            } else {
                                $returnedRuleArray = $templateRules[strtoupper($customerType)]['fields'][strtoupper($paymentMethodName['type'])];
                            }

                            if ($paymentMethodName['type'] === 'PAYMENT_PROVIDER') {
                                $this->canSkipGovernmentIdValidation = true;
                                $returnedRuleArray = $templateRules[strtoupper($customerType)]['fields'][strtoupper($paymentMethodName['type'])];
                            }
                        }
                    }
                }
            }
        } else {
            if (isset($templateRules[strtoupper($customerType)]) && isset($templateRules[strtoupper($customerType)]['fields'][strtoupper($paymentMethodName)])) {
                $returnedRuleArray = $templateRules[strtoupper($customerType)]['fields'][strtoupper($specificType)];
            }
        }
        $returnedRules = [
            'fields' => $returnedRuleArray,
            'display' => $templateRules['display'],
            'regexp' => $templateRules['regexp'],
        ];
        $this->templateFieldsByMethodResponse = $returnedRules;

        return $returnedRules;
    }
}
