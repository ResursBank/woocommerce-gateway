<?php
/**
 * Copyright © Tomas Tornevall / Tornevall Networks. All rights reserved.
 * See LICENSE for license details.
 */

namespace TorneLIB\Helpers;

use TorneLIB\Exception\ExceptionHandler;
use TorneLIB\Module\Network\NetWrapper;

/**
 * Class Network
 * @package TorneLIB\Helpers
 * @since 6.1.0
 */
class NetUtils {
    /**
     * @param $gitRequest
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
                if (!preg_match("/\^/", $tag)) {
                    if ($numericsOnly) {
                        if (($currentTag = $this->getGitTagsSanitized($tag, $numericsSanitized))) {
                            $return[] = $currentTag;
                        }
                    } else {
                        if (!isset($return[$tag])) {
                            $return[$tag] = $tag;
                        }
                    }
                }
            }
        }

        return $this->getGitTagsUnAssociated($return);
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
            } else {
                if ($numericsSanitized) {
                    // Sanitize string if content is dual.
                    $numericStringOnly = preg_replace("/[^0-9$]/is", '', $tagValue);
                    $tagArrayUnCombined[] = $numericStringOnly;
                }
            }
        }

        if (count($tagArrayUnCombined)) {
            $return = implode('.', $tagArrayUnCombined);
        }

        return $return;
    }

    /**
     * getGitTagsByUrl (From 6.1, the $keepCredentials has no effect).
     *
     * @param $url
     * @param bool $numericsOnly
     * @param bool $numericsSanitized
     * @return array
     * @since 6.0.4 Moved from Network Library.
     */
    public function getGitTagsByUrl($url, $numericsOnly = false, $numericsSanitized = false)
    {
        $url .= "/info/refs?service=git-upload-pack";
        $gitRequest = (new NetWrapper())->setTimeout(10)->request($url);
        return $this->getGitsTagsRegEx($gitRequest->getBody());
    }

    /**
     * @param $giturl
     * @param $version1
     * @param $version2
     * @param $operator
     * @return array
     */
    public function getGitTagsByVersion($giturl, $version1, $version2, $operator)
    {
        $return = [];
        $versionList = $this->getGitTagsByUrl($giturl);
        if (is_array($versionList) && count($versionList)) {
            foreach ($versionList as $versionNum) {
                if (version_compare($versionNum, $version1, '>=') &&
                    version_compare($versionNum, $version2, '<=') &&
                    !in_array($versionNum, $return)
                ) {
                    $return[] = $versionNum;
                }
            }
        }

        return $return;
    }

    /**
     * @param $gitUrl
     * @param string $yourVersion
     * @return array
     */
    public function getHigherVersions($gitUrl, $yourVersion = '')
    {
        $versionArray = $this->getGitTagsByUrl($gitUrl, true, true);
        $versionsHigher = [];
        foreach ($versionArray as $tagVersion) {
            if (version_compare($tagVersion, $yourVersion, ">")) {
                $versionsHigher[] = $tagVersion;
            }
        }

        return $versionsHigher;
    }

    /**
     * Checks if requested version is the latest compared to the git repo.
     *
     * @param $gitUrl
     * @param string $yourVersion
     * @return bool
     */
    public function getVersionLatest($gitUrl, $yourVersion = '')
    {
        if (!count($this->getHigherVersions($gitUrl, $yourVersion))) {
            return true;
        }

        return false;
    }
}
