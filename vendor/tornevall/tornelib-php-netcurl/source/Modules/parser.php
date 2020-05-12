<?php

namespace TorneLIB;

use Exception;

if (!class_exists('NETCURL_PARSER', NETCURL_CLASS_EXISTS_AUTOLOAD) &&
    !class_exists('TorneLIB\NETCURL_PARSER', NETCURL_CLASS_EXISTS_AUTOLOAD)
) {
    /**
     * Class NETCURL_PARSER Network communications driver detection
     *
     * @package TorneLIB
     * @version 6.0.5
     * @deprecated netcurl 6.1 is rewritten without the guessing games.
     */
    class NETCURL_PARSER
    {
        private $PARSE_CONTAINER = '';
        private $PARSE_CONTENT_TYPE = '';
        private $PARSE_CONTENT_OUTPUT = '';

        /**
         * @var bool
         */
        private $NETCURL_CONTENT_IS_DOMCONTENT = false;

        /**
         * Do not include Dom content in the basic parser (default = true, as it might destroy output data in legacy products)
         *
         * @var bool $NETCURL_PROHIBIT_DOMCONTENT_PARSE
         * @since 6.0.3
         */
        private $NETCURL_PROHIBIT_DOMCONTENT_PARSE = true;


        /** @var MODULE_IO $IO */
        private $IO;

        /** @var MODULE_NETWORK */
        private $NETWORK;

        /**
         * NETCURL_PARSER constructor.
         *
         * @param string $htmlContent
         * @param string $contentType
         * @param array $flags
         * @throws Exception
         * @since 6.0.0
         */
        public function __construct($htmlContent = '', $contentType = '', $flags = [])
        {
            $this->NETWORK = new MODULE_NETWORK();
            $this->IO = new MODULE_IO();

            if (isset($flags['NETCURL_PROHIBIT_DOMCONTENT_PARSE'])) {
                $this->NETCURL_PROHIBIT_DOMCONTENT_PARSE = $flags['NETCURL_PROHIBIT_DOMCONTENT_PARSE'];
            }

            $this->PARSE_CONTAINER = $htmlContent;
            $this->PARSE_CONTENT_TYPE = $contentType;
            $this->PARSE_CONTENT_OUTPUT = $this->getContentByTest();

            // Consider the solution below.
            /*try {
                $this->PARSE_CONTENT_OUTPUT = $this->getContentByTest();
            } catch (Exception $e) {
                $this->PARSE_CONTENT_OUTPUT = $this->getContentByHeaderType(
                    $this->PARSE_CONTAINER,
                    $this->PARSE_CONTENT_TYPE
                );
            }*/
        }

        /**
         * @param bool $returnAsIs
         * @return null|string
         * @since 6.0.0
         */
        public function getContentByJson($returnAsIs = false)
        {
            try {
                if ($returnAsIs) {
                    return $this->IO->getFromJson($this->PARSE_CONTAINER);
                }

                return $this->getNull($this->IO->getFromJson($this->PARSE_CONTAINER));
            } catch (Exception $e) {
            }

            return null;
        }

        /**
         * Enable/disable the parsing of Dom content
         *
         * @param bool $domContentProhibit
         * @since 6.0.3
         */
        public function setDomContentParser($domContentProhibit = false)
        {
            $this->NETCURL_PROHIBIT_DOMCONTENT_PARSE = $domContentProhibit;
        }

        /**
         * Get the status of dom content parser mode
         *
         * @return bool
         * @since 6.0.3
         */
        public function getDomContentParser()
        {
            return $this->NETCURL_PROHIBIT_DOMCONTENT_PARSE;
        }

        /**
         * @param bool $returnAsIs
         * @return null|string
         * @since 6.0.0
         */
        public function getContentByXml($returnAsIs = false)
        {
            try {
                if ($returnAsIs) {
                    return $this->IO->getFromXml($this->PARSE_CONTAINER);
                }

                return $this->getNull($this->IO->getFromXml($this->PARSE_CONTAINER));
            } catch (Exception $e) {
            }

            return null;
        }

        /**
         * @param bool $returnAsIs
         * @return null|string
         * @throws Exception
         * @since 6.0.0
         * @deprecated Do not use this. It will be removed from version 6.1.0 anyway.
         */
        public function getContentByYaml($returnAsIs = false)
        {
            try {
                if ($returnAsIs) {
                    $this->IO->getFromYaml($this->PARSE_CONTAINER);
                }

                return $this->getNull($this->IO->getFromYaml($this->PARSE_CONTAINER));
            } catch (Exception $e) {
            }

            return null;
        }

        /**
         * @param bool $returnAsIs
         * @return null|string
         * @since 6.0.0
         * @deprecated This function is not supported in version 6.1.0 and above.
         */
        public function getContentBySerial($returnAsIs = false)
        {
            $return = null;

            try {
                if ($returnAsIs) {
                    return $this->getFromSerializerInternal($this->PARSE_CONTAINER);
                }

                return $this->getNull($this->getFromSerializerInternal($this->PARSE_CONTAINER));
            } catch (Exception $e) {
            }

            return $return;
        }

        /**
         * Return serialized-by-php content. Not supported in the IO-package from v6.1 so we're taking it home.
         *
         * @param string $serialInput
         * @param bool $assoc
         * @return mixed|null
         * @throws Exception
         * @since 6.0.26
         * @deprecated This function is not supported in version 6.1.0 and above.
         */
        public function getFromSerializerInternal($serialInput = '', $assoc = false)
        {
            // Skip this if there's nothing to serialize, as some error handlers might pick up errors even if we
            // suppress them.
            $trimData = trim($serialInput);
            if (empty($trimData)) {
                return null;
            }
            if (!$assoc) {
                return @unserialize($serialInput);
            } else {
                return $this->arrayObjectToStdClass(@unserialize($serialInput));
            }
        }

        /**
         * @param array $objectArray
         * @return mixed|null
         * @throws Exception
         */
        public function arrayObjectToStdClass($objectArray = [])
        {
            /**
             * If json_decode and json_encode exists as function, do it the simple way.
             * http://php.net/manual/en/function.json-encode.php
             */
            if (function_exists('json_decode') &&
                function_exists('json_encode')) {
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
         * @param string $testData
         * @return null|string
         * @since 6.0.0
         */
        private function getNull($testData = '')
        {
            if (is_array($testData) || is_object($testData)) {
                return $testData;
            }

            return empty($testData) ? null : $testData;
        }

        /**
         * @return array|null|string
         * @throws Exception
         * @since 6.0.0
         * @deprecated Stop using this. Run by content-type instead.
         */
        private function getContentByTest()
        {
            $returnNonNullValue = null;

            // Trust content-type higher than the guessing game (NETCURL-290, implementation imported from netcur 6.1).
            // Note: This solution support only xml and json.
            if (!empty($this->PARSE_CONTENT_TYPE)) {
                $response = $this->getContentByHeaderType($this->PARSE_CONTAINER, $this->PARSE_CONTENT_TYPE);
                if ($response !== $this->PARSE_CONTAINER && !empty($response)) {
                    return $response;
                }
            }

            if (!is_null($respond = $this->getContentByJson())) {
                $returnNonNullValue = $respond;
            } elseif (!is_null($respond = $this->getContentBySerial())) {
                $returnNonNullValue = $respond;
            } elseif (!is_null($respond = $this->getContentByXml())) {
                $returnNonNullValue = $respond;
            } elseif (!is_null($respond = $this->getContentByYaml())) {
                $returnNonNullValue = $respond;
            } elseif (!$this->NETCURL_PROHIBIT_DOMCONTENT_PARSE && !is_null($response = $this->getDomElements())) {
                return $response;
            }

            return $returnNonNullValue;
        }

        /**
         * @param $content
         * @param $contentType
         * @return mixed|string|null
         * @since 6.1.0 Imported feature.
         */
        private function getContentByHeaderType($content, $contentType)
        {
            $return = $content;

            switch ($contentType) {
                case (!empty($contentType) && preg_match('/\/xml/i', $contentType) ? true : false):
                    $return = $this->getContentByXml();
                    break;
                case (preg_match('/\/json/i', $contentType) ? true : false):
                    $return = json_decode($content);
                    break;
                default:
                    break;
            }

            return $return;
        }

        /**
         * Experimental: Convert DOMDocument to an array
         *
         * @param array $childNode
         * @param string $getAs
         * @return array
         * @since 6.0.0
         */
        private function getChildNodes($childNode = [], $getAs = '')
        {
            $childNodeArray = [];
            $childAttributeArray = [];
            $childIdArray = [];
            $returnContext = "";
            if (is_object($childNode)) {
                /** @var \DOMElement $nodeItem */
                foreach ($childNode as $nodeItem) {
                    if (is_object($nodeItem)) {
                        if (isset($nodeItem->tagName)) {
                            if (strtolower($nodeItem->tagName) == "title") {
                                $elementData['pageTitle'] = $nodeItem->nodeValue;
                            }

                            $elementData = ['tagName' => $nodeItem->tagName];
                            $elementData['id'] = $nodeItem->getAttribute('id');
                            $elementData['name'] = $nodeItem->getAttribute('name');
                            $elementData['context'] = $nodeItem->nodeValue;
                            /** @since 6.0.20 Saving innerhtml */
                            $elementData['innerhtml'] = $nodeItem->ownerDocument->saveHTML($nodeItem);
                            if ($nodeItem->hasChildNodes()) {
                                $elementData['childElement'] = $this->getChildNodes($nodeItem->childNodes, $getAs);
                            }
                            $identificationName = $nodeItem->tagName;
                            if (empty($identificationName) && !empty($elementData['name'])) {
                                $identificationName = $elementData['name'];
                            }
                            if (empty($identificationName) && !empty($elementData['id'])) {
                                $identificationName = $elementData['id'];
                            }
                            $childNodeArray[] = $elementData;
                            if (!isset($childAttributeArray[$identificationName])) {
                                $childAttributeArray[$identificationName] = $elementData;
                            } else {
                                $childAttributeArray[$identificationName][] = $elementData;
                            }

                            $idNoName = $nodeItem->tagName;
                            // Forms without id namings will get the tagname. This will open up for reading forms and
                            // other elements without id's.
                            // NOTE: If forms are not tagged with an id, the form will not render "properly" and the
                            // form fields might pop outside the real form.
                            if (empty($elementData['id'])) {
                                $elementData['id'] = $idNoName;
                            }

                            if (!empty($elementData['id'])) {
                                if (!isset($childIdArray[$elementData['id']])) {
                                    $childIdArray[$elementData['id']] = $elementData;
                                } else {
                                    $childIdArray[$elementData['id']][] = $elementData;
                                }
                            }
                        }
                    }
                }
            }
            if (empty($getAs) || $getAs == "domnodes") {
                $returnContext = $childNodeArray;
            } else {
                if ($getAs == "tagnames") {
                    $returnContext = $childAttributeArray;
                } else {
                    if ($getAs == "id") {
                        $returnContext = $childIdArray;
                    }
                }
            }

            return $returnContext;
        }

        /**
         * @return bool
         * @since 6.0.1
         */
        public function getIsDomContent()
        {
            return $this->NETCURL_CONTENT_IS_DOMCONTENT;
        }

        /**
         * @return array
         * @throws Exception
         * @since 6.0.0
         */
        private function getDomElements()
        {
            $domContent = [];
            $domContent['ByNodes'] = [];
            $domContent['ByClosestTag'] = [];
            $domContent['ById'] = [];
            $hasContent = false;
            if (class_exists('DOMDocument', NETCURL_CLASS_EXISTS_AUTOLOAD)) {
                if (!empty($this->PARSE_CONTAINER)) {
                    $DOM = new \DOMDocument();
                    libxml_use_internal_errors(true);
                    $DOM->loadHTML($this->PARSE_CONTAINER);
                    if (isset($DOM->childNodes->length) && $DOM->childNodes->length > 0) {
                        $this->NETCURL_CONTENT_IS_DOMCONTENT = true;

                        $elementsByTagName = $DOM->getElementsByTagName('*');
                        $childNodeArray = $this->getChildNodes($elementsByTagName);
                        $childTagArray = $this->getChildNodes($elementsByTagName, 'tagnames');
                        $childIdArray = $this->getChildNodes($elementsByTagName, 'id');
                        if (is_array($childNodeArray) && count($childNodeArray)) {
                            $domContent['ByNodes'] = $childNodeArray;
                            $hasContent = true;
                        }
                        if (is_array($childTagArray) && count($childTagArray)) {
                            $domContent['ByClosestTag'] = $childTagArray;
                        }
                        if (is_array($childIdArray) && count($childIdArray)) {
                            $domContent['ById'] = $childIdArray;
                        }
                    }
                }
            } else {
                throw new Exception(
                    NETCURL_CURL_CLIENTNAME . " HtmlParse exception: Can not parse DOMDocuments without the DOMDocuments class",
                    $this->NETWORK->getExceptionCode("NETCURL_DOMDOCUMENT_CLASS_MISSING")
                );
            }

            if (!$hasContent) {
                return null;
            }

            return $domContent;
        }

        public function getParsedResponse()
        {
            return $this->PARSE_CONTENT_OUTPUT;
        }
    }
}
