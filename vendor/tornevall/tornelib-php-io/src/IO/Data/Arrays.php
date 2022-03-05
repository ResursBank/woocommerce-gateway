<?php

namespace TorneLIB\IO\Data;

use TorneLIB\Exception\ExceptionHandler;
use TorneLIB\Utils\Security;

/**
 * Class Arrays
 * @package TorneLIB\IO\Data
 * @version 6.1.7
 */
class Arrays
{
    private $duplicator = 0;

    /**
     * Convert object to a data object. Formerly known as a repair tool for __PHP_Incomplete_Class.
     *
     * @param array $objectArray
     * @return object|array|mixed
     * @throws ExceptionHandler
     * @since 6.0.0
     */
    public function arrayObjectToStdClass($objectArray = [])
    {
        /**
         * If json_decode and json_encode exists as function, do it the simple way.
         * http://php.net/manual/en/function.json-encode.php
         */
        if (Security::getCurrentFunctionState('json_decode', false) &&
            Security::getCurrentFunctionState('json_encode', false)) {
            return json_decode(json_encode($objectArray));
        }
        $newArray = null;
        if (is_array($objectArray) || is_object($objectArray)) {
            foreach ($objectArray as $itemKey => $itemValue) {
                if (is_array($itemValue)) {
                    $newArray[$itemKey] = (array)$this->arrayObjectToStdClass($itemValue);
                } elseif (is_object($itemValue)) {
                    $newArray[$itemKey] = (object)(array)$this->arrayObjectToStdClass($itemValue);
                } else {
                    $newArray[$itemKey] = $itemValue;
                }
            }
        }

        return $newArray;
    }

    /**
     * Convert objects to arrays
     *
     * @param $arrObjData
     * @param array $arrSkipIndices
     * @return array
     * @since 6.0.0
     */
    public function objectsIntoArray($arrObjData, $arrSkipIndices = [])
    {
        $arrData = [];
        // if input is object, convert into array
        if (is_object($arrObjData)) {
            $arrObjData = get_object_vars($arrObjData);
        }
        if (is_array($arrObjData)) {
            foreach ($arrObjData as $index => $value) {
                if (isset($value) && (is_object($value) || is_array($value))) {
                    $value = $this->objectsIntoArray($value, $arrSkipIndices); // recursive call
                }
                if (isset($arrSkipIndices) && in_array($index, $arrSkipIndices)) {
                    continue;
                }
                $arrData[$index] = $value;
            }
        }

        return $arrData;
    }

    /**
     * @param array $arrayData
     * @return bool
     * @since 6.0.2
     */
    public function isAssoc(array $arrayData)
    {
        if ([] === $arrayData) {
            return false;
        }

        return array_keys($arrayData) !== range(0, count($arrayData) - 1);
    }

    /**
     * @param $html
     * @param $options
     * @return false|string
     * @since 6.1.4
     */
    public function getHtmlAsArray($html, $options)
    {
        $options['asArray'] = true;
        return $this->getHtmlAsJson($html, $options);
    }

    /**
     * @since 6.1.3
     */
    public function getHtmlAsJson($html, $options = [])
    {
        $return = [];
        $document = $this->getPreparedDocument($html);

        if (is_object($document) && isset($document->childNodes)) {
            $return = $this->getDomChildren($document);
        }

        if (isset($options['assoc'])) {
            $return = $this->getAssocArrayFromDomTags([$return], $options);
        }

        if (isset($options['asArray'])) {
            return $return;
        }

        return json_encode($return);
    }

    /**
     * @param $html
     * @return \DOMElement
     * @since 6.1.2
     */
    private function getPreparedDocument($html)
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        return $dom->documentElement;
    }

    /**
     * @param $element
     * @return array
     * @since 6.1.2
     */
    private function getDomChildren($element)
    {
        $nameTypeTag = $this->getNameType($element);

        $domObject = ["tag" => $nameTypeTag];
        if (isset($element->attributes) && (is_array($element->attributes) || is_object($element->attributes))) {
            foreach ($element->attributes as $attribute) {
                $domObject[$attribute->name] = $attribute->value;
            }
        }
        if (isset($element->childNodes)) {
            foreach ($element->childNodes as $subElement) {
                if ($subElement->nodeType == XML_TEXT_NODE) {
                    $domObject["html"] = $subElement->wholeText;
                } else {
                    $domObject["children"][] = $this->getDomChildren($subElement, true);
                }
            }
        }

        return $domObject;
    }

    /**
     * @param $element
     * @return null
     * @since 6.1.2
     */
    private function getNameType($element)
    {
        $return = null;
        $names = ['tagName', 'nodeName'];

        foreach ($names as $name) {
            if (isset($element->{$name}) && !empty($element->{$name})) {
                $return = $element->{$name};
                break;
            }
        }

        $return = preg_replace('/^#/', '', $return);

        return $return;
    }

    /**
     * @param $array
     * @param array $options
     * @return array
     * @since 6.1.3
     */
    public function getAssocArrayFromDomTags($array, $options = [])
    {
        $return = [];
        foreach ($array as $item) {
            $tag = isset($item['tag']) ? $item['tag'] : 'noTag';
            $class = isset($item['class']) ? '[' . $item['class'] . ']' : '[noClass]';
            if (isset($item['children'])) {
                $item['children'] = $this->getAssocArrayFromDomTags($item['children'], $options);
            }
            foreach ($item as $key => $values) {
                if (is_string($values)) {
                    $item[$key] = trim($values);
                    if (empty($item[$key])) {
                        unset($item[$key]);
                    }
                }
            }
            $itemKeyName = sprintf('%s%s', $tag, $class);
            if (!isset($return[$itemKeyName])) {
                if (isset($options['indexing']) && $options['indexing'] !== true) {
                    $return[sprintf('%s%s', $tag, $class)] = $item;
                } else {
                    $return[sprintf('%s%s', $tag, $class)][] = $item;
                }
            } else {
                if (isset($options['duplicate'])) {
                    $this->duplicator++;
                    if ($options['duplicate'] !== 'index') {
                        $itemKeyName = sprintf('%s%s_dup_%d', $tag, $class, $this->duplicator);
                        if (!isset($return[$itemKeyName])) {
                            $return[$itemKeyName] = $item;
                        }
                    } else {
                        $itemKeyName = sprintf('%s%s', $tag, $class);
                        $return[$itemKeyName][] = $item;
                    }
                } else {
                    $return[sprintf('%s%s', $tag, $class)] = $item;
                }
            }
        }
        return $return;
    }

    /**
     * @param $html
     * @param array $tags
     * @param array $getOptions
     * @return array
     * @since 6.1.2
     */
    public function getHtmlElements($html, $tags = [], $getOptions = [])
    {
        $return = [];
        $document = $this->getPreparedDocument($html);

        if (is_string($tags) && (bool)preg_match('/,/', $tags)) {
            $tags = explode(',', $tags);
        }

        foreach ((array)$tags as $tag) {
            /** @var \DOMNodeList $elements */
            $elements = $document->getElementsByTagName($tag);
            foreach ($elements as $element) {
                $addElement = false;
                if (!count($getOptions)) {
                    $addElement = true;
                }
                $arrayElement = $this->getDomChildren($element);
                $walkedElement = null;
                if ($this->getDomWalker($arrayElement, $getOptions)) {
                    $walkedElement = $this->getElementFromWalk($arrayElement, $getOptions);
                    $addElement = true;
                }

                if ($addElement && $walkedElement) {
                    $return = array_merge($return, $walkedElement);
                }
            }
        }

        if (isset($getOptions['assoc'])) {
            $return = $this->getAssocArrayFromDomTags($return, $getOptions);
        }

        return $return;
    }

    /**
     * @param $arrayElement
     * @param $getOptions
     * @return bool
     * @since 6.1.3
     */
    private function getDomWalker($arrayElement, $getOptions)
    {
        $walkerResult = false;
        try {
            array_walk_recursive($arrayElement, function ($item, $key, $getOptions) {
                $this->getHasWalkedOption($item, $key, $getOptions);
            }, $getOptions);
        } catch (ExceptionHandler $e) {
            $walkerResult = true;
        }

        return $walkerResult;
    }

    /**
     * @param $item
     * @param $key
     * @param $getOptions
     * @throws ExceptionHandler
     * @since 6.1.3
     */
    private function getHasWalkedOption($item, $key, $getOptions)
    {
        foreach ($getOptions as $optionKey => $optionValue) {
            if (strtolower($key) === strtolower($optionKey)) {
                if (strtolower($item) === strtolower($optionValue)) {
                    throw new ExceptionHandler('Success.', 200);
                }
                if (isset($getOptions['regex'])) {
                    if ((bool)preg_match(sprintf('/%s/i', $optionValue), $item)) {
                        throw new ExceptionHandler('Success.', 200);
                    }
                }
            }
        }
    }

    /**
     * @param $arrayElement
     * @param $getOptions
     * @return mixed|null
     * @since 6.1.3
     */
    private function getElementFromWalk($arrayElement, $getOptions)
    {
        $return = [];
        foreach ($arrayElement as $key => $item) {
            try {
                $this->getHasWalkedOption($item, $key, $getOptions);
            } catch (ExceptionHandler $e) {
                $return[] = $arrayElement;
            }
        }
        return $return;
    }

    /**
     * Pair data into array.
     *
     * Example: http://api.tornevall.net/2.0/endpoints/getendpointmethods/endpoint/test/
     * As a pair, this URI looks like:
     * /endpoints/getendpointmethods/ - Endpoint+Verb (key=>value)
     * /endpoint/test/                - Key=>Value (array('endpoint'=>'test'))
     *
     * @param array $arrayArgs
     * @return array
     * @since 6.1.2
     */
    public function getArrayPair($arrayArgs = [])
    {
        $pairedArray = [];
        for ($keyCount = 0; $keyCount < count($arrayArgs); $keyCount = $keyCount + 2) {
            /**
             * Silently suppress things that does not exist
             */
            if (!isset($pairedArray[$arrayArgs[$keyCount]])) {
                // Repair possible dual slashes.
                if (empty($arrayArgs[$keyCount])) {
                    $keyCount--;
                    continue;
                }
                $pairedArray[$arrayArgs[$keyCount]] = null;
            }
            if (!isset($arrayArgs[$keyCount + 1])) {
                $arrayArgs[$keyCount + 1] = null;
            }

            /**
             * Start the pairing
             */
            $pairedArray[$arrayArgs[$keyCount]] = (!empty($arrayArgs[$keyCount + 1]) &&
            isset($arrayArgs[$keyCount + 1]) ?
                $arrayArgs[$keyCount + 1] :
                ""
            );
        }
        return $pairedArray;
    }

    /**
     * @param $array
     * @param $insertValue
     * @param $keyNameTo
     * @param $newName
     * @param $arrayLocation
     * @return array
     * @since 6.1.7
     */
    private function injectArray($array, $insertValue, $keyNameTo, $newName, $arrayLocation) {
        $newArray = [];

        foreach ($array as $key => $item) {
            if ($key === $keyNameTo && $arrayLocation === 'before') {
                $newArray[$newName] = $insertValue;
            }
            $newArray[$key] = $item;
            if ($key === $keyNameTo && $arrayLocation === 'after') {
                $newArray[$newName] = $insertValue;
            }
        }

        return $newArray;
    }

    /**
     * Inject new data before a spot in an array.
     *
     * @param $array
     * @param $insertValue
     * @param $keyNameTo
     * @param $newName
     * @return array
     * @since 6.1.7
     */
    public function moveArrayBefore($array, $insertValue, $keyNameTo, $newName)
    {
        return $this->injectArray($array, $insertValue, $keyNameTo, $newName, 'before');
    }

    /**
     * Inject new data before a spot in an array.
     *
     * @param $array
     * @param $insertValue
     * @param $keyNameTo
     * @param $newName
     * @return array
     * @since 6.1.7
     */
    public function moveArrayAfter($array, $insertValue, $keyNameTo, $newName)
    {
        return $this->injectArray($array, $insertValue, $keyNameTo, $newName, 'after');
    }
}
