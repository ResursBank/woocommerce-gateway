<?php

namespace TorneLIB\Helpers;

class SimpleDomParser
{
    /**
     * getContentFromXPath is an automated feature that collects the behaviour of a manual handling of xpath requests.
     *
     * @param $html
     * @param $xpath
     * @param $elements
     * @param $extractValueArray
     * @param $valueNodeContainer
     * @return array
     * @throws Exception
     * @since 6.1.5
     * @link https://docs.tornevall.net/display/TORNEVALL/Generating+content+from+DOMDocument
     */
    public static function getContentFromXPath($html, $xpath, $elements, $extractValueArray, $valueNodeContainer)
    {
        $nodeInfo = GenericParser::getElementsByXPath(
            self::getFromXPath(
                $html,
                $xpath
            ),
            $elements,
            $extractValueArray
        );

        $renderedArray = self::getRenderedArrayFromXPathNodes(
            $nodeInfo,
            $elements,
            $extractValueArray,
            $valueNodeContainer
        );

        return [
            'nodeInfo' => $nodeInfo,
            'rendered' => $renderedArray,
        ];
    }

    /**
     * Value extractor for elements in a xpath. Merges into a human readable array, depending on the requested keys.
     * @param array $domListData An array with elements.
     * @param array $elements Array of xpaths that defines where to look for content.
     * @param array $extractKeys Values to extract from each element.
     * @return array
     * @since 6.1.5
     */
    public static function getElementsByXPath($domListData, $elements, $extractKeys = [])
    {
        if (isset($domListData['domList'])) {
            $domList = $domListData['domList'];
        } else {
            $domList = $domListData;
        }
        $return = [];
        $error = false;
        if (isset($domList) && count($domList)) {
            /**
             * @var int $domItemIndex
             * @var array $domItem
             */
            foreach ($domList as $domItemIndex => $domItem) {
                /**
                 * @var string $elementName The name of the element that should be generated for humans.
                 * @var string $elementInformation xpath string.
                 */
                foreach ($elements as $elementName => $elementInformation) {
                    /** @var array $extractedSubPath */
                    if ($extractedSubPath = GenericParser::getBySubXPath($domItem, $elementInformation)) {
                        $mainNode = $extractedSubPath['mainNode'];
                        /** @var array $subNode */
                        $subNode = $extractedSubPath['subNode'];
                        if (is_array($extractKeys) && count($extractKeys)) {
                            $newExtraction = $extractedSubPath;
                            $newExtraction['mainNode'] = [];
                            $newExtraction['subNode'] = [];
                            foreach ($extractKeys as $extractKey) {
                                try {
                                    switch ($extractKey) {
                                        default:
                                            if (!empty($attribute = self::getAttributeFromDom($mainNode['node'], $extractKey))) {
                                                $newExtraction['mainNode'][$extractKey] = $attribute;
                                            } elseif (!empty($attribute = self::getAttributeFromDom($domItem['node'], $extractKey))) {
                                                $newExtraction['mainNode'][$extractKey] = $attribute;
                                            } else {
                                                $newExtraction['mainNode'][$extractKey] = null;
                                            }

                                            if (!empty($attribute = self::getAttributeFromDom($subNode['node'], $extractKey))) {
                                                $newExtraction['subNode'][$extractKey] = $attribute;
                                            } else {
                                                $newExtraction['subNode'][$extractKey] = null;
                                            }
                                    }
                                } catch (Exception $e) {
                                    $error = true;
                                }
                                $return[$domItemIndex][$elementName] = $newExtraction;
                            }
                        } else {
                            $return[$elementName][] = $extractedSubPath;
                        }
                    }
                }
            }
        }
        return $return;
    }

    /**
     * Get element by sub-xpath.
     *
     * @param array $domItem
     * @param string $subXpath
     * @since 6.1.5
     */
    public static function getBySubXPath($domItem, $subXpath)
    {
        /** @var \DOMElement $useNode */
        $useNode = $domItem['node'];

        if (method_exists($useNode, 'item')) {
            /** @var \DOMNodeList $mainNode */
            $mainNode = $useNode->item(0);
        } else {
            $mainNode = $useNode;
        }

        $mainNodeItem = self::getFromExtendedXpath(
            $subXpath,
            $domItem['path'],
            $domItem['domDocument']
        );
        $subNodeItem = self::getFromExtendedXpath(
            $subXpath,
            $domItem['path'],
            $domItem['domDocument']
        );

        $return = [
            'mainNode' => $mainNodeItem,
            'subNode' => $subNodeItem,
            'path' => method_exists($mainNode, 'getNodePath') ? $mainNode->getNodePath() : null,
        ];

        return $return;
    }

    /**
     * Continue merge the xpath with further xpath data.
     *
     * @param $xpath
     * @param $currentPath
     * @param \DOMDocument $domDoc
     * @return array
     * @since 6.1.5
     */
    private static function getFromExtendedXpath($xpath, $currentPath, $domDoc)
    {
        $queryResult = null;
        $finder = new \DOMXPath($domDoc);
        if (is_string($xpath)) {
            $currentPath .= $xpath;
        }
        // In some queries we have to dive deep into the finder and jump through more elements before we can properly use
        // the query. The below example is specific to this problem where we have a lot of child elements in the a-tag,
        // but where we - to find the time element which is the specific tag in this example - can't make a deep search
        // past the point where we have at least 3 hops down. The example needs to add /div/span/time to the search.
        //$qq = $finder->query('/html/body/div[11]/div/div/div[1]/div/div[1]/div[1]/div/a');
        //$i = $qq->item(0)
        //$i->childNodes->item(8)->childNodes->item(3)->childNodes->item(1)->getAttribute('datetime')

        //$currentPath .= implode('', $xpath);
        if (is_array($xpath)) {
            foreach ($xpath as $xPathItem) {
                $testPath = $currentPath . $xPathItem;
                $runQuery = $finder->query($testPath);
                if (self::getNodeListCount($runQuery)) {
                    $queryResult = $runQuery;
                    break;
                }
            }
        } else {
            $queryResult = $finder->query($currentPath);
        }

        /** @var \DOMNodeList $queryResult */
        return [
            'domDocument' => $domDoc,
            'path' => $currentPath,
            'node' => $queryResult,
        ];
    }

    /**
     * Count number of available nodes in a DOMNodeList.
     * count() works from PHP 7.2, but not for older releases.
     * This function also works as a validator to make sure the node is a proper node.
     *
     * @param \DOMNodeList $nodeList
     * @return int
     * @since 6.1.5
     */
    private static function getNodeListCount($nodeList)
    {
        $nodeListCount = 0;
        if (version_compare(PHP_VERSION, '7.2', '>=')) {
            if (method_exists($nodeList, 'item')) {
                $nodeListCount = $nodeList->count();
            }
        } elseif (isset($nodeList->length)) {
            $nodeListCount = $nodeList->length;
        }

        return (int)$nodeListCount;
    }

    /**
     * @param $domItem
     * @param $attributeKey
     * @since 6.1.5
     */
    private static function getAttributeFromDom($domItem, $attributeKey)
    {
        $return = null;
        if (method_exists($domItem, 'getAttribute') && $returnAttribute = $domItem->getAttribute($attributeKey)) {
            $return = $returnAttribute;
        } elseif (self::getNodeListCount($domItem)) {
            $testAttribute = self::getAttributeFromDom($domItem->item(0), $attributeKey);
            if (!empty($testAttribute)) {
                $return = $testAttribute;
            }
        } elseif ($attributeKey === 'value' && isset($domItem->nodeValue) && !empty($domItem->nodeValue)) {
            $return = $domItem->nodeValue;
        }

        return trim($return);
    }

    /**
     * Initial method to extract expath data from html content.
     *
     * @param $htmlString
     * @param $xpath
     * @return array
     * @since 6.1.5
     */
    public static function getFromXPath($htmlString, $xpath)
    {
        libxml_use_internal_errors(true);
        return self::getDataFromXPath($htmlString, $xpath);
    }

    /**
     * Extract DOMDocument data by xpath.
     *
     * @param $html
     * @param $xpath
     * @return array
     * @since 6.1.5
     */
    private static function getDataFromXPath($html, $xpath)
    {
        $domDoc = new \DOMDocument();
        $domDoc->loadHTML($html);
        $return = [
            'domDocument' => $domDoc,
            'domList' => [],
        ];

        // If request is based on an array, this request will be transformed into recursive scanning.
        //$useXpath = is_array($xpath) ? array_shift($xpath) : $xpath;
        if (is_array($xpath)) {
            foreach ($xpath as $xPathItem) {
                $return = self::getXPathDataExtracted($domDoc, $xPathItem, $return);
            }
        } else {
            $return = self::getXPathDataExtracted($domDoc, $xpath, $return);
        }

        return $return;
    }

    /**
     * Get content from xpath elements, with XPathfinder..
     *
     * @param $domDoc
     * @param $xpath
     * @param $return
     * @return array
     * @since 6.1.5
     */
    private static function getXPathDataExtracted($domDoc, $xpath, $return)
    {
        try {
            $finder = new \DOMXPath($domDoc);
            $nodeList = $finder->query($xpath);
            $return['domDocument'] = $domDoc;

            if (!empty($nodeList) || self::getNodeListCount($nodeList)) {
                /** @var \DOMNodeList $nodeList */
                for ($nodeIndex = 0; $nodeIndex < self::getNodeListCount($nodeList); $nodeIndex++) {
                    try {
                        /** @var \DOMElement $nodeItem */
                        $nodeItem = $nodeList->item($nodeIndex);
                        if (is_array($xpath)) {
                            $return['domList'][] = self::getFromExtendedXpath(
                                $xpath,
                                $nodeItem->getNodePath(),
                                $domDoc
                            );
                        } else {
                            $return['domList'][] = [
                                'domDocument' => $domDoc,
                                'path' => $nodeItem->getNodePath(),
                                'node' => $nodeItem,
                            ];
                        }
                    } catch (Exception $e) {
                    }
                }
            }
        } catch (Exception $e) {
        }
        return (array)$return;
    }

    /**
     * Dynamically render an array with xpath requested elements and values.
     *
     * @param $nodeList
     * @param $elements
     * @param $extractValueArray
     * @param $valueNodeContainer
     * @return array
     * @throws Exception
     * @since 6.1.5
     */
    public static function getRenderedArrayFromXPathNodes($nodeList, $elements, $extractValueArray, $valueNodeContainer)
    {
        $return = [];
        foreach ($nodeList as $nodeIndex => $node) {
            $return[$nodeIndex] = [];
            foreach ($elements as $elementName => $elementItem) {
                $return[$nodeIndex][$elementName] = [];
                foreach ($extractValueArray as $extractValueKey) {
                    if ($extractValueKey !== 'value' && !isset($valueNodeContainer[$extractValueKey])) {
                        continue;
                    }

                    // Nulling valuekey here means that we want to fill all elements found.
                    $return[$nodeIndex][$elementName][$extractValueKey] = self::getValuesFromXPath(
                        $node,
                        [
                            $elementName,
                            isset($valueNodeContainer[$extractValueKey]) ? $valueNodeContainer[$extractValueKey] : $extractValueKey,
                            $extractValueKey,
                        ],
                        $valueNodeContainer
                    );
                }
            }
        }

        return $return;
    }

    /**
     * Follow the requested array and extract a proper value from each element, if exists. If not, null is returned
     * to mark the missing data.
     *
     * @param $xPath
     * @param $fromElementRequestArray
     * @return mixed
     * @throws Exception
     * @since 6.1.5
     */
    public static function getValuesFromXPath($xPath, $fromElementRequestArray, $valueNodeContainer)
    {
        if (!is_array($fromElementRequestArray) || !count($fromElementRequestArray)) {
            throw new Exception(sprintf('%s Exception: Not a valid array path', __FUNCTION__), 404);
        }
        $inElement = $fromElementRequestArray[0];
        $inNode = $fromElementRequestArray[1];
        if ($inNode === 'value') {
            $inNode = $valueNodeContainer[$inElement];
        }
        $thisKey = $fromElementRequestArray[2];

        if (isset($xPath[$inElement][$inNode][$thisKey])) {
            $return = $xPath[$inElement][$inNode][$thisKey];
        } else {
            $return = null;
        }

        return $return;
    }
}
