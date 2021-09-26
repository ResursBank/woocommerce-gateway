<?php

namespace Resursbank\RBEcomPHP;

/**
 * Class RESURS_ENVIRONMENTS
 * Environments in 1.4 and above uses true/false values for whether we're in test or not. This is why we replaced the
 * old values with booleans.
 * @package Resursbank\RBEcomPHP
 */
class RESURS_ENVIRONMENTS
{
    const PRODUCTION = 0;
    const TEST = 1;

    /**
     * @var int
     * @deprecated Do NOT use this variable. It will be removed!
     */
    const ENVIRONMENT_PRODUCTION = 0;
    /**
     * @var int
     * @deprecated Do NOT use this variable. It will be removed!
     */
    const ENVIRONMENT_TEST = 1;

    /**
     * Not set by anyone.
     * @var int
     * @deprecated Do NOT use this variable. It will be removed!
     */
    const NOT_SET = 2;

    /**
     * @var int
     * @deprecated Do NOT use this variable. It will be removed!
     */
    const ENVIRONMENT_NOT_SET = 2;
}
