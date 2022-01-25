<?php

namespace TorneLIB\Utils;

use Exception;
use ReflectionClass;
use ReflectionException;
use TorneLIB\Exception\Constants;
use TorneLIB\Exception\ExceptionHandler;

/**
 * Class Generic Generic functions
 * @package TorneLIB\Utils
 * @version 6.1.15
 */
class Generic
{
    /**
     * @var object
     * @since 6.1.12
     */
    private $composerData = null;

    /**
     * @var string
     * @since 6.1.12
     */
    private $composerLocation;

    /**
     * @var string $templatePath
     * @since 6.1.6
     */
    private $templatePath = '';

    /**
     * Defines if current rendered template is plain.
     * @var bool $templateText
     * @since 6.1.6
     */
    private $templatePlain = false;

    /**
     * @var array $templateExtension
     * @since 6.1.6
     */
    private $templateExtension = ['htm', 'html', 'txt', 'php', 'phtml'];

    /**
     * @var array Name entry from composer.
     * @since 6.1.13
     */
    private $composerNameEntry;

    /**
     * Internal errorhandler.
     * @var
     * @since 6.1.15
     */
    private $internalErrorHandler;

    /**
     * Error message on internal handled errors, if any.
     * @var string
     * @since 6.1.15
     */
    private $internalExceptionMessage = '';

    /**
     * Error code on internal handled errors, if any.
     * @var int
     * @since 6.1.15
     */
    private $internalExceptionCode = 0;

    /**
     * If open_basedir-warnings has been triggered once, we store that here.
     * @var bool
     * @since 6.1.15
     */
    private $openBaseDirExceptionTriggered = false;

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

    /**
     * @param $composerLocation
     * @return mixed|string
     * @throws ExceptionHandler
     * @since 6.1.13
     */
    public function getComposerShortName($composerLocation)
    {
        return $this->getNameEntry('name', $composerLocation);
    }

    /**
     * @param $part
     * @param $composerLocation
     * @return mixed|string
     * @throws ExceptionHandler
     * @since 6.1.13
     */
    private function getNameEntry($part, $composerLocation)
    {
        $return = '';
        $this->composerNameEntry = explode('/', $this->getComposerTag($composerLocation, 'name'), 2);

        switch ($part) {
            case 'name':
                if (isset($this->composerNameEntry[1])) {
                    $return = $this->composerNameEntry[1];
                }
                break;
            case 'vendor':
                if (isset($this->composerNameEntry[0])) {
                    $return = $this->composerNameEntry[0];
                }
                break;
            default:
        }

        return $return;
    }

    /**
     * @param $location
     * @param $tag
     * @return string
     * @throws ExceptionHandler
     * @since 6.1.3
     */
    public function getComposerTag($location, $tag)
    {
        $return = '';

        if (empty($this->composerData)) {
            $this->getComposerConfig($location);
        }

        if (isset($this->composerData->{$tag})) {
            $return = $this->composerData->{$tag};
        } elseif ($this->isOpenBaseDirException()) {
            $return = $this->getOpenBaseDirExceptionString();
        }

        return (string)$return;
    }

    /**
     * @return $this
     * @since 6.1.4
     */
    private function getInternalErrorHandler()
    {
        if (!is_null($this->internalErrorHandler)) {
            restore_error_handler();
            $this->internalErrorHandler = null;
        }

        $this->internalErrorHandler = set_error_handler(function ($errNo, $errStr) {
            if (empty($this->internalExceptionMessage)) {
                $this->internalExceptionCode = $errNo;
                $this->internalExceptionMessage = $errStr;
            }
            restore_error_handler();
            return $errNo === 2 && (bool)preg_match('/open_basedir/', $errStr) ? true : false;
        }, E_WARNING);

        return $this;
    }

    /**
     * @param $location
     * @param int $maxDepth
     * @return string|null
     * @throws ExceptionHandler
     * @since 6.1.12
     */
    public function getComposerConfig($location, $maxDepth = 3)
    {
        $this->getInternalErrorHandler();

        if ($maxDepth > 3 || $maxDepth < 1) {
            $maxDepth = 3;
        }

        // Pre-check if file exists, to also make sure that open_basedir is not a problem.
        $locationCheck = file_exists($location);
        $this->isOpenBaseDirException();

        if (!$this->openBaseDirExceptionTriggered && !$locationCheck) {
            throw new ExceptionHandler('Invalid path', Constants::LIB_INVALID_PATH);
        }
        if ($this->isOpenBaseDirException()) {
            return $this->getOpenBaseDirExceptionString();
        }
        if ($this->isOpenBaseDirException()) {
            return $this->getOpenBaseDirExceptionString();
        }
        $startAt = dirname($location);
        if ($this->hasComposerFile($startAt)) {
            $this->getComposerConfigData($startAt);
            return $startAt;
        }

        $composerLocation = null;
        while ($maxDepth--) {
            $startAt .= '/..';
            if ($this->hasComposerFile($startAt)) {
                $composerLocation = $startAt;
                break;
            }
        }

        $this->getComposerConfigData($composerLocation);

        return $this->composerLocation;
    }

    /**
     * Exception string that is used in several places that will mark up if the running methods have
     * had problems with open_basedir security.
     * @return string
     * @since 6.1.15
     */
    private function getOpenBaseDirExceptionString()
    {
        return 'N/A (open_basedir security active)';
    }

    /**
     * Checks internal warnings for open_basedir exceptions during runs.
     * @return bool
     * @since 6.1.15
     */
    private function isOpenBaseDirException()
    {
        // If triggered once, skip checks.
        if ($this->openBaseDirExceptionTriggered) {
            return $this->openBaseDirExceptionTriggered;
        }

        $return = $this->hasInternalException() &&
            $this->internalExceptionCode === 2 &&
            (bool)preg_match('/open_basedir/', $this->internalExceptionMessage);

        if ($return) {
            $this->openBaseDirExceptionTriggered = true;
        }

        return $return;
    }

    /**
     * @return bool
     */
    private function hasInternalException()
    {
        return !empty($this->internalExceptionMessage);
    }

    /**
     * @param $location
     * @return bool
     * @since 6.1.3
     */
    private function hasComposerFile($location)
    {
        $return = false;

        if (file_exists(sprintf('%s/composer.json', $location))) {
            $return = true;
        }

        return $return;
    }

    /**
     * @param $location
     */
    private function getComposerConfigData($location)
    {
        $this->composerLocation = $location;

        $getFrom = sprintf('%s/composer.json', $location);
        if (file_exists($getFrom)) {
            $this->composerData = json_decode(
                file_get_contents(
                    $getFrom
                )
            );
        }
    }

    /**
     * @param $composerLocation
     * @return mixed|string
     * @since 6.1.13
     */
    public function getComposerVendor($composerLocation)
    {
        return $this->getNameEntry('vendor', $composerLocation);
    }

    /**
     * Using both class and composer.json to discover version (in case that composer.json are removed in a "final").
     *
     * @param string $composerLocation
     * @param int $composerDepth
     * @param string $className
     * @return string|null
     * @throws ExceptionHandler
     * @throws ReflectionException
     * @since 6.1.7
     */
    public function getVersionByAny($composerLocation = '', $composerDepth = 3, $className = '')
    {
        $return = null;

        $byComposer = $this->getVersionByComposer($composerLocation, $composerDepth);
        $byClass = $this->getVersionByClassDoc($className);

        // Composer always have higher priority.
        if (!empty($byComposer)) {
            $return = $byComposer;
        } elseif (!empty($byClass)) {
            $return = $byClass;
        }

        return $return;
    }

    /**
     * @param $location
     * @param int $maxDepth Default is 3.
     * @return string
     * @throws ExceptionHandler
     * @since 6.1.3
     */
    public function getVersionByComposer($location, $maxDepth = 3)
    {
        $return = '';

        if (!empty(($this->getComposerConfig($location, $maxDepth))) && !$this->isOpenBaseDirException()) {
            $return = $this->getComposerTag($this->composerLocation, 'version');
        } elseif ($this->isOpenBaseDirException()) {
            $return = $this->getOpenBaseDirExceptionString();
        }

        return $return;
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
     * @param $templateName
     * @param array $assignedVariables
     * @return false|string
     * @throws Exception
     * @since 6.1.6
     */
    public function getTemplate($templateName, $assignedVariables = [])
    {
        $templateFile = $this->getProperTemplate($templateName);

        if (empty($templateFile)) {
            throw new Exception('Template file not found!', 404);
        }

        if (is_array($assignedVariables) && count($assignedVariables)) {
            foreach ($assignedVariables as $key => $value) {
                if (preg_match('/^\$/', $key)) {
                    $key = substr($key, 1);
                }
                ${$key} = $value;
            }
        }

        ob_start();
        include($templateFile);
        $templateHtml = ob_get_clean();
        return $templateHtml;
    }

    /**
     * @param $templateName
     * @return string
     * @throws Exception
     * @since 6.1.6
     */
    public function getProperTemplate($templateName)
    {
        return (string)$this->getProperFileName(
            sprintf(
                '%s/%s',
                $this->getTemplatePath(),
                preg_replace('/(.*)\.(.*)$/', '$1', $templateName)
            )
        );
    }

    /**
     * @param $partialFilename
     * @return string|null
     * @since 6.1.6
     */
    private function getProperFileName($partialFilename)
    {
        $templateFile = null;

        foreach ($this->templateExtension as $extension) {
            if (file_exists($partialFilename . '.' . $extension)) {
                $templateFile = $partialFilename . '.' . $extension;
                $this->setTemplatePlain(true);
                break;
            }
        }

        return $templateFile;
    }

    /**
     * @param bool $templatePlain
     * @since 6.1.6
     */
    private function setTemplatePlain($templatePlain)
    {
        $this->templatePlain = $templatePlain;

        return $this;
    }

    /**
     * Get path of templates. Default: __DIR__/templates.
     * @return string
     * @throws Exception
     * @since 6.1.6
     */
    public function getTemplatePath()
    {
        if (empty($this->templatePath)) {
            $this->templatePath = __DIR__ . '/templates';
        }

        if (!file_exists($this->templatePath)) {
            throw new Exception(
                sprintf(
                    'Template path %s not found!',
                    $this->templatePath
                ),
                404
            );
        }

        return $this->templatePath;
    }

    /**
     * @param string $templatePath
     * @return Generic
     * @since 6.1.6
     */
    public function setTemplatePath($templatePath)
    {
        $this->templatePath = $templatePath;

        return $this;
    }

    /**
     * @param $namespaceClassName
     * @param null $skipReflection
     * @return mixed
     * @since 6.1.9
     */
    public function getShortClassName($namespaceClassName = null, $skipReflection = null)
    {
        if (is_null($namespaceClassName)) {
            $namespaceClassName = self::class;
        }
        $return = $namespaceClassName;

        if (class_exists($namespaceClassName)) {
            if (!class_exists('\ReflectionClass')) {
                $skipReflection = true;
            }
            if (!$skipReflection && class_exists('\ReflectionClass')) {
                /** @noinspection PhpFullyQualifiedNameUsageInspection */
                $useReflection = new \ReflectionClass($namespaceClassName);
                $return = $useReflection->getShortName();
            } else {
                $wrapperClassExplode = explode('\\', $namespaceClassName);
                if (is_array($wrapperClassExplode) && count($wrapperClassExplode)) {
                    $return = $wrapperClassExplode[count($wrapperClassExplode) - 1];
                }
            }
        }

        return $return;
    }

    /**
     * @return bool
     * @since 6.1.6
     */
    public function isTemplatePlain()
    {
        return $this->templatePlain;
    }
}
