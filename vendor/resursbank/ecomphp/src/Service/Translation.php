<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */
namespace Resursbank\Ecommerce\Service;

use Exception;
use TorneLIB\IO\Data\Content;
use TorneLIB\IO\Data\Strings;

/**
 * Class Helper Helper functions in ecom, currently for translations.
 */
class Translation
{
    const TRANSLATION_SWEDISH = 'sv';
    const TRANSLATION_NORWEGIAN = 'no';
    const TRANSLATION_DANISH = 'da';
    const TRANSLATION_FINNISH = 'fi';

    private $languageContainer;
    private $languageSet = 'sv';
    private $preloadedLanguage;
    private $languageExtensions = ['json', 'xml'];
    private $allowedLanguages = ['sv', 'no', 'da', 'fi'];
    private $isXml = false;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->languageContainer = __DIR__ . '/Container';
    }

    /**
     * @param string $methodItem
     * @param int $infoText
     * @return array|mixed|string
     */
    public function getMethodInfo($methodItem, $infoText = 1)
    {
        return $this->getPhraseByMethod($methodItem, sprintf('infoText%d', (int)$infoText));
    }

    /**
     * Quick fetch a translated phrase from a predefined payment method.
     *
     * @param $methodItem
     * @param string $keyString
     * @return array|mixed|string
     */
    public function getPhraseByMethod($methodItem, $keyString = '')
    {
        $return = '';
        $getMethodInfo = $this->getPhrase($methodItem, ['paymentMethods']);

        if (!empty($keyString) && isset($getMethodInfo[$keyString]) && !empty($getMethodInfo[$keyString])) {
            $return = $getMethodInfo[$keyString];
        } elseif (!empty($getMethodInfo)) {
            $return = $getMethodInfo;
        }

        return $return;
    }

    /**
     * Get an translated phrase by its key name.
     *
     * @param $keyString
     * @return mixed|string
     */
    public function getPhrase($keyString, $subKey = [])
    {
        if ($this->isXml) {
            $return = $this->getPhraseByXml($keyString);
        } else {
            $return = $this->getPhraseByJson($keyString, $subKey);
        }

        return $return;
    }

    /**
     * Fetch a phrase recursively from a XML language list (Probably danish).
     * @param $keyString
     * @return mixed|string|null
     */
    private function getPhraseByXml($keyString)
    {
        $return = null;

        foreach ($this->preloadedLanguage as $translationType => $translationItems) {
            if (isset($translationItems->translation) &&
                is_array($translationItems->translation) &&
                count($translationItems->translation)
            ) {
                foreach ($translationItems->translation as $translationObject) {
                    if (isset($translationObject->{'@attributes'}) &&
                        $translationObject->{'@attributes'}->class &&
                        $translationObject->{'@attributes'}->class === $keyString
                    ) {
                        $return = $this->getValue($translationObject);
                        break;
                    }
                }
            }
        }

        return $return;
    }

    /**
     * Get Value from what is preferrably XML data.
     *
     * @param $translationObject
     * @return mixed|string
     */
    private function getValue($translationObject)
    {
        $return = '';
        switch ($this->languageSet) {
            case 'sv':
                $return = isset($translationObject->swedish) ? $translationObject->swedish : '';
                break;
            case 'no':
                $return = isset($translationObject->norwegian) ? $translationObject->norwegian : '';
                break;
            case 'fi':
                $return = isset($translationObject->finnish) ? $translationObject->finnish : '';
                break;
            case 'da':
                $return = isset($translationObject->danish) ? $translationObject->danish : '';
                break;
            default:
        }

        return $return;
    }

    /**
     * Fetch phrase from the json arrays.
     * @param $keyString
     * @param array $subKey On recursive searches, add the path to follow.
     * @return null
     */
    private function getPhraseByJson($keyString, $subKey = [])
    {
        $return = null;

        $useLanguageArray = $this->preloadedLanguage;

        if (is_array($subKey)) {
            foreach (array_change_key_case($subKey, CASE_LOWER) as $keyName) {
                if (isset($useLanguageArray[$keyName])) {
                    $useLanguageArray = array_change_key_case($useLanguageArray[$keyName], CASE_LOWER);
                }
            }
        }

        foreach ($useLanguageArray as $translationType => $translationItem) {
            if (is_array($translationItem)) {
                if (is_array($useLanguageArray) && isset($useLanguageArray[$keyString])) {
                    $return = $useLanguageArray[$keyString];
                    break;
                }
                foreach ($translationItem as $translationCategory => $translationCategoryItem) {
                    if ((is_string($translationCategoryItem) && strtolower($translationCategoryItem) === strtolower($keyString)) ||
                        $translationCategory === $keyString
                    ) {
                        $return = $translationCategoryItem;
                        break;
                    }

                    if (is_string($translationType) && strtolower($translationType) === strtolower($keyString)) {
                        $return = $translationItem;
                        break;
                    }
                }
                if (!empty($return)) {
                    break;
                }
            } elseif ($translationType === $keyString) {
                $return = $translationItem;
                break;
            }
        }

        return $return;
    }

    /**
     * Get payment method list with supported translations.
     *
     * @param bool $snakeCase By using snake_case transformations we may get better control over stored translations.
     * @return array
     * @throws Exception
     * @todo Investigate snake_case types instead of in_array-exact-matches in the primary ecom fetcher.
     */
    public function getMethodsByPhrases($snakeCase = false)
    {
        $return = [];

        if (empty($this->getLanguage())) {
            throw new Exception('You need to use setLanguage first.');
        }

        $methodList = $this->getPhrase('paymentMethods');
        if (count($methodList)) {
            foreach ($methodList as $keyName => $keyArray) {
                if ($snakeCase) {
                    $return[] = Strings::returnSnakeCase($keyName);
                } else {
                    $return[] = strtolower($keyName);
                }
            }
        }

        return $return;
    }

    /**
     * Get the phrases, raw.
     * @return mixed
     */
    public function getLanguage()
    {
        return $this->preloadedLanguage;
    }

    /**
     * Set language country code. Resurs Bank usually partially uses country codes based on ISO 639-1 here.
     * (Partially = Finland is considered 'fin' according to ISO 639-1)
     *
     * @param string $useLanguage
     * @return Translation
     * @throws Exception
     * @link https://en.wikipedia.org/wiki/List_of_ISO_639-1_codes
     */
    public function setLanguage($useLanguage = self::TRANSLATION_SWEDISH)
    {
        switch (strtolower($useLanguage)) {
            case 'se':
                $configureLanguage = 'sv';
                break;
            case 'fin':
                $configureLanguage = 'fi';
                break;
            case 'da':
            case 'dk':
                $this->isXml = true;
                $configureLanguage = 'da';
                break;
            default:
                $configureLanguage = $useLanguage;
        }

        if (!in_array($configureLanguage, $this->allowedLanguages, true)) {
            throw new Exception(sprintf('%s is not an allowed country code.', $configureLanguage), 403);
        }

        $this->languageSet = $configureLanguage;
        $this->getPreloadedLanguage();
        return $this;
    }

    /**
     * Prefetch language.
     * @return $this
     */
    private function getPreloadedLanguage()
    {
        foreach ($this->languageExtensions as $extension) {
            $languageFile = sprintf('%s/lang.common.%s.%s', $this->languageContainer, $this->languageSet, $extension);
            if (file_exists($languageFile)) {
                $content = file_get_contents($languageFile);
                switch ($extension) {
                    case 'json':
                        $this->preloadedLanguage = json_decode($content, true, 512);
                        break;
                    case 'xml':
                        $this->preloadedLanguage = (new Content())->getFromXml(
                            $content,
                            Content::XML_NORMALIZE + Content::XML_NO_PATH
                        );
                        break;
                    default:
                }
                break;
            }
        }

        return $this;
    }
}
