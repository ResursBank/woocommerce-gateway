<?php

namespace TorneLIB\Utils;

use ReflectionClass;
use ReflectionException;
use TorneLIB\Exception\Constants;
use TorneLIB\Exception\ExceptionHandler;

/**
 * Class Generic Generic functions
 * @package TorneLIB\Utils
 * @version 6.1.5
 */
class Generic
{
    /**
     * Generic constructor.
     * @since 6.1.0
     */
    public function __construct()
    {
        return $this;
    }

    /**
     * @param $item
     * @param $functionName
     * @param string $className
     * @return string
     * @throws ReflectionException
     * @since 6.1.0
     * @noinspection PhpUnusedParameterInspection Called from externals.
     */
    private function getExtractedDocBlock(
        $item,
        $functionName,
        $className = ''
    ) {
        if (empty($className)) {
            $className = __CLASS__;
        }
        if (!class_exists($className)) {
            return '';
        }

        $doc = new ReflectionClass($className);

        if (empty($functionName)) {
            $return = $doc->getDocComment();
        } else {
            $return = $doc->getMethod($functionName)->getDocComment();
        }

        return (string)$return;
    }

    /**
     * @param $item
     * @param $doc
     * @return string
     * @since 6.1.0
     */
    private function getExtractedDocBlockItem($item, $doc)
    {
        $return = '';

        if (!empty($doc)) {
            preg_match_all(sprintf('/%s\s(\w.+)\n/s', $item), $doc, $docBlock);

            if (isset($docBlock[1]) && isset($docBlock[1][0])) {
                $return = $docBlock[1][0];

                // Strip stuff after line breaks
                if (preg_match('/[\n\r]/', $return)) {
                    $multiRowData = preg_split('/[\n\r]/', $return);
                    $return = isset($multiRowData[0]) ? $multiRowData[0] : '';
                }
            }
        }

        return (string)$return;
    }

    /**
     * @param $item
     * @param string $functionName
     * @param string $className
     * @return string
     * @throws ReflectionException
     * @since 6.1.0
     */
    public function getDocBlockItem($item, $functionName = '', $className = '')
    {
        return (string)$this->getExtractedDocBlockItem(
            $item,
            $this->getExtractedDocBlock(
                $item,
                $functionName,
                $className
            )
        );
    }

    /**
     * @param string $className
     * @return string
     * @throws ReflectionException
     * @since 6.1.0
     */
    public function getVersionByClassDoc($className = '')
    {
        return $this->getDocBlockItem('@version', '', $className);
    }

    /**
     * @param $location
     * @param int $maxDepth
     * @return string
     * @throws ExceptionHandler
     * @since 6.1.3
     */
    public function getVersionByComposer($location, $maxDepth = 3)
    {
        $return = '';
        if ($maxDepth > 3 || $maxDepth < 1) {
            $maxDepth = 3;
        }
        if (!file_exists($location)) {
            throw new ExceptionHandler('Invalid path', Constants::LIB_INVALID_PATH);
        }
        $startAt = dirname($location);
        if ($this->getComposerJson($startAt)) {
            return $this->getComposerTag($startAt, 'version');
        }

        $composerLocation = null;
        while ($maxDepth--) {
            $startAt .= '/..';
            if ($this->getComposerJson($startAt)) {
                $composerLocation = $startAt;
                break;
            }
        }

        if (!empty($composerLocation)) {
            $return = $this->getComposerTag($composerLocation, 'version');
        }

        return $return;
    }

    /**
     * @param $location
     * @return bool
     * @since 6.1.3
     */
    private function getComposerJson($location)
    {
        $return = false;

        if (file_exists(sprintf('%s/composer.json', $location))) {
            $return = true;
        }

        return $return;
    }

    /**
     * @param $location
     * @param $tag
     * @return string
     * @since 6.1.3
     */
    private function getComposerTag($location, $tag)
    {
        $return = '';

        $composerData = json_decode(
            file_get_contents(
                sprintf('%s/composer.json', $location)
            )
        );

        if (isset($composerData->$tag)) {
            $return = $composerData->$tag;
        }

        return (string)$return;
    }

    /**
     * Check if class files exists somewhere in platform (pear/pecl-based functions).
     * Initially used to fetch XML-serializers. Returns first successful match.
     *
     * @param $classFile
     * @return string
     * @since 6.1.0
     */
    public function getStreamPath($classFile)
    {
        $return = null;

        $checkClassFiles = [
            $classFile,
            sprintf('%s.php', $classFile),
        ];

        $return = false;

        foreach ($checkClassFiles as $classFileName) {
            $serializerPath = stream_resolve_include_path($classFileName);
            if (!empty($serializerPath)) {
                $return = $serializerPath;
                break;
            }
        }

        return $return;
    }
}
