<?php
/**
 * Copyright © Tomas Tornevall / Tornevall Networks. All rights reserved.
 * See LICENSE.md for license details.
 */

namespace TorneLIB\Helpers;

use TorneLIB\Exception\ExceptionHandler;
use TorneLIB\Module\Network\NetWrapper;

/**
 * Class Network
 * @package TorneLIB\Helpers
 * @since 6.1.0
 */
class NetUtils
{
    /**
     * @param string $gitUrl
     * @param string $version1
     * @param string $version2
     * @return array
     * @throws ExceptionHandler
     */
    public function getGitTagsByVersion($gitUrl, $version1, $version2)
    {
        $return = [];
        $versionList = $this->getGitTagsByUrl($gitUrl);
        if (is_array($versionList) && count($versionList)) {
            foreach ($versionList as $versionNum) {
                if (version_compare($versionNum, $version1, '>=') &&
                    version_compare($versionNum, $version2, '<=') &&
                    !in_array($versionNum, $return, false)
                ) {
                    $return[] = $versionNum;
                }
            }
        }

        return $return;
    }

    /**
     * getGitTagsByUrl (From 6.1, the $keepCredentials has no effect).
     *
     * @param $url
     * @return array
     * @throws ExceptionHandler
     * @since 6.0.4 Moved from Network Library.
     */
    public function getGitTagsByUrl($url)
    {
        $url .= "/info/refs?service=git-upload-pack";
        $gitRequest = (new NetWrapper())->request($url);
        return $this->getGitsTagsRegEx($gitRequest->getBody());
    }

    /**
     * @param $gitRequest
     * @param bool $numericsOnly
     * @param bool $numericsSanitized
     * @return array
     * @since 6.1.0
     */
    private function getGitsTagsRegEx($gitRequest, $numericsOnly = false, $numericsSanitized = false)
    {
        $return = [];
        preg_match_all("/refs\/tags\/(.*?)\n/s", $gitRequest, $tagMatches);
        if (isset($tagMatches[1]) && is_array($tagMatches[1])) {
            $tagList = $tagMatches[1];
            foreach ($tagList as $tag) {
                if (!(bool)preg_match("/\^/", $tag)) {
                    if ($numericsOnly) {
                        $currentTag = $this->getGitTagsSanitized($tag, $numericsSanitized);
                        if ($currentTag) {
                            $return[] = $currentTag;
                        }
                    } elseif (!isset($return[$tag])) {
                        $return[$tag] = $tag;
                    }
                }
            }
        }

        return $this->getGitTagsUnAssociated($return);
    }

    /**
     * @param $tagString
     * @param bool $numericsSanitized
     * @return string
     * @since 6.1.0
     */
    private function getGitTagsSanitized($tagString, $numericsSanitized = false)
    {
        $return = '';
        $splitTag = explode(".", $tagString);

        $tagArrayUnCombined = [];
        foreach ($splitTag as $tagValue) {
            if (is_numeric($tagValue)) {
                $tagArrayUnCombined[] = $tagValue;
            } elseif ($numericsSanitized) {
                // Sanitize string if content is dual.
                $numericStringOnly = preg_replace("/[^0-9$]/", '', $tagValue);
                $tagArrayUnCombined[] = $numericStringOnly;
            }
        }

        if (count($tagArrayUnCombined)) {
            $return = implode('.', $tagArrayUnCombined);
        }

        return $return;
    }

    /**
     * @param array $return
     * @return array
     * @since 6.1.0
     */
    private function getGitTagsUnAssociated($return = [])
    {
        $newArray = [];
        if (count($return)) {
            asort($return, SORT_NATURAL);
            $newArray = [];
            /** @noinspection PhpUnusedLocalVariableInspection */
            foreach ($return as $arrayKey => $arrayValue) {
                $newArray[] = $arrayValue;
            }
        }

        if (count($newArray)) {
            $return = $newArray;
        }

        return $return;
    }

    /**
     * Checks if requested version is the latest compared to the git repo.
     *
     * @param $gitUrl
     * @param string $yourVersion
     * @return bool
     * @throws ExceptionHandler
     */
    public function getVersionLatest($gitUrl, $yourVersion = '')
    {
        if (!count($this->getHigherVersions($gitUrl, $yourVersion))) {
            return true;
        }

        return false;
    }

    /**
     * @param $gitUrl
     * @param string $yourVersion
     * @return array
     * @throws ExceptionHandler
     */
    public function getHigherVersions($gitUrl, $yourVersion = '')
    {
        $versionArray = $this->getGitTagsByUrl($gitUrl);
        $versionsHigher = [];
        foreach ($versionArray as $tagVersion) {
            if (version_compare($tagVersion, $yourVersion, ">")) {
                $versionsHigher[] = $tagVersion;
            }
        }

        return $versionsHigher;
    }
}
